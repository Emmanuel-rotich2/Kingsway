<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use PDO;
use RuntimeException;

/**
 * Registry of executable bulk-document producers.
 *
 * A kind is only listed in PRODUCERS once its producer is implemented and
 * tested. PLANNED lists the remaining printables the platform is designed to
 * carry; it is UI metadata only and never executable, so no half-built kind can
 * be queued.
 */
final class DocumentBatchRegistry
{
    /** @var array<string, class-string<DocumentBatchProducer>> */
    private const PRODUCERS = [
        'student_id_cards' => StudentIdCardsProducer::class,
        'report_cards' => ReportCardsProducer::class,
        'portfolios' => PortfoliosProducer::class,
        'staff_security_passes' => StaffSecurityPassesProducer::class,
        'payslips' => PayslipsProducer::class,
        'fee_statements' => FeeStatementsProducer::class,
        'certificates' => CertificatesProducer::class,
        'transcripts' => TranscriptsProducer::class,
    ];

    /** Kinds the pipeline is designed to carry but that are not yet executable. */
    private const PLANNED = [
    ];

    public static function has(string $kind): bool
    {
        return isset(self::PRODUCERS[$kind]);
    }

    /**
     * @return class-string<DocumentBatchProducer>
     */
    public static function resolve(string $kind): string
    {
        if (!isset(self::PRODUCERS[$kind])) {
            throw new RuntimeException('Unknown or unavailable document batch kind.');
        }
        return self::PRODUCERS[$kind];
    }

    public static function make(string $kind, PDO $db): DocumentBatchProducer
    {
        $class = self::resolve($kind);
        return new $class($db);
    }

    /**
     * Executable kinds with labels, for the UI catalogue.
     *
     * @return array<int, array{kind: string, label: string, available: bool}>
     */
    public static function catalogue(): array
    {
        $out = [];
        foreach (array_keys(self::PRODUCERS) as $kind) {
            $class = self::PRODUCERS[$kind];
            $out[] = [
                'kind' => $kind,
                'label' => $class::label(),
                'available' => true,
            ];
        }
        foreach (self::PLANNED as $kind => $label) {
            $out[] = ['kind' => $kind, 'label' => $label, 'available' => false];
        }
        return $out;
    }

    /** @return list<string> */
    public static function plannedKinds(): array
    {
        return array_keys(self::PLANNED);
    }
}
