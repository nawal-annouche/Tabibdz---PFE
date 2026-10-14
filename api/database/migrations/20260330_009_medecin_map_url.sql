ALTER TABLE medecin_profiles
  ADD COLUMN IF NOT EXISTS map_url VARCHAR(1024) NULL AFTER hopitaux_text;
