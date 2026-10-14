ALTER TABLE appointment_documents
  ADD COLUMN IF NOT EXISTS document_type VARCHAR(40) NOT NULL DEFAULT 'patient_document' AFTER file_size,
  ADD COLUMN IF NOT EXISTS uploaded_by_role ENUM('patient','medecin') NOT NULL DEFAULT 'patient' AFTER document_type;

UPDATE appointment_documents
SET document_type = COALESCE(NULLIF(document_type, ''), 'patient_document'),
    uploaded_by_role = COALESCE(NULLIF(uploaded_by_role, ''), 'patient')
WHERE 1 = 1;
