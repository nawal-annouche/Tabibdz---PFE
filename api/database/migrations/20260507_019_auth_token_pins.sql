ALTER TABLE email_verification_tokens
  ADD COLUMN IF NOT EXISTS pin_hash CHAR(64) NULL AFTER token_hash;

ALTER TABLE password_reset_tokens
  ADD COLUMN IF NOT EXISTS pin_hash CHAR(64) NULL AFTER token_hash;

