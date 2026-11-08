-- Synthetic catalog only. No live member, payment, or lesson text.
SET NAMES utf8mb4;

INSERT INTO degrees (num, title, summary, status, part_a_fee, part_b_fee, wait_days, intro, is_public) VALUES
(0, 'Demo Naught', 'Synthetic public degree for the isolated demo.', 'released', 0, 0, 0, 'Synthetic demo text. This is not a live lesson.', 1),
(1, 'Demo Degree 1', 'Synthetic degree used to exercise Part A, lessons, and Part B.', 'released', 1.00, 1.00, 0, 'Synthetic demo text. This is not a live lesson.', 0),
(2, 'Demo Degree 2', 'Synthetic next degree. Opens after Part B of Demo Degree 1.', 'released', 2.00, 1.00, 0, 'Synthetic demo text. This is not a live lesson.', 0),
(3, 'Demo Degree 3', 'Synthetic unreleased degree. Members must not see its lesson.', 'planned', 3.00, 2.00, 30, 'Synthetic demo text. This is not a live lesson.', 0);

INSERT INTO lessons (id, degree_num, position, title, body, link_url, link_label, pdf_file, pdf_name, pdf_size, unlock_after_days, published, created_at, updated_at) VALUES
(1, 0, 10, 'Demo public note', 'Synthetic public demo note. Not from the live site.', NULL, NULL, NULL, NULL, NULL, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
(2, 1, 10, 'Demo lesson', 'Synthetic demo lesson. Not from the live site.', NULL, NULL, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf', 'demo-lesson.pdf', 64, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
(3, 1, 20, 'Demo draft', 'Synthetic draft. Members must not see this.', NULL, NULL, NULL, NULL, NULL, 0, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP());
