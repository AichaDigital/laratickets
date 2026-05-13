<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\AttachmentUploaderRole;
use AichaDigital\Laratickets\Events\AttachmentUploaded;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Attachment service — sube, lista, borra archivos asociados a un ticket
 * (ADR-002).
 *
 * Toda autorización pasa por el TicketAuthorizationContract — el service
 * NO implementa policy, solo orchestra. Las apps customizan policy
 * rebindando el contract en su AppServiceProvider.
 */
class AttachmentService
{
    public function __construct(
        protected TicketAuthorizationContract $authorization
    ) {}

    /**
     * Sube un archivo y persiste la fila TicketAttachment.
     *
     * @param  mixed  $uploader  User model instance
     *
     * @throws RuntimeException
     */
    public function attach(
        Ticket $ticket,
        $uploader,
        UploadedFile $file,
        AttachmentUploaderRole $role,
    ): TicketAttachment {
        if (! config('laratickets.attachments.enabled', true)) {
            throw new RuntimeException('Attachments are disabled.');
        }

        if (! $this->authorization->canAttachFile($uploader, $ticket)) {
            throw new RuntimeException('User is not authorized to attach files to this ticket.');
        }

        $this->validateFile($file);
        $this->validateTotalSize($ticket, $file);

        $disk = (string) config('laratickets.attachments.disk', 'local');
        $basePath = trim((string) config('laratickets.attachments.path', 'ticket-attachments'), '/');

        $uuid = (string) Str::orderedUuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $this->extensionFromMime($file->getMimeType() ?? ''));
        $storedName = $extension !== '' ? "$uuid.$extension" : $uuid;
        $relativePath = "$basePath/{$ticket->id}/$storedName";

        Storage::disk($disk)->putFileAs(
            "$basePath/{$ticket->id}",
            $file,
            $storedName,
        );

        $uploaderId = $uploader->{config('laratickets.user.id_column', 'id')};

        $attachment = new TicketAttachment([
            'ticket_id' => $ticket->id,
            'uploader_id' => $uploaderId,
            'uploader_role' => $role,
            'disk' => $disk,
            'path' => $relativePath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
        ]);
        $attachment->id = $uuid;
        $attachment->save();

        event(new AttachmentUploaded($attachment));

        return $attachment;
    }

    /**
     * Borra el attachment (BD + archivo físico).
     *
     * @param  mixed  $actor  User model instance
     *
     * @throws RuntimeException
     */
    public function delete(TicketAttachment $attachment, $actor): void
    {
        if (! $this->authorization->canDeleteAttachment($actor, $attachment)) {
            throw new RuntimeException('User is not authorized to delete this attachment.');
        }

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    /**
     * Lista attachments visibles para un viewer, aplicando policy por fila.
     *
     * @param  mixed  $viewer  User model instance
     * @return Collection<int, TicketAttachment>
     */
    public function listFor(Ticket $ticket, $viewer): Collection
    {
        return $ticket->attachments()
            ->get()
            ->filter(fn (TicketAttachment $att) => $this->authorization->canDownloadFile($viewer, $att))
            ->values();
    }

    /**
     * Tamaño total acumulado de attachments de un ticket (en bytes).
     */
    public function totalSizeBytes(Ticket $ticket): int
    {
        return (int) $ticket->attachments()->sum('size_bytes');
    }

    private function validateFile(UploadedFile $file): void
    {
        $maxKb = (int) config('laratickets.attachments.max_file_size_kb', 5120);
        $sizeKb = (int) ceil(($file->getSize() ?: 0) / 1024);

        if ($sizeKb > $maxKb) {
            throw new RuntimeException("File exceeds max size ($maxKb KB).");
        }

        /** @var array<int, string> $allowedMimes */
        $allowedMimes = (array) config('laratickets.attachments.allowed_mime_types', []);
        /** @var array<int, string> $allowedExts */
        $allowedExts = (array) config('laratickets.attachments.allowed_extensions', []);

        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        $mimeOk = $allowedMimes === [] || ($mime !== null && in_array($mime, $allowedMimes, true));
        $extOk = $allowedExts === [] || in_array($ext, $allowedExts, true);

        if (! $mimeOk || ! $extOk) {
            throw new RuntimeException("File type not allowed (mime: $mime, ext: $ext).");
        }
    }

    private function validateTotalSize(Ticket $ticket, UploadedFile $file): void
    {
        $maxTotalKb = (int) config('laratickets.attachments.max_total_size_kb_per_ticket', 25600);
        $currentBytes = $this->totalSizeBytes($ticket);
        $newBytes = $file->getSize() ?: 0;
        $projectedKb = (int) ceil(($currentBytes + $newBytes) / 1024);

        if ($projectedKb > $maxTotalKb) {
            throw new RuntimeException("Total attachments size for this ticket would exceed limit ($maxTotalKb KB).");
        }
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'text/plain' => 'txt',
            default => '',
        };
    }
}
