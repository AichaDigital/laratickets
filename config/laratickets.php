<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | User Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the user model and ID type for your application.
    | Supports: integer, uuid, uuid_binary (UUID v7 binary), ulid
    |
    */
    'user' => [
        'model' => env('LARATICKETS_USER_MODEL', config('auth.providers.users.model')),
        'id_column' => env('LARATICKETS_USER_ID_COLUMN', 'id'),
        'id_type' => env('LARATICKETS_USER_ID_TYPE', 'integer'),
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
            \AichaDigital\Laratickets\Implementations\BasicTicketAuthorization::class
        ),
        'capability_handler' => env(
            'LARATICKETS_CAPABILITY_HANDLER',
            \AichaDigital\Laratickets\Implementations\BasicUserCapabilityHandler::class
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
            \AichaDigital\Laratickets\Implementations\DefaultNotificationHandler::class
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
];
