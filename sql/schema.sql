SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS search_history;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS cover_letters;
DROP TABLE IF EXISTS resume_versions;
DROP TABLE IF EXISTS experience_entries;
DROP TABLE IF EXISTS resume_sections;
DROP TABLE IF EXISTS resume_profile;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE settings (
  `key` VARCHAR(64) NOT NULL PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resume_profile (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  title VARCHAR(200) NOT NULL DEFAULT '',
  email VARCHAR(160) NOT NULL DEFAULT '',
  phone VARCHAR(80) NOT NULL DEFAULT '',
  location VARCHAR(160) NOT NULL DEFAULT '',
  gender VARCHAR(40) NOT NULL DEFAULT '',
  date_of_birth DATE NULL,
  country VARCHAR(120) NOT NULL DEFAULT '',
  nationality VARCHAR(120) NOT NULL DEFAULT '',
  photo_path VARCHAR(255) NOT NULL DEFAULT '',
  show_photo TINYINT(1) NOT NULL DEFAULT 1,
  links JSON NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resume_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  section_key VARCHAR(64) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_section_key (section_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE experience_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  company VARCHAR(200) NOT NULL DEFAULT '',
  position VARCHAR(200) NOT NULL DEFAULT '',
  location VARCHAR(160) NOT NULL DEFAULT '',
  start_date VARCHAR(40) NOT NULL DEFAULT '',
  end_date VARCHAR(40) NOT NULL DEFAULT '',
  bullets MEDIUMTEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resume_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  company VARCHAR(160) NOT NULL DEFAULT '',
  is_base TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  profile_title VARCHAR(200) NOT NULL DEFAULT '',
  snapshot MEDIUMTEXT NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_base (is_base),
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cover_letters (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  company VARCHAR(160) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_base TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  company VARCHAR(160) NOT NULL,
  role VARCHAR(200) NOT NULL,
  status ENUM('applied','rejected','interview','offer','custom') NOT NULL DEFAULT 'applied',
  applied_date DATE NULL,
  notes TEXT NULL,
  jd_snippet TEXT NULL,
  link VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_applied_date (applied_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE search_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  company VARCHAR(160) NOT NULL DEFAULT '',
  role VARCHAR(200) NOT NULL DEFAULT '',
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, `value`) VALUES
  ('accent_color', '#4E6351'),
  ('theme', 'sage'),
  ('font_family', 'candara'),
  ('pdf_mode', '0'),
  ('active_company', '');

INSERT INTO resume_profile (full_name, title, email, phone, location, links) VALUES
(
  'Your Name',
  'Full Stack Developer',
  'you@example.com',
  '+1 555 0100',
  'City, Country',
  JSON_ARRAY(
    JSON_OBJECT('label', 'LinkedIn', 'url', 'https://linkedin.com/in/yourprofile'),
    JSON_OBJECT('label', 'GitHub', 'url', 'https://github.com/yourhandle')
  )
);

INSERT INTO resume_sections (section_key, title, body, sort_order, visible) VALUES
(
  'summary',
  'Summary',
  'Results-driven developer with experience building web applications, APIs, and polished user interfaces. Comfortable owning features end-to-end — from clarifying requirements to shipping and iterating.',
  10,
  1
),
(
  'experience',
  'Experience',
  "Senior Developer — Example Corp (2022–Present)\n• Led delivery of customer-facing features used by thousands of users weekly.\n• Improved page performance and reduced load times through targeted refactors.\n• Collaborated with design and product to ship accessible, maintainable UI.\n\nDeveloper — Startup Co (2019–2022)\n• Built REST APIs and admin tools in PHP and MySQL.\n• Owned bug triage and release notes for bi-weekly deployments.",
  20,
  1
),
(
  'skills',
  'Skills',
  'PHP, MySQL, JavaScript, HTML/CSS, REST APIs, Git, Docker, Linux, Agile',
  30,
  1
),
(
  'education',
  'Education',
  'B.Sc. Computer Science — University Name (2015–2019)',
  40,
  1
),
(
  'projects',
  'Projects',
  "Job Search Portal — Personal tooling to track applications, tailor resumes, and export print-ready PDFs.\nPortfolio Site — Responsive marketing site with custom CMS content.",
  50,
  1
);

INSERT INTO cover_letters (title, body, company, is_active, is_base) VALUES
(
  'Main cover letter',
  "Dear Hiring Manager,\n\nI am writing to express my interest in the open role on your team. I bring hands-on experience building reliable web applications, collaborating across product and design, and shipping features that matter to users.\n\nIn my recent work I have focused on clean PHP/MySQL backends, clear front-end interfaces, and practical tooling that keeps delivery predictable. I am especially drawn to teams that value craft, ownership, and continuous improvement.\n\nI would welcome the chance to discuss how my background fits your needs. Thank you for your time and consideration.\n\nSincerely,\nYour Name",
  '',
  1,
  1
);

INSERT INTO applications (company, role, status, applied_date, notes, jd_snippet, link) VALUES
(
  'Example Corp',
  'Full Stack Developer',
  'applied',
  CURDATE(),
  'Submitted via careers page.',
  'Looking for PHP/MySQL developer with front-end skills.',
  'https://example.com/careers'
),
(
  'Other Co',
  'Backend Engineer',
  'rejected',
  DATE_SUB(CURDATE(), INTERVAL 14 DAY),
  'Rejection email received.',
  NULL,
  NULL
);

INSERT INTO search_history (company, role, note) VALUES
(
  'Example Corp',
  'Full Stack Developer',
  'Initial portal seed — placeholder tailor session.'
);
