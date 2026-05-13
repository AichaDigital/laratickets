<?php

declare(strict_types=1);
use AichaDigital\Laratickets\Implementations\BasicTicketAuthorization;
use AichaDigital\Laratickets\Implementations\BasicUserCapabilityHandler;
use AichaDigital\Laratickets\Implementations\DefaultNotificationHandler;

return [
    /*
    |--------------------------------------------------------------------------
    | User Model Configuration
    |--------------------------------------------------------------------------
    |
    | Laratickets is UUID-first (per ADR-001). The consumer app's `users.id`
    | column MUST be UUID v7 char(36). bigInteger and ULID are not supported.
    |
    | See: docs/ADR-001-uuid-first.md
    | See setup guide: https://github.com/AichaDigital/larabill/blob/main/docs/setup-uuid.md
    |
    */
    'user' => [
        'model' => env('LARATICKETS_USER_MODEL', config('auth.providers.users.model')),
        'id_column' => env('LARATICKETS_USER_ID_COLUMN', 'id'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authorization and capability handlers.
    | Default implementations use Bouncer for permissions.
    |
    */
    'authorization' => [
        'handler' => env(
            'LARATICKETS_AUTHORIZATION_HANDLER',
            BasicTicketAuthorization::class
        ),
        'capability_handler' => env(
            'LARATICKETS_CAPABILITY_HANDLER',
            BasicUserCapabilityHandler::class
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure notification handler and channels for different events.
    |
    */
    'notifications' => [
        'handler' => env(
            'LARATICKETS_NOTIFICATION_HANDLER',
            DefaultNotificationHandler::class
        ),
        'enabled' => env('LARATICKETS_NOTIFICATIONS_ENABLED', true),
        'channels' => [
            'ticket_created' => ['mail', 'database'],
            'ticket_assigned' => ['mail', 'database'],
            'escalation_requested' => ['mail', 'database'],
            'escalation_approved' => ['mail', 'database'],
            'escalation_rejected' => ['mail', 'database'],
            'ticket_closed' => ['mail', 'database'],
            'evaluation_received' => ['database'],
            'sla_breached' => ['mail', 'database'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticket Levels Configuration
    |--------------------------------------------------------------------------
    |
    | Default ticket levels and their settings.
    |
    */
    'levels' => [
        'default_sla_hours' => [
            1 => 24,
            2 => 48,
            3 => 72,
            4 => 96,
        ],
        'auto_escalation_enabled' => env('LARATICKETS_AUTO_ESCALATION_ENABLED', true),
        'sla_check_frequency' => env('LARATICKETS_SLA_CHECK_FREQUENCY', 'hourly'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Departments Configuration
    |--------------------------------------------------------------------------
    |
    | Default departments for ticket categorization.
    |
    */
    'departments' => [
        'default' => [
            ['name' => 'Technical', 'description' => 'Technical support department'],
            ['name' => 'Administrative', 'description' => 'Administrative support department'],
            ['name' => 'Commercial', 'description' => 'Commercial support department'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Evaluation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for ticket and agent evaluation system.
    |
    */
    'evaluation' => [
        'enabled' => env('LARATICKETS_EVALUATION_ENABLED', true),
        'required_on_close' => env('LARATICKETS_EVALUATION_REQUIRED', false),
        'agent_rating_enabled' => env('LARATICKETS_AGENT_RATING_ENABLED', true),
        'min_score' => 1.0,
        'max_score' => 5.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Assessment Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for risk assessment functionality.
    |
    */
    'risk_assessment' => [
        'enabled' => env('LARATICKETS_RISK_ASSESSMENT_ENABLED', true),
        'required_levels' => [3, 4],
        'auto_escalate_on_critical' => env('LARATICKETS_RISK_AUTO_ESCALATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA Configuration
    |--------------------------------------------------------------------------
    |
    | Service Level Agreement settings.
    |
    */
    'sla' => [
        'enabled' => env('LARATICKETS_SLA_ENABLED', true),
        'breach_notifications' => env('LARATICKETS_SLA_BREACH_NOTIFICATIONS', true),
        'auto_escalation_on_breach' => env('LARATICKETS_SLA_AUTO_ESCALATE', true),
        'warning_threshold_hours' => env('LARATICKETS_SLA_WARNING_HOURS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assignment Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for ticket assignment.
    |
    */
    'assignment' => [
        'auto_assign_enabled' => env('LARATICKETS_AUTO_ASSIGN_ENABLED', false),
        'auto_assign_strategy' => env('LARATICKETS_AUTO_ASSIGN_STRATEGY', 'round_robin'),
        'max_concurrent_tickets' => env('LARATICKETS_MAX_CONCURRENT_TICKETS', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments Configuration (ADR-002)
    |--------------------------------------------------------------------------
    |
    | File attachments on tickets. Storage disk is configurable; defaults to
    | 'local'. Tipos y tamaños default son restrictivos — apps consumidoras
    | pueden ampliar los arrays en su .env publicado.
    |
    | See: docs/ADR-002-ticket-attachments.md
    |
    */
    'attachments' => [
        'enabled' => env('LARATICKETS_ATTACHMENTS_ENABLED', true),
        'disk' => env('LARATICKETS_ATTACHMENTS_DISK', 'local'),
        'path' => env('LARATICKETS_ATTACHMENTS_PATH', 'ticket-attachments'),
        'max_file_size_kb' => (int) env('LARATICKETS_ATTACHMENTS_MAX_FILE_KB', 5120),
        'max_total_size_kb_per_ticket' => (int) env('LARATICKETS_ATTACHMENTS_MAX_TOTAL_KB', 25600),
        'allowed_mime_types' => [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'text/plain',
        ],
        'allowed_extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'log'],
    ],
];
