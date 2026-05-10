<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Tests\Integration\Mysql;

/**
 * Fresh install MySQL contract: every user-FK column must be char(36) UUID.
 *
 * Verifies the 9 FK columns documented in ADR-001:
 *   tickets.created_by, tickets.resolved_by,
 *   ticket_assignments.user_id,
 *   escalation_requests.requester_id, escalation_requests.approver_id,
 *   ticket_evaluations.evaluator_id,
 *   agent_ratings.agent_id, agent_ratings.rater_id,
 *   risk_assessments.assessor_id.
 */
final class FreshInstallTest extends MysqlIntegrationTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function userFkColumnsProvider(): array
    {
        return [
            'tickets.created_by' => ['tickets', 'created_by'],
            'tickets.resolved_by' => ['tickets', 'resolved_by'],
            'ticket_assignments.user_id' => ['ticket_assignments', 'user_id'],
            'escalation_requests.requester_id' => ['escalation_requests', 'requester_id'],
            'escalation_requests.approver_id' => ['escalation_requests', 'approver_id'],
            'ticket_evaluations.evaluator_id' => ['ticket_evaluations', 'evaluator_id'],
            'agent_ratings.agent_id' => ['agent_ratings', 'agent_id'],
            'agent_ratings.rater_id' => ['agent_ratings', 'rater_id'],
            'risk_assessments.assessor_id' => ['risk_assessments', 'assessor_id'],
        ];
    }

    public function test_fresh_install_emits_all_user_fk_columns_as_char36(): void
    {
        $this->bootstrap();

        foreach (self::userFkColumnsProvider() as $label => [$table, $column]) {
            $type = $this->getMysqlColumnType($table, $column);
            $length = $this->getMysqlColumnLength($table, $column);

            $this->assertSame(
                'char',
                $type,
                "Column {$label} should be CHAR (got: {$type})"
            );
            $this->assertSame(
                36,
                $length,
                "Column {$label} should have length 36 (got: ".($length ?? 'null').')'
            );
        }
    }
}
