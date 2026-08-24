<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Requests;

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Support\DepartmentEligibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * AID-1085: `department_id` was a bare `exists:departments,id`, so a ticket
     * could be MOVED INTO a department taken out of service.
     *
     * Narrower than the create rule on purpose: the department the ticket is
     * already in stays acceptable even once retired. A client resubmitting a
     * whole form sends it back unchanged, and rejecting it would make every
     * historical ticket in a since retired department uneditable — including an
     * edit that has nothing to do with the department, such as the subject.
     * Forbidding that permanence too is a data question (inventory and
     * migration), not a validation one.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currentDepartmentId = $this->currentDepartmentId();

        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'department_id' => ['sometimes', DepartmentEligibility::rule(alsoAllow: $currentDepartmentId)],
        ];
    }

    /**
     * The department the ticket is in right now, or null when the route carries
     * no resolvable ticket.
     *
     * Deliberately independent of route-model binding: `SubstituteBindings` is
     * middleware, so a consumer that mounts these routes without it — or any
     * caller resolving the request outside the router — would otherwise get the
     * raw key here, silently lose the exemption, and find every historical
     * ticket in a retired department uneditable.
     */
    private function currentDepartmentId(): int|string|null
    {
        $routeTicket = $this->route('ticket');

        if ($routeTicket instanceof Ticket) {
            return $routeTicket->department_id;
        }

        // A route parameter that binding did not resolve arrives as a string.
        if (is_string($routeTicket) && $routeTicket !== '') {
            /** @var int|string|null $departmentId */
            $departmentId = Ticket::query()->whereKey($routeTicket)->value('department_id');

            return $departmentId;
        }

        return null;
    }
}
