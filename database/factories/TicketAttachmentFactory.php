<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Factories;

use AichaDigital\Laratickets\Enums\AttachmentUploaderRole;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'ticket_id' => Ticket::factory(),
            'uploader_id' => (string) Str::uuid7(),
            'uploader_role' => AttachmentUploaderRole::CLIENT,
            'disk' => 'local',
            'path' => 'tickets/'.fake()->uuid().'/'.$name,
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1_024, 5_000_000),
        ];
    }

    public function client(): static
    {
        return $this->state(fn (): array => ['uploader_role' => AttachmentUploaderRole::CLIENT]);
    }

    public function staff(): static
    {
        return $this->state(fn (): array => ['uploader_role' => AttachmentUploaderRole::STAFF]);
    }

    public function image(): static
    {
        $name = fake()->word().'.png';

        return $this->state(fn (): array => [
            'path' => 'tickets/'.fake()->uuid().'/'.$name,
            'original_name' => $name,
            'mime_type' => 'image/png',
        ]);
    }
}
