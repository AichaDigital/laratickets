<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use AichaDigital\Laratickets\Concerns\HasUserRelation;
use AichaDigital\Laratickets\Concerns\HasUuid;
use AichaDigital\Laratickets\Database\Factories\TicketAttachmentFactory;
use AichaDigital\Laratickets\Enums\AttachmentUploaderRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id UUID v7
 * @property string $ticket_id UUID reference to ticket
 * @property mixed $uploader_id User ID
 * @property AttachmentUploaderRole $uploader_role
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read Model $uploader
 */
class TicketAttachment extends Model
{
    /** @use HasFactory<TicketAttachmentFactory> */
    use HasFactory;

    use HasUserRelation;
    use HasUuid;

    /**
     * @var array<string>
     */
    protected array $userColumns = ['uploader_id'];

    protected $fillable = [
        'ticket_id',
        'uploader_id',
        'uploader_role',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'uploader_role' => AttachmentUploaderRole::class,
        'size_bytes' => 'integer',
    ];

    protected static function newFactory(): TicketAttachmentFactory
    {
        return TicketAttachmentFactory::new();
    }

    /**
     * @return BelongsTo<Ticket, TicketAttachment>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
