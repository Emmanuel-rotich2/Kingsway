<?php

namespace App\API\Services;

/**
 * Turns the text extracted from an uploaded question paper into reviewable
 * question rows. Pure text processing: no database, no network.
 *
 * Recognised layout (the common shapes of a classroom paper):
 *   1. What is ... ? (2 marks)        numbered questions: 1. 1) Q1 Question 1:
 *      a) choice  b) choice           lettered choices make it multiple choice
 *   Answer: ...                       an answer / marking-guide line
 * Lines that are neither continue the current question. When nothing is
 * numbered, each line ending in "?" becomes a question.
 * The result is a preview: the teacher reviews and edits before saving.
 */
final class AssessmentQuestionParser
{
    private const MAX_QUESTIONS = 100;

    /** @return list<array{question_type:string,question_text:string,options:list<string>,answer_text:string,marks:float}> */
    public static function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r", ' | '], ["\n", "\n", ' '], $text);
        $lines = array_values(array_filter(array_map(
            static fn(string $l): string => trim(preg_replace('/\s+/u', ' ', $l) ?? ''),
            explode("\n", $text)
        ), static fn(string $l): bool => $l !== ''));

        $questions = [];
        $current = null;
        $push = static function (?array $q) use (&$questions): void {
            if ($q === null || trim($q['question_text']) === '') return;
            $questions[] = $q;
        };

        foreach ($lines as $line) {
            if (preg_match('/^(?:q(?:uestion)?\s*)?(\d{1,3})\s*[\.\):\-]\s*(.+)$/iu', $line, $m)
                && !preg_match('/^\d{1,3}\s*[\.\)]\s*\d/u', $line)) {
                $push($current);
                $current = self::blank($m[2]);
                continue;
            }
            if ($current === null) continue;
            if (preg_match('/^\(?([a-h])[\.\)]\s+(.+)$/iu', $line, $m)) {
                $current['options'][] = self::stripMarks($m[2], $current);
                continue;
            }
            if (preg_match('/^(?:answer|ans|marking\s+guide)\s*[:\-]\s*(.*)$/iu', $line, $m)) {
                $current['answer_text'] = trim($current['answer_text'] . ' ' . $m[1]);
                continue;
            }
            $current['question_text'] = self::stripMarks(trim($current['question_text'] . ' ' . $line), $current);
        }
        $push($current);

        if (!$questions) {
            foreach ($lines as $line) {
                if (str_ends_with($line, '?')) $questions[] = self::blank($line);
            }
        }

        return array_map([self::class, 'finish'], array_slice($questions, 0, self::MAX_QUESTIONS));
    }

    private static function blank(string $text): array
    {
        $q = ['question_type' => 'short_answer', 'question_text' => '', 'options' => [], 'answer_text' => '', 'marks' => 0.0];
        $q['question_text'] = self::stripMarks(trim($text), $q);
        return $q;
    }

    /** Pull "(2 marks)", "[3 mks]", "2 marks" out of the text into the marks field. */
    private static function stripMarks(string $text, array &$q): string
    {
        if (preg_match('/[\(\[]\s*(\d+(?:\.\d+)?)\s*(?:marks?|mks?|pts?|points?)\s*[\)\]]|\b(\d+(?:\.\d+)?)\s*(?:marks|mks)\b\.?$/iu', $text, $m)) {
            $q['marks'] = (float) ($m[1] !== '' ? $m[1] : ($m[2] ?? 0));
            $text = trim(str_replace($m[0], '', $text));
        }
        return $text;
    }

    private static function finish(array $q): array
    {
        $text = mb_substr(trim($q['question_text']), 0, 5000);
        $options = array_values(array_slice(array_map(static fn($o) => mb_substr(trim((string) $o), 0, 500), $q['options']), 0, 8));
        $type = 'short_answer';
        if (count($options) >= 2) {
            $type = 'multiple_choice';
        } elseif (preg_match('/\btrue\s*(?:or|\/)\s*false\b|\(t\/f\)/iu', $text)) {
            $type = 'true_false';
        } elseif (mb_strlen($text) > 220 || preg_match('/^(?:explain|describe|discuss|write an essay)/iu', $text)) {
            $type = 'long_answer';
        }
        return [
            'question_type' => $type,
            'question_text' => $text,
            'options' => $type === 'multiple_choice' ? $options : [],
            'answer_text' => mb_substr(trim($q['answer_text']), 0, 2000),
            'marks' => $q['marks'] > 0 ? min(1000.0, $q['marks']) : 1.0,
        ];
    }
}
