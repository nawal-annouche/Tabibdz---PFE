ALTER TABLE medecin_profiles
  ADD COLUMN IF NOT EXISTS langues VARCHAR(255) NULL AFTER adresse,
  ADD COLUMN IF NOT EXISTS secteur ENUM('public','prive','mixte') NOT NULL DEFAULT 'prive' AFTER langues;

UPDATE medecin_profiles
SET langues = COALESCE(NULLIF(langues, ''), 'Arabe,Français')
WHERE langues IS NULL OR langues = '';

UPDATE medecin_profiles
SET secteur = CASE
  WHEN secteur IS NULL OR secteur = '' THEN 'prive'
  ELSE secteur
END;
