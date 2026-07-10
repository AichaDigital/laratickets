<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\AttachmentUploaderRole;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\AgentRating;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;
use AichaDigital\Laratickets\Models\TicketAttachment;
use AichaDigital\Laratickets\Models\TicketEvaluation;
use AichaDigital\Laratickets\Models\TicketLevel;

it('creates a TicketLevel standalone', function () {
    $level = TicketLevel::factory()->create();

    expect($level->exists)->toBeTrue()
        ->and($level->active)->toBeTrue()
        ->and($level->level)->toBeInt();
});

it('creates a Department standalone', function () {
    $department = Department::factory()->create();

    expect($department->exists)->toBeTrue()
        ->and($department->active)->toBeTrue()
        ->and($department->name)->toBeString();
});

it('creates a Ticket resolving its level and department chain', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->exists)->toBeTrue()
        ->and($ticket->currentLevel)->toBeInstanceOf(TicketLevel::class)
        ->and($ticket->department)->toBeInstanceOf(Department::class)
        ->and($ticket->status)->toBe(TicketStatus::NEW)
        ->and($ticket->user_priority)->toBe(Priority::MEDIUM)
        ->and($ticket->created_by)->toBeString();
});

it('applies the Ticket resolved state', function () {
    $ticket = Ticket::factory()->resolved()->create();

    expect($ticket->status)->toBe(TicketStatus::RESOLVED)
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->resolved_by)->toBeString();
});

it('applies the Ticket closed state (matches applyClose semantics)', function () {
    $ticket = Ticket::factory()->closed()->create();

    expect($ticket->status)->toBe(TicketStatus::CLOSED)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->resolved_by)->toBeString()
        ->and($ticket->resolved_at)->toBeNull();
});

it('creates a TicketAssignment resolving its ticket chain', function () {
    $assignment = TicketAssignment::factory()->create();

    expect($assignment->exists)->toBeTrue()
        ->and($assignment->ticket)->toBeInstanceOf(Ticket::class)
        ->and($assignment->user_id)->toBeString()
        ->and($assignment->completed_at)->toBeNull()
        ->and($assignment->isActive())->toBeTrue();
});

it('applies the TicketAssignment completed state', function () {
    $assignment = TicketAssignment::factory()->completed()->create();

    expect($assignment->completed_at)->not->toBeNull()
        ->and($assignment->isActive())->toBeFalse()
        ->and($assignment->individual_rating)->not->toBeNull();
});

it('creates a TicketAttachment (client + pdf by default)', function () {
    $attachment = TicketAttachment::factory()->create();

    expect($attachment->exists)->toBeTrue()
        ->and($attachment->ticket)->toBeInstanceOf(Ticket::class)
        ->and($attachment->uploader_role)->toBe(AttachmentUploaderRole::CLIENT)
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->size_bytes)->toBeInt();
});

it('applies the TicketAttachment staff and image states', function () {
    $attachment = TicketAttachment::factory()->staff()->image()->create();

    expect($attachment->uploader_role)->toBe(AttachmentUploaderRole::STAFF)
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->original_name)->toEndWith('.png');
});

it('creates a TicketEvaluation with a score inside the 1-5 range', function () {
    $evaluation = TicketEvaluation::factory()->create();

    expect($evaluation->exists)->toBeTrue()
        ->and((float) $evaluation->score)->toBeGreaterThanOrEqual(1.0)
        ->and((float) $evaluation->score)->toBeLessThanOrEqual(5.0);
});

it('applies the TicketEvaluation highRated and lowRated states', function () {
    expect((float) TicketEvaluation::factory()->highRated()->create()->score)->toBeGreaterThanOrEqual(4.0);
    expect((float) TicketEvaluation::factory()->lowRated()->create()->score)->toBeLessThanOrEqual(2.0);
});

it('creates an AgentRating with distinct agent and rater ids', function () {
    $rating = AgentRating::factory()->create();

    expect($rating->exists)->toBeTrue()
        ->and($rating->agent_id)->toBeString()
        ->and($rating->rater_id)->toBeString()
        ->and((float) $rating->score)->toBeGreaterThanOrEqual(1.0);
});

it('applies the AgentRating highRated and lowRated states', function () {
    expect((float) AgentRating::factory()->highRated()->create()->score)->toBeGreaterThanOrEqual(4.0);
    expect((float) AgentRating::factory()->lowRated()->create()->score)->toBeLessThanOrEqual(2.0);
});

it('creates an EscalationRequest (pending) resolving both level chains', function () {
    $escalation = EscalationRequest::factory()->create();

    expect($escalation->exists)->toBeTrue()
        ->and($escalation->ticket)->toBeInstanceOf(Ticket::class)
        ->and($escalation->fromLevel)->toBeInstanceOf(TicketLevel::class)
        ->and($escalation->toLevel)->toBeInstanceOf(TicketLevel::class)
        ->and($escalation->status)->toBe('pending')
        ->and($escalation->approver_id)->toBeNull()
        ->and($escalation->is_automatic)->toBeFalse();
});

it('applies the EscalationRequest approved, rejected and automatic states', function () {
    $approved = EscalationRequest::factory()->approved()->create();
    expect($approved->status)->toBe('approved')
        ->and($approved->approver_id)->not->toBeNull()
        ->and($approved->isApproved())->toBeTrue();

    $rejected = EscalationRequest::factory()->rejected()->create();
    expect($rejected->status)->toBe('rejected')
        ->and($rejected->rejection_reason)->not->toBeNull()
        ->and($rejected->isRejected())->toBeTrue();

    $automatic = EscalationRequest::factory()->automatic()->create();
    expect($automatic->is_automatic)->toBeTrue()
        ->and($automatic->requester_id)->toBeNull();
});
