<?php

namespace App\API\Modules\transport;

use App\API\Services\ReadReplicaService;
use PDO;
use RuntimeException;

/**
 * The driver's vehicle logbook: the assigned vehicle with its service and
 * insurance dates, service/repair records, fuel bought, daily pre-trip
 * checks and problems reported.
 *
 * Scope: a driver only ever reaches the vehicle assigned to them
 * (transport_vehicles.driver_id = their staff id). The Director and School
 * Administrator may open any vehicle by id.
 *
 * Problems and failed pre-trip checks are written to
 * student_transport_incidents so the transport office sees them with the
 * route's other incidents.
 */
final class DriverLogbookService
{
    public const OVERSIGHT_ROLES = [3, 4];
    public const CHECK_ITEMS = [
        'tyres' => 'Tyres and wheel nuts',
        'brakes' => 'Brakes',
        'lights' => 'Lights and indicators',
        'mirrors' => 'Mirrors and windscreen',
        'wipers' => 'Wipers',
        'horn' => 'Horn',
        'seat_belts' => 'Seat belts',
        'doors' => 'Doors and emergency exit',
        'speed_governor' => 'Speed governor',
        'first_aid' => 'First-aid kit',
        'extinguisher' => 'Fire extinguisher',
        'fluids' => 'Oil, water and fuel levels',
        'clean' => 'Clean inside',
    ];
    private const SERVICE_TYPES = ['routine', 'repair', 'inspection', 'emergency'];
    private const PROBLEM_TYPES = ['vehicle_breakdown', 'accident', 'late_pickup', 'late_dropoff', 'wrong_stop', 'behavior', 'medical', 'other'];
    private const TRIPS = ['morning_pickup', 'evening_dropoff', 'midday_trip', 'special_trip'];

    public function __construct(private PDO $db) {}

    /** The vehicle this user may work with, or null. */
    public function vehicleFor(int $userId, array $roleIds, ?int $vehicleId = null): ?array
    {
        if ($vehicleId && array_intersect($roleIds, self::OVERSIGHT_ROLES)) {
            $stmt = $this->db->prepare('SELECT * FROM transport_vehicles WHERE id = ? LIMIT 1');
            $stmt->execute([$vehicleId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $staff = $this->db->prepare(
            'SELECT staff_id, full_name, phone FROM ' . ReadReplicaService::qualifiedRef('staff_directory') . '
              WHERE user_id = ? AND staff_status IN (\'active\', \'on_leave\') ORDER BY staff_id DESC LIMIT 1'
        );
        $staff->execute([$userId]);
        $driver = $staff->fetch(PDO::FETCH_ASSOC);
        if (!$driver) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM transport_vehicles WHERE driver_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([(int)$driver['staff_id']]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$vehicle) {
            return null;
        }
        $vehicle['driver_name'] = $driver['full_name'] ?? '';
        $vehicle['driver_phone'] = $driver['phone'] ?? '';
        return $vehicle;
    }

    /** Everything the My Vehicle page shows. */
    public function logbook(array $vehicle): array
    {
        $id = (int)$vehicle['id'];
        $services = $this->rows(
            'SELECT id, maintenance_date, maintenance_type, description, cost, odometer_reading,
                    next_maintenance_date, next_maintenance_reading, parts_replaced, mechanic_details, created_at
               FROM vehicle_maintenance WHERE vehicle_id = ? ORDER BY maintenance_date DESC, id DESC LIMIT 60',
            [$id]
        );
        $fuel = $this->rows(
            'SELECT id, fuel_date, litres, amount, odometer_reading, station, receipt_no, notes
               FROM vehicle_fuel_logs WHERE vehicle_id = ? ORDER BY fuel_date DESC, id DESC LIMIT 60',
            [$id]
        );
        $checks = $this->rows(
            'SELECT id, check_date, trip_session, odometer_reading, items, defects, fit_to_drive, checked_at
               FROM vehicle_daily_checks WHERE vehicle_id = ? ORDER BY check_date DESC, id DESC LIMIT 30',
            [$id]
        );
        foreach ($checks as &$c) {
            $c['items'] = json_decode((string)$c['items'], true) ?: [];
            $c['fit_to_drive'] = (bool)$c['fit_to_drive'];
        }
        unset($c);
        $problems = $this->rows(
            'SELECT id, incident_datetime, incident_type, description, action_taken, escalated
               FROM student_transport_incidents
              WHERE vehicle_id = ? AND student_id IS NULL ORDER BY incident_datetime DESC LIMIT 20',
            [$id]
        );

        // Month figures from the rows already read (no aggregate query on the primary).
        $month = date('Y-m');
        $fuelMonth = array_filter($fuel, fn ($f) => str_starts_with((string)$f['fuel_date'], $month));
        $odometers = array_filter(array_merge(
            array_column($services, 'odometer_reading'),
            array_column($fuel, 'odometer_reading'),
            array_column($checks, 'odometer_reading')
        ));

        $today = date('Y-m-d');
        return [
            'vehicle' => $vehicle,
            'odometer' => $odometers ? max(array_map('intval', $odometers)) : null,
            'month' => [
                'fuel_litres' => round(array_sum(array_column($fuelMonth, 'litres')), 2),
                'fuel_amount' => round(array_sum(array_column($fuelMonth, 'amount')), 2),
                'fill_ups' => count($fuelMonth),
            ],
            'checked_today' => array_values(array_map(
                fn ($c) => $c['trip_session'],
                array_filter($checks, fn ($c) => $c['check_date'] === $today)
            )),
            'check_items' => self::CHECK_ITEMS,
            'services' => $services,
            'fuel' => $fuel,
            'checks' => $checks,
            'problems' => $problems,
        ];
    }

    public function addService(array $vehicle, int $userId, array $d): int
    {
        $type = (string)($d['maintenance_type'] ?? '');
        if (!in_array($type, self::SERVICE_TYPES, true)) {
            throw new RuntimeException('Choose what kind of work was done.');
        }
        $date = $this->date($d['maintenance_date'] ?? null, 'service date');
        $description = trim((string)($d['description'] ?? ''));
        if ($description === '') {
            throw new RuntimeException('Describe the work done.');
        }
        $next = !empty($d['next_maintenance_date']) ? $this->date($d['next_maintenance_date'], 'next service date') : null;
        if ($next !== null && $next <= $date) {
            throw new RuntimeException('The next service date must be after this one.');
        }
        $this->db->prepare(
            'INSERT INTO vehicle_maintenance (vehicle_id, maintenance_date, maintenance_type, description, cost,
                    odometer_reading, next_maintenance_date, next_maintenance_reading, parts_replaced, mechanic_details, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int)$vehicle['id'], $date, $type, mb_substr($description, 0, 2000),
            $this->money($d['cost'] ?? null), $this->int($d['odometer_reading'] ?? null), $next,
            $this->int($d['next_maintenance_reading'] ?? null),
            $this->text($d['parts_replaced'] ?? null, 1000), $this->text($d['mechanic_details'] ?? null, 500), $userId,
        ]);
        $id = (int)$this->db->lastInsertId();
        if ($next !== null) {
            $this->db->prepare('UPDATE transport_vehicles SET service_due_date = ? WHERE id = ?')->execute([$next, (int)$vehicle['id']]);
        }
        return $id;
    }

    public function addFuel(array $vehicle, int $userId, array $d): int
    {
        $litres = (float)($d['litres'] ?? 0);
        $amount = (float)($d['amount'] ?? 0);
        if ($litres <= 0 || $litres > 2000) {
            throw new RuntimeException('Enter the litres bought.');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Enter the amount paid.');
        }
        $this->db->prepare(
            'INSERT INTO vehicle_fuel_logs (vehicle_id, fuel_date, litres, amount, odometer_reading, station, receipt_no, notes, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int)$vehicle['id'], $this->date($d['fuel_date'] ?? null, 'date'), round($litres, 2), round($amount, 2),
            $this->int($d['odometer_reading'] ?? null), $this->text($d['station'] ?? null, 120),
            $this->text($d['receipt_no'] ?? null, 60), $this->text($d['notes'] ?? null, 255), $userId,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Record the pre-trip check. A check with any defect marked "not fit to
     * drive" is also raised as an escalated incident for the transport office.
     */
    public function addCheck(array $vehicle, int $userId, array $d): array
    {
        $trip = (string)($d['trip_session'] ?? 'morning_pickup');
        if (!in_array($trip, self::TRIPS, true)) {
            throw new RuntimeException('Choose the trip.');
        }
        $given = is_array($d['items'] ?? null) ? $d['items'] : [];
        $items = [];
        foreach (array_keys(self::CHECK_ITEMS) as $key) {
            $items[$key] = ($given[$key] ?? 'ok') === 'defect' ? 'defect' : 'ok';
        }
        $defectKeys = array_keys(array_filter($items, fn ($v) => $v === 'defect'));
        $defects = $this->text($d['defects'] ?? null, 1000);
        if ($defectKeys && $defects === null) {
            throw new RuntimeException('Describe what is wrong with the items marked as a problem.');
        }
        $fit = !empty($d['fit_to_drive']) && $d['fit_to_drive'] !== 'false';
        if (!$defectKeys) {
            $fit = true;
        }
        $date = date('Y-m-d');
        $this->db->prepare(
            'INSERT INTO vehicle_daily_checks (vehicle_id, check_date, trip_session, odometer_reading, items, defects, fit_to_drive, checked_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE odometer_reading = VALUES(odometer_reading), items = VALUES(items), defects = VALUES(defects),
                    fit_to_drive = VALUES(fit_to_drive), checked_by = VALUES(checked_by), checked_at = CURRENT_TIMESTAMP'
        )->execute([(int)$vehicle['id'], $date, $trip, $this->int($d['odometer_reading'] ?? null),
            json_encode($items), $defects, $fit ? 1 : 0, $userId]);

        $raised = false;
        if ($defectKeys && !$fit) {
            $names = implode(', ', array_map(fn ($k) => self::CHECK_ITEMS[$k], $defectKeys));
            $this->insertProblem($vehicle, $userId, 'vehicle_breakdown',
                "Pre-trip check: not fit to drive. Problems: {$names}. {$defects}", true);
            $raised = true;
        }
        return ['fit_to_drive' => $fit, 'defects' => $defectKeys, 'raised' => $raised];
    }

    public function reportProblem(array $vehicle, int $userId, array $d): int
    {
        $type = (string)($d['incident_type'] ?? 'vehicle_breakdown');
        if (!in_array($type, self::PROBLEM_TYPES, true)) {
            throw new RuntimeException('Choose what happened.');
        }
        $description = trim((string)($d['description'] ?? ''));
        if (mb_strlen($description) < 5) {
            throw new RuntimeException('Describe the problem.');
        }
        // A medical problem or an accident is always escalated.
        $urgent = (!empty($d['urgent']) && $d['urgent'] !== 'false') || in_array($type, ['medical', 'accident'], true);
        $routeIds = $this->routeIds($vehicle);
        $routeId = (int)($d['route_id'] ?? 0) ?: ($routeIds[0] ?? null);
        if ($routeId && !in_array($routeId, $routeIds, true)) {
            throw new RuntimeException('That route is not one your vehicle runs.');
        }
        $studentId = (int)($d['student_id'] ?? 0) ?: null;
        if ($studentId) {
            if (!$routeId) {
                throw new RuntimeException('Your vehicle is not on a route.');
            }
            $on = $this->db->prepare(
                "SELECT 1 FROM student_transport_assignments WHERE student_id = ? AND route_id = ? AND status IN ('active','suspended') LIMIT 1"
            );
            $on->execute([$studentId, $routeId]);
            if (!$on->fetchColumn()) {
                throw new RuntimeException('That learner is not a passenger on your route.');
            }
        }
        return $this->insertProblem($vehicle, $userId, $type, $description, $urgent,
            $this->text($d['action_taken'] ?? null, 1000), $studentId, $routeId);
    }

    /** @return int[] the routes the vehicle is on */
    private function routeIds(array $vehicle): array
    {
        $stmt = $this->db->prepare(
            "SELECT route_id FROM transport_vehicle_routes WHERE vehicle_id = ? AND status = 'active' ORDER BY id DESC"
        );
        $stmt->execute([(int)$vehicle['id']]);
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    private function insertProblem(array $vehicle, int $userId, string $type, string $description, bool $escalated,
        ?string $action = null, ?int $studentId = null, ?int $routeId = null): int
    {
        $routeId ??= $this->routeIds($vehicle)[0] ?? null;
        $this->db->prepare(
            'INSERT INTO student_transport_incidents (student_id, route_id, vehicle_id, incident_datetime, incident_type,
                    description, action_taken, escalated, escalated_at, reported_by)
             VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)'
        )->execute([$studentId, $routeId, (int)$vehicle['id'], $type, mb_substr($description, 0, 2000), $action,
            $escalated ? 1 : 0, $escalated ? date('Y-m-d H:i:s') : null, $userId]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * One route as the route board shows it: the route, its stops in order,
     * the vehicle and driver on it, coming schedules and recent incidents.
     * Learner names are not included (the passenger list has its own page).
     */
    public function routeBoard(int $routeId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM transport_routes WHERE id = ? LIMIT 1');
        $stmt->execute([$routeId]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$route) {
            return null;
        }
        $route['stops'] = $this->rows(
            "SELECT id, name, sequence, arrival_time, departure_time, location
               FROM transport_stops WHERE route_id = ? AND status = 'active' ORDER BY sequence",
            [$routeId]
        );
        $route['vehicles'] = $this->rows(
            "SELECT v.id AS vehicle_id, v.registration_number, v.capacity, v.status, tvr.direction, d.full_name AS driver_name, d.phone AS driver_phone
               FROM transport_vehicle_routes tvr
               JOIN transport_vehicles v ON v.id = tvr.vehicle_id
               LEFT JOIN " . ReadReplicaService::qualifiedRef('staff_directory') . " d ON d.staff_id = v.driver_id
              WHERE tvr.route_id = ? AND tvr.status = 'active'
              ORDER BY tvr.direction",
            [$routeId]
        );
        $route['schedules'] = $this->rows(
            "SELECT id, date, pickup_time, status FROM transport_schedules
              WHERE route_id = ? AND status = 'active' AND (date IS NULL OR date >= CURDATE())
              ORDER BY date IS NULL, date, pickup_time LIMIT 14",
            [$routeId]
        );
        $route['recent_incidents'] = $this->rows(
            'SELECT id, incident_datetime, incident_type, description, escalated
               FROM student_transport_incidents WHERE route_id = ? ORDER BY incident_datetime DESC LIMIT 10',
            [$routeId]
        );
        return $route;
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function date($value, string $what): string
    {
        $value = $value ? (string)$value : date('Y-m-d');
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            throw new RuntimeException("Enter a valid {$what}.");
        }
        if ($value > date('Y-m-d') && $what !== 'next service date') {
            throw new RuntimeException("The {$what} cannot be in the future.");
        }
        return $value;
    }

    private function int($v): ?int
    {
        return ($v === null || $v === '') ? null : max(0, (int)$v);
    }

    private function money($v): ?float
    {
        return ($v === null || $v === '') ? null : round(max(0, (float)$v), 2);
    }

    private function text($v, int $max): ?string
    {
        $v = trim((string)($v ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
