<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * ParentPtaMembershipService — owns parent_pta_memberships reads/writes.
 * Extracted from CommunicationsController under the strict SQL placement rule:
 * controllers expose the API shape; this service owns the persistence.
 */
final class ParentPtaMembershipService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT m.id,m.parent_id,m.role,m.membership_status AS status,m.appointed_at,m.ended_at,m.notes,
                CONCAT_WS(' ',pp.first_name,pp.middle_name,pp.last_name) AS name,pp.phone,pp.email
            FROM parent_pta_memberships m JOIN " . ReadReplicaService::qualifiedRef("parents") . " p ON p.id=m.parent_id JOIN " . ReadReplicaService::qualifiedRef("persons") . " pp ON pp.id=p.person_id WHERE m.id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listAll(): array
    {
        return $this->pdo->query("SELECT m.id,m.parent_id,m.role,m.membership_status AS status,m.appointed_at,m.ended_at,m.notes,
                CONCAT_WS(' ',pp.first_name,pp.middle_name,pp.last_name) AS name,pp.phone,pp.email
            FROM parent_pta_memberships m JOIN " . ReadReplicaService::qualifiedRef("parents") . " p ON p.id=m.parent_id JOIN " . ReadReplicaService::qualifiedRef("persons") . " pp ON pp.id=p.person_id
            ORDER BY m.membership_status='active' DESC, pp.first_name,pp.last_name,m.id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function parentExists(int $parentId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM parents WHERE id=?');
        $stmt->execute([$parentId]);
        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data, int $actorId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO parent_pta_memberships (parent_id,role,membership_status,appointed_at,notes,created_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            (int) $data['parent_id'], trim((string) ($data['role'] ?? 'Member')),
            $data['status'] ?? 'active', $data['appointed_at'] ?? null, $data['notes'] ?? null, $actorId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $values = [];
        foreach (['role', 'status', 'appointed_at', 'ended_at', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field === 'status' ? 'membership_status=?' : $field . '=?';
                $values[] = $data[$field];
            }
        }
        if ($fields === []) {
            throw new \InvalidArgumentException('No PTA membership changes supplied');
        }
        $values[] = $id;
        $this->pdo->prepare('UPDATE parent_pta_memberships SET ' . implode(',', $fields) . ' WHERE id=?')->execute($values);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM parent_pta_memberships WHERE id=?')->execute([$id]);
    }
}
