-- Demo-only session store. Not part of the imported members schema.
-- PHP file sessions are not durable on a stateless Vercel container.
CREATE TABLE IF NOT EXISTS demo_sessions (
  id VARCHAR(128) NOT NULL,
  payload MEDIUMBLOB NOT NULL,
  touched INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY ix_touched (touched)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
