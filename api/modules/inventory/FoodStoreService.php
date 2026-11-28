<?php

namespace App\API\Modules\inventory;

use App\API\Services\ReadReplicaService;
use PDO;
use RuntimeException;
use Throwable;

/**
 * The food store: the food items the school keeps (inventory_items in the
 * categories whose `store` is 'food'), and its bin card, the stock ledger in
 * inventory_transactions. Every change of quantity goes through the ledger:
 *
 *   receive  a delivery from a supplier (in)
 *   issue    food handed to the kitchen for a meal (out)
 *   spoil    food thrown away, spoilt or expired (out)
 *   count    a stock count that corrects the quantity (in or out)
 *
 * Issues and spoilage are also written to food_consumption_records so the
 * consumption reports and the catering review keep working.
 *
 * Reads are single-table keyed reads on the primary (a change shows at once),
 * joined in PHP. Quantities never go below zero.
 */
final class FoodStoreService
{
    /** Director, School Administrator, Food Store Manager. */
    public const MANAGE_ROLES = [3, 4, 71];
    /** Also the Headteacher, Accountant, Cateress and kitchen staff (read only). */
    public const VIEW_ROLES = [3, 4, 5, 10, 16, 32, 71];
    public const MEALS = ['breakfast', 'snack', 'lunch', 'dinner'];
    private const MOVEMENTS = ['receive', 'issue', 'spoil', 'count'];

    public function __construct(private PDO $db) {}

    public static function canManage(array $roleIds): bool
    {
        return (bool) array_intersect(array_map('intval', $roleIds), self::MANAGE_ROLES);
    }

    public static function canView(array $roleIds): bool
    {
        return (bool) array_intersect(array_map('intval', $roleIds), self::VIEW_ROLES);
    }

    // ---------------------------------------------------------------- reads

    public function categories(): array
    {
        return $this->db->query("SELECT id, name, code FROM inventory_categories
                                  WHERE store = 'food' AND status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function categoryIds(): array
    {
        return array_map('intval', array_column($this->categories(), 'id'));
    }

    /**
     * The store: every food item with its level, its usual daily use (the last
     * 14 days of issues) and how many days that stock lasts.
     */
    public function store(bool $includeInactive = false): array
    {
        $cats = $this->categories();
        $catName = array_column($cats, 'name', 'id');
        $items = [];
        if ($catName) {
            $in = implode(',', array_map('intval', array_keys($catName)));
            $sql = "SELECT id, category_id, name, code, unit, current_quantity, minimum_quantity, reorder_level,
                           unit_cost, location_id, supplier_id, expiry_date, status, last_purchase_date,
                           last_purchase_price, last_audit_date
                      FROM inventory_items WHERE category_id IN ($in)"
                . ($includeInactive ? '' : " AND status <> 'inactive'") . ' ORDER BY name';
            $items = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }

        $use = $this->dailyUse(array_map('intval', array_column($items, 'id')), 14);
        $suppliers = array_column($this->suppliers(), 'name', 'id');
        $locations = array_column($this->locations(), 'location_name', 'id');
        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+7 days'));

        $items = array_map(function (array $i) use ($catName, $use, $suppliers, $locations, $today, $soon) {
            $id = (int) $i['id'];
            $qty = (int) $i['current_quantity'];
            $reorder = (int) $i['reorder_level'];
            $daily = $use[$id] ?? 0.0;
            $expiry = $i['expiry_date'] ?: null;
            return [
                'id' => $id,
                'name' => $i['name'],
                'code' => $i['code'],
                'category_id' => (int) $i['category_id'],
                'category' => $catName[(int) $i['category_id']] ?? '',
                'unit' => $i['unit'],
                'on_hand' => $qty,
                'reorder_level' => $reorder,
                'minimum_quantity' => (int) $i['minimum_quantity'],
                'unit_cost' => (float) $i['unit_cost'],
                'value' => round($qty * (float) $i['unit_cost'], 2),
                'location_id' => $i['location_id'] ? (int) $i['location_id'] : null,
                'location' => $i['location_id'] ? ($locations[(int) $i['location_id']] ?? '') : '',
                'supplier_id' => $i['supplier_id'] ? (int) $i['supplier_id'] : null,
                'supplier' => $i['supplier_id'] ? ($suppliers[(int) $i['supplier_id']] ?? '') : '',
                'expiry_date' => $expiry,
                'expiry' => !$expiry || $qty <= 0 ? 'none' : ($expiry < $today ? 'expired' : ($expiry <= $soon ? 'soon' : 'ok')),
                'level' => $qty <= 0 ? 'out' : ($qty <= $reorder ? 'low' : 'ok'),
                'daily_use' => round($daily, 2),
                'days_left' => $daily > 0 ? (int) floor($qty / $daily) : null,
                'last_delivery' => $i['last_purchase_date'],
                'last_count' => $i['last_audit_date'],
                'active' => $i['status'] !== 'inactive',
            ];
        }, $items);

        return [
            'items' => $items,
            'categories' => $cats,
            'locations' => $this->locations(),
            'suppliers' => $this->suppliers(),
            'meals' => self::MEALS,
        ];
    }

    /** Average daily issues per item over the last $days days. */
    private function dailyUse(array $itemIds, int $days): array
    {
        if (!$itemIds) return [];
        $in = implode(',', array_map('intval', $itemIds));
        $stmt = $this->db->prepare("SELECT item_id, quantity FROM inventory_transactions
                                     WHERE item_id IN ($in) AND movement = 'issue' AND transaction_date >= ?");
        $stmt->execute([date('Y-m-d', strtotime('-' . $days . ' days'))]);
        $sum = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sum[(int) $r['item_id']] = ($sum[(int) $r['item_id']] ?? 0) + (int) $r['quantity'];
        }
        return array_map(fn ($q) => $q / $days, $sum);
    }

    public function locations(): array
    {
        return $this->db->query("SELECT id, location_name FROM inventory_locations WHERE status = 'active' ORDER BY location_name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function suppliers(): array
    {
        return $this->db->query("SELECT id, name, phone FROM suppliers WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The ledger between two dates: deliveries, issues, spoilage and counts,
     * newest first. Optionally one item (its bin card) or one kind of movement.
     */
    public function ledger(string $from, string $to, ?int $itemId = null, ?string $movement = null): array
    {
        [$from, $to] = $this->range($from, $to, 366);
        $foodIds = $this->foodItemIds();
        if (!$foodIds) return ['from' => $from, 'to' => $to, 'rows' => []];
        if ($itemId !== null && !in_array($itemId, $foodIds, true)) {
            throw new RuntimeException('That item is not in the food store.', 404);
        }
        $ids = $itemId !== null ? [$itemId] : $foodIds;
        $in = implode(',', $ids);
        $sql = "SELECT id, item_id, transaction_type, movement, quantity, balance_after, unit_cost, transaction_date,
                       created_at, notes, recorded_by, supplier_id, document_no, issued_to, meal_type, expiry_date
                  FROM inventory_transactions
                 WHERE item_id IN ($in) AND transaction_date BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($movement !== null && $movement !== '') {
            if (!in_array($movement, self::MOVEMENTS, true)) throw new RuntimeException('Unknown kind of movement.');
            $sql .= ' AND movement = ?';
            $params[] = $movement;
        }
        $sql .= ' ORDER BY transaction_date DESC, id DESC LIMIT 2000';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        $stmt = $this->db->query("SELECT id, name, unit FROM inventory_items WHERE id IN ($in)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $i) $items[(int) $i['id']] = $i;
        $suppliers = array_column($this->suppliers(), 'name', 'id');
        $people = $this->names(array_column($rows, 'recorded_by'));

        $rows = array_map(function (array $r) use ($items, $suppliers, $people) {
            $out = $r['transaction_type'] === 'out';
            $qty = (int) $r['quantity'];
            return [
                'id' => (int) $r['id'],
                'item_id' => (int) $r['item_id'],
                'item' => $items[(int) $r['item_id']]['name'] ?? '',
                'unit' => $items[(int) $r['item_id']]['unit'] ?? '',
                'date' => $r['transaction_date'],
                'at' => $r['created_at'],
                'movement' => $r['movement'] ?: ($out ? 'issue' : 'receive'),
                'in' => $out ? 0 : $qty,
                'out' => $out ? $qty : 0,
                'balance' => $r['balance_after'] !== null ? (int) $r['balance_after'] : null,
                'unit_cost' => $r['unit_cost'] !== null ? (float) $r['unit_cost'] : null,
                'value' => $r['unit_cost'] !== null ? round($qty * (float) $r['unit_cost'], 2) : null,
                'supplier' => $r['supplier_id'] ? ($suppliers[(int) $r['supplier_id']] ?? '') : '',
                'document_no' => $r['document_no'],
                'issued_to' => $r['issued_to'],
                'meal_type' => $r['meal_type'],
                'expiry_date' => $r['expiry_date'],
                'notes' => $r['notes'],
                'recorded_by' => $r['recorded_by'] ? ($people[(int) $r['recorded_by']] ?? 'Staff') : '',
            ];
        }, $rows);

        return ['from' => $from, 'to' => $to, 'rows' => $rows];
    }

    /** Staff names for user ids (read model, keyed read). */
    private function names(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) return [];
        try {
            $stmt = $this->db->query('SELECT user_id, full_name FROM ' . ReadReplicaService::qualifiedRef('staff_directory')
                . ' WHERE user_id IN (' . implode(',', $ids) . ')');
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'full_name', 'user_id');
        } catch (Throwable) {
            return [];
        }
    }

    private function foodItemIds(): array
    {
        $cats = $this->categoryIds();
        if (!$cats) return [];
        return array_map('intval', $this->db->query('SELECT id FROM inventory_items WHERE category_id IN (' . implode(',', $cats) . ')')->fetchAll(PDO::FETCH_COLUMN));
    }

    // --------------------------------------------------------------- writes

    /** Add or edit a food item. The quantity of an existing item changes only through the ledger. */
    public function saveItem(array $d, int $userId): array
    {
        $id = (int) ($d['id'] ?? 0);
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255) throw new RuntimeException('Give the item a name.');
        $unit = trim((string) ($d['unit'] ?? ''));
        if ($unit === '' || mb_strlen($unit) > 20) throw new RuntimeException('Say how the item is counted, for example bags, kg or crates.');
        $cat = (int) ($d['category_id'] ?? 0);
        if (!in_array($cat, $this->categoryIds(), true)) throw new RuntimeException('Choose a food category.');
        $reorder = max(0, (int) ($d['reorder_level'] ?? 0));
        $min = max(0, min($reorder, (int) ($d['minimum_quantity'] ?? 0)));
        $cost = max(0, round((float) ($d['unit_cost'] ?? 0), 2));
        $loc = (int) ($d['location_id'] ?? 0) ?: null;
        $sup = (int) ($d['supplier_id'] ?? 0) ?: null;
        if ($loc && !in_array($loc, array_map('intval', array_column($this->locations(), 'id')), true)) $loc = null;
        if ($sup && !in_array($sup, array_map('intval', array_column($this->suppliers(), 'id')), true)) $sup = null;
        $status = !empty($d['active']) || !array_key_exists('active', $d) ? 'active' : 'inactive';

        $dup = $this->db->prepare('SELECT id FROM inventory_items WHERE LOWER(name) = LOWER(?) AND id <> ? AND category_id IN (' . implode(',', $this->categoryIds()) . ') LIMIT 1');
        $dup->execute([$name, $id]);
        if ($dup->fetchColumn()) throw new RuntimeException('The store already has an item with that name.', 409);

        if ($id > 0) {
            if (!in_array($id, $this->foodItemIds(), true)) throw new RuntimeException('That item is not in the food store.', 404);
            $this->db->prepare('UPDATE inventory_items SET name = ?, category_id = ?, unit = ?, reorder_level = ?, minimum_quantity = ?,
                                       unit_cost = ?, location_id = ?, supplier_id = ?, status = ? WHERE id = ?')
                ->execute([$name, $cat, $unit, $reorder, $min, $cost, $loc, $sup, $status, $id]);
            return ['id' => $id];
        }

        $opening = max(0, (int) ($d['opening_quantity'] ?? 0));
        return $this->inTransaction(function () use ($cat, $name, $unit, $min, $opening, $cost, $loc, $sup, $status, $reorder, $userId) {
            $code = $this->newCode();
            $this->db->prepare('INSERT INTO inventory_items (category_id, name, code, unit, minimum_quantity, current_quantity, unit_cost,
                                       location_id, supplier_id, status, reorder_level, last_audit_date)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$cat, $name, $code, $unit, $min, $opening, $cost, $loc, $sup, $status, $reorder, $opening > 0 ? date('Y-m-d') : null]);
            $newId = (int) $this->db->lastInsertId();
            if ($opening > 0) {
                $this->ledgerRow($newId, 'in', 'count', $opening, $opening, $cost, date('Y-m-d'), $userId, ['notes' => 'Opening stock']);
            }
            return ['id' => $newId, 'code' => $code];
        });
    }

    private function newCode(): string
    {
        $stmt = $this->db->prepare('SELECT 1 FROM inventory_items WHERE code = ?');
        do {
            $code = 'FS-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $stmt->execute([$code]);
        } while ($stmt->fetchColumn());
        return $code;
    }

    /**
     * A delivery: one or more items from one supplier, with the delivery note
     * number. Each line may carry its own cost and use-by date.
     */
    public function receive(array $d, int $userId): array
    {
        $date = $this->date($d['date'] ?? null);
        $supplier = (int) ($d['supplier_id'] ?? 0) ?: null;
        if ($supplier && !in_array($supplier, array_map('intval', array_column($this->suppliers(), 'id')), true)) {
            throw new RuntimeException('Choose a supplier from the list.');
        }
        $doc = mb_substr(trim((string) ($d['document_no'] ?? '')), 0, 60) ?: null;
        $notes = mb_substr(trim((string) ($d['notes'] ?? '')), 0, 500) ?: null;
        $lines = $this->lines($d['lines'] ?? []);

        return $this->inTransaction(function () use ($lines, $date, $supplier, $doc, $notes, $userId) {
            $done = [];
            foreach ($lines as $l) {
                $item = $this->lockItem($l['item_id']);
                $cost = $l['unit_cost'] !== null ? $l['unit_cost'] : (float) $item['unit_cost'];
                $expiry = $l['expiry_date'];
                $balance = (int) $item['current_quantity'] + $l['quantity'];
                // The item's use-by date is the soonest one still in the store.
                $keep = $item['expiry_date'] && (int) $item['current_quantity'] > 0 ? $item['expiry_date'] : null;
                $newExpiry = $expiry && $keep ? min($expiry, $keep) : ($expiry ?: $keep);
                $this->db->prepare('UPDATE inventory_items SET current_quantity = ?, unit_cost = ?, last_purchase_date = ?, last_purchase_price = ?,
                                           expiry_date = ?, supplier_id = COALESCE(?, supplier_id),
                                           status = IF(status = \'out_of_stock\', \'active\', status) WHERE id = ?')
                    ->execute([$balance, $cost, $date, $cost, $newExpiry, $supplier, $l['item_id']]);
                $this->ledgerRow($l['item_id'], 'in', 'receive', $l['quantity'], $balance, $cost, $date, $userId,
                    ['supplier_id' => $supplier, 'document_no' => $doc, 'expiry_date' => $expiry, 'notes' => $notes]);
                $done[] = ['item_id' => $l['item_id'], 'balance' => $balance];
            }
            return ['received' => count($done), 'items' => $done];
        });
    }

    /** Food handed to the kitchen. Refuses more than the store holds. */
    public function issue(array $d, int $userId): array
    {
        $date = $this->date($d['date'] ?? null);
        $to = mb_substr(trim((string) ($d['issued_to'] ?? '')), 0, 120);
        if ($to === '') throw new RuntimeException('Say who took the food, for example the Cateress or the cook on duty.');
        $meal = (string) ($d['meal_type'] ?? '');
        if ($meal !== '' && !in_array($meal, self::MEALS, true)) throw new RuntimeException('Choose the meal the food is for.');
        $doc = mb_substr(trim((string) ($d['document_no'] ?? '')), 0, 60) ?: null;
        $notes = mb_substr(trim((string) ($d['notes'] ?? '')), 0, 500) ?: null;
        $lines = $this->lines($d['lines'] ?? []);

        return $this->inTransaction(function () use ($lines, $date, $to, $meal, $doc, $notes, $userId) {
            foreach ($lines as $l) {
                $item = $this->lockItem($l['item_id']);
                $balance = (int) $item['current_quantity'] - $l['quantity'];
                if ($balance < 0) {
                    throw new RuntimeException(sprintf('Only %d %s of %s are in the store.', (int) $item['current_quantity'], $item['unit'], $item['name']), 409);
                }
                $this->setQuantity($l['item_id'], $balance);
                $this->ledgerRow($l['item_id'], 'out', 'issue', $l['quantity'], $balance, (float) $item['unit_cost'], $date, $userId,
                    ['issued_to' => $to, 'meal_type' => $meal ?: null, 'document_no' => $doc, 'notes' => $notes]);
                $this->consumption($l['item_id'], $date, $l['quantity'], 0, $item, $userId, trim(($meal ? ucfirst($meal) . '. ' : '') . 'Issued to ' . $to . ($notes ? '. ' . $notes : '')));
            }
            return ['issued' => count($lines)];
        });
    }

    /** Food thrown away: spoilt, expired, damaged by pests. */
    public function spoil(array $d, int $userId): array
    {
        $date = $this->date($d['date'] ?? null);
        $reason = mb_substr(trim((string) ($d['reason'] ?? '')), 0, 500);
        if ($reason === '') throw new RuntimeException('Say why the food was thrown away.');
        $lines = $this->lines($d['lines'] ?? []);
        return $this->inTransaction(function () use ($lines, $date, $reason, $userId) {
            foreach ($lines as $l) {
                $item = $this->lockItem($l['item_id']);
                $balance = (int) $item['current_quantity'] - $l['quantity'];
                if ($balance < 0) {
                    throw new RuntimeException(sprintf('Only %d %s of %s are in the store.', (int) $item['current_quantity'], $item['unit'], $item['name']), 409);
                }
                $this->setQuantity($l['item_id'], $balance);
                $this->ledgerRow($l['item_id'], 'out', 'spoil', $l['quantity'], $balance, (float) $item['unit_cost'], $date, $userId, ['notes' => $reason]);
                $this->consumption($l['item_id'], $date, 0, $l['quantity'], $item, $userId, 'Thrown away: ' . $reason);
            }
            return ['written_off' => count($lines)];
        });
    }

    /**
     * A stock count: what was physically found on the shelf. Each difference
     * is written to the ledger as a correction; every counted item gets today
     * as its last count date.
     */
    public function count(array $d, int $userId): array
    {
        $date = $this->date($d['date'] ?? null);
        $rows = is_array($d['counts'] ?? null) ? $d['counts'] : [];
        $food = $this->foodItemIds();
        $counts = [];
        foreach ($rows as $r) {
            $id = (int) ($r['item_id'] ?? 0);
            if (!in_array($id, $food, true) || !isset($r['counted']) || $r['counted'] === '') continue;
            $n = (int) $r['counted'];
            if ($n < 0 || $n > 1000000) throw new RuntimeException('A counted quantity cannot be below zero.');
            $counts[$id] = ['counted' => $n, 'note' => mb_substr(trim((string) ($r['note'] ?? '')), 0, 200)];
        }
        if (!$counts) throw new RuntimeException('Enter the quantity found for at least one item.');

        return $this->inTransaction(function () use ($counts, $date, $userId) {
            $changed = 0;
            foreach ($counts as $id => $c) {
                $item = $this->lockItem($id);
                $diff = $c['counted'] - (int) $item['current_quantity'];
                $this->db->prepare('UPDATE inventory_items SET current_quantity = ?, last_audit_date = ? WHERE id = ?')
                    ->execute([$c['counted'], $date, $id]);
                if ($diff !== 0) {
                    $this->ledgerRow($id, $diff > 0 ? 'in' : 'out', 'count', abs($diff), $c['counted'], (float) $item['unit_cost'], $date, $userId,
                        ['notes' => trim('Stock count: found ' . $c['counted'] . ', book showed ' . (int) $item['current_quantity'] . ($c['note'] ? '. ' . $c['note'] : ''))]);
                    $changed++;
                }
            }
            return ['counted' => count($counts), 'corrected' => $changed];
        });
    }

    // -------------------------------------------------------------- helpers

    private function lines($raw): array
    {
        $food = $this->foodItemIds();
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $l) {
            $id = (int) ($l['item_id'] ?? 0);
            $q = (int) ($l['quantity'] ?? 0);
            if (!$id && !$q) continue;
            if (!in_array($id, $food, true)) throw new RuntimeException('Choose items from the food store.');
            if ($q <= 0 || $q > 1000000) throw new RuntimeException('Each quantity must be a whole number above zero.');
            $cost = isset($l['unit_cost']) && $l['unit_cost'] !== '' ? max(0, round((float) $l['unit_cost'], 2)) : null;
            $exp = !empty($l['expiry_date']) ? $this->date($l['expiry_date'], true) : null;
            if (isset($out[$id])) {
                $out[$id]['quantity'] += $q;
            } else {
                $out[$id] = ['item_id' => $id, 'quantity' => $q, 'unit_cost' => $cost, 'expiry_date' => $exp];
            }
        }
        if (!$out) throw new RuntimeException('Add at least one item.');
        return array_values($out);
    }

    private function lockItem(int $id): array
    {
        $stmt = $this->db->prepare('SELECT id, name, unit, current_quantity, unit_cost, expiry_date FROM inventory_items WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('That item is not in the food store.', 404);
        return $row;
    }

    private function setQuantity(int $id, int $qty): void
    {
        $this->db->prepare('UPDATE inventory_items SET current_quantity = ? WHERE id = ?')->execute([$qty, $id]);
    }

    private function ledgerRow(int $itemId, string $dir, string $movement, int $qty, int $balance, float $cost, string $date, int $userId, array $extra = []): void
    {
        $ref = ['receive' => 'purchase', 'issue' => 'transfer', 'spoil' => 'adjustment', 'count' => 'adjustment'][$movement];
        $this->db->prepare('INSERT INTO inventory_transactions
                (item_id, transaction_type, quantity, balance_after, transaction_date, reference_type, movement, unit_cost, notes,
                 recorded_by, supplier_id, document_no, issued_to, meal_type, expiry_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$itemId, $dir, $qty, $balance, $date, $ref, $movement, $cost, $extra['notes'] ?? null,
                $userId ?: null, $extra['supplier_id'] ?? null, $extra['document_no'] ?? null, $extra['issued_to'] ?? null,
                $extra['meal_type'] ?? null, $extra['expiry_date'] ?? null]);
    }

    private function consumption(int $itemId, string $date, int $used, int $waste, array $item, int $userId, string $notes): void
    {
        $this->db->prepare('INSERT INTO food_consumption_records
                (consumption_date, inventory_item_id, quantity_planned, quantity_used, unit, waste_quantity, cost_per_unit, recorded_by, recorded_at, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)')
            ->execute([$date, $itemId, $used, $used, mb_substr((string) $item['unit'], 0, 20), $waste, (float) $item['unit_cost'], $userId, mb_substr($notes, 0, 1000)]);
    }

    private function inTransaction(callable $fn): array
    {
        if ($this->db->inTransaction()) {
            return $fn(); // the caller owns the transaction
        }
        $this->db->beginTransaction();
        try {
            $r = $fn();
            $this->db->commit();
            return $r;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function date($v, bool $future = false): string
    {
        $v = trim((string) ($v ?? ''));
        if ($v === '') return date('Y-m-d');
        $d = \DateTime::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) throw new RuntimeException('Enter a valid date.');
        if (!$future && $v > date('Y-m-d')) throw new RuntimeException('The date cannot be in the future.');
        return $v;
    }

    private function range(string $from, string $to, int $maxDays): array
    {
        $to = $to !== '' ? $this->date($to, true) : date('Y-m-d');
        $from = $from !== '' ? $this->date($from, true) : date('Y-m-d', strtotime($to . ' -30 days'));
        if ($from > $to) [$from, $to] = [$to, $from];
        if ((strtotime($to) - strtotime($from)) / 86400 > $maxDays) {
            $from = date('Y-m-d', strtotime($to . ' -' . $maxDays . ' days'));
        }
        return [$from, $to];
    }
}
