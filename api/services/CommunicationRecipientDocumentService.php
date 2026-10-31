<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Modules\staff\StaffPayrollManager;
use PDO;
use RuntimeException;

/** Resolves approved school documents separately for one delivery endpoint. */
final class CommunicationRecipientDocumentService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{strategy:string,file_paths:list<string>,public_urls:list<string>,file_path:?string,public_url:?string,link_message:string,mime_type:string}
     */
    public function generate(string $strategy, array $endpoint, array $options): array
    {
        $resolved = match ($strategy) {
            'fee_statement' => $this->feeStatements($endpoint, $options),
            'payslip' => $this->payslip($endpoint, $options),
            'report_card' => $this->releasedReportCards($endpoint, $options),
            'student_id_card' => $this->learnerProducerDocuments('student_id_cards', $endpoint, $options),
            'portfolio' => $this->learnerProducerDocuments('portfolios', $endpoint, $options),
            'certificate' => $this->issuedCertificates($endpoint, $options),
            'staff_security_pass' => $this->staffPass($endpoint, $options),
            default => throw new RuntimeException('This personalized document type is not available.'),
        };
        $paths = array_values(array_filter($resolved['file_paths'] ?? [], 'is_string'));
        $urls = array_values(array_filter($resolved['public_urls'] ?? [], 'is_string'));
        return [
            'strategy' => $strategy,
            'file_paths' => $paths,
            'public_urls' => $urls,
            'file_path' => $paths[0] ?? null,
            'public_url' => count($urls) === 1 ? $urls[0] : null,
            'link_message' => count($urls) > 1 ? $this->linkMessage($strategy, $urls) : '',
            'mime_type' => 'application/pdf',
        ];
    }

    /** @return array{file_paths:list<string>,public_urls:list<string>} */
    private function feeStatements(array $endpoint, array $options): array
    {
        $parentUserId = (int) ($endpoint['recipient_id'] ?? 0);
        if ($parentUserId < 1) throw new RuntimeException('Fee statement delivery requires a linked parent account.');
        $studentIds = $this->linkedLearners($parentUserId, $options);
        if (!$studentIds) throw new RuntimeException('No active learner relationship was found for this parent; no statement was sent.');

        $prints = new PrintService();
        $head = '';
        $bodies = [];
        $yearId = isset($options['academic_year_id']) ? (int) $options['academic_year_id'] : null;
        foreach ($studentIds as $studentId) {
            $data = $prints->prepareStudentFeeStatement($studentId, $yearId);
            $html = $prints->buildFeeStatementHtml($data, ['orientation' => 'portrait', 'paperSize' => 'A4', 'filename' => 'parent_fee_statement']);
            if ($head === '' && preg_match('/<head\b[^>]*>(.*?)<\/head>/is', $html, $match)) $head = $match[1];
            if (!preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $match)) throw new RuntimeException('A learner fee statement could not be rendered.');
            $bodies[] = '<section style="page-break-after:always">' . $match[1] . '</section>';
        }
        $combined = '<!doctype html><html><head><meta charset="utf-8">' . $head . '</head><body>' . implode('', $bodies) . '</body></html>';
        $path = $prints->generatePDFFromHtml($combined, [
            'orientation' => 'portrait', 'paperSize' => 'A4',
            'filename' => 'parent_fee_statements_' . bin2hex(random_bytes(8)), 'showPageNumbers' => false,
        ]);
        return $this->pathsAndUrls([$path]);
    }

    /** @return array{file_paths:list<string>,public_urls:list<string>} */
    private function payslip(array $endpoint, array $options): array
    {
        $userId = (int) ($endpoint['recipient_id'] ?? 0);
        $month = (int) ($options['payroll_month'] ?? 0);
        $year = (int) ($options['payroll_year'] ?? 0);
        if ($userId < 1 || $month < 1 || $month > 12 || $year < 2000 || $year > (int) date('Y') + 1) {
            throw new RuntimeException('Payslip delivery requires one staff account and a valid payroll period.');
        }
        $directory = ReadReplicaService::qualifiedRef('staff_directory');
        $stmt = $this->db->prepare("SELECT staff_id FROM {$directory} WHERE user_id = ? AND user_status = 'active' AND staff_status = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        $staffId = (int) $stmt->fetchColumn();
        if ($staffId < 1) throw new RuntimeException('No active staff record is linked to this recipient.');

        $result = (new StaffPayrollManager())->viewPayslip($staffId, ['payroll_month' => $month, 'payroll_year' => $year]);
        $payslip = $result['data']['payslip'] ?? null;
        if (!is_array($payslip) || !in_array((string) ($payslip['payslip_status'] ?? ''), ['approved', 'paid'], true)) {
            throw new RuntimeException('There is no approved payslip for this staff member and payroll period.');
        }
        $data = [
            'payslip' => $payslip,
            'allowances_breakdown' => $result['data']['allowances_breakdown'] ?? [],
            'deductions_breakdown' => $result['data']['deductions_breakdown'] ?? [],
        ];
        $prints = new PrintService();
        $html = $prints->buildPayslipHtml($data, ['orientation' => 'portrait', 'paperSize' => 'A4', 'filename' => 'staff_payslip']);
        $path = $prints->generatePDFFromHtml($html, [
            'orientation' => 'portrait', 'paperSize' => 'A4',
            'filename' => 'staff_payslip_' . bin2hex(random_bytes(8)), 'showPageNumbers' => false,
        ]);
        return $this->pathsAndUrls([$path]);
    }

    /** @return array{file_paths:list<string>,public_urls:list<string>} */
    private function releasedReportCards(array $endpoint, array $options): array
    {
        $parentUserId = (int) ($endpoint['recipient_id'] ?? 0);
        if ($parentUserId < 1) throw new RuntimeException('Released report cards require a linked parent account.');
        $studentIds = $this->linkedLearners($parentUserId, $options);
        if (!$studentIds) throw new RuntimeException('No active learner relationship was found for this parent; no report card was sent.');

        // Deliver only immutable, released snapshots. Never generate or send a
        // draft from the current assessment data in a communications worker.
        $stmt = $this->db->prepare(
            "SELECT pdf_path, pdf_sha256 FROM report_card_releases
              WHERE student_id = ? AND status = 'released'
              ORDER BY released_at DESC, version_no DESC LIMIT 1"
        );
        $paths = [];
        foreach ($studentIds as $studentId) {
            $stmt->execute([$studentId]);
            $release = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$release) continue;
            $path = (string) ($release['pdf_path'] ?? '');
            if ($path === '' || !is_file($path) || !hash_equals((string) ($release['pdf_sha256'] ?? ''), (string) hash_file('sha256', $path))) {
                throw new RuntimeException('A released report card failed its integrity check; no report card was sent.');
            }
            $paths[] = $path;
        }
        if (!$paths) throw new RuntimeException('No released report cards are available for this parent.');
        return $this->pathsAndUrls($paths);
    }

    /** Render data-backed registry documents one learner at a time for this parent. */
    private function learnerProducerDocuments(string $kind, array $endpoint, array $options): array
    {
        $parentUserId = (int) ($endpoint['recipient_id'] ?? 0);
        if ($parentUserId < 1) throw new RuntimeException('This learner document requires a linked parent account.');
        $studentIds = $this->linkedLearners($parentUserId, $options);
        if (!$studentIds) throw new RuntimeException('No active learner relationship was found for this parent.');

        $selection = ['student_ids' => $studentIds];
        $context = ['user_id' => 0, 'permissions' => [], 'request_id' => 'communications'];
        $producer = \App\API\Services\documents\DocumentBatchRegistry::make($kind, $this->db);
        $resolved = $producer->resolveEntities($selection, $options, $context);
        $paths = [];
        $prints = new PrintService();
        foreach ((array) ($resolved['entities'] ?? []) as $entity) {
            if (!is_array($entity)) continue;
            $rendered = $producer->renderHtml($entity, $options, $context);
            if (!is_array($rendered) || trim((string) ($rendered['html'] ?? '')) === '') continue;
            $paths[] = $prints->generatePDFFromHtml((string) $rendered['html'], [
                'orientation' => 'portrait', 'paperSize' => 'A4',
                'filename' => $kind . '_' . bin2hex(random_bytes(8)), 'showPageNumbers' => false,
            ]);
        }
        if (!$paths) throw new RuntimeException('No eligible ' . str_replace('_', ' ', $kind) . ' documents were found for this parent.');
        return $this->pathsAndUrls($paths);
    }

    /** Use already issued, stored learner award certificates; never synthesize one in a send job. */
    private function issuedCertificates(array $endpoint, array $options): array
    {
        $parentUserId = (int) ($endpoint['recipient_id'] ?? 0);
        if ($parentUserId < 1) throw new RuntimeException('Certificates require a linked parent account.');
        $studentIds = $this->linkedLearners($parentUserId, $options);
        if (!$studentIds) throw new RuntimeException('No active learner relationship was found for this parent.');
        $marks = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT ac.pdf_path FROM " . ReadReplicaService::masterRef('student_award_certificates') . " ac
              JOIN " . ReadReplicaService::masterRef('student_awards') . " a ON a.id = ac.award_id
             WHERE a.student_id IN ({$marks}) AND a.status = 'awarded'
               AND ac.pdf_path IS NOT NULL AND ac.pdf_path <> ''
             ORDER BY a.student_id, a.issue_date DESC, ac.id DESC"
        );
        $stmt->execute($studentIds);
        $paths = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $candidate) {
            $path = (string) $candidate;
            if (!is_file($path)) continue;
            $paths[] = $path;
        }
        if (!$paths) throw new RuntimeException('No issued award certificates are available for this parent.');
        return $this->pathsAndUrls(array_values(array_unique($paths)));
    }

    private function staffPass(array $endpoint, array $options): array
    {
        $userId = (int) ($endpoint['recipient_id'] ?? 0);
        if ($userId < 1) throw new RuntimeException('A staff account is required for security pass delivery.');
        $directory = ReadReplicaService::qualifiedRef('staff_directory');
        $stmt = $this->db->prepare("SELECT staff_id FROM {$directory} WHERE user_id = ? AND user_status = 'active' AND staff_status IN ('active','on_leave') LIMIT 1");
        $stmt->execute([$userId]);
        $staffId = (int) $stmt->fetchColumn();
        if ($staffId < 1) throw new RuntimeException('No active staff record is linked to this recipient.');
        $context = ['user_id' => 0, 'permissions' => [], 'request_id' => 'communications'];
        $producer = \App\API\Services\documents\DocumentBatchRegistry::make('staff_security_passes', $this->db);
        $resolved = $producer->resolveEntities(['staff_ids' => [$staffId]], $options, $context);
        $entity = (array) (($resolved['entities'] ?? [])[0] ?? []);
        if (!$entity) throw new RuntimeException('No existing staff security pass is available for this recipient.');
        $rendered = $producer->renderHtml($entity, $options, $context);
        if (!is_array($rendered) || trim((string) ($rendered['html'] ?? '')) === '') throw new RuntimeException('The staff security pass could not be rendered.');
        $path = (new PrintService())->generatePDFFromHtml((string) $rendered['html'], [
            'orientation' => 'portrait', 'paperSize' => 'A4',
            'filename' => 'staff_security_pass_' . bin2hex(random_bytes(8)), 'showPageNumbers' => false,
        ]);
        return $this->pathsAndUrls([$path]);
    }

    /** @return list<int> */
    private function linkedLearners(int $parentUserId, array $options): array
    {
        $guardianLink = ReadReplicaService::qualifiedRef('guardian_link');
        $stmt = $this->db->prepare(
            "SELECT DISTINCT student_id FROM {$guardianLink}
              WHERE parent_user_id = ? AND student_status = 'active' AND parent_status = 'active'
              ORDER BY student_id"
        );
        $stmt->execute([$parentUserId]);
        $linked = array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
        if (($options['recipient_type'] ?? '') !== 'selected_students') return $linked;
        $targets = array_values(array_unique(array_filter(array_map('intval', (array) ($options['target_student_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
        $selected = array_values(array_intersect($linked, $targets));
        if (!$selected) throw new RuntimeException('No selected learner is actively linked to this parent; no document was sent.');
        return $selected;
    }

    /** @param list<string> $paths @return array{file_paths:list<string>,public_urls:list<string>} */
    private function pathsAndUrls(array $paths): array
    {
        $downloads = new DownloadService();
        $urls = [];
        foreach ($paths as $path) $urls[] = $downloads->generatedDownloadUrlForAbsolutePath($path, 1800);
        return ['file_paths' => array_values($paths), 'public_urls' => $urls];
    }

    /** @param list<string> $urls */
    private function linkMessage(string $strategy, array $urls): string
    {
        $label = $strategy === 'report_card' ? 'Released report cards' : 'Documents';
        return $label . ":\n" . implode("\n", array_map(static fn (string $url): string => '- ' . $url, $urls));
    }
}
