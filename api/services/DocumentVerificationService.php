<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use Throwable;

/**
 * PHP-owned document-type verification gate.
 *
 * Admission forms state what each upload is supposed to be. This service sends
 * the authorized bytes to the private Python verifier and turns its bounded
 * verdict into either a confident rejection or a clear referral to a school
 * administrator. It never writes school records and never rejects on missing
 * OCR, an unavailable Python service, or an uncertain result.
 */
final class DocumentVerificationService
{
    private const MAX_SOURCE_BYTES = 8 * 1024 * 1024;

    /** @var list<string> */
    private const VERIFIABLE_EXTENSIONS = [
        'pdf', 'docx', 'doc', 'xls', 'xlsx', 'ods',
        'csv', 'tsv', 'txt', 'md', 'jpg', 'jpeg', 'png', 'webp',
    ];

    private PythonDocumentBridge $bridge;

    public function __construct(?PythonDocumentBridge $bridge = null)
    {
        $this->bridge = $bridge ?? new PythonDocumentBridge();
    }

    /**
     * @return array<string,mixed>
     */
    public function verifyFile(
        string $absolutePath,
        string $expectedType,
        string $originalName = ''
    ): array {
        $expectedType = strtolower(trim(str_replace([' ', '-'], '_', $expectedType)));
        $label = $this->label($expectedType);

        if ($expectedType === '') {
            return $this->descriptor('', $label, 'not_applicable', 0.0, [
                'No document type was requested by the upload form.',
            ]);
        }

        $size = is_file($absolutePath) ? filesize($absolutePath) : false;
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($size === false || $size <= 0) {
            return $this->descriptor($expectedType, $label, 'needs_review', 0.0, [
                'The uploaded file could not be read for automatic verification.',
            ]);
        }
        if ($size > self::MAX_SOURCE_BYTES) {
            return $this->descriptor($expectedType, $label, 'needs_review', 0.0, [
                'The file exceeds the 8 MB automatic verification limit.',
            ]);
        }
        if (!in_array($extension, self::VERIFIABLE_EXTENSIONS, true)) {
            return $this->descriptor($expectedType, $label, 'needs_review', 0.0, [
                'This file format has no safe automatic verification path.',
            ]);
        }

        $content = (string) file_get_contents($absolutePath);
        if ($content === '') {
            return $this->descriptor($expectedType, $label, 'needs_review', 0.0, [
                'The uploaded file is empty.',
            ]);
        }

        if (!$this->bridge->available()) {
            return $this->descriptor($expectedType, $label, 'needs_review', 0.0, [
                'Automatic verification is unavailable; refer this upload to a school administrator.',
            ]);
        }

        try {
            $raw = $this->bridge->verifyDocument([
                'filename' => basename($originalName),
                'expected_type' => $expectedType,
                'profile' => $this->profileFor($expectedType),
                'content_base64' => base64_encode($content),
            ]);
        } catch (Throwable $error) {
            FileLogger::write('document_verification', [
                'type' => 'document_verify_unavailable',
                'expected_type' => $expectedType,
                'filename' => basename($originalName),
                'error_class' => $error::class,
            ]);
            return $this->descriptor($expectedType, $label, 'needs_review', 0.0, [
                'Automatic verification could not read this file; refer it to a school administrator.',
            ]);
        }

        $decision = strtolower(trim((string) ($raw['decision'] ?? 'needs_review')));
        if (!in_array($decision, ['match', 'mismatch', 'needs_review', 'not_applicable'], true)) {
            $decision = 'needs_review';
        }
        $verification = $this->descriptor(
            (string) ($raw['expected_type'] ?? $expectedType),
            (string) ($raw['expected_label'] ?? $label),
            $decision,
            (float) ($raw['confidence'] ?? 0),
            is_array($raw['reasons'] ?? null) ? $raw['reasons'] : []
        );
        $verification['detected_type'] = (string) ($raw['detected_type'] ?? 'other');
        $verification['detected_label'] = (string) ($raw['detected_label'] ?? 'Unidentified document');
        $verification['identity_subtype'] = (string) ($raw['identity_subtype'] ?? '');
        $verification['auto_reject'] = $decision === 'mismatch' && !empty($raw['auto_reject']);

        FileLogger::write('document_verification', [
            'type' => 'document_verify_completed',
            'expected_type' => $verification['expected_type'],
            'detected_type' => $verification['detected_type'],
            'decision' => $decision,
            'confidence' => $verification['confidence'],
            'filename' => basename($originalName),
            'bytes' => $size,
        ]);

        return $verification;
    }

    /** A mismatch is rejected only when the verifier is confident. */
    public function shouldReject(array $verification): bool
    {
        return ($verification['decision'] ?? '') === 'mismatch'
            && !empty($verification['auto_reject'])
            && (float) ($verification['confidence'] ?? 0) >= 0.8;
    }

    /** Short, safe text for the existing admission_documents.notes column. */
    public function note(array $verification): string
    {
        $decision = (string) ($verification['decision'] ?? 'needs_review');
        $reason = $this->firstReason($verification);

        $message = match ($decision) {
            'mismatch' => 'AI document check: rejected - ' . $reason,
            'match' => 'AI document check: type matched (confidence '
                . number_format((float) ($verification['confidence'] ?? 0), 2) . '). ' . $reason,
            default => 'AI document check: needs school administrator review - ' . $reason,
        };

        return substr(trim($message), 0, 500);
    }

    public function rejectionMessage(array $verification): string
    {
        $label = (string) ($verification['expected_label'] ?? 'the requested document');
        $message = 'The uploaded file does not appear to be a ' . $label
            . '. ' . $this->firstReason($verification)
            . ' Please upload the correct document.';

        return substr(trim($message), 0, 500);
    }

    /** @param array<int,mixed> $reasons @return array<string,mixed> */
    private function descriptor(
        string $expectedType,
        string $expectedLabel,
        string $decision,
        float $confidence,
        array $reasons
    ): array {
        $clean = [];
        foreach ($reasons as $reason) {
            $text = substr(trim((string) $reason), 0, 240);
            if ($text !== '') {
                $clean[] = $text;
            }
        }

        return [
            'expected_type' => $expectedType,
            'expected_label' => $expectedLabel,
            'decision' => $decision,
            'confidence' => max(0.0, min(1.0, $confidence)),
            'reasons' => array_slice($clean, 0, 4),
            'preview_only' => true,
            'requires_staff_confirmation' => true,
        ];
    }

    private function firstReason(array $verification): string
    {
        $reasons = $verification['reasons'] ?? [];
        if (is_array($reasons) && $reasons !== []) {
            return (string) reset($reasons);
        }

        return $verification['message'] ?? 'The document type could not be confirmed.';
    }

    private function label(string $type): string
    {
        return match ($type) {
            'birth_certificate' => 'Birth Certificate',
            'parent_id' => 'Parent/Guardian ID',
            'passport_photo' => 'Passport Photograph',
            'previous_school_report' => 'Previous School Report',
            'progress_report' => 'Progress Report',
            'medical_records' => 'Health / Medical Records',
            'immunization_card' => 'Immunization Card',
            'leaving_certificate' => 'Leaving Certificate',
            'transfer_letter' => 'Transfer Letter',
            'other' => 'Other Document',
            default => ucwords(str_replace('_', ' ', $type)) ?: 'Document',
        };
    }

    private function profileFor(string $type): string
    {
        return match ($type) {
            'medical_records', 'immunization_card' => 'health',
            'parent_id' => 'staff',
            default => 'admission',
        };
    }
}
