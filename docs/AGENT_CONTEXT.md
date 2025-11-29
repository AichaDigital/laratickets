# Laratickets - Package Context for AI Agents

> **Read this file first** to understand the package's purpose, architecture, and conventions.

## 🎯 Package Identity

**Laratickets** is a Laravel package for **support ticket management** with 4-level escalation, risk assessment, evaluations, and a full RESTful API.

### Critical Information

| Item | Value |
|------|-------|
| **Version** | dev-main (targeting v1.0 for Dec 15, 2025) |
| **PHP** | ^8.4 |
| **Laravel** | ^12.0 |
| **License** | AGPL-3.0-or-later |
| **Status** | Alpha (v0.x) |

### Ecosystem Context

Laratickets is part of the **AichaDigital billing ecosystem**:

```
aichadigital/
├── larabill/        # Core billing
├── lara100/         # Base-100 monetary calculations
├── lararoi/         # EU VAT/ROI verification
├── lara-verifactu/  # Spain AEAT VeriFACTU
└── laratickets/     # Support tickets (THIS PACKAGE)
```

**Primary staging environment**: [Larafactu](https://github.com/AichaDigital/larafactu)

## 🏗️ Architecture

### Core Features

1. **4-Level Escalation**: I (Basic) → II (Advanced) → III (Expert) → IV (Critical)
2. **Department Management**: Technical, Administrative, Commercial
3. **Risk Assessment**: Level III/IV agents assess ticket risk
4. **Evaluations**: Global ticket scoring and agent ratings
5. **SLA Management**: Configurable hours per level with auto-escalation
6. **RESTful API**: Full versioned API (v1) with Sanctum auth

### Key Models

```
Ticket              → Main ticket entity (UUID primary key)
TicketMessage       → Messages/responses in ticket
TicketLevel         → Escalation levels (I-IV)
TicketDepartment    → Department categorization
TicketRiskAssessment → Risk evaluation by agents
TicketEvaluation    → Customer satisfaction ratings
TicketAgent         → Agent assignments
TicketHistory       → Audit trail
```

### UUID Strategy

Tickets use **string UUID v7** for primary keys:

```php
use AichaDigital\Laratickets\Concerns\HasUuid;

class Ticket extends Model
{
    use HasUuid;
}
```

## 📁 Package Structure

```
laratickets/
├── config/laratickets.php      # Package configuration
├── database/
│   ├── migrations/             # 8 migration files
│   └── seeders/                # Default levels/departments
├── docs/
│   ├── AGENT_CONTEXT.md        # This file
│   └── API.md                  # API documentation
├── resources/
│   └── lang/                   # Translations (es, en)
├── src/
│   ├── Concerns/               # Traits (HasUuid)
│   ├── Contracts/              # Interfaces
│   ├── DTOs/                   # Data Transfer Objects
│   ├── Enums/                  # Status, Priority enums
│   ├── Events/                 # 11 domain events
│   ├── Exceptions/             # Custom exceptions
│   ├── Http/
│   │   ├── Controllers/Api/    # API controllers
│   │   └── Resources/          # API resources
│   ├── Models/                 # Eloquent models
│   ├── Policies/               # Authorization policies
│   └── Services/               # Business logic
└── tests/                      # Pest tests
```

## ⚙️ Configuration

### Environment Variables

```env
# SLA Hours per Level
LARATICKETS_SLA_LEVEL_I=24
LARATICKETS_SLA_LEVEL_II=12
LARATICKETS_SLA_LEVEL_III=6
LARATICKETS_SLA_LEVEL_IV=2

# Auto-escalation
LARATICKETS_AUTO_ESCALATE=true

# User Model
LARATICKETS_USER_MODEL=App\Models\User
```

### Config File

```php
// config/laratickets.php
return [
    'models' => [
        'user' => \App\Models\User::class,
    ],
    'sla' => [
        'level_i' => 24,   // hours
        'level_ii' => 12,
        'level_iii' => 6,
        'level_iv' => 2,
    ],
    'auto_escalate' => true,
];
```

## 🔧 Key Services

### TicketService

Main entry point for ticket operations:

```php
use AichaDigital\Laratickets\Services\TicketService;

$service = app(TicketService::class);

// Create ticket
$ticket = $service->create([
    'subject' => 'Help needed',
    'body' => 'Description...',
    'department_id' => 1,
    'priority' => 'high',
]);

// Escalate
$service->escalate($ticket);

// Assign agent
$service->assign($ticket, $agent);
```

### EscalationService

Handles automatic and manual escalation:

```php
use AichaDigital\Laratickets\Services\EscalationService;

$service = app(EscalationService::class);
$service->checkAndEscalate($ticket);
```

## 🧪 Testing

```bash
# Run all tests
composer test

# Run specific tests
composer test -- --filter=Ticket

# Static analysis
vendor/bin/phpstan analyse
```

## ⚠️ Important Conventions

### Escalation Levels

| Level | Name | SLA | Description |
|-------|------|-----|-------------|
| I | Basic | 24h | First-line support |
| II | Advanced | 12h | Technical issues |
| III | Expert | 6h | Complex problems |
| IV | Critical | 2h | Business-critical |

### Ticket Status Flow

```
open → in_progress → pending → resolved → closed
                  ↘ escalated ↗
```

### Event System

11 events for extensibility:

- `TicketCreated`
- `TicketUpdated`
- `TicketEscalated`
- `TicketAssigned`
- `TicketResolved`
- `TicketClosed`
- `TicketReopened`
- `MessageAdded`
- `RiskAssessed`
- `EvaluationSubmitted`
- `SLABreached`

## 🚫 Anti-Patterns

**DON'T**:
- ❌ Skip escalation levels
- ❌ Modify closed tickets directly
- ❌ Ignore SLA breaches
- ❌ Bypass the service layer

**DO**:
- ✅ Use TicketService for all operations
- ✅ Handle events for notifications
- ✅ Respect escalation flow
- ✅ Track all changes via history

## 📚 Key Documentation

| File | Purpose |
|------|---------|
| `docs/API.md` | REST API documentation |
| `README.md` | Installation and usage |
| `CHANGELOG.md` | Version history |

## 🎯 Integration with Larabill

Laratickets can be linked to invoices:

```php
// Link ticket to invoice
$ticket->update([
    'related_type' => 'invoice',
    'related_id' => $invoice->id,
]);
```

---

**Remember**: This package handles customer support. Maintain audit trails and respect escalation flows. Target: v1.0 stable by December 15, 2025.

