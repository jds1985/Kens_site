-- Upgrade a piece-2 database to piece 3. Run ONCE in phpMyAdmin > SQL (a new install does not need this: schema.sql has it all).
-- Back up first: phpMyAdmin > Export. Existing members, payments and lessons are kept.
SET NAMES utf8mb4;
ALTER TABLE progress
  ADD COLUMN part_b_facilitator_id INT UNSIGNED NULL AFTER part_b_status,   -- who the member pays Part B to (NULL = Ken)
  ADD COLUMN part_b_chosen_at DATETIME NULL AFTER part_b_facilitator_id,
  ADD COLUMN part_b_claimed_at DATETIME NULL AFTER part_b_chosen_at,        -- member says "I paid Part B"
  ADD COLUMN part_b_ref VARCHAR(64) NULL AFTER part_b_claimed_at,
  ADD KEY ix_fac (part_b_facilitator_id, part_b_status);
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
