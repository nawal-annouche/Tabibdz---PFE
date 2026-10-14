ALTER TABLE medecin_profiles
  ADD COLUMN IF NOT EXISTS experience_years INT NULL AFTER adresse,
  ADD COLUMN IF NOT EXISTS agrement_no VARCHAR(120) NULL AFTER experience_years,
  ADD COLUMN IF NOT EXISTS bio TEXT NULL AFTER agrement_no,
  ADD COLUMN IF NOT EXISTS formations_text TEXT NULL AFTER bio,
  ADD COLUMN IF NOT EXISTS hopitaux_text TEXT NULL AFTER formations_text,
  ADD COLUMN IF NOT EXISTS mutuelle_private TINYINT(1) NOT NULL DEFAULT 0 AFTER hopitaux_text;
