<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Controllers\Api;

use AichaDigital\Laratickets\Enums\RiskLevel;
use AichaDigital\Laratickets\Http\Requests\AssessRiskRequest;
use AichaDigital\Laratickets\Http\Resources\RiskAssessmentResource;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Services\RiskAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RiskAssessmentController extends Controller
{
    public function __construct(
        protected RiskAssessmentService $riskService
    ) {}

    public function store(AssessRiskRequest $request, Ticket $ticket): JsonResponse
    {
        $riskLevel = RiskLevel::from($request->risk_level);

        $assessment = $this->riskService->assessRisk(
            $ticket,
            $request->user(),
            $riskLevel,
            $request->justification
        );

        return response()->json([
            'message' => 'Risk assessed successfully',
            'data' => new RiskAssessmentResource($assessment),
        ], 201);
    }

    public function highRisk(): JsonResponse
    {
        return response()->json([
            'data' => RiskAssessmentResource::collection($this->riskService->getHighRiskTickets()),
        ]);
    }

    public function statistics(): JsonResponse
    {
        return response()->json([
            'data' => $this->riskService->getRiskStatistics(),
        ]);
    }
}
