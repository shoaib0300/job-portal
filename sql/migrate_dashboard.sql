-- Additive only. Do not DROP applications or other live tables.
-- Safe to re-run: PHP App::ensureDashboardSchema() checks columns first.

ALTER TABLE applications
  ADD COLUMN location VARCHAR(160) NOT NULL DEFAULT '' AFTER role;

ALTER TABLE applications
  ADD COLUMN resume_version_id INT UNSIGNED NULL AFTER link;

ALTER TABLE applications
  ADD COLUMN cover_letter_id INT UNSIGNED NULL AFTER resume_version_id;

INSERT INTO settings (`key`, `value`) VALUES
  ('ui_density', 'comfortable'),
  ('sidebar_mode', 'expanded'),
  ('ui_mode', 'warm'),
  ('dashboard_palette', 'light'),
  ('name_size', 'md'),
  ('section_spacing', 'md')
ON DUPLICATE KEY UPDATE `key` = `key`;
