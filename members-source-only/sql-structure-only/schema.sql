-- Invisible Society members area - schema (pieces 1 and 2) (MySQL 5.7+/MariaDB 10.3+, InnoDB, utf8mb4)
-- Import this first, then seed.sql, using phpMyAdmin in cPanel.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  member_id VARCHAR(20) NULL,
  first_name VARCHAR(60) NOT NULL DEFAULT '',
  last_name VARCHAR(60) NOT NULL DEFAULT '',
  pseudonym VARCHAR(40) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email_verified_at DATETIME NULL,
  pledge_name VARCHAR(120) NOT NULL,
  pledge_version VARCHAR(20) NOT NULL,
  pledged_at DATETIME NOT NULL,
  role ENUM('member','admin') NOT NULL DEFAULT 'member',
  current_degree TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  pw_changed_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_id (member_id),
  UNIQUE KEY uq_pseudonym (pseudonym),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  kind ENUM('verify','reset') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token_hash),
  KEY ix_user_kind (user_id, kind),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  k CHAR(64) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS degrees (
  num TINYINT UNSIGNED NOT NULL,
  title VARCHAR(120) NULL,                 -- Ken has not given titles yet
  summary VARCHAR(500) NULL,               -- short public description (shown to everyone)
  status ENUM('planned','available','released') NOT NULL DEFAULT 'planned',
  part_a_fee DECIMAL(12,2) NOT NULL DEFAULT 0,   -- dues to Invisible Society (= degree number in dollars)
  part_b_fee DECIMAL(12,2) NOT NULL DEFAULT 0,   -- tuition to a Member Facilitator (Fibonacci)
  wait_days SMALLINT UNSIGNED NOT NULL DEFAULT 30, -- wait before the next Degree (used in piece 2)
  intro TEXT NULL,                         -- intro text (plain text): public for the Naught Degree, otherwise only for members who opened the Degree
  is_public TINYINT(1) NOT NULL DEFAULT 0, -- 1 = anyone may read it (Naught Degree)
  PRIMARY KEY (num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- piece 2 ----
-- One row per member per Degree (created when the member says they paid, or when Ken marks it paid).
CREATE TABLE IF NOT EXISTS progress (
  user_id INT UNSIGNED NOT NULL,
  degree_num TINYINT UNSIGNED NOT NULL,
  part_a_status ENUM('none','claimed','paid') NOT NULL DEFAULT 'none',  -- claimed = member says "I paid", waiting for Ken
  part_a_amount DECIMAL(12,2) NOT NULL DEFAULT 0,                       -- the fee at the time (so later fee changes do not rewrite history)
  part_a_method ENUM('paypal','manual') NULL,
  part_a_ref VARCHAR(64) NULL,                                          -- member's PayPal receipt / transaction number (optional)
  part_a_claimed_at DATETIME NULL,
  opened_at DATETIME NULL,                                              -- when Part A was confirmed = the date the Degree opened
  part_a_by INT UNSIGNED NULL,                                          -- admin who confirmed
  part_b_status ENUM('none','confirmed') NOT NULL DEFAULT 'none',
  part_b_facilitator_id INT UNSIGNED NULL,                              -- piece 3: who the member pays Part B to (NULL = Ken)
  part_b_chosen_at DATETIME NULL,
  part_b_claimed_at DATETIME NULL,                                      -- piece 3: member says "I paid Part B"
  part_b_ref VARCHAR(64) NULL,
  part_b_amount DECIMAL(12,2) NULL,
  part_b_confirmed_at DATETIME NULL,
  part_b_by INT UNSIGNED NULL,
  PRIMARY KEY (user_id, degree_num),
  KEY ix_status (part_a_status, part_b_status),
  KEY ix_fac (part_b_facilitator_id, part_b_status),
  CONSTRAINT fk_prog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_degree FOREIGN KEY (degree_num) REFERENCES degrees(num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lessons: plain text, an optional link, an optional PDF (stored outside the web, served only to members who may read it).
CREATE TABLE IF NOT EXISTS lessons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  degree_num TINYINT UNSIGNED NOT NULL,
  position SMALLINT NOT NULL DEFAULT 10,           -- sort order inside the Degree (small numbers first)
  title VARCHAR(160) NOT NULL,
  body MEDIUMTEXT NULL,
  link_url VARCHAR(500) NULL,
  link_label VARCHAR(160) NULL,
  pdf_file CHAR(36) NULL,                          -- random file name in members/storage/pdfs
  pdf_name VARCHAR(120) NULL,                      -- name shown to members
  pdf_size INT UNSIGNED NULL,
  unlock_after_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- opens this many days after the member's Part A was confirmed
  published TINYINT(1) NOT NULL DEFAULT 0,         -- 0 = draft, members cannot see it
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_degree (degree_num, position),
  CONSTRAINT fk_lessons_degree FOREIGN KEY (degree_num) REFERENCES degrees(num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What the admin did (and what members claimed), for Ken's records.
CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  at DATETIME NOT NULL,
  actor_id INT UNSIGNED NULL,
  actor_name VARCHAR(40) NOT NULL,
  action VARCHAR(40) NOT NULL,
  user_id INT UNSIGNED NULL,
  degree_num TINYINT UNSIGNED NULL,
  detail VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY ix_at (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- piece 3 ----
-- Member Facilitators: one row per member who asked to be one. Ken approves. The site never handles Part B money:
-- it only shows HOW to pay the Facilitator, and records "member says paid" and "Facilitator confirms".
CREATE TABLE IF NOT EXISTS facilitators (
  user_id INT UNSIGNED NOT NULL,
  status ENUM('applied','approved','paused','removed') NOT NULL DEFAULT 'applied',
  pay_method ENUM('paypal','other') NOT NULL DEFAULT 'paypal',
  paypal_me VARCHAR(60) NULL,                 -- the part after paypal.me/ in the Facilitator's own link
  pay_note VARCHAR(500) NULL,                 -- "other method" instructions, or an extra note
  intro VARCHAR(500) NULL,                    -- a few words members see when choosing
  applied_at DATETIME NOT NULL,
  approved_at DATETIME NULL,
  approved_by INT UNSIGNED NULL,
  PRIMARY KEY (user_id),
  KEY ix_status (status),
  CONSTRAINT fk_fac_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
