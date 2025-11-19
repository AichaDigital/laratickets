<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Controllers\Api;

use AichaDigital\Laratickets\Http\Requests\RequestEscalationRequest;
use AichaDigital\Laratickets\Http\Resources\EscalationRequestResource;
use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\EscalationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EscalationController extends Controller
{
    public function __construct(
        protected EscalationService $escalationService
    ) {}

    public function store(RequestEscalationRequest $request, Ticket $ticket): JsonResponse
    {
        $targetLevel = TicketLevel::findOrFail($request->target_level_id);

        $escalation = $this->escalationService->requestEscalation(
            $ticket,
            $targetLevel,
            $request->justification,
            $request->user()
        );

        return response()->json([
            'message' => 'Escalation requested successfully',
            'data' => new EscalationRequestResource($escalation->load(['fromLevel', 'toLevel'])),
        ], 201);
    }

    public function approve(Request $request, EscalationRequest $escalationRequest): JsonResponse
    {
        $this->escalationService->approveEscalation($escalationRequest, $request->user());

        return response()->json([
            'message' => 'Escalation approved successfully',
            'data' => new EscalationRequestResource($escalationRequest->fresh(['fromLevel', 'toLevel'])),
        ]);
    }

    public function reject(Request $request, EscalationRequest $escalationRequest): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:10'],
        ]);

        $this->escalationService->rejectEscalation(
            $escalationRequest,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'message' => 'Escalation rejected successfully',
            'data' => new EscalationRequestResource($escalationRequest->fresh(['fromLevel', 'toLevel'])),
        ]);
    }
}
