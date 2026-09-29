<?php
declare(strict_types=1);

namespace App\API\Services;

use DomainException;

/** Validates AI proposals against server supplied exam papers, slots and teacher constraints. */
final class ExamTimetableDraftValidator
{
    public static function normalize(array $assignments, array $input): array
    {
        $papers = [];
        foreach ((array) ($input['papers'] ?? []) as $paper) {
            $papers[(int) ($paper['paper_id'] ?? 0)] = $paper;
        }
        $slots = [];
        foreach ((array) ($input['allowed_slots'] ?? []) as $slot) {
            $slots[(int) ($slot['slot_id'] ?? 0)] = $slot;
        }
        if ($papers === [] || $slots === [] || count($assignments) !== count($papers)) {
            throw new DomainException('AI timetable must schedule every selected class learning area exactly once.', 502);
        }

        $seenPapers = $classSlots = $dailyCounts = $teacherSlots = [];
        $normalized = [];
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)) throw new DomainException('AI timetable assignment is malformed.', 502);
            $paperId = (int) ($assignment['paper_id'] ?? 0);
            $slotId = (int) ($assignment['slot_id'] ?? 0);
            if (!isset($papers[$paperId], $slots[$slotId]) || isset($seenPapers[$paperId])) {
                throw new DomainException('AI timetable contains an unknown, duplicate or missing paper/slot.', 502);
            }
            $paper = $papers[$paperId];
            $slot = $slots[$slotId];
            if (!in_array((string) $paper['grade_band'], (array) $slot['grade_bands'], true)) {
                throw new DomainException('AI timetable placed an exam outside its permitted session window.', 502);
            }
            $seenPapers[$paperId] = true;
            $date = (string) $slot['date'];
            $band = (string) $paper['grade_band'];
            $classKey = (int) $paper['class_key'];
            $maxDaily = $band === 'early' ? 2 : 3;
            $dayKey = $classKey . ':' . $date;
            if (++$dailyCounts[$dayKey] > $maxDaily) {
                throw new DomainException('AI timetable exceeds the daily exam limit for a class.', 502);
            }
            $cell = $classKey . ':' . $slotId;
            if (isset($classSlots[$cell])) throw new DomainException('A class cannot sit two papers at the same time.', 502);
            $classSlots[$cell] = true;
            foreach ((array) ($paper['teacher_groups'] ?? []) as $teacherGroup) {
                $teacherKey = (string) $teacherGroup . ':' . $slotId;
                if (isset($teacherSlots[$teacherKey])) {
                    throw new DomainException('AI timetable has a teacher supervising two simultaneous papers.', 502);
                }
                $teacherSlots[$teacherKey] = true;
            }
            $normalized[] = [
                'paper_id' => $paperId,
                'slot_id' => $slotId,
                'date' => $date,
                'start_time' => (string) $slot['start_time'],
                'end_time' => (string) $slot['end_time'],
                'class_name' => (string) $paper['class_name'],
                'learning_area' => (string) $paper['learning_area'],
            ];
        }
        if (count($seenPapers) !== count($papers)) throw new DomainException('AI timetable omitted one or more selected papers.', 502);
        usort($normalized, static fn(array $a, array $b): int => [$a['date'], $a['start_time'], $a['class_name'], $a['learning_area']] <=> [$b['date'], $b['start_time'], $b['class_name'], $b['learning_area']]);
        return $normalized;
    }
}
