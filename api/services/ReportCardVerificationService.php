<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * Verification codes printed (as a QR) on every report book.
 *
 * A code is 12 characters from an unambiguous alphabet (no 0/O/1/I), random
 * enough not to be guessed. Looking one up shows only what someone holding
 * the paper needs to confirm it is genuine: learner, class, term, issue date
 * and the overall result printed on it. No marks breakdown, contacts or
 * other personal data.
 */
final class ReportCardVerificationService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<string,mixed> $summary learner_name, admission_no, class, term, year, overall_percentage, overall_grade
     * @return array{code:string,url:string}
     */
    public function issue(int $studentId, int $academicYearTermId, array $summary, ?int $userId): array
    {
        $summary = array_intersect_key($summary, array_flip([
            'learner_name', 'admission_no', 'class', 'term', 'year', 'overall_percentage', 'overall_grade', 'school',
        ]));
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $this->code();
            try {
                $this->db->prepare(
                    'INSERT INTO report_card_verifications (code, student_id, academic_year_term_id, summary_json, issued_by, issued_at)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                )->execute([$code, $studentId, $academicYearTermId, json_encode($summary, JSON_UNESCAPED_UNICODE), $userId ?: null]);
                return ['code' => $code, 'url' => self::verifyUrl($code)];
            } catch (\PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e; // retry only on a code collision
            }
        }
        throw new \RuntimeException('A verification code could not be issued. Please try again.');
    }

    /** @return array<string,mixed>|null */
    public function lookup(string $code): ?array
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($code) !== 12) return null;
        $stmt = $this->db->prepare('SELECT code, summary_json, issued_at, revoked_at FROM report_card_verifications WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return [
            'code' => $row['code'],
            'status' => $row['revoked_at'] ? 'revoked' : 'valid',
            'issued_at' => $row['issued_at'],
            'summary' => json_decode((string) $row['summary_json'], true) ?: [],
        ];
    }

    public static function verifyUrl(string $code): string
    {
        // The index.php facade route works without the clean-URL rewrite
        // (.htaccess is host config, not deployed with the code).
        return rtrim((string) BASE_URL, '/') . '/index.php?route=verify-report&code=' . rawurlencode($code);
    }

    private function code(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 12; $i++) $out .= self::ALPHABET[random_int(0, $max)];
        return $out;
    }
}
