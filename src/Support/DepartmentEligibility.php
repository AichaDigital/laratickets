<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Support;

use AichaDigital\Laratickets\Exceptions\InactiveDepartmentException;
use AichaDigital\Laratickets\Models\Department;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * AID-1085 — the single place that answers "may a ticket be filed into this
 * department?".
 *
 * `Department::scopeActive()` shipped with v1.0 and had zero callers: the
 * service wrote `department_id` unchecked and both FormRequests validated with
 * a bare `exists:departments,id`, so every write path accepted a department the
 * operator had taken out of service.
 *
 * Enforcement is on by default and reversible through
 * `laratickets.departments.enforce_active`. The default lives HERE, at the point
 * of use, and not merely in the published config file: `mergeConfigFrom()` is a
 * shallow `array_merge`, so a consumer that published `config/laratickets.php`
 * keeps its own top-level `departments` block and would never see a key we add
 * to ours.
 */
final class DepartmentEligibility
{
    /**
     * Whether the guard is active. False restores the pre-1.2.0 behaviour on
     * every write path at once.
     */
    public static function enforced(): bool
    {
        return (bool) config('laratickets.departments.enforce_active', true);
    }

    /**
     * Throw unless the department may receive a ticket.
     *
     * This is a check, not a lock: a department disabled between this read and
     * the write still lets that ticket through. Closing that window is a
     * separate concern from the contract fixed here.
     */
    public static function assert(int|string $departmentId): void
    {
        if (! self::enforced()) {
            return;
        }

        $isEligible = Department::query()
            ->active()
            ->whereKey($departmentId)
            ->exists();

        if (! $isEligible) {
            throw InactiveDepartmentException::forId($departmentId);
        }
    }

    /**
     * The `exists` rule for a `department_id` field.
     *
     * @param  int|string|null  $alsoAllow  A department id accepted even when
     *                                      retired — the one a ticket already
     *                                      sits in. Rejecting it would make
     *                                      every historical ticket in a since
     *                                      retired department uneditable, since
     *                                      a client resubmitting a whole form
     *                                      sends the department unchanged.
     */
    public static function rule(int|string|null $alsoAllow = null): Exists
    {
        $rule = Rule::exists((new Department)->getTable(), 'id');

        if (! self::enforced()) {
            return $rule;
        }

        // Nested on purpose. Laravel applies these closures FLAT onto the
        // verifier's query, so an ungrouped `orWhere` would compile to
        // `(id = ? and active = 1) or (id = ?)` — a clause that matches on its
        // own and would wave every retired department through.
        return $rule->where(function (Builder $query) use ($alsoAllow): void {
            $query->where(function (Builder $group) use ($alsoAllow): void {
                $group->where('active', true);

                if ($alsoAllow !== null) {
                    $group->orWhere('id', $alsoAllow);
                }
            });
        });
    }
}
