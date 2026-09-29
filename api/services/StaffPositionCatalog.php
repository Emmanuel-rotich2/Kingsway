<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/** School-managed employment positions. This catalogue is separate from roles and learning areas. */
final class StaffPositionCatalog
{
    public static function list(PDO $db, bool $activeOnly = true): array
    {
        return $db->query(
            'SELECT p.id, p.name, p.staff_type_id, p.staff_category_id,
                    p.is_active, st.name AS staff_type, sc.category_name AS staff_category,
                    GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") AS system_roles,
                    GROUP_CONCAT(DISTINCT r.id ORDER BY r.id) AS role_ids,
                    (SELECT GROUP_CONCAT(d.role_id ORDER BY d.role_id)
                       FROM staff_role_default_positions d WHERE d.position_id=p.id) AS default_role_ids
               FROM staff_positions p
               LEFT JOIN staff_types st ON st.id=p.staff_type_id
               LEFT JOIN staff_categories sc ON sc.id=p.staff_category_id
               LEFT JOIN staff_position_roles spr ON spr.position_id=p.id
               LEFT JOIN roles r ON r.id=spr.role_id' .
            ($activeOnly ? ' WHERE p.is_active=1' : '') .
            ' GROUP BY p.id,p.name,p.staff_type_id,p.staff_category_id,p.is_active,st.name,sc.category_name ORDER BY p.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function assertActive(PDO $db, string $name, ?int $typeId = null, ?int $categoryId = null, ?int $roleId = null): string
    {
        $name = trim($name);
        if ($name === '') throw new RuntimeException('Choose an employment position from the school position list.');
        $stmt = $db->prepare(
            'SELECT 1 FROM staff_positions
              WHERE name=? AND is_active=1
                AND (staff_type_id IS NULL OR staff_type_id = ?)
                AND (staff_category_id IS NULL OR staff_category_id = ?)
                AND (NOT EXISTS (SELECT 1 FROM staff_position_roles spr WHERE spr.position_id=staff_positions.id)
                     OR EXISTS (SELECT 1 FROM staff_position_roles spr WHERE spr.position_id=staff_positions.id AND spr.role_id=?))
              LIMIT 1'
        );
        $stmt->execute([$name, $typeId, $categoryId, $roleId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('The selected employment position is not active for this staff classification. Choose a position managed by the School Administrator.');
        return $name;
    }

    public static function normalize(string $position): string
    {
        return trim($position);
    }

    /** Resolve an already validated catalogue label to its normalized key. */
    public static function resolveId(PDO $db, string $name): int
    {
        $name = self::normalize($name);
        if ($name === '') {
            throw new RuntimeException('Choose an employment position from the school position list.');
        }
        $stmt = $db->prepare('SELECT id FROM staff_positions WHERE name=? AND is_active=1 LIMIT 1');
        $stmt->execute([$name]);
        $id = (int)$stmt->fetchColumn();
        if ($id < 1) {
            throw new RuntimeException('The selected employment position is no longer active. Choose an active school position.');
        }
        return $id;
    }

    public static function defaultForRole(PDO $db, int $roleId, ?int $typeId = null, ?int $categoryId = null): ?array
    {
        if ($roleId < 1) return null;
        $stmt = $db->prepare(
            'SELECT p.id, p.name FROM staff_role_default_positions d
              JOIN staff_positions p ON p.id=d.position_id AND p.is_active=1
             WHERE d.role_id=?
               AND (p.staff_type_id IS NULL OR p.staff_type_id=?)
               AND (p.staff_category_id IS NULL OR p.staff_category_id=?)
             LIMIT 1'
        );
        $stmt->execute([$roleId, $typeId, $categoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
