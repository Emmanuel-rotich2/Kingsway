<?php

declare(strict_types=1);

namespace App\API\Services;

use InvalidArgumentException;

/**
 * Deterministic, database-independent validation for editable timetable
 * assignments. Database-backed scope, eligibility, and active-slot checks
 * remain in SchedulesAPI; this class handles the collision contract shared by
 * AI-proposed and human-edited draft entries.
 */
final class TimetableAssignmentDraftValidator
{
    /** @return array<int, array<string, int|string>> */
    public static function normalize(array $entries, bool $allowTeacherOverlap = false): array
    {
        if (count($entries) > 500) {
            throw new InvalidArgumentException('A timetable draft cannot contain more than 500 assignments.');
        }

        $normalized = [];
        $cells = [];
        $teachers = [];
        $rooms = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException('Each timetable assignment must be an object.');
            }
            $stream = (int) ($entry['academic_year_class_stream_id'] ?? 0);
            $day = (int) ($entry['day_of_week'] ?? 0);
            $slot = (int) ($entry['time_slot_id'] ?? 0);
            $area = (int) ($entry['learning_area_id'] ?? 0);
            $teacher = (int) ($entry['teacher_id'] ?? 0);
            $room = (int) ($entry['room_id'] ?? 0);

            // Empty matrix cells are intentionally ignored by the existing
            // editor contract.
            if (($stream <= 0 && $day <= 0 && $slot <= 0 && $area <= 0 && $teacher <= 0) || ($area <= 0 && $teacher <= 0)) continue;
            if ($stream <= 0 || $day < 1 || $day > 7 || $slot <= 0 || $area <= 0 || $teacher <= 0) {
                throw new InvalidArgumentException('Every timetable assignment requires a stream, day, active slot, learning area, and teacher.');
            }

            $cell = "{$stream}:{$day}:{$slot}";
            if (isset($cells[$cell])) throw new InvalidArgumentException('A class stream cannot have two assignments in the same time slot.');
            $cells[$cell] = true;

            $teacherKey = "{$teacher}:{$day}:{$slot}";
            if (!$allowTeacherOverlap && isset($teachers[$teacherKey])) {
                throw new InvalidArgumentException('A teacher cannot be assigned to two class streams at the same time.');
            }
            $teachers[$teacherKey] = true;

            if ($room > 0) {
                $roomKey = "{$room}:{$day}:{$slot}";
                if (isset($rooms[$roomKey])) throw new InvalidArgumentException('A room cannot be assigned to two class streams at the same time.');
                $rooms[$roomKey] = true;
            }

            $normalized[] = [
                'academic_year_class_stream_id' => $stream,
                'day_of_week' => $day,
                'time_slot_id' => $slot,
                'learning_area_id' => $area,
                'teacher_id' => $teacher,
                'room_id' => $room,
                'notes' => mb_substr(trim((string) ($entry['notes'] ?? '')), 0, 500),
            ];
        }
        return $normalized;
    }
}
