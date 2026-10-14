ALTER TABLE medecin_profiles
  ADD COLUMN IF NOT EXISTS approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER secteur,
  ADD COLUMN IF NOT EXISTS submitted_at DATETIME NULL AFTER approval_status,
  ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER submitted_at,
  ADD COLUMN IF NOT EXISTS review_note VARCHAR(255) NULL AFTER approved_at;

UPDATE medecin_profiles mp
INNER JOIN users u ON u.id = mp.user_id
SET
  mp.approval_status = 'approved',
  mp.submitted_at = COALESCE(mp.submitted_at, u.created_at, NOW()),
  mp.approved_at = COALESCE(mp.approved_at, NOW()),
  mp.review_note = NULL
WHERE u.role = 'medecin'
  AND (mp.approval_status IS NULL OR mp.approval_status = 'pending');
