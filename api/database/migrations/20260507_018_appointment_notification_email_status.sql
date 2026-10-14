ALTER TABLE appointment_notification_events
  ADD COLUMN IF NOT EXISTS email_sent_at DATETIME NULL AFTER notification_id,
  ADD COLUMN IF NOT EXISTS email_error TEXT NULL AFTER email_sent_at;

