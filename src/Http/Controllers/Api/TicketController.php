<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Controllers\Api;

use AichaDigital\Laratickets\Http\Requests\StoreTicketRequest;
use AichaDigital\Laratickets\Http\Requests\UpdateTicketRequest;
use AichaDigital\Laratickets\Http\Resources\TicketResource;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ticket::with(['currentLevel', 'department']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('level')) {
            $query->byLevel($request->level);
        }

        if ($request->has('department_id')) {
            $query->byDepartment($request->department_id);
        }

        if ($request->boolean('open_only')) {
            $query->open();
        }

        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        $tickets = $query->latest()->paginate($request->get('per_page', 15));

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->createTicket(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Ticket created successfully',
            'data' => new TicketResource($ticket->load(['currentLevel', 'department'])),
        ], 201);
    }

    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource(
            $ticket->load(['currentLevel', 'requestedLevel', 'department', 'assignments', 'evaluations'])
        );
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket->update($request->validated());

        return response()->json([
            'message' => 'Ticket updated successfully',
            'data' => new TicketResource($ticket->fresh(['currentLevel', 'department'])),
        ]);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json([
            'message' => 'Ticket deleted successfully',
        ]);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $closedTicket = $this->ticketService->closeTicket($ticket, $request->user());

        return response()->json([
            'message' => 'Ticket closed successfully',
            'data' => new TicketResource($closedTicket),
        ]);
    }

    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        $resolvedTicket = $this->ticketService->resolveTicket($ticket, $request->user());

        return response()->json([
            'message' => 'Ticket resolved successfully',
            'data' => new TicketResource($resolvedTicket),
        ]);
    }

    public function cancel(Request $request, Ticket $ticket): JsonResponse
    {
        $cancelledTicket = $this->ticketService->cancelTicket(
            $ticket,
            $request->user(),
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Ticket cancelled successfully',
            'data' => new TicketResource($cancelledTicket),
        ]);
    }
}
