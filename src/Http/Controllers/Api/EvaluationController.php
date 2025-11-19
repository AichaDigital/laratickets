<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Controllers\Api;

use AichaDigital\Laratickets\Http\Requests\EvaluateTicketRequest;
use AichaDigital\Laratickets\Http\Resources\TicketEvaluationResource;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class EvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    public function store(EvaluateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $evaluation = $this->evaluationService->evaluateTicket(
            $ticket,
            $request->user(),
            $request->score,
            $request->comment
        );

        return response()->json([
            'message' => 'Ticket evaluated successfully',
            'data' => new TicketEvaluationResource($evaluation),
        ], 201);
    }

    public function statistics(): JsonResponse
    {
        return response()->json([
            'data' => $this->evaluationService->getTicketStatistics(),
        ]);
    }
}
