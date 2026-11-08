-- Invisible Society members area - sign-up upgrade (legal first/last name + member ID).
-- Only for a site that is ALREADY installed. A new install does not need it (schema.sql has these columns).
-- Run ONCE in phpMyAdmin > your database > Import (take an Export backup first).
SET NAMES utf8mb4;
ALTER TABLE users
  ADD COLUMN member_id VARCHAR(20) NULL AFTER id,
  ADD COLUMN first_name VARCHAR(60) NOT NULL DEFAULT '' AFTER member_id,
  ADD COLUMN last_name VARCHAR(60) NOT NULL DEFAULT '' AFTER first_name,
  ADD UNIQUE KEY uq_member_id (member_id);
