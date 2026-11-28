<?php

namespace App\API\Modules\inventory;

use PDO;
use RuntimeException;

/**
 * Read side of the uniform store, built on the tables the scanner till,
 * stock intake and the catalogue keep:
 *
 *   uniform_sizes        the balance per size (available, held for orders, sold)
 *   catalog_stock_units  one labelled unit per garment (in stock, reserved, sold)
 *   catalog_orders       every sale: counter (physical), staff and parent orders
 *
 * The older uniform_sales register is no longer written by any screen, so
 * the store's pages read orders instead. Stock is added only through Stock
 * Intake (which prints the labels) and sold only through the till or an
 * order, so the labelled count and the balance stay equal.
 *
 * Single-table reads on the primary, joined in PHP.
 */
final class UniformStoreService
{
    /** Director, School Administrator, Accountant, Uniform Store Manager. */
    public const VIEW_ROLES = [3, 4, 10, 14];
    private const SOURCE = ['physical' => 'Counter', 'online' => 'Parent order', 'internal' => 'Staff order'];

    public function __construct(private PDO $db) {}

    public static function canView(array $roleIds): bool
    {
        return (bool) array_intersect(array_map('intval', $roleIds), self::VIEW_ROLES);
    }

    /** Stock on hand per product, colour and size, with its labelled units. */
    public function stock(): array
    {
        $sizes = $this->db->query('SELECT id, item_id, size, size_label, size_type, quantity_available, quantity_reserved,
                                          quantity_sold, unit_price, reorder_level, last_restocked
                                     FROM uniform_sizes ORDER BY item_id, id')->fetchAll(PDO::FETCH_ASSOC);
        $products = $this->db->query("SELECT id, item_id, title, category_slug, product_type, status FROM uniform_catalog_products")->fetchAll(PDO::FETCH_ASSOC);
        $variants = $this->db->query("SELECT id, product_id, item_id, name, color_name, swatch_hex, status FROM uniform_catalog_variants")->fetchAll(PDO::FETCH_ASSOC);
        $itemIds = array_values(array_unique(array_map('intval', array_column($sizes, 'item_id'))));
        $items = $itemIds ? array_column($this->db->query('SELECT id, name, code FROM inventory_items WHERE id IN (' . implode(',', $itemIds) . ')')->fetchAll(PDO::FETCH_ASSOC), null, 'id') : [];

        // Who owns each inventory item: a colour of a product, or the product itself.
        $byItem = [];
        $productById = array_column($products, null, 'id');
        foreach ($products as $p) {
            if ($p['item_id']) $byItem[(int) $p['item_id']] = ['product' => $p, 'variant' => null];
        }
        foreach ($variants as $v) {
            if ($v['item_id'] && isset($productById[$v['product_id']])) {
                $byItem[(int) $v['item_id']] = ['product' => $productById[$v['product_id']], 'variant' => $v];
            }
        }

        $labelled = [];
        foreach ($this->db->query("SELECT size_id, status FROM catalog_stock_units WHERE status IN ('in_stock', 'reserved')")->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $labelled[(int) $u['size_id']][$u['status']] = ($labelled[(int) $u['size_id']][$u['status']] ?? 0) + 1;
        }

        $rows = array_map(function (array $s) use ($byItem, $items, $labelled) {
            $item = (int) $s['item_id'];
            $own = $byItem[$item] ?? null;
            $available = (int) $s['quantity_available'];
            $held = (int) $s['quantity_reserved'];
            $free = max(0, $available - $held);
            $reorder = (int) $s['reorder_level'];
            $units = $labelled[(int) $s['id']] ?? [];
            $unitsIn = (int) ($units['in_stock'] ?? 0) + (int) ($units['reserved'] ?? 0);
            return [
                'size_id' => (int) $s['id'],
                'item_id' => $item,
                'product' => $own['product']['title'] ?? ($items[$item]['name'] ?? 'Uniform item'),
                'product_id' => isset($own['product']) ? (int) $own['product']['id'] : null,
                'category' => $own['product']['category_slug'] ?? '',
                'colour' => $own['variant']['color_name'] ?? ($own['variant']['name'] ?? ''),
                'swatch' => $own['variant']['swatch_hex'] ?? null,
                'code' => $items[$item]['code'] ?? '',
                'size' => $s['size_label'] ?: $s['size'],
                'on_shelf' => $available,
                'held' => $held,
                'free' => $free,
                'sold' => (int) $s['quantity_sold'],
                'price' => (float) $s['unit_price'],
                'value' => round($available * (float) $s['unit_price'], 2),
                'reorder_level' => $reorder,
                'level' => $free <= 0 ? 'out' : ($free <= $reorder ? 'low' : 'ok'),
                'labelled' => $unitsIn,
                'unlabelled' => max(0, $available - $unitsIn),
                'last_restocked' => $s['last_restocked'],
                'on_sale' => isset($own['product']) && $own['product']['status'] === 'active',
            ];
        }, $sizes);

        return ['sizes' => $rows];
    }

    /**
     * The sales register between two dates: each order with its lines, buyer,
     * learner, payment and who served. Cancelled orders are listed but not counted.
     */
    public function sales(string $from, string $to): array
    {
        [$from, $to] = $this->range($from, $to);
        $stmt = $this->db->prepare('SELECT id, order_reference, buyer_type, buyer_id, student_id, source, status, payment_status,
                                           fulfilment_method, subtotal, discount_total, total_amount, placed_by, paid_at, fulfilled_at, created_at
                                      FROM catalog_orders WHERE created_at >= ? AND created_at < ? ORDER BY created_at DESC LIMIT 3000');
        $stmt->execute([$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00']);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map('intval', array_column($orders, 'id'));

        $lines = [];
        if ($ids) {
            $s = $this->db->query('SELECT order_id, product_title, variant_name, size_label, quantity, unit_price, discount_amount, line_total
                                     FROM catalog_order_items WHERE order_id IN (' . implode(',', $ids) . ') ORDER BY id');
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $l) $lines[(int) $l['order_id']][] = $l;
        }

        $userIds = [];
        $parentIds = [];
        foreach ($orders as $o) {
            if ($o['placed_by']) $userIds[] = (int) $o['placed_by'];
            if ($o['buyer_type'] === 'staff' && $o['buyer_id']) $userIds[] = (int) $o['buyer_id'];
            if ($o['buyer_type'] === 'parent' && $o['buyer_id']) $parentIds[] = (int) $o['buyer_id'];
        }
        $users = $this->names('users', $userIds);
        $parents = $this->names('parents', $parentIds);
        $learners = $this->learners(array_column($orders, 'student_id'));

        $out = array_map(function (array $o) use ($lines, $users, $parents, $learners) {
            $buyer = match ($o['buyer_type']) {
                'staff' => $users[(int) $o['buyer_id']] ?? 'Staff member',
                'parent' => $parents[(int) $o['buyer_id']] ?? 'Parent',
                default => 'Walk-in customer',
            };
            $l = $learners[(int) $o['student_id']] ?? null;
            return [
                'id' => (int) $o['id'],
                'reference' => $o['order_reference'],
                'at' => $o['created_at'],
                'source' => self::SOURCE[$o['source']] ?? ucfirst((string) $o['source']),
                'buyer' => $buyer,
                'learner' => $l['name'] ?? '',
                'admission_no' => $l['admission_no'] ?? '',
                'status' => $o['status'],
                'payment' => $o['payment_status'],
                'subtotal' => (float) $o['subtotal'],
                'discount' => (float) $o['discount_total'],
                'total' => (float) $o['total_amount'],
                'served_by' => $o['placed_by'] ? ($users[(int) $o['placed_by']] ?? '') : '',
                'lines' => array_map(fn ($x) => [
                    'product' => $x['product_title'], 'colour' => $x['variant_name'], 'size' => $x['size_label'],
                    'quantity' => (int) $x['quantity'], 'price' => (float) $x['unit_price'], 'total' => (float) $x['line_total'],
                ], $lines[(int) $o['id']] ?? []),
            ];
        }, $orders);

        return ['from' => $from, 'to' => $to, 'orders' => $out];
    }

    /** id => "First Last" for users, parents or students, through persons (two keyed reads). */
    private function people(string $table, array $ids, array $extra = []): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $cols = implode('', array_map(fn ($c) => ", {$c}", $extra));
        $rows = $this->db->query("SELECT id, person_id{$cols} FROM {$table} WHERE id IN (" . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_ASSOC);
        $pids = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'person_id')))));
        $persons = [];
        if ($pids) {
            foreach ($this->db->query('SELECT id, first_name, last_name FROM persons WHERE id IN (' . implode(',', $pids) . ')')->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $persons[(int) $p['id']] = trim($p['first_name'] . ' ' . $p['last_name']);
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = ['name' => $persons[(int) $r['person_id']] ?? ''] + array_intersect_key($r, array_flip($extra));
        }
        return $out;
    }

    private function names(string $table, array $ids): array
    {
        return array_map(fn ($r) => $r['name'], $this->people($table, $ids));
    }

    private function learners(array $ids): array
    {
        return $this->people('students', $ids, ['admission_no']);
    }

    private function range(string $from, string $to): array
    {
        $ok = fn ($v) => ($d = \DateTime::createFromFormat('!Y-m-d', $v)) && $d->format('Y-m-d') === $v;
        $to = $to !== '' && $ok($to) ? $to : date('Y-m-d');
        $from = $from !== '' && $ok($from) ? $from : date('Y-m-d', strtotime($to . ' -30 days'));
        if ($from > $to) [$from, $to] = [$to, $from];
        if ((strtotime($to) - strtotime($from)) / 86400 > 400) {
            throw new RuntimeException('Choose dates no more than about a year apart.');
        }
        return [$from, $to];
    }
}
