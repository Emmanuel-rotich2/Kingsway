<?php

namespace App\API\Modules\catering;

use PDO;
use RuntimeException;

/**
 * The school kitchen's menu: the dishes it cooks (menu_items), what is
 * planned for each meal of each day (meal_plans, one row per dish), and
 * what was actually prepared and served.
 *
 * Reads are open to any signed-in staff member (the menu is not private).
 * Planning and recording are for the Cateress (16), the Director (3) and
 * the School Administrator (4).
 *
 * All reads are single-table keyed reads on the primary, so a change shows
 * at once (the meal_plans read model refreshes on a timer).
 */
final class KitchenService
{
    public const WRITE_ROLES = [3, 4, 16];
    public const MEALS = ['breakfast', 'snack', 'lunch', 'dinner'];
    private const STATUSES = ['planned', 'prepared', 'served', 'cancelled'];

    public function __construct(private PDO $db) {}

    public static function canWrite(array $roleIds): bool
    {
        return (bool) array_intersect(array_map('intval', $roleIds), self::WRITE_ROLES);
    }

    public function dishes(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, description, meal_type, status FROM menu_items'
            . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY name';
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveDish(array $d): array
    {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException('Give the dish a name.');
        }
        $meal = (string)($d['meal_type'] ?? 'lunch');
        if (!in_array($meal, self::MEALS, true)) {
            throw new RuntimeException('Choose the meal the dish is usually served at.');
        }
        $status = ($d['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $description = trim((string)($d['description'] ?? '')) ?: null;
        $id = (int)($d['id'] ?? 0);

        $dup = $this->db->prepare('SELECT id FROM menu_items WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
        $dup->execute([$name, $id]);
        if ($dup->fetchColumn()) {
            throw new RuntimeException('A dish with that name already exists.');
        }
        if ($id > 0) {
            $stmt = $this->db->prepare('UPDATE menu_items SET name = ?, description = ?, meal_type = ?, status = ? WHERE id = ?');
            $stmt->execute([$name, $description, $meal, $status, $id]);
            if (!$stmt->rowCount() && !$this->dish($id)) {
                throw new RuntimeException('That dish no longer exists.', 404);
            }
        } else {
            $this->db->prepare('INSERT INTO menu_items (name, description, meal_type, status) VALUES (?, ?, ?, ?)')
                ->execute([$name, $description, $meal, $status]);
            $id = (int)$this->db->lastInsertId();
        }
        return $this->dish($id);
    }

    /** The plan for a date range (max 31 days), each row with its dish name. */
    public function plans(string $from, string $to): array
    {
        $from = $this->date($from);
        $to = $this->date($to);
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        if ((new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days > 31) {
            throw new RuntimeException('Choose at most a month at a time.');
        }
        $stmt = $this->db->prepare(
            'SELECT id, plan_date, meal_type, menu_item_id, planned_servings, prepared_quantity, actual_servings,
                    status, prepared_at, notes
               FROM meal_plans WHERE plan_date BETWEEN ? AND ?
              ORDER BY plan_date, FIELD(meal_type, \'breakfast\', \'snack\', \'lunch\', \'dinner\'), id'
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($this->dishes(), null, 'id');
        foreach ($rows as &$r) {
            $dish = $names[$r['menu_item_id']] ?? null;
            $r['menu_item'] = $dish['name'] ?? null;
            $r['description'] = $dish['description'] ?? null;
            foreach (['planned_servings', 'prepared_quantity', 'actual_servings'] as $k) {
                $r[$k] = (int)$r[$k];
            }
        }
        unset($r);
        return ['from' => $from, 'to' => $to, 'plans' => $rows];
    }

    /** Add a dish to a meal, or change its planned servings / notes. */
    public function savePlan(array $d, int $userId): array
    {
        $date = $this->date($d['plan_date'] ?? null);
        $meal = (string)($d['meal_type'] ?? '');
        if ($meal === 'supper') {
            $meal = 'dinner';
        }
        if (!in_array($meal, self::MEALS, true)) {
            throw new RuntimeException('Choose the meal.');
        }
        $dishId = (int)($d['menu_item_id'] ?? 0);
        if (!$dishId || !$this->dish($dishId)) {
            throw new RuntimeException('Choose a dish from the list, or add it to the dishes first.');
        }
        $servings = (int)($d['planned_servings'] ?? 0);
        if ($servings < 0 || $servings > 5000) {
            throw new RuntimeException('Enter how many servings to prepare.');
        }
        $notes = trim((string)($d['notes'] ?? '')) ?: null;
        $id = (int)($d['id'] ?? 0);

        if ($id > 0) {
            $plan = $this->plan($id);
            if ($plan['status'] === 'served') {
                throw new RuntimeException('This meal has been served; its plan cannot be changed.', 409);
            }
            $this->db->prepare('UPDATE meal_plans SET plan_date = ?, meal_type = ?, menu_item_id = ?, planned_servings = ?, notes = ? WHERE id = ?')
                ->execute([$date, $meal, $dishId, $servings, $notes, $id]);
            return $this->plan($id);
        }
        $exists = $this->db->prepare('SELECT id FROM meal_plans WHERE plan_date = ? AND meal_type = ? AND menu_item_id = ? LIMIT 1');
        $exists->execute([$date, $meal, $dishId]);
        if ($exists->fetchColumn()) {
            throw new RuntimeException('That dish is already on this meal.', 409);
        }
        $this->db->prepare(
            "INSERT INTO meal_plans (plan_date, meal_type, menu_item_id, planned_servings, prepared_quantity, actual_servings, status, created_by, notes)
             VALUES (?, ?, ?, ?, 0, 0, 'planned', ?, ?)"
        )->execute([$date, $meal, $dishId, $servings, $userId, $notes]);
        return $this->plan((int)$this->db->lastInsertId());
    }

    public function removePlan(int $id): void
    {
        $plan = $this->plan($id);
        if (in_array($plan['status'], ['prepared', 'served'], true)) {
            throw new RuntimeException('This dish has already been cooked; mark it cancelled instead.', 409);
        }
        $this->db->prepare('DELETE FROM meal_plans WHERE id = ?')->execute([$id]);
    }

    /** Record what the kitchen did: prepared / served (with counts) or cancelled. */
    public function record(int $id, array $d, int $userId): array
    {
        $plan = $this->plan($id);
        $status = (string)($d['status'] ?? '');
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Choose what happened to this dish.');
        }
        $prepared = array_key_exists('prepared_quantity', $d) && $d['prepared_quantity'] !== '' ? max(0, (int)$d['prepared_quantity']) : (int)$plan['prepared_quantity'];
        $served = array_key_exists('actual_servings', $d) && $d['actual_servings'] !== '' ? max(0, (int)$d['actual_servings']) : (int)$plan['actual_servings'];
        if ($status === 'prepared' && $prepared <= 0) {
            $prepared = (int)$plan['planned_servings'];
        }
        if ($status === 'served' && $served <= 0) {
            throw new RuntimeException('Enter how many servings were served.');
        }
        if ($status === 'served' && $prepared <= 0) {
            $prepared = $served;
        }
        $notes = array_key_exists('notes', $d) ? (trim((string)$d['notes']) ?: null) : $plan['notes'];
        $preparedAt = in_array($status, ['prepared', 'served'], true) ? ($plan['prepared_at'] ?: date('Y-m-d H:i:s')) : $plan['prepared_at'];
        $preparedBy = in_array($status, ['prepared', 'served'], true) ? ($plan['prepared_by'] ?: $userId) : $plan['prepared_by'];
        $this->db->prepare(
            'UPDATE meal_plans SET status = ?, prepared_quantity = ?, actual_servings = ?, prepared_at = ?, prepared_by = ?, notes = ? WHERE id = ?'
        )->execute([$status, $prepared, $served, $preparedAt, $preparedBy, $notes, $id]);
        return $this->plan($id);
    }

    /** Copy one week's plan onto another week (only into empty meals). */
    public function copyWeek(string $fromMonday, string $toMonday, int $userId): array
    {
        $src = new \DateTimeImmutable($this->date($fromMonday));
        $dst = new \DateTimeImmutable($this->date($toMonday));
        $shift = (int)$src->diff($dst)->format('%r%a');
        if ($shift === 0) {
            throw new RuntimeException('Choose a different week to copy to.');
        }
        $source = $this->plans($src->format('Y-m-d'), $src->modify('+6 days')->format('Y-m-d'))['plans'];
        $target = $this->plans($dst->format('Y-m-d'), $dst->modify('+6 days')->format('Y-m-d'))['plans'];
        $taken = [];
        foreach ($target as $t) {
            $taken[$t['plan_date'] . '|' . $t['meal_type']] = true;
        }
        $insert = $this->db->prepare(
            "INSERT IGNORE INTO meal_plans (plan_date, meal_type, menu_item_id, planned_servings, prepared_quantity, actual_servings, status, created_by, notes)
             VALUES (?, ?, ?, ?, 0, 0, 'planned', ?, ?)"
        );
        $copied = 0;
        foreach ($source as $p) {
            if ($p['status'] === 'cancelled' || !$p['menu_item_id']) {
                continue;
            }
            $date = (new \DateTimeImmutable($p['plan_date']))->modify("{$shift} days")->format('Y-m-d');
            if (isset($taken[$date . '|' . $p['meal_type']])) {
                continue;
            }
            $insert->execute([$date, $p['meal_type'], $p['menu_item_id'], $p['planned_servings'], $userId, $p['notes']]);
            $copied += $insert->rowCount();
        }
        return ['copied' => $copied];
    }

    private function dish(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, description, meal_type, status FROM menu_items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function plan(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, plan_date, meal_type, menu_item_id, planned_servings, prepared_quantity, actual_servings,
                    status, prepared_at, prepared_by, notes FROM meal_plans WHERE id = ?'
        );
        $stmt->execute([$id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            throw new RuntimeException('That meal plan no longer exists.', 404);
        }
        $dish = $p['menu_item_id'] ? $this->dish((int)$p['menu_item_id']) : null;
        $p['menu_item'] = $dish['name'] ?? null;
        return $p;
    }

    private function date($value): string
    {
        $value = (string)($value ?: date('Y-m-d'));
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            throw new RuntimeException('Enter a valid date.');
        }
        return $value;
    }
}
