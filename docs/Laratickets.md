# Laratickets: Arquitectura y Propuesta de Desarrollo

## Contexto del Sistema

Sistema de tickets de soporte con niveles jerárquicos de escalado, departamentalización, valoraciones y gestión de riesgo. El paquete debe mantener independencia del sistema de autorización de la aplicación mientras proporciona una estructura organizativa robusta.

## Principios Arquitectónicos

### Separación de Responsabilidades

**El paquete gestiona:**

- Estructura organizativa (Levels, Departamentos)
- Flujo de tickets (estados, escalado, asignación)
- Valoraciones y puntuaciones
- Reglas de negocio del dominio de soporte

**La aplicación gestiona:**

- Autenticación y autorización
- Permisos y roles concretos
- Integración con su modelo de usuarios
- Políticas de acceso específicas

### Inversión de Dependencias

El paquete define contratos que la aplicación implementa según su infraestructura de permisos elegida.

## Arquitectura Propuesta

### Capa de Dominio

#### Entidades Core

**Ticket**

- Estado (nuevo, asignado, en progreso, escalado, resuelto, cerrado)
- Nivel actual (I, II, III, IV)
- Nivel solicitado (para escalados pendientes)
- Prioridad usuario (baja, media, alta, crítica)
- Riesgo evaluado (valorado por Level III/IV)
- Departamento asignado
- Usuario creador
- Agentes asignados
- Puntuación global
- Fecha límite estimada

**TicketLevel**

- Nivel (I, II, III, IV)
- Descripción
- Puede escalar a nivel superior
- Puede valorar riesgo
- Tiempo SLA por defecto

**Department**

- Nombre (Técnico, Administración, Comercial)
- Descripción
- Activo

**TicketAssignment**

- Ticket
- Usuario asignado
- Fecha asignación
- Fecha finalización participación
- Valoración individual recibida

**TicketEvaluation**

- Ticket
- Usuario evaluador
- Puntuación global (1-5)
- Comentario
- Fecha evaluación

**AgentRating**

- Ticket
- Agente evaluado
- Puntuación (1-5)
- Comentario
- Evaluador

**RiskAssessment**

- Ticket
- Evaluador (debe ser Level III/IV)
- Nivel riesgo (bajo, medio, alto, crítico)
- Justificación
- Fecha evaluación

**EscalationRequest**

- Ticket
- Nivel origen
- Nivel destino
- Solicitante
- Justificación
- Estado (pendiente, aprobado, rechazado)
- Aprobador
- Fecha resolución

#### Value Objects

**TicketStatus**

```
- new
- assigned
- in_progress
- escalation_requested
- escalated
- resolved
- closed
- cancelled
```

**Priority**

```
- low
- medium
- high
- critical
```

**RiskLevel**
```
- low
- medium
- high
- critical
```

### Capa de Contratos de Autorización

#### TicketAuthorizationContract

```
Métodos básicos:
- canViewTicket(User, Ticket): bool
- canCreateTicket(User): bool
- canUpdateTicket(User, Ticket): bool
- canDeleteTicket(User, Ticket): bool
- canCloseTicket(User, Ticket): bool

Métodos específicos de nivel:
- canAccessLevel(User, TicketLevel): bool
- canAssignToLevel(User, TicketLevel): bool
- canRequestEscalation(User, Ticket): bool
- canApproveEscalation(User, EscalationRequest): bool

Métodos de valoración:
- canEvaluateTicket(User, Ticket): bool
- canRateAgent(User, Ticket, Agent): bool
- canAssessRisk(User, Ticket): bool

Métodos departamentales:
- canAccessDepartment(User, Department): bool
- canAssignToDepartment(User, Department): bool

Métodos administrativos:
- canManageLevels(User): bool
- canManageDepartments(User): bool
- canViewStatistics(User): bool
```

#### UserCapabilityContract

```
Obtención de contexto del usuario en el sistema:
- getUserLevel(User): ?TicketLevel
- getUserDepartments(User): Collection<Department>
- canUserEscalateTo(User, TicketLevel): bool
- getUserAssignedTickets(User): Collection<Ticket>
```

#### NotificationContract

```
Sistema de notificaciones desacoplado:
- notifyTicketCreated(Ticket): void
- notifyTicketAssigned(Ticket, User): void
- notifyEscalationRequested(EscalationRequest): void
- notifyEscalationApproved(EscalationRequest): void
- notifyTicketClosed(Ticket): void
- notifyEvaluationReceived(User, TicketEvaluation): void
```

### Capa de Servicios

#### TicketService

```
Operaciones principales:
- createTicket(data, creator): Ticket
- assignTicket(Ticket, User, Department): void
- updateTicketStatus(Ticket, status): void
- closeTicket(Ticket, resolver): void
```

#### EscalationService

```
Gestión de escalados:
- requestEscalation(Ticket, targetLevel, justification): EscalationRequest
- approveEscalation(EscalationRequest, approver): void
- rejectEscalation(EscalationRequest, approver, reason): void
- autoEscalateByTimeout(Ticket): void
```

#### EvaluationService

```
Sistema de valoraciones:
- evaluateTicket(Ticket, evaluator, score, comment): TicketEvaluation
- rateAgent(Ticket, agent, rater, score, comment): AgentRating
- calculateAgentAverageRating(User): float
- getTicketStatistics(): array
```

#### RiskAssessmentService

```
Valoración de riesgos:
- assessRisk(Ticket, assessor, level, justification): RiskAssessment
- canUserAssessRisk(User): bool
- getHighRiskTickets(): Collection
- escalateByRisk(Ticket): void
```

#### AssignmentService

```
Gestión de asignaciones:
- assignAgent(Ticket, User): TicketAssignment
- unassignAgent(Ticket, User): void
- getAvailableAgentsForLevel(TicketLevel): Collection
- autoAssignByWorkload(Ticket): void
```

### Capa de Eventos

```
TicketCreated
TicketAssigned
TicketStatusChanged
EscalationRequested
EscalationApproved
EscalationRejected
TicketEvaluated
AgentRated
RiskAssessed
TicketClosed
SLABreached
```

Todos los eventos deben ser escuchables por la aplicación para implementar lógica adicional.

### Capa de Configuración

#### config/laratickets.php

```
Configuración modular:

authorization:
  - handler: clase que implementa TicketAuthorizationContract
  - capability_handler: clase que implementa UserCapabilityContract

notifications:
  - handler: clase que implementa NotificationContract
  - channels: array de canales por tipo de evento

levels:
  - definición de niveles por defecto
  - sla_hours por nivel
  - auto_escalation_enabled

departments:
  - definición de departamentos por defecto
  
evaluation:
  - enabled
  - required_on_close
  - agent_rating_enabled
  
risk_assessment:
  - enabled
  - required_levels: [III, IV]
  - auto_escalate_on_critical

sla:
  - enabled
  - breach_notifications
  - auto_escalation_on_breach
```

## Flujos de Negocio Principales

### Creación de Ticket

1. Usuario crea ticket (prioridad subjetiva)
2. Sistema asigna nivel I por defecto
3. Sistema determina departamento inicial
4. Evento TicketCreated
5. Notificación a agentes Level I del departamento

### Escalado de Ticket

**Escalado Manual:**

1. Agente Level I solicita escalado a Level II
2. Sistema crea EscalationRequest
3. Evento EscalationRequested
4. Notificación a responsables Level II
5. Responsable Level II aprueba/rechaza
6. Si aprueba: ticket cambia de nivel
7. Evento EscalationApproved/Rejected

**Escalado Automático:**

1. SLA excedido o riesgo crítico detectado
2. Sistema crea escalado automático
3. Ticket sube de nivel sin aprobación
4. Notificación a nivel superior

### Valoración de Riesgo

1. Level III o IV evalúa ticket
2. Sistema registra RiskAssessment
3. Si riesgo crítico: dispara escalado automático
4. Evento RiskAssessed
5. Recálculo de prioridad efectiva

### Cierre y Evaluación

1. Agente resuelve ticket
2. Usuario cierra ticket (o cierre automático tras N días)
3. Sistema solicita evaluación global
4. Sistema solicita valoración de agentes implicados
5. Eventos TicketEvaluated y AgentRated
6. Actualización de puntuaciones agregadas

## Integración con la Aplicación

### Implementación de Contratos

La aplicación debe proporcionar:

```
App\Laratickets\Authorization\TicketAuthorizationHandler
  implements TicketAuthorizationContract

App\Laratickets\Authorization\UserCapabilityHandler
  implements UserCapabilityContract

App\Laratickets\Notifications\TicketNotificationHandler
  implements NotificationContract
```

### Modelos de Usuario

La aplicación debe relacionar su modelo User con:
- TicketLevel (relación muchos a muchos para agentes multinivel)
- Department (relación muchos a muchos)
- Roles/Permisos de su sistema elegido

### Seeders y Datos Iniciales

El paquete proporciona:

- Seeder de niveles por defecto (I, II, III, IV)
- Seeder de departamentos por defecto (Técnico, Administración, Comercial)
- Factory para testing

La aplicación debe:

- Ejecutar seeders en instalación
- Vincular usuarios a niveles y departamentos
- Configurar permisos según su sistema

## Fases de Desarrollo

### Fase 1: Core Package

**Sprint 1: Fundamentos**

- Migraciones de entidades core
- Modelos Eloquent con relaciones
- Contratos de autorización
- Implementación por defecto de contratos
- Sistema de configuración

**Sprint 2: Servicios de Negocio**

- TicketService básico
- EscalationService
- AssignmentService
- Sistema de eventos

**Sprint 3: Valoraciones y Riesgo**

- EvaluationService
- RiskAssessmentService
- Cálculos agregados
- Lógica de escalado automático

**Sprint 4: Testing y Documentación**

- Tests unitarios de servicios
- Tests de integración
- Documentación de instalación
- Ejemplos de implementación

### Fase 2: API y Backend

**Sprint 5: API REST**
- Rutas API RESTful
- Resources para serialización
- Middleware de autorización
- Versionado API
- Documentación OpenAPI

**Sprint 6: Filament Backend**

- Recursos Filament para todas las entidades
- Dashboard de estadísticas
- Widgets de métricas
- Filtros y búsqueda avanzada
- Acciones bulk

**Sprint 7: Integraciones**

- Webhooks salientes
- Sistema de plantillas de respuesta
- Adjuntos y archivos
- Integración con correo

## Sistema de Permisos: Bouncer vs Spatie

### Análisis Comparativo

#### Bouncer

**Ventajas:**

- Más ligero y performante (menos overhead)
- Sintaxis más fluida y expresiva
- Menos tablas en base de datos (mejor para escalabilidad)
- Abilities dinámicas más naturales
- Mejor para permisos contextuales y condicionales
- Scope de modelos integrado nativamente
- API más limpia para chequeos complejos
- Perfecto para sistemas con lógica de autorización compleja

**Desventajas:**

- Ecosistema menor comparado con Spatie
- Menos helpers out-of-the-box
- Documentación más concisa (aunque suficiente)
- Comunidad más pequeña

**Estructura para Laratickets:**

```
Roles:
- support-agent-level-1
- support-agent-level-2
- support-agent-level-3
- support-agent-level-4
- risk-assessor
- department-manager

Abilities (con scope contextual):
- view-ticket (con restricciones de nivel/departamento/ownership)
- create-ticket
- update-ticket (con ownership y assignment checks)
- assign-ticket (con nivel de destino)
- escalate-ticket (con validación de nivel)
- approve-escalation (con nivel requerido)
- evaluate-ticket (con restricción post-cierre)
- rate-agent (con participación en ticket)
- assess-risk (con nivel III/IV)
- manage-departments
- manage-levels
- view-statistics

Scopes contextuales:
- own: tickets propios
- assigned: tickets asignados al usuario
- department: tickets del departamento del usuario
- level: tickets del nivel del usuario
- all: todos los tickets
```

#### Spatie Permission

**Ventajas:**

- Ecosistema Laravel más adoptado para permisos
- Documentación exhaustiva y comunidad amplia
- Caché de permisos nativa
- Soporte wildcard en permisos
- Helpers eloquentes intuitivos
- Middleware incluidos
- Compatible con teams/tenancy

**Desventajas:**

- Más opinionado en estructura
- Mayor footprint en base de datos
- Menos flexible para permisos contextuales complejos
- Requiere más boilerplate para lógica condicional
- Curva de aprendizaje en casos complejos

### Recomendación: Bouncer

**Justificación:**

1. **Naturaleza Contextual del Dominio:** Laratickets tiene autorización altamente contextual (nivel del usuario vs nivel del ticket, departamento, ownership, estado del ticket). Bouncer maneja esto de forma más natural con sus abilities condicionales y closures.

2. **Performance y Escalabilidad:** Con menor footprint en base de datos y queries más optimizados, Bouncer es superior para un sistema de tickets que puede manejar miles de operaciones de autorización por minuto.

3. **Flexibilidad Arquitectónica:** La sintaxis fluida de Bouncer permite expresar reglas complejas de forma más legible y mantenible, crucial para un paquete que otros desarrolladores extenderán.

4. **Autorización Programática:** Bouncer permite definir abilities con closures directamente, perfecto para reglas como "puede escalar si está asignado Y su nivel es menor al del ticket Y el ticket no está cerrado".

5. **Menor Acoplamiento:** La API más ligera de Bouncer facilita la creación de abstracciones y contratos, manteniendo la independencia arquitectónica del paquete.

6. **Scopes Nativos:** Los scopes de Bouncer (`owned-by`, `where`) se integran perfectamente con Eloquent para filtrar tickets según contexto de autorización sin queries adicionales.

7. **Modernidad y Futuro:** Bouncer adopta un enfoque más moderno y alineado con patrones de autorización actuales, especialmente para aplicaciones API-first.

### Implementación Sugerida con Bouncer

**Instalación en la Aplicación:**

```bash
composer require silber/bouncer
php artisan vendor:publish --tag="bouncer.migrations"
php artisan migrate
```

**Seeder de Roles y Abilities:**

El paquete Laratickets debe proporcionar un comando artisan:

```bash
php artisan laratickets:install --permissions
```

Este comando:

1. Crea roles base según niveles y departamentos
2. Define abilities base para el dominio de tickets
3. Asigna abilities a roles según matriz de autorización
4. Configura scopes y restricciones contextuales
5. Permite personalización posterior por la aplicación

**Implementación del Contrato:**

```php
// App\Laratickets\Authorization\BouncerTicketAuthorization

use Silber\Bouncer\BouncerFacade as Bouncer;

class BouncerTicketAuthorization implements TicketAuthorizationContract
{
    public function canViewTicket(User $user, Ticket $ticket): bool
    {
        // Bouncer permite chequeos contextuales elegantes
        return Bouncer::can('view-ticket', $ticket)
            || ($user->id === $ticket->created_by && Bouncer::can('view-own-tickets'))
            || $this->canViewByDepartment($user, $ticket)
            || $this->canViewByLevel($user, $ticket);
    }
    
    protected function canViewByDepartment(User $user, Ticket $ticket): bool
    {
        if (!Bouncer::can('view-department-tickets')) {
            return false;
        }
        
        return $user->departments->contains($ticket->department_id);
    }
    
    protected function canViewByLevel(User $user, Ticket $ticket): bool
    {
        if (!Bouncer::can('view-level-tickets')) {
            return false;
        }
        
        $userLevel = $this->getUserLevel($user);
        return $userLevel && $userLevel->level >= $ticket->current_level;
    }
    
    public function canRequestEscalation(User $user, Ticket $ticket): bool
    {
        // Ability con validación contextual
        return Bouncer::can('escalate-ticket') 
            && $this->isUserAssignedOrInLevel($user, $ticket)
            && $ticket->current_level < 4
            && !in_array($ticket->status->value, ['resolved', 'closed', 'cancelled']);
    }
    
    public function canApproveEscalation(User $user, EscalationRequest $request): bool
    {
        if (!Bouncer::can('approve-escalation')) {
            return false;
        }
        
        $userLevel = $this->getUserLevel($user);
        
        // Usuario debe ser del nivel destino o superior
        return $userLevel && $userLevel->level >= $request->target_level;
    }
    
    public function canAssessRisk(User $user, Ticket $ticket): bool
    {
        // Ability específica solo para niveles III y IV
        return Bouncer::can('assess-risk')
            && $user->isA(['support-agent-level-3', 'support-agent-level-4']);
    }
    
    public function canEvaluateTicket(User $user, Ticket $ticket): bool
    {
        // Solo el creador puede evaluar y solo si está cerrado
        return Bouncer::can('evaluate-ticket')
            && $user->id === $ticket->created_by
            && in_array($ticket->status->value, ['resolved', 'closed']);
    }
    
    public function canRateAgent(User $user, Ticket $ticket, User $agent): bool
    {
        if (!Bouncer::can('rate-agent')) {
            return false;
        }
        
        // Usuario debe ser creador o haber participado en el ticket
        $isCreator = $user->id === $ticket->created_by;
        $wasAssigned = $ticket->assignments()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->exists();
            
        // Agente debe haber participado
        $agentParticipated = $ticket->assignments()
            ->where('user_id', $agent->id)
            ->exists();
        
        return ($isCreator || $wasAssigned) 
            && $agentParticipated
            && in_array($ticket->status->value, ['resolved', 'closed']);
    }
    
    public function canManageLevels(User $user): bool
    {
        return Bouncer::can('manage-levels');
    }
    
    public function canManageDepartments(User $user): bool
    {
        return Bouncer::can('manage-departments');
    }
    
    public function canViewStatistics(User $user): bool
    {
        return Bouncer::can('view-statistics');
    }
    
    protected function isUserAssignedOrInLevel(User $user, Ticket $ticket): bool
    {
        $isAssigned = $ticket->assignments()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->exists();
            
        if ($isAssigned) {
            return true;
        }
        
        $userLevel = $this->getUserLevel($user);
        return $userLevel && $userLevel->level === $ticket->current_level;
    }
    
    // Implementación auxiliar para obtener nivel del usuario
    protected function getUserLevel(User $user): ?TicketLevel
    {
        // Delegar al UserCapabilityHandler
        return app(UserCapabilityContract::class)->getUserLevel($user);
    }
}
```

**UserCapabilityHandler con Bouncer:**

```php
use Silber\Bouncer\BouncerFacade as Bouncer;

class BouncerUserCapabilityHandler implements UserCapabilityContract
{
    public function getUserLevel(User $user): ?TicketLevel
    {
        // Bouncer permite consultar roles del usuario fácilmente
        for ($level = 4; $level >= 1; $level--) {
            if ($user->isA("support-agent-level-{$level}")) {
                return TicketLevel::where('level', $level)->first();
            }
        }
        
        return null;
    }
    
    public function getUserDepartments(User $user): Collection
    {
        // Relación directa many-to-many con departamentos
        // (no extraída de roles como en Spatie)
        return $user->departments;
    }
    
    public function canUserEscalateTo(User $user, TicketLevel $targetLevel): bool
    {
        $userLevel = $this->getUserLevel($user);
        
        if (!$userLevel) {
            return false;
        }
        
        // Usuario puede escalar a su mismo nivel o al siguiente
        return $targetLevel->level <= $userLevel->level + 1;
    }
    
    public function getUserAssignedTickets(User $user): Collection
    {
        return Ticket::whereHas('assignments', function($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->whereNull('completed_at');
        })->get();
    }
}
```

**Definición de Abilities con Bouncer:**

```php
// En LaraticketsServiceProvider o comando de instalación

use Silber\Bouncer\BouncerFacade as Bouncer;

public function seedBouncerAbilities()
{
    // Abilities básicas de tickets
    Bouncer::ability()->firstOrCreate([
        'name' => 'view-ticket',
        'title' => 'View any ticket'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'view-own-tickets',
        'title' => 'View own tickets',
        'entity_type' => Ticket::class
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'view-department-tickets',
        'title' => 'View department tickets'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'view-level-tickets',
        'title' => 'View tickets of own level'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'create-ticket',
        'title' => 'Create tickets'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'update-ticket',
        'title' => 'Update tickets',
        'entity_type' => Ticket::class
    ]);
    
    // Abilities de escalado
    Bouncer::ability()->firstOrCreate([
        'name' => 'escalate-ticket',
        'title' => 'Request ticket escalation'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'approve-escalation',
        'title' => 'Approve escalation requests'
    ]);
    
    // Abilities de valoración
    Bouncer::ability()->firstOrCreate([
        'name' => 'evaluate-ticket',
        'title' => 'Evaluate closed tickets'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'rate-agent',
        'title' => 'Rate agent performance'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'assess-risk',
        'title' => 'Assess ticket risk level'
    ]);
    
    // Abilities administrativas
    Bouncer::ability()->firstOrCreate([
        'name' => 'manage-levels',
        'title' => 'Manage ticket levels'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'manage-departments',
        'title' => 'Manage departments'
    ]);
    
    Bouncer::ability()->firstOrCreate([
        'name' => 'view-statistics',
        'title' => 'View system statistics'
    ]);
}

public function seedBouncerRoles()
{
    // Roles por nivel
    $level1 = Bouncer::role()->firstOrCreate([
        'name' => 'support-agent-level-1',
        'title' => 'Support Agent Level I'
    ]);
    
    $level2 = Bouncer::role()->firstOrCreate([
        'name' => 'support-agent-level-2',
        'title' => 'Support Agent Level II'
    ]);
    
    $level3 = Bouncer::role()->firstOrCreate([
        'name' => 'support-agent-level-3',
        'title' => 'Support Agent Level III'
    ]);
    
    $level4 = Bouncer::role()->firstOrCreate([
        'name' => 'support-agent-level-4',
        'title' => 'Support Agent Level IV'
    ]);
    
    // Asignar abilities a Level I
    Bouncer::allow($level1)->to([
        'view-own-tickets',
        'view-level-tickets',
        'create-ticket',
        'update-ticket',
        'escalate-ticket',
    ]);
    
    // Asignar abilities a Level II (hereda de I más extras)
    Bouncer::allow($level2)->to([
        'view-own-tickets',
        'view-level-tickets',
        'view-department-tickets',
        'create-ticket',
        'update-ticket',
        'escalate-ticket',
        'approve-escalation', // Puede aprobar escalados de I a II
        'rate-agent',
    ]);
    
    // Asignar abilities a Level III
    Bouncer::allow($level3)->to([
        'view-own-tickets',
        'view-level-tickets',
        'view-department-tickets',
        'create-ticket',
        'update-ticket',
        'escalate-ticket',
        'approve-escalation',
        'rate-agent',
        'assess-risk', // Nuevo: puede valorar riesgo
        'evaluate-ticket',
    ]);
    
    // Asignar abilities a Level IV (máximo nivel)
    Bouncer::allow($level4)->to([
        'view-ticket', // Ve todos los tickets
        'create-ticket',
        'update-ticket',
        'approve-escalation',
        'rate-agent',
        'assess-risk',
        'evaluate-ticket',
        'view-statistics',
        'manage-departments',
        'manage-levels',
    ]);
}
```

**Uso de Scopes con Bouncer:**

```php
// En modelos o query builders

// Obtener tickets que el usuario puede ver
$tickets = Ticket::whereOwnedBy($user)
    ->orWhere(function($query) use ($user) {
        // Tickets del departamento del usuario
        $query->whereIn('department_id', $user->departments->pluck('id'));
    })
    ->orWhere(function($query) use ($user) {
        // Tickets del nivel del usuario o inferior
        $userLevel = app(UserCapabilityContract::class)->getUserLevel($user);
        if ($userLevel) {
            $query->where('current_level', '<=', $userLevel->level);
        }
    })
    ->get();

// Bouncer también permite scopes personalizados
Bouncer::scope()->onceTo($user->departments->pluck('id'), function ($departmentIds) {
    return Ticket::whereIn('department_id', $departmentIds);
});
```

**Middleware con Bouncer:**

```php
// routes/api.php o web.php

Route::middleware(['auth', 'can:view-ticket'])->group(function () {
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
});

Route::middleware(['auth', 'can:create-ticket'])->group(function () {
    Route::post('/tickets', [TicketController::class, 'store']);
});

Route::middleware(['auth', 'can:manage-levels'])->group(function () {
    Route::resource('ticket-levels', TicketLevelController::class);
});
```

**Policies con Bouncer:**

```php
// app/Policies/TicketPolicy.php

class TicketPolicy
{
    use HandlesAuthorization;
    
    protected TicketAuthorizationContract $authorization;
    
    public function __construct(TicketAuthorizationContract $authorization)
    {
        $this->authorization = $authorization;
    }
    
    public function view(User $user, Ticket $ticket): bool
    {
        return $this->authorization->canViewTicket($user, $ticket);
    }
    
    public function update(User $user, Ticket $ticket): bool
    {
        return $this->authorization->canUpdateTicket($user, $ticket);
    }
    
    public function escalate(User $user, Ticket $ticket): bool
    {
        return $this->authorization->canRequestEscalation($user, $ticket);
    }
    
    public function assessRisk(User $user, Ticket $ticket): bool
    {
        return $this->authorization->canAssessRisk($user, $ticket);
    }
    
    // Bouncer automáticamente delega a estas policies cuando
    // se usa la sintaxis $user->can('update', $ticket)
}
```

### Consideraciones de Migración

Si la aplicación ya usa otro sistema de permisos:

1. **Spatie Existente:** El paquete puede proporcionar `SpatieTicketAuthorization` como implementación alternativa. La arquitectura basada en contratos permite cambiar entre sistemas sin modificar lógica de negocio.

2. **Sistema Custom:** Documentar interfaz del contrato claramente para implementación custom. Bouncer ofrece mayor flexibilidad para integrar sistemas existentes.

3. **Sin Sistema:** Recomendar Bouncer en documentación y proporcionar guía de instalación completa con ejemplos de seeding.

4. **Migración de Spatie a Bouncer:** Proporcionar comando artisan para migrar roles y permisos:

```bash
php artisan laratickets:migrate-from-spatie
```

### Ventajas Específicas de Bouncer para Laratickets

**1. Autorización Condicional Natural:**

```php
// Con Bouncer
Bouncer::allow($user)->to('escalate-ticket', $ticket, function ($user, $ticket) {
    return $ticket->current_level < 4 
        && $ticket->status !== 'closed'
        && $user->id === $ticket->assigned_to;
});

// Vs Spatie (requiere Gates o Policies adicionales)
Gate::define('escalate-ticket', function ($user, $ticket) {
    if (!$user->hasPermissionTo('escalate-ticket')) return false;
    // ... resto de lógica
});
```

**2. Abilities por Instancia:**

```php
// Permitir a un usuario específico gestionar un ticket específico
Bouncer::allow($supervisor)->to('override-ticket', $criticalTicket);

// Útil para casos excepcionales sin modificar roles
```

**3. Scopes Temporales:**

```php
// Durante una sesión de emergencia, elevar permisos temporalmente
Bouncer::scope()->onceTo(Role::where('name', 'emergency-responder')->first(), function () {
    // Usuario tiene permisos elevados solo en este scope
    $tickets = Ticket::all(); // Ve todos
});
```

**4. Forbid Explícito:**

```php
// Revocar explícitamente un ability sin eliminar el rol
Bouncer::forbid($user)->to('escalate-ticket');

// Útil para sanciones temporales o restricciones específicas
```

**5. Queries Optimizados:**

```php
// Bouncer optimiza queries con eager loading automático
$tickets = Ticket::all();
foreach ($tickets as $ticket) {
    if ($user->can('update', $ticket)) { // Sin N+1 queries
        // ...
    }
}
```

## Estructura de Directorios del Paquete

```
src/
├── Commands/
│   ├── InstallCommand.php
│   ├── SeedBouncerPermissionsCommand.php
│   └── MigrateFromSpatieCommand.php
├── Contracts/
│   ├── TicketAuthorizationContract.php
│   ├── UserCapabilityContract.php
│   └── NotificationContract.php
├── Models/
│   ├── Ticket.php
│   ├── TicketLevel.php
│   ├── Department.php
│   ├── TicketAssignment.php
│   ├── EscalationRequest.php
│   ├── TicketEvaluation.php
│   ├── AgentRating.php
│   └── RiskAssessment.php
├── Services/
│   ├── TicketService.php
│   ├── EscalationService.php
│   ├── EvaluationService.php
│   ├── RiskAssessmentService.php
│   └── AssignmentService.php
├── Events/
│   ├── TicketCreated.php
│   ├── TicketAssigned.php
│   ├── EscalationRequested.php
│   └── [resto de eventos]
├── Enums/
│   ├── TicketStatus.php
│   ├── Priority.php
│   └── RiskLevel.php
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Resources/
├── Implementations/
│   ├── BouncerTicketAuthorization.php
│   ├── BouncerUserCapabilityHandler.php
│   └── DefaultNotificationHandler.php
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
├── Config/
│   └── laratickets.php
├── Policies/
│   └── TicketPolicy.php
└── LaraticketsServiceProvider.php
```

## Puntos Críticos de Decisión

### Modelo de Usuario

**Decisión:** No crear modelo User en el paquete.

**Implementación:**

- Usar morfismo para relaciones con usuarios cuando sea necesario
- Referenciar `config('auth.providers.users.model')` por defecto
- Permitir configuración del modelo en config del paquete
- Soporte para diferentes tipos de ID: integer, UUID v4, UUID v7 (binario y no binario), ULID

**Configuración en config/laratickets.php:**

```php
'user' => [
    'model' => env('LARATICKETS_USER_MODEL', config('auth.providers.users.model')),
    'id_column' => env('LARATICKETS_USER_ID_COLUMN', 'id'),
    'id_type' => env('LARATICKETS_USER_ID_TYPE', 'auto'), // auto, int, uuid, ulid
],
```

> **Nota:** `uuid_binary` fue eliminado en v1.0 por incompatibilidad con FilamentPHP v4.
> Ver ADR-002 para más detalles.

**Migraciones Adaptables:**

El paquete usa `MigrationHelper` para crear columnas de usuario de forma agnóstica:

```php
use AichaDigital\Laratickets\Support\MigrationHelper;

Schema::create('tickets', function (Blueprint $table) {
    // Crea columna del tipo correcto según config
    MigrationHelper::userIdColumn($table, 'created_by');

    // ...resto de campos
});
```

**Testing con UUID v7:**

En el escenario de pruebas del paquete sobre aplicación:

```php
// config/laratickets.php en app de testing
return [
    'user' => [
        'model' => App\Models\User::class,
        'id_column' => 'id',
        'id_type' => 'uuid', // UUID v7 string (recomendado)
    ],
    // ...resto configuración
];

// app/Models/User.php en app de testing
class User extends Authenticatable
{
    use HasUuids;
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid7();
            }
        });
    }
    
    // Cast para trabajar con UUID binario
    protected $casts = [
        'id' => 'string',
    ];
}
```

### SLA y Tiempos

**Decisión:** Sistema básico incorporado, extensible.

**Implementación:**

- Configuración de horas SLA por nivel
- Comando programado para detección de breaches
- Eventos para que la aplicación añada lógica adicional
- Bouncer puede gestionar abilities temporales basadas en SLA

### Notificaciones

**Decisión:** Sistema delegado completamente.

**Implementación:**

- Eventos ricos con toda la información
- NotificationContract para casos estándar
- Aplicación decide canales y destinatarios
- Bouncer facilita filtrar destinatarios por abilities

## Métricas y Observabilidad

El paquete debe proporcionar:

**Métricas Básicas:**

- Tickets abiertos por nivel
- Tiempo promedio de resolución por nivel
- Tasa de escalado
- Puntuación promedio de agentes
- Distribución de riesgos
- Breaches de SLA
- Uso de abilities (auditoría de Bouncer)

**Queries Optimizados:**

- Eager loading por defecto en relaciones frecuentes
- Índices en columnas de filtrado común
- Scopes eloquent para queries comunes
- Aprovechar scopes de Bouncer para filtrado por autorización

**Logging:**

- Eventos críticos logueados automáticamente
- Nivel configurable
- Información contextual rica
- Auditoría de cambios de permisos con Bouncer

**Dashboard de Bouncer:**

```php
// Métricas de autorización
$metrics = [
    'escalations_approved_by_user' => EscalationRequest::where('approved_by', $user->id)->count(),
    'risk_assessments_by_level' => RiskAssessment::whereHas('assessor', function($q) use ($user) {
        $q->whereIs('support-agent-level-3')
          ->orWhereIs('support-agent-level-4');
    })->count(),
    'tickets_by_ability' => Ticket::whereAllowed('view', $user)->count(),
];
```

## Conclusión

Laratickets debe ser un paquete robusto, opinado en su dominio pero flexible en infraestructura. La separación clara entre responsabilidades del paquete y de la aplicación, junto con contratos bien definidos, permitirá adopción amplia y customización profunda sin sacrificar coherencia arquitectónica.

La elección de **Bouncer** como sistema de permisos recomendado proporciona:

- **Performance superior** para operaciones de autorización frecuentes
- **Flexibilidad natural** para la complejidad contextual del dominio
- **Sintaxis expresiva** que mejora la mantenibilidad del código
- **Menor footprint** en base de datos, mejorando escalabilidad
- **Integración elegante** con la arquitectura basada en contratos

La arquitectura basada en contratos garantiza que aplicaciones con Spatie Permission u otros sistemas puedan implementar sus propios handlers sin modificar el core del paquete, manteniendo así la independencia arquitectónica mientras se optimiza para el caso de uso más eficiente.
