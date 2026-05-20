<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use AichaDigital\Laratickets\Concerns\HasUserRelation;
use AichaDigital\Laratickets\Concerns\HasUuid;
use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Enums\MessageVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id UUID v7
 * @property string $ticket_id UUID reference to ticket
 * @property string $author_id User ID
 * @property MessageAuthorRole $author_role
 * @property MessageVisibility $visibility
 * @property string $body
 * @property Carbon|null $redacted_at
 * @property string|null $redacted_by
 * @property string|null $redaction_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read Model $author
 */
class TicketMessage extends Model
{
    use HasFactory;
    use HasUserRelation;
    use HasUuid;

    /**
     * User columns for HasUserRelation trait.
     *
     * @var array<string>
     */
    protected array $userColumns = ['author_id', 'redacted_by'];

    protected $fillable = [
        'ticket_id',
        'author_id',
        'author_role',
        'visibility',
        'body',
    ];

    protected $casts = [
        'author_role' => MessageAuthorRole::class,
        'visibility' => MessageVisibility::class,
        'redacted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Ticket, TicketMessage>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<Model, TicketMessage>
     */
    public function author(): BelongsTo
    {
        /** @var class-string<Model> $userModelClass */
        $userModelClass = $this->getUserModelClass();

        return $this->belongsTo(
            $userModelClass,
            'author_id',
            $this->getUserIdColumnName()
        );
    }

    public function isRedacted(): bool
    {
        return $this->redacted_at !== null;
    }
}
