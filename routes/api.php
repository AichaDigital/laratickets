<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Http\Controllers\Api\EscalationController;
use AichaDigital\Laratickets\Http\Controllers\Api\EvaluationController;
use AichaDigital\Laratickets\Http\Controllers\Api\RiskAssessmentController;
use AichaDigital\Laratickets\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Laratickets API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1/laratickets')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        // Tickets
        Route::apiResource('tickets', TicketController::class);
        Route::post('tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
        Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('tickets.resolve');
        Route::post('tickets/{ticket}/cancel', [TicketController::class, 'cancel'])->name('tickets.cancel');

        // Escalations
        Route::post('tickets/{ticket}/escalations', [EscalationController::class, 'store'])->name('escalations.store');
        Route::post('escalations/{escalationRequest}/approve', [EscalationController::class, 'approve'])->name('escalations.approve');
        Route::post('escalations/{escalationRequest}/reject', [EscalationController::class, 'reject'])->name('escalations.reject');

        // Evaluations
        Route::post('tickets/{ticket}/evaluations', [EvaluationController::class, 'store'])->name('evaluations.store');
        Route::get('evaluations/statistics', [EvaluationController::class, 'statistics'])->name('evaluations.statistics');

        // Risk Assessments
        Route::post('tickets/{ticket}/risk-assessments', [RiskAssessmentController::class, 'store'])->name('risk-assessments.store');
        Route::get('risk-assessments/high-risk', [RiskAssessmentController::class, 'highRisk'])->name('risk-assessments.high-risk');
        Route::get('risk-assessments/statistics', [RiskAssessmentController::class, 'statistics'])->name('risk-assessments.statistics');
    });
