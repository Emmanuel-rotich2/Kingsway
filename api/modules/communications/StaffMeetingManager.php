<?php
namespace App\API\Modules\communications;

use PDO;
use Exception;

/**
 * StaffMeetingManager
 *
 * Internal staff meetings scheduled by heads (director, school admin, HODs to
 * department members or selected members, deputies, class teachers, etc.),
 * integrated with the academic calendar:
 *
 *   * Every meeting creates/syncs a linked `school_events` row (type
 *     'Meeting', location = venue, description carries the online link), so it
 *     appears on the academic calendar, the Year Calendar and the events pages
 *     - everyone sees the meeting, the venue, or the online link.
 *   * Attendees are tracked with RSVP (invited/accepted/declined/maybe).
 *   * Attendees get notifications through the `notifications` table when the
 *     meeting is created/updated and when a reminder is sent.
 */
class StaffMeetingManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ==================== STAFF PICKER ====================

    /**
     * Active staff for the attendee picker (id, name, position, department)
     * plus the departments list for department-wide targeting.
     */
    public function listStaffForPicker(): array
    {
        $stmt = $this->db->query(
            "SELECT s.id, s.staff_no,
                    CONCAT(p.first_name, ' ', p.last_name) AS name,
                    s.position,
                    d.name AS department_name
             FROM staff s
             LEFT JOIN persons p ON p.id = s.person_id
             LEFT JOIN staff_department_assignments sda
                    ON sda.staff_id = s.id
                   AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
             LEFT JOIN departments d ON d.id = sda.department_id
             WHERE s.status = 'active'
             ORDER BY p.first_name, p.last_name"
        );
        return [
            'staff' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'departments' => $this->db->query(
                "SELECT id, name FROM departments ORDER BY name"
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    // ==================== LIST / GET ====================

    public function listMeetings(array $filters = [], ?int $staffId = null): array
    {
        $where = ['1=1'];
        $bindings = [];
        if (!empty($filters['meeting_type'])) {
            $where[] = 'm.meeting_type = ?';
            $bindings[] = $filters['meeting_type'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'm.department_id = ?';
            $bindings[] = (int) $filters['department_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'm.status = ?';
            $bindings[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(m.title LIKE ? OR m.venue LIKE ? OR m.meeting_link LIKE ? OR CONCAT(p.first_name, " ", p.last_name) LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            $bindings = array_merge($bindings, [$term, $term, $term, $term]);
        }
        if (!empty($filters['mine']) && $staffId) {
            $where[] = '(m.organizer_staff_id = ? OR EXISTS (
                            SELECT 1 FROM staff_meeting_attendees ma
                            WHERE ma.meeting_id = m.id AND ma.staff_id = ?))';
            $bindings[] = $staffId;
            $bindings[] = $staffId;
        }

        $sql = "
            SELECT m.*,
                   CONCAT(p.first_name, ' ', p.last_name) AS organizer_name,
                   d.name AS department_name,
                   COUNT(a.id) AS attendee_count,
                   COALESCE(SUM(a.status = 'accepted'), 0) AS accepted_count,
                   COALESCE(SUM(a.status = 'declined'), 0) AS declined_count,
                   MAX(CASE WHEN a.staff_id = ? THEN a.status END) AS my_status
            FROM staff_meetings m
            LEFT JOIN staff s ON s.id = m.organizer_staff_id
            LEFT JOIN persons p ON p.id = s.person_id
            LEFT JOIN departments d ON d.id = m.department_id
            LEFT JOIN staff_meeting_attendees a ON a.meeting_id = m.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY m.id
            ORDER BY m.meeting_date, m.start_time";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$staffId ?: 0], $bindings));

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->decorate($row);
        }
        return $rows;
    }

    public function getMeeting(int $id, ?int $staffId = null): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*,
                    CONCAT(p.first_name, ' ', p.last_name) AS organizer_name,
                    d.name AS department_name,
                    (SELECT COUNT(*) FROM staff_meeting_attendees a WHERE a.meeting_id = m.id) AS attendee_count
             FROM staff_meetings m
             LEFT JOIN staff s ON s.id = m.organizer_staff_id
             LEFT JOIN persons p ON p.id = s.person_id
             LEFT JOIN departments d ON d.id = m.department_id
             WHERE m.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $atts = $this->db->prepare(
            "SELECT a.staff_id, a.status, a.responded_at,
                    CONCAT(p.first_name, ' ', p.last_name) AS name,
                    s.position, d.name AS department_name
             FROM staff_meeting_attendees a
             LEFT JOIN staff s ON s.id = a.staff_id
             LEFT JOIN persons p ON p.id = s.person_id
             LEFT JOIN staff_department_assignments sda
                    ON sda.staff_id = a.staff_id
                   AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
             LEFT JOIN departments d ON d.id = sda.department_id
             WHERE a.meeting_id = ?
             ORDER BY p.first_name, p.last_name"
        );
        $atts->execute([$id]);
        $row['attendees'] = $atts->fetchAll(PDO::FETCH_ASSOC);

        return $this->decorate($row);
    }

    // ==================== CREATE / UPDATE / CANCEL / DELETE ====================

    public function createMeeting(int $organizerStaffId, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $date = $data['meeting_date'] ?? null;
        $start = $data['start_time'] ?? null;
        if ($title === '' || empty($date) || empty($start)) {
            return ['error' => 'Title, meeting date and start time are required', 'code' => 400];
        }
        $end = $data['end_time'] ?? null;
        if (empty($end)) {
            $end = date('H:i:s', strtotime($start) + 3600); // default 1 hour
        }
        $type = $data['meeting_type'] ?? 'general';
        $allowed = ['general', 'departmental', 'administrative', 'heads', 'class_teachers', 'assembly', 'other'];
        if (!in_array($type, $allowed, true)) {
            return ['error' => 'Invalid meeting type', 'code' => 400];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO staff_meetings
                    (title, description, meeting_type, meeting_date, start_time, end_time,
                     venue, meeting_link, department_id, class_id, organizer_staff_id, agenda, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')"
            );
            $stmt->execute([
                $title,
                $data['description'] ?? null,
                $type,
                $date,
                $start,
                $end,
                $data['venue'] ?? null,
                $data['meeting_link'] ?? null,
                !empty($data['department_id']) ? (int) $data['department_id'] : null,
                !empty($data['class_id']) ? (int) $data['class_id'] : null,
                $organizerStaffId,
                $data['agenda'] ?? null,
            ]);
            $meetingId = (int) $this->db->lastInsertId();

            $attendees = $this->resolveAttendees($data, $organizerStaffId);
            $this->replaceAttendees($meetingId, $attendees);

            $eventId = $this->syncCalendarEvent($meetingId, [
                'title' => $title,
                'meeting_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'venue' => $data['venue'] ?? null,
                'meeting_link' => $data['meeting_link'] ?? null,
                'description' => $data['description'] ?? null,
                'agenda' => $data['agenda'] ?? null,
            ]);

            $this->db->commit();

            $this->notifyAttendees($attendees, $title, $date, $start, $end, $data['venue'] ?? null, $data['meeting_link'] ?? null, 'Meeting scheduled');

            return ['meeting_id' => $meetingId, 'calendar_event_id' => $eventId];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function updateMeeting(int $id, array $data): array
    {
        $stmt = $this->db->prepare("SELECT * FROM staff_meetings WHERE id = ?");
        $stmt->execute([$id]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$meeting) {
            return ['error' => 'Meeting not found', 'code' => 404];
        }

        $fields = [
            'title' => 'title', 'description' => 'description', 'meeting_type' => 'meeting_type',
            'meeting_date' => 'meeting_date', 'start_time' => 'start_time', 'end_time' => 'end_time',
            'venue' => 'venue', 'meeting_link' => 'meeting_link',
            'department_id' => 'department_id', 'class_id' => 'class_id', 'agenda' => 'agenda',
        ];
        $sets = [];
        $values = [];
        foreach ($fields as $from => $col) {
            if (array_key_exists($from, $data) && $data[$from] !== null) {
                $sets[] = "$col = ?";
                $values[] = $data[$from];
            }
        }
        if (!empty($data['status'])) {
            $sets[] = "status = ?";
            $values[] = $data['status'];
        }
        if (empty($sets)) {
            return ['error' => 'No fields to update', 'code' => 400];
        }
        $values[] = $id;
        $this->db->prepare("UPDATE staff_meetings SET " . implode(', ', $sets) . " WHERE id = ?")->execute($values);

        // Re-resolve attendees when the audience changed.
        if (array_key_exists('staff_ids', $data) || array_key_exists('department_id', $data)) {
            $attendees = $this->resolveAttendees($data, (int) $meeting['organizer_staff_id']);
            $this->replaceAttendees($id, $attendees);
        } else {
            $attendees = $this->attendeeStaffIds($id);
        }

        $fresh = $this->db->prepare("SELECT * FROM staff_meetings WHERE id = ?");
        $fresh->execute([$id]);
        $updated = $fresh->fetch(PDO::FETCH_ASSOC);

        $this->syncCalendarEvent($id, [
            'title' => $updated['title'],
            'meeting_date' => $updated['meeting_date'],
            'start_time' => $updated['start_time'],
            'end_time' => $updated['end_time'],
            'venue' => $updated['venue'],
            'meeting_link' => $updated['meeting_link'],
            'description' => $updated['description'],
            'agenda' => $updated['agenda'],
        ]);

        $this->notifyAttendees($attendees, $updated['title'], $updated['meeting_date'], $updated['start_time'], $updated['end_time'], $updated['venue'], $updated['meeting_link'], 'Meeting updated');

        return ['meeting_id' => $id, 'message' => 'Meeting updated'];
    }

    public function cancelMeeting(int $id): array
    {
        $stmt = $this->db->prepare("UPDATE staff_meetings SET status = 'cancelled' WHERE id = ? AND status <> 'cancelled'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            return ['error' => 'Meeting not found or already cancelled', 'code' => 404];
        }
        return ['message' => 'Meeting cancelled'];
    }

    public function deleteMeeting(int $id): array
    {
        $stmt = $this->db->prepare("SELECT calendar_event_id FROM staff_meetings WHERE id = ?");
        $stmt->execute([$id]);
        $eventId = (int) ($stmt->fetchColumn() ?: 0);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM staff_meeting_attendees WHERE meeting_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM staff_meetings WHERE id = ?")->execute([$id]);
            if ($eventId) {
                // Remove the linked calendar event too (calendar stays clean).
                $this->db->prepare("DELETE FROM school_events WHERE id = ? AND type = 'Meeting'")->execute([$eventId]);
            }
            $this->db->commit();
            return ['message' => 'Meeting deleted'];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ==================== RSVP / REMIND ====================

    public function respondToMeeting(int $meetingId, int $staffId, string $status): array
    {
        if (!in_array($status, ['accepted', 'declined', 'maybe'], true)) {
            return ['error' => 'Invalid response status', 'code' => 400];
        }
        $stmt = $this->db->prepare(
            "UPDATE staff_meeting_attendees
             SET status = ?, responded_at = NOW()
             WHERE meeting_id = ? AND staff_id = ?"
        );
        $stmt->execute([$status, $meetingId, $staffId]);
        if ($stmt->rowCount() === 0) {
            return ['error' => 'You are not invited to this meeting', 'code' => 403];
        }
        return ['message' => 'Response saved'];
    }

    public function remindAttendees(int $meetingId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM staff_meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$meeting) {
            return ['error' => 'Meeting not found', 'code' => 404];
        }
        $attendees = $this->attendeeStaffIds($meetingId);
        $this->notifyAttendees($attendees, $meeting['title'], $meeting['meeting_date'], $meeting['start_time'], $meeting['end_time'], $meeting['venue'], $meeting['meeting_link'], 'Meeting reminder');
        return ['message' => 'Reminder sent to ' . count($attendees) . ' attendee(s)'];
    }

    // ==================== INTERNALS ====================

    private function decorate(array $m): array
    {
        $m['attendee_count'] = (int) ($m['attendee_count'] ?? 0);
        $m['accepted_count'] = (int) ($m['accepted_count'] ?? 0);
        $m['declined_count'] = (int) ($m['declined_count'] ?? 0);
        // Derived status from the schedule unless explicitly cancelled.
        if (($m['status'] ?? 'scheduled') === 'scheduled') {
            $end = $m['meeting_date'] . ' ' . ($m['end_time'] ?? $m['start_time']);
            if ($end < date('Y-m-d H:i:s')) {
                $m['status'] = 'completed';
            } elseif ($m['meeting_date'] . ' ' . $m['start_time'] <= date('Y-m-d H:i:s')) {
                $m['status'] = 'ongoing';
            }
        }
        return $m;
    }

    private function resolveAttendees(array $data, int $organizerStaffId): array
    {
        $ids = [];
        if (!empty($data['staff_ids']) && is_array($data['staff_ids'])) {
            foreach ($data['staff_ids'] as $sid) {
                $ids[(int) $sid] = true;
            }
        }
        if (!empty($data['department_id'])) {
            $stmt = $this->db->prepare(
                "SELECT staff_id FROM staff_department_assignments
                 WHERE department_id = ? AND (effective_to IS NULL OR effective_to >= CURDATE())"
            );
            $stmt->execute([(int) $data['department_id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                $ids[(int) $sid] = true;
            }
        }
        $ids[$organizerStaffId] = true; // organizer is always a participant
        return array_keys($ids);
    }

    private function replaceAttendees(int $meetingId, array $staffIds): void
    {
        $this->db->prepare("DELETE FROM staff_meeting_attendees WHERE meeting_id = ?")->execute([$meetingId]);
        $stmt = $this->db->prepare(
            "INSERT INTO staff_meeting_attendees (meeting_id, staff_id, status) VALUES (?, ?, 'invited')"
        );
        foreach ($staffIds as $sid) {
            $stmt->execute([$meetingId, $sid]);
        }
    }

    private function attendeeStaffIds(int $meetingId): array
    {
        $stmt = $this->db->prepare("SELECT staff_id FROM staff_meeting_attendees WHERE meeting_id = ?");
        $stmt->execute([$meetingId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Create/refresh the linked school_events row so the meeting appears on the
     * academic calendar, the Year Calendar and the events pages with the venue
     * tagged (location) and the online link in the description.
     */
    private function syncCalendarEvent(int $meetingId, array $m): ?int
    {
        $stmt = $this->db->prepare("SELECT calendar_event_id FROM staff_meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
        $eventId = (int) ($stmt->fetchColumn() ?: 0);

        $startAt = $m['meeting_date'] . ' ' . $m['start_time'];
        $endAt = $m['meeting_date'] . ' ' . ($m['end_time'] ?? $m['start_time']);
        $desc = trim((string) ($m['description'] ?? ''));
        $agenda = trim((string) ($m['agenda'] ?? ''));
        $link = trim((string) ($m['meeting_link'] ?? ''));
        $description = '';
        if ($agenda !== '') {
            $description .= "Agenda: " . $agenda;
        }
        if ($desc !== '') {
            $description .= ($description !== '' ? "\n" : '') . $desc;
        }
        if ($link !== '') {
            $description .= ($description !== '' ? "\n" : '') . "Join online: " . $link;
        }

        if ($eventId) {
            $this->db->prepare(
                "UPDATE school_events
                 SET title = ?, start_at = ?, end_at = ?, location = ?, description = ?, status = 'upcoming'
                 WHERE id = ?"
            )->execute([$m['title'], $startAt, $endAt, $m['venue'], $description !== '' ? $description : null, $eventId]);
            return $eventId;
        }

        $this->db->prepare(
            "INSERT INTO school_events (title, description, start_at, end_at, type, location, status, source)
             VALUES (?, ?, ?, ?, 'Meeting', ?, 'upcoming', 'manual')"
        )->execute([$m['title'], $description !== '' ? $description : null, $startAt, $endAt, $m['venue']]);
        $id = (int) $this->db->lastInsertId();
        $this->db->prepare("UPDATE staff_meetings SET calendar_event_id = ? WHERE id = ?")->execute([$id, $meetingId]);
        return $id;
    }

    private function notifyAttendees(array $staffIds, string $title, string $date, string $start, ?string $end, ?string $venue, ?string $link, string $action): void
    {
        if (empty($staffIds)) {
            return;
        }
        $in = implode(',', array_fill(0, count($staffIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT u.id FROM users u
             JOIN staff s ON s.person_id = u.person_id
             WHERE s.id IN ($in) AND u.status = 'active'"
        );
        $stmt->execute($staffIds);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($userIds)) {
            return;
        }

        $time = $start . (($end && $end !== $start) ? ' - ' . $end : '');
        $where = trim((string) $venue);
        $msg = "{$action}: {$title} on " . date('D, d M Y', strtotime($date)) . " at {$time}.";
        if ($where !== '') {
            $msg .= " Venue: {$where}.";
        }
        if (!empty($link)) {
            $msg .= " Join: {$link}";
        }

        $ins = $this->db->prepare(
            "INSERT INTO notifications (user_id, type, title, message, priority)
             VALUES (?, 'meeting', ?, ?, 'high')"
        );
        foreach ($userIds as $uid) {
            $ins->execute([(int) $uid, $title, $msg]);
        }
    }
}
