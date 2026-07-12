-- Store admission/student documents under uploads/students/documents/{application_id}
-- instead of the generic uploads/documents/{application_id} path.

UPDATE media_files mf
SET mf.context = 'students/documents'
WHERE mf.context = 'documents'
  AND EXISTS (
      SELECT 1
        FROM admission_documents ad
       WHERE ad.application_id = mf.entity_id
  );

UPDATE admission_documents
SET document_path = REPLACE(document_path, '/uploads/documents/', '/uploads/students/documents/')
WHERE document_path LIKE '/uploads/documents/%';
