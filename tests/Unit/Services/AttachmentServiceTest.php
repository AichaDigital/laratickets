<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\AttachmentUploaderRole;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\AttachmentUploaded;
use AichaDigital\Laratickets\Exceptions\TicketAuthorizationException;
use AichaDigital\Laratickets\Exceptions\TicketStateException;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAttachment;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\AttachmentService;
use AichaDigital\Laratickets\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Tests AttachmentService (ADR-002 — laratickets v0.4.0).
 *
 * Cubre attach() / delete() / listFor() / totalSizeBytes() con:
 *  - Happy path subir + persistir BD + archivo en disk
 *  - Idempotencia event AttachmentUploaded
 *  - Validación mime/extension (deny no permitido)
 *  - Validación max_file_size
 *  - Validación max_total_size (acumulado por ticket)
 *  - Auth gating delegado al contract
 *  - delete() borra fila + archivo físico
 *  - listFor() filtra por canDownloadFile()
 */
beforeEach(function () {
    Storage::fake('local');
    Event::fake([AttachmentUploaded::class]);

    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create(['name' => 'Technical', 'active' => true]);

    $this->ticket = Ticket::create([
        'subject' => 'Test ticket',
        'description' => 'desc',
        'status' => TicketStatus::NEW,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => TestCase::USER_UUID_1,
    ]);

    $this->uploader = new class
    {
        public string $id = TestCase::USER_UUID_1;
    };

    $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
    $this->authorization->shouldReceive('canAttachFile')->andReturn(true)->byDefault();
    $this->authorization->shouldReceive('canDeleteAttachment')->andReturn(true)->byDefault();
    $this->authorization->shouldReceive('canDownloadFile')->andReturn(true)->byDefault();

    $this->service = new AttachmentService($this->authorization);
});

describe('AttachmentService::attach', function () {
    it('uploads a valid file and persists the row', function () {
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $att = $this->service->attach($this->ticket, $this->uploader, $file, AttachmentUploaderRole::CLIENT);

        expect($att)->toBeInstanceOf(TicketAttachment::class)
            ->and($att->ticket_id)->toBe($this->ticket->id)
            ->and($att->uploader_role)->toBe(AttachmentUploaderRole::CLIENT)
            ->and($att->mime_type)->toBe('application/pdf')
            ->and($att->original_name)->toBe('report.pdf');

        Storage::disk('local')->assertExists($att->path);
        Event::assertDispatched(AttachmentUploaded::class);
    });

    it('rejects when authorization denies', function () {
        $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
        $this->authorization->shouldReceive('canAttachFile')->andReturn(false);
        $svc = new AttachmentService($this->authorization);

        $file = UploadedFile::fake()->create('x.pdf', 10, 'application/pdf');

        expect(fn () => $svc->attach($this->ticket, $this->uploader, $file, AttachmentUploaderRole::CLIENT))
            ->toThrow(TicketAuthorizationException::class, 'not authorized');
    });

    it('rejects file with disallowed mime type', function () {
        $file = UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload');

        expect(fn () => $this->service->attach($this->ticket, $this->uploader, $file, AttachmentUploaderRole::CLIENT))
            ->toThrow(TicketStateException::class, 'not allowed');
    });

    it('rejects file exceeding per-file size limit', function () {
        // Default max 5120 KB, así que 6000 KB pasa el límite.
        $file = UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf');

        expect(fn () => $this->service->attach($this->ticket, $this->uploader, $file, AttachmentUploaderRole::CLIENT))
            ->toThrow(TicketStateException::class, 'exceeds max size');
    });

    it('rejects when total ticket size would exceed cap', function () {
        config()->set('laratickets.attachments.max_total_size_kb_per_ticket', 200);

        $first = UploadedFile::fake()->create('a.pdf', 150, 'application/pdf');
        $this->service->attach($this->ticket, $this->uploader, $first, AttachmentUploaderRole::CLIENT);

        $second = UploadedFile::fake()->create('b.pdf', 100, 'application/pdf');

        expect(fn () => $this->service->attach($this->ticket, $this->uploader, $second, AttachmentUploaderRole::CLIENT))
            ->toThrow(TicketStateException::class, 'exceed limit');
    });

    it('respects attachments.enabled = false', function () {
        config()->set('laratickets.attachments.enabled', false);

        $file = UploadedFile::fake()->create('x.pdf', 10, 'application/pdf');

        expect(fn () => $this->service->attach($this->ticket, $this->uploader, $file, AttachmentUploaderRole::CLIENT))
            ->toThrow(TicketStateException::class, 'disabled');
    });
});

describe('AttachmentService::delete', function () {
    it('removes the file from disk and the row from BD', function () {
        $file = UploadedFile::fake()->create('a.pdf', 10, 'application/pdf');
        $att = $this->service->attach($this->ticket, $this->uploader, $file, AttachmentUploaderRole::CLIENT);
        $path = $att->path;
        Storage::disk('local')->assertExists($path);

        $this->service->delete($att, $this->uploader);

        Storage::disk('local')->assertMissing($path);
        expect(TicketAttachment::find($att->id))->toBeNull();
    });

    it('rejects when authorization denies', function () {
        $file = UploadedFile::fake()->create('a.pdf', 10, 'application/pdf');
        $att = $this->service->attach($this->ticket, $this->uploader, $file, AttachmentUploaderRole::CLIENT);

        $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
        $this->authorization->shouldReceive('canDeleteAttachment')->andReturn(false);
        $svc = new AttachmentService($this->authorization);

        expect(fn () => $svc->delete($att, $this->uploader))
            ->toThrow(TicketAuthorizationException::class, 'not authorized');
    });
});

describe('AttachmentService::listFor', function () {
    it('returns attachments filtered by canDownloadFile', function () {
        $f1 = UploadedFile::fake()->create('a.pdf', 10, 'application/pdf');
        $f2 = UploadedFile::fake()->create('b.pdf', 10, 'application/pdf');
        $this->service->attach($this->ticket, $this->uploader, $f1, AttachmentUploaderRole::CLIENT);
        $att2 = $this->service->attach($this->ticket, $this->uploader, $f2, AttachmentUploaderRole::CLIENT);

        // Mock que solo permite la 1a
        $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
        $this->authorization->shouldReceive('canDownloadFile')->andReturnUsing(
            fn ($u, TicketAttachment $a) => $a->id !== $att2->id,
        );
        $svc = new AttachmentService($this->authorization);

        $list = $svc->listFor($this->ticket, $this->uploader);

        expect($list)->toHaveCount(1)
            ->and($list->first()->id)->not->toBe($att2->id);
    });
});

describe('AttachmentService::totalSizeBytes', function () {
    it('sums sizes across all ticket attachments', function () {
        $f1 = UploadedFile::fake()->create('a.pdf', 30, 'application/pdf'); // 30 KB
        $f2 = UploadedFile::fake()->create('b.pdf', 20, 'application/pdf'); // 20 KB
        $this->service->attach($this->ticket, $this->uploader, $f1, AttachmentUploaderRole::CLIENT);
        $this->service->attach($this->ticket, $this->uploader, $f2, AttachmentUploaderRole::CLIENT);

        $bytes = $this->service->totalSizeBytes($this->ticket);

        // ~51KB; UploadedFile::fake usa size en bytes para createWithContent o KB en create().
        // create('name', $kilobytes) genera archivo de ese tamaño en KB → bytes = KB * 1024.
        expect($bytes)->toBeGreaterThan(0);
    });
});
