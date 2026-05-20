<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Controllers\Api;

use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Http\Requests\RedactTicketMessageRequest;
use AichaDigital\Laratickets\Http\Requests\StoreTicketMessageRequest;
use AichaDigital\Laratickets\Http\Resources\TicketMessageResource;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketMessage;
use AichaDigital\Laratickets\Services\TicketMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class TicketMessageController extends Controller
{
    public function __construct(
        protected TicketMessageService $messageService
    ) {}

    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $messages = $this->messageService->listFor($ticket, $request->user());

        return TicketMessageResource::collection($messages);
    }

    public function store(StoreTicketMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $message = $this->messageService->post(
            $ticket,
            $request->user(),
            $request->body,
            MessageAuthorRole::from($request->author_role),
        );

        return response()->json([
            'message' => 'Ticket message posted successfully',
            'data' => new TicketMessageResource($message),
        ], 201);
    }

    public function redact(
        RedactTicketMessageRequest $request,
        Ticket $ticket,
        TicketMessage $message
    ): JsonResponse {
        if ($message->ticket_id !== $ticket->id) {
            abort(404);
        }

        $redacted = $this->messageService->redact($message, $request->user(), $request->reason);

        return response()->json([
            'message' => 'Ticket message redacted successfully',
            'data' => new TicketMessageResource($redacted),
        ]);
    }
}
