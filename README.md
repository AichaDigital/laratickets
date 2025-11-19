# Laratickets - Advanced Support Ticket System for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aichadigital/laratickets.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laratickets)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/aichadigital/laratickets/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aichadigital/laratickets/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/aichadigital/laratickets/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/aichadigital/laratickets/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/aichadigital/laratickets.svg?style=flat-square)](https://packagist.org/packages/aichadigital/laratickets)

A comprehensive support ticket management system for Laravel with 4-level escalation, risk assessment, evaluations, and full RESTful API.

## Features

- **4-Level Escalation System**: Automatic and manual escalation between support levels (I, II, III, IV)
- **Department Management**: Organize tickets by departments (Technical, Administrative, Commercial)
- **Risk Assessment**: Level III/IV agents can assess ticket risk with automatic critical escalation
- **Ticket Evaluations**: Global ticket scoring and individual agent ratings
- **RESTful API**: Full versioned API (v1) with Laravel Sanctum authentication
- **Flexible Authorization**: Contract-based authorization system, easily adaptable to any permission package
- **Multi-ID Support**: Works with integer, UUID (v4/v7), UUID binary, and ULID primary keys
- **Event System**: 11 events for complete extensibility
- **SLA Management**: Configurable SLA hours per level with auto-escalation on breach
- **Comprehensive Testing**: 25+ tests with 80+ assertions

## Requirements

- PHP 8.4+
- Laravel 11.x or 12.x

## Installation

Install via Composer:

```bash
composer require aichadigital/laratickets
```

Run the installation command:

```bash
php artisan laratickets:install --seed
```

This will:
- Publish configuration file
- Run migrations (8 tables)
- Seed default levels (I-IV) and departments

## Configuration

The configuration file is published to `config/laratickets.php`:

```php
return [
    // User model configuration
    'user' => [
        'model' => env('LARATICKETS_USER_MODEL', config('auth.providers.users.model')),
        'id_type' => env('LARATICKETS_USER_ID_TYPE', 'integer'), // integer|uuid|uuid_binary|ulid
    ],

    // Authorization handlers
    'authorization' => [
        'handler' => \AichaDigital\Laratickets\Implementations\BasicTicketAuthorization::class,
        'capability_handler' => \AichaDigital\Laratickets\Implementations\BasicUserCapabilityHandler::class,
    ],

    // Features
    'evaluation' => ['enabled' => true],
    'risk_assessment' => ['enabled' => true, 'auto_escalate_on_critical' => true],
    'sla' => ['enabled' => true, 'auto_escalation_on_breach' => true],
    // ... more configuration options
];
```

## Basic Usage

### Creating a Ticket

```php
use AichaDigital\Laratickets\Services\TicketService;
use AichaDigital\Laratickets\Enums\Priority;

$ticketService = app(TicketService::class);

$ticket = $ticketService->createTicket([
    'subject' => 'Database connection issue',
    'description' => 'Cannot connect to production database',
    'priority' => Priority::HIGH,
    'department_id' => 1,
], $user);
```

### Requesting Escalation

```php
use AichaDigital\Laratickets\Services\EscalationService;

$escalationService = app(EscalationService::class);

$escalation = $escalationService->requestEscalation(
    $ticket,
    $targetLevel, // TicketLevel model
    'Issue requires senior expertise',
    $requester
);
```

### Assessing Risk

```php
use AichaDigital\Laratickets\Services\RiskAssessmentService;
use AichaDigital\Laratickets\Enums\RiskLevel;

$riskService = app(RiskAssessmentService::class);

$assessment = $riskService->assessRisk(
    $ticket,
    $assessor, // Must be Level III or IV
    RiskLevel::CRITICAL,
    'Affects production systems for 10k+ users'
);
// Auto-escalates if risk is CRITICAL
```

### Evaluating a Ticket

```php
use AichaDigital\Laratickets\Services\EvaluationService;

$evaluationService = app(EvaluationService::class);

$evaluation = $evaluationService->evaluateTicket(
    $ticket,
    $evaluator,
    4.5, // Score 1.0-5.0
    'Excellent support, quick resolution'
);
```

## API Usage

All API endpoints require Laravel Sanctum authentication.

**Base URL:** `/api/v1/laratickets`

### Examples

**Create Ticket:**
```bash
curl -X POST /api/v1/laratickets/tickets \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Login issue",
    "description": "Cannot log in to dashboard",
    "priority": "high",
    "department_id": 1
  }'
```

**List Tickets:**
```bash
curl /api/v1/laratickets/tickets?status=open&level=1 \
  -H "Authorization: Bearer {token}"
```

**Request Escalation:**
```bash
curl -X POST /api/v1/laratickets/tickets/1/escalations \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "target_level_id": 2,
    "justification": "Requires advanced database knowledge"
  }'
```

See [API Documentation](docs/API.md) for complete endpoint reference.

## Authorization

Laratickets uses a contract-based authorization system. The default implementation provides basic authorization, but you can easily integrate with any permission package (Bouncer, Spatie Permission, etc.).

### Custom Authorization Handler

```php
namespace App\Laratickets\Authorization;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use Silber\Bouncer\BouncerFacade as Bouncer;

class MyTicketAuthorization implements TicketAuthorizationContract
{
    public function canViewTicket($user, Ticket $ticket): bool
    {
        return Bouncer::can('view-tickets')
            || $user->id === $ticket->created_by;
    }

    // Implement other methods...
}
```

Update config:

```php
'authorization' => [
    'handler' => \App\Laratickets\Authorization\MyTicketAuthorization::class,
],
```

## Events

Laratickets dispatches 11 events that you can listen to:

- `TicketCreated`
- `TicketAssigned`
- `TicketStatusChanged`
- `TicketClosed`
- `EscalationRequested`
- `EscalationApproved`
- `EscalationRejected`
- `TicketEvaluated`
- `AgentRated`
- `RiskAssessed`
- `SLABreached`

### Example Listener

```php
use AichaDigital\Laratickets\Events\TicketCreated;

class SendTicketCreatedNotification
{
    public function handle(TicketCreated $event): void
    {
        $ticket = $event->ticket;

        // Send notification to level I agents
        Notification::send(
            $this->getLevelIAgents($ticket->department_id),
            new NewTicketNotification($ticket)
        );
    }
}
```

## Testing

```bash
composer test
```

## Code Style

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Abdelkarim Mateos Sanchez](https://github.com/aichadigital)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
