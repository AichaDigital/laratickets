<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use AichaDigital\Laratickets\Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $mailbox_email
 * @property string|null $head_user_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'mailbox_email',
        'head_user_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }

    /**
     * @return HasMany<Ticket, Department>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<Department>  $query
     * @return Builder<Department>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
