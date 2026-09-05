<?php

namespace App\API\Services;

use App\Database\Database;
use DomainException;
use PDO;
use Throwable;

/** System Administrator-only inventory and irreversible purge for the classified test realm. */
final class TestDataManagementService
{
    private PDO $db;
    private array $deletionStack = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function inventory(): array
    {
        $counts = [
            'test_accounts' => $this->count("SELECT COUNT(*) FROM users WHERE account_type='test' OR is_test_user=1"),
            'test_persons' => $this->count("SELECT COUNT(*) FROM persons WHERE data_scope='test'"),
            'test_staff' => $this->count("SELECT COUNT(*) FROM staff WHERE data_scope='test'"),
            'test_payroll_profiles' => $this->count("SELECT COUNT(*) FROM staff_payroll_profiles WHERE data_scope='test'"),
            'test_payslips' => $this->count("SELECT COUNT(*) FROM payslips WHERE data_scope='test'"),
            'test_payroll_runs' => $this->count("SELECT COUNT(*) FROM payroll_runs WHERE data_scope='test'"),
        ];
        $counts['classified_records'] = array_sum($counts);
        return [
            'counts' => $counts,
            'permanent' => true,
            'confirmation_phrase' => 'PERMANENTLY DELETE ALL TEST DATA',
            'scope' => 'Only identities and records classified in the test realm and their direct identity/payroll dependants.',
        ];
    }

    public function purgeAll(int $actorId, string $confirmation, string $reason): array
    {
        if ($actorId <= 0) throw new DomainException('A valid System Administrator is required');
        if (trim($confirmation) !== 'PERMANENTLY DELETE ALL TEST DATA') {
            throw new DomainException('Type PERMANENTLY DELETE ALL TEST DATA to confirm');
        }
        $reason = trim($reason);
        if ($reason === '') throw new DomainException('A deletion reason is required');

        $before = $this->inventory();
        $ids = $this->rootIds();
        $this->db->beginTransaction();
        try {
            $this->purgeRoots($ids, true);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }

        Logger::audit('test_realm_permanently_purged', 'system', null, 'All classified test-realm data was permanently deleted.', [
            'deleted_by' => $actorId,
            'reason' => $reason,
            'counts' => $before['counts'],
        ]);
        return ['deleted' => $before['counts'], 'remaining' => $this->inventory()['counts']];
    }

    public function purgeAccount(int $userId, int $actorId, string $reason): array
    {
        if ($userId <= 0 || $actorId <= 0) throw new DomainException('Valid account and administrator IDs are required');
        if ($userId === $actorId) throw new DomainException('You cannot delete your own account');
        $stmt = $this->db->prepare(
            "SELECT u.id,u.person_id FROM users u
             WHERE u.id=? AND (u.account_type='test' OR u.is_test_user=1) LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) throw new DomainException('Only a test account can be deleted with test-data cleanup');

        $personId = (int) ($user['person_id'] ?? 0);
        $staffStmt = $this->db->prepare("SELECT id FROM staff WHERE person_id=? AND data_scope='test'");
        $staffStmt->execute([$personId]);
        $staffIds = array_map('intval', $staffStmt->fetchAll(PDO::FETCH_COLUMN));
        $payslipIds = [];
        if ($staffIds) {
            [$in, $params] = $this->inClause($staffIds);
            $pay = $this->db->prepare("SELECT id FROM payslips WHERE data_scope='test' AND staff_id IN ({$in})");
            $pay->execute($params);
            $payslipIds = array_map('intval', $pay->fetchAll(PDO::FETCH_COLUMN));
        }
        $ids = [
            'users' => [$userId],
            'persons' => $personId > 0 ? [$personId] : [],
            'staff' => $staffIds,
            'payslips' => $payslipIds,
            'payroll_runs' => [],
        ];

        $this->db->beginTransaction();
        try {
            $this->purgeRoots($ids, false);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        Logger::audit('test_account_permanently_deleted', 'user', $userId, 'Test account and its classified test data were permanently deleted.', [
            'deleted_by' => $actorId,
            'reason' => trim($reason),
        ]);
        return ['id' => $userId, 'deleted' => true, 'test_data_deleted' => true];
    }

    private function purgeRoots(array $ids, bool $allTestData): void
    {
        $this->deleteDependants('payslip_id', $ids['payslips'], ['payslips']);
        $this->deleteDependants('payroll_run_id', $ids['payroll_runs'], ['payroll_runs']);
        $this->deleteDependants('staff_id', $ids['staff'], ['staff', 'payslips', 'staff_payroll_profiles']);
        $this->deleteDependants('person_id', $ids['persons'], ['persons', 'users', 'staff']);
        $this->deleteDependants('user_id', $ids['users'], ['users']);

        if ($allTestData) {
            $this->deleteByScope('payslips');
            $this->deleteByScope('staff_payroll_profiles');
            $this->deleteByScope('payroll_runs');
            $this->deleteByScope('staff');
        } else {
            $this->deleteIds('payslips', 'id', $ids['payslips']);
            $this->deleteIds('staff_payroll_profiles', 'staff_id', $ids['staff']);
            $this->deleteIds('staff', 'id', $ids['staff']);
        }

        // Resolve declared user references which are not conventional user_id
        // ownership columns. Nullable audit references are cleared; required
        // test-owned records are removed.
        $this->resolveForeignKeyReferences('users', $ids['users'], ['users']);
        $this->deleteIds('users', 'id', $ids['users']);
        if ($allTestData) {
            $this->deleteByScope('persons');
        } else {
            $this->deleteIds('persons', 'id', $ids['persons']);
        }
    }

    private function rootIds(): array
    {
        return [
            'users' => $this->ids("SELECT id FROM users WHERE account_type='test' OR is_test_user=1"),
            'persons' => $this->ids("SELECT id FROM persons WHERE data_scope='test'"),
            'staff' => $this->ids("SELECT id FROM staff WHERE data_scope='test'"),
            'payslips' => $this->ids("SELECT id FROM payslips WHERE data_scope='test'"),
            'payroll_runs' => $this->ids("SELECT id FROM payroll_runs WHERE data_scope='test'"),
        ];
    }

    private function deleteDependants(string $column, array $ids, array $excluded): void
    {
        if (!$ids) return;
        $stmt = $this->db->prepare(
            'SELECT TABLE_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME=? AND TABLE_NAME NOT LIKE ?'
        );
        $stmt->execute([$column, 'vw\_%']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (in_array($table, $excluded, true)) continue;
            $this->deleteIds((string) $table, $column, $ids);
        }
    }

    private function resolveForeignKeyReferences(string $parent, array $ids, array $excluded): void
    {
        if (!$ids) return;
        $stmt = $this->db->prepare(
            'SELECT k.TABLE_NAME,k.COLUMN_NAME,c.IS_NULLABLE
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.COLUMNS c
               ON c.TABLE_SCHEMA=k.TABLE_SCHEMA AND c.TABLE_NAME=k.TABLE_NAME AND c.COLUMN_NAME=k.COLUMN_NAME
             WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME=?'
        );
        $stmt->execute([$parent]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $reference) {
            $table = (string) $reference['TABLE_NAME'];
            if (in_array($table, $excluded, true)) continue;
            $column = (string) $reference['COLUMN_NAME'];
            if ($reference['IS_NULLABLE'] === 'YES') {
                [$in, $params] = $this->inClause($ids);
                $this->db->prepare("UPDATE `{$table}` SET `{$column}`=NULL WHERE `{$column}` IN ({$in})")
                    ->execute($params);
            } else {
                $this->deleteIds($table, $column, $ids);
            }
        }
    }

    private function deleteByScope(string $table): void
    {
        $this->db->exec("DELETE FROM `{$table}` WHERE data_scope='test'");
    }

    private function deleteIds(string $table, string $column, array $ids): void
    {
        if (!$ids) return;
        [$in, $params] = $this->inClause($ids);
        $stackKey = $table . ':' . $column . ':' . hash('sha256', implode(',', $params));
        if (isset($this->deletionStack[$stackKey])) return;
        $this->deletionStack[$stackKey] = true;

        // Some legacy tables use RESTRICT rather than CASCADE. Walk declared
        // child constraints before the parent delete so the entire operation
        // can remain transactional without disabling foreign-key checks.
        $references = $this->db->prepare(
            'SELECT k.TABLE_NAME,k.COLUMN_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
             WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME=?'
        );
        $references->execute([$table]);
        foreach ($references->fetchAll(PDO::FETCH_ASSOC) as $reference) {
            if (in_array($reference['DELETE_RULE'], ['CASCADE', 'SET NULL'], true)) continue;
            $parentValues = $this->db->prepare(
                "SELECT DISTINCT `{$reference['REFERENCED_COLUMN_NAME']}`
                 FROM `{$table}` WHERE `{$column}` IN ({$in})"
            );
            $parentValues->execute($params);
            $values = array_values(array_filter(
                $parentValues->fetchAll(PDO::FETCH_COLUMN),
                static fn($value) => $value !== null
            ));
            $this->deleteIds((string) $reference['TABLE_NAME'], (string) $reference['COLUMN_NAME'], $values);
        }
        $this->db->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN ({$in})")->execute($params);
        unset($this->deletionStack[$stackKey]);
    }

    private function inClause(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return [implode(',', array_fill(0, count($ids), '?')), $ids];
    }

    private function ids(string $sql): array
    {
        return array_map('intval', $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    private function count(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }
}
