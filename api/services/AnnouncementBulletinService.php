<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

class AnnouncementBulletinService
{
    private PDO $db;

    private const VALID_TYPES = ['general', 'academic', 'administrative', 'event', 'emergency', 'maintenance'];
    private const VALID_PRIORITIES = ['low', 'normal', 'high', 'critical'];
    private const VALID_STATUSES = ['draft', 'scheduled', 'published', 'archived', 'expired'];
    private const VALID_AUDIENCES = ['all', 'staff', 'students', 'parents', 'specific'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(a.title LIKE :search OR a.content LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES, true)) {
            $where[] = 'a.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['announcement_type']) && in_array($filters['announcement_type'], self::VALID_TYPES, true)) {
            $where[] = 'a.announcement_type = :announcement_type';
            $params[':announcement_type'] = $filters['announcement_type'];
        }

        if (!empty($filters['priority']) && in_array($filters['priority'], self::VALID_PRIORITIES, true)) {
            $where[] = 'a.priority = :priority';
            $params[':priority'] = $filters['priority'];
        }

        if (!empty($filters['target_audience']) && in_array($filters['target_audience'], self::VALID_AUDIENCES, true)) {
            $where[] = 'a.target_audience = :target_audience';
            $params[':target_audience'] = $filters['target_audience'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM announcements_bulletin a {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $sql = "SELECT a.*, 
                        s.id AS staff_id, 
                        p.first_name, 
                        p.last_name
                FROM announcements_bulletin a
                LEFT JOIN staff s ON a.published_by = s.id
                LEFT JOIN persons p ON s.person_id = p.id
                {$whereClause}
                ORDER BY a.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $items[] = $this->formatRow($row);
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT a.*, 
                       s.id AS staff_id, 
                       p.first_name, 
                       p.last_name
                FROM announcements_bulletin a
                LEFT JOIN staff s ON a.published_by = s.id
                LEFT JOIN persons p ON s.person_id = p.id
                WHERE a.id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $update = $this->db->prepare(
            "UPDATE announcements_bulletin SET view_count = view_count + 1 WHERE id = :id"
        );
        $update->execute([':id' => $id]);

        return $this->formatRow($row);
    }

    public function create(array $data, int $operatorStaffId): array
    {
        if (empty($data['title']) || !is_string($data['title'])) {
            throw new \InvalidArgumentException('Title is required.');
        }
        if (empty($data['content']) || !is_string($data['content'])) {
            throw new \InvalidArgumentException('Content is required.');
        }

        $type = $data['announcement_type'] ?? 'general';
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid announcement_type: {$type}");
        }

        $priority = $data['priority'] ?? 'normal';
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            throw new \InvalidArgumentException("Invalid priority: {$priority}");
        }

        $audience = $data['target_audience'] ?? 'all';
        if (!in_array($audience, self::VALID_AUDIENCES, true)) {
            throw new \InvalidArgumentException("Invalid target_audience: {$audience}");
        }

        $status = $data['status'] ?? 'draft';
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $audienceJson = $data['audience_json'] ?? null;
        if ($audienceJson !== null) {
            if (is_string($audienceJson)) {
                json_decode($audienceJson);
                if (json_last_error() !== \JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('audience_json must be valid JSON.');
                }
            } else {
                $audienceJson = json_encode($audienceJson);
            }
        }

        $scheduledAt = $data['scheduled_at'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;
        $publishedAt = null;

        if ($status === 'published') {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $sql = "INSERT INTO announcements_bulletin 
                    (title, content, announcement_type, priority, target_audience, audience_json, 
                     published_by, status, scheduled_at, published_at, expires_at)
                VALUES 
                    (:title, :content, :announcement_type, :priority, :target_audience, :audience_json,
                     :published_by, :status, :scheduled_at, :published_at, :expires_at)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':announcement_type' => $type,
            ':priority' => $priority,
            ':target_audience' => $audience,
            ':audience_json' => $audienceJson,
            ':published_by' => $operatorStaffId,
            ':status' => $status,
            ':scheduled_at' => $scheduledAt,
            ':published_at' => $publishedAt,
            ':expires_at' => $expiresAt,
        ]);

        $newId = (int) $this->db->lastInsertId();

        return $this->getById($newId) ?? ['id' => $newId];
    }

    public function update(int $id, array $data, int $operatorStaffId): array
    {
        $existing = $this->getById($id);
        if (!$existing) {
            throw new \OutOfBoundsException("Announcement not found: {$id}");
        }

        $allowed = [
            'title', 'content', 'announcement_type', 'priority',
            'target_audience', 'audience_json', 'status',
            'scheduled_at', 'expires_at', 'published_at',
        ];

        $sets = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            switch ($field) {
                case 'announcement_type':
                    if (!in_array($value, self::VALID_TYPES, true)) {
                        throw new \InvalidArgumentException("Invalid announcement_type: {$value}");
                    }
                    break;
                case 'priority':
                    if (!in_array($value, self::VALID_PRIORITIES, true)) {
                        throw new \InvalidArgumentException("Invalid priority: {$value}");
                    }
                    break;
                case 'status':
                    if (!in_array($value, self::VALID_STATUSES, true)) {
                        throw new \InvalidArgumentException("Invalid status: {$value}");
                    }
                    break;
                case 'target_audience':
                    if (!in_array($value, self::VALID_AUDIENCES, true)) {
                        throw new \InvalidArgumentException("Invalid target_audience: {$value}");
                    }
                    break;
                case 'audience_json':
                    if ($value !== null) {
                        if (is_string($value)) {
                            json_decode($value);
                            if (json_last_error() !== \JSON_ERROR_NONE) {
                                throw new \InvalidArgumentException('audience_json must be valid JSON.');
                            }
                        } else {
                            $value = json_encode($value);
                        }
                    }
                    break;
            }

            $sets[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        if (empty($sets)) {
            return $this->formatRow($existing);
        }

        if (
            array_key_exists('status', $data)
            && $data['status'] === 'published'
            && empty($existing['published_at'])
            && !array_key_exists('published_at', $data)
        ) {
            $sets[] = 'published_at = :published_at_auto';
            $params[':published_at_auto'] = date('Y-m-d H:i:s');
        }

        $sql = "UPDATE announcements_bulletin SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getById($id) ?? ['id' => $id];
    }

    public function delete(int $id): array
    {
        $stmt = $this->db->prepare("SELECT id FROM announcements_bulletin WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetch(\PDO::FETCH_ASSOC)) {
            throw new \OutOfBoundsException("Announcement not found: {$id}");
        }

        $delete = $this->db->prepare("DELETE FROM announcements_bulletin WHERE id = :id");
        $delete->execute([':id' => $id]);

        return ['deleted' => true, 'id' => $id];
    }

    public function getStats(): array
    {
        $sql = "SELECT status, COUNT(*) AS cnt FROM announcements_bulletin GROUP BY status";
        $stmt = $this->db->query($sql);

        $counts = ['draft' => 0, 'scheduled' => 0, 'published' => 0, 'archived' => 0, 'expired' => 0];
        $total = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $status = $row['status'];
            $cnt = (int) $row['cnt'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = $cnt;
            }
            $total += $cnt;
        }

        return array_merge(['total' => $total], $counts);
    }

    private function formatRow(array $row): array
    {
        $publishedByName = null;
        if (!empty($row['first_name']) || !empty($row['last_name'])) {
            $publishedByName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        }

        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'content' => $row['content'],
            'announcement_type' => $row['announcement_type'],
            'priority' => $row['priority'],
            'target_audience' => $row['target_audience'],
            'audience_json' => $row['audience_json'] !== null ? json_decode($row['audience_json'], true) : null,
            'published_by' => $row['published_by'] !== null ? (int) $row['published_by'] : null,
            'published_by_name' => $publishedByName,
            'status' => $row['status'],
            'scheduled_at' => $row['scheduled_at'],
            'published_at' => $row['published_at'],
            'expires_at' => $row['expires_at'],
            'view_count' => (int) $row['view_count'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}
