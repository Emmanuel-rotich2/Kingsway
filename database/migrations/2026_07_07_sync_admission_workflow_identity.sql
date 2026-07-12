-- Sync denormalized admission workflow identity fields from the canonical application row.
-- This repairs workflow_instances.data_json rows that show another applicant's details.

UPDATE workflow_instances wi
JOIN admission_applications aa
  ON wi.reference_type = 'admission_application'
 AND wi.reference_id = aa.id
SET wi.data_json = JSON_SET(
    COALESCE(NULLIF(wi.data_json, ''), '{}'),
    '$.application_no', aa.application_no,
    '$.applicant_name', aa.applicant_name,
    '$.grade', aa.grade_applying_for
)
WHERE wi.reference_type = 'admission_application';
