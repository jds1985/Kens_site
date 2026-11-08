-- Upgrade a piece-1 database to piece 2. Run ONCE in phpMyAdmin > SQL (a new install does not need this: schema.sql has it all).
-- Back up first: phpMyAdmin > Export.
SET NAMES utf8mb4;
ALTER TABLE degrees ADD COLUMN summary VARCHAR(500) NULL AFTER title;

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
  part_b_amount DECIMAL(12,2) NULL,
  part_b_confirmed_at DATETIME NULL,
  part_b_by INT UNSIGNED NULL,
  PRIMARY KEY (user_id, degree_num),
  KEY ix_status (part_a_status, part_b_status),
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
