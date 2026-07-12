-- Keep admission document categories aligned with the admissions workspace upload checklist.
ALTER TABLE admission_documents
    MODIFY document_type ENUM(
        'birth_certificate',
        'immunization_card',
        'progress_report',
        'previous_school_report',
        'medical_records',
        'passport_photo',
        'parent_id',
        'nemis_upi',
        'leaving_certificate',
        'transfer_letter',
        'behavior_report',
        'other'
    ) NOT NULL;

-- Convert legacy media-id document_path values to readable site paths.
UPDATE admission_documents ad
JOIN media_files mf
  ON ad.document_path REGEXP '^[0-9]+$'
 AND mf.id = CAST(ad.document_path AS UNSIGNED)
SET ad.document_path = CONCAT(
    '/uploads/',
    mf.context,
    IF(mf.entity_id IS NULL OR mf.entity_id = '', '', CONCAT('/', mf.entity_id)),
    IF(mf.album_id IS NULL OR mf.album_id = '', '', CONCAT('/album_', mf.album_id)),
    '/',
    mf.filename
);
