<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\TicketAttachment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado tras persistir un TicketAttachment exitoso (ADR-002).
 *
 * Extension point para listeners: notificaciones email (creator/staff),
 * antivirus scan async, replicación S3, métricas. El paquete NO incluye
 * implementación por defecto — el consumer la engancha.
 */
class AttachmentUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(public TicketAttachment $attachment) {}
}
