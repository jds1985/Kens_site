# Checklist for a later move to HostGator

Do not run this against the live site until Ken has said to. Nothing in this file connects to HostGator, cPanel, or qabbalah.institute.

Do these steps in order.

## 1. Back up first

- Run `scripts/backup-members-source.sh` on a copy of this repository. It makes one dated zip of the members source. It does not include a live `config.php`, a database password, or member rows.
- In cPanel, export the live members database with phpMyAdmin before changing anything. Use **Export**, method **Quick**, format **SQL**, and save the file somewhere private. That file is the rollback copy of the data.
- Download the current `members` folder from the host, or note its path, so the old files can be put back.

## 2. Put the files in place

- The members app is the `members` folder inside `members-fresh-20261107`.
- On the host, that folder is the web directory for `qabbalah.institute/members`.
- Copy the PHP, the `app` folder, the `assets` folder, and `index.php`.
- Copy `routing/members.htaccess` into that folder as `.htaccess` only if Ken wants these rules. The file in this repo is the 662-byte rules already recorded for that directory. Do not replace it with a different file by accident.
- Do not upload `config.php` from a laptop into git. Keep the live config on the server.
- Create `storage/pdfs` if it is missing, and keep it outside the public file list. The `.htaccess` rules forbid `app/` and `storage/` as web addresses.
- `app/content/pledge.txt` is Ken's Pledge of Word and Name. Do not replace it unless he asks for a new wording.

## 3. Database

- The app expects the tables in `members-source-only/sql-structure-only/schema.sql`. A live database that already has those tables does not need to be recreated.
- `users.member_id` is a unique text column, up to 20 characters. New sign-ups write `QDI-` plus the row number. Existing rows are not rewritten by the app.
- Optional, and only after a backup: on the server, from the members directory, run `php app/migrate-member-ids.php` with no extra flag. It prints a plan and writes nothing. Read the plan. If Ken wants those old six-letter IDs replaced, run `php app/migrate-member-ids.php --apply`. IDs he has typed by hand are left alone.
- Do the dry run on a copy of the database first if there is any doubt.

## 4. Mail

- Leave `mail.driver` as `mail` so HostGator uses PHP `mail()`. Set `mail.from` to an address on the site's domain.
- SMTP stays off until a mailbox exists. If Ken later wants SMTP, set `mail.driver` to `smtp` and fill in host, port, `ssl` or `tls`, user, and password. See `docs/MAIL_SETTINGS.md`.
- The demo's Mailpit settings are only for the local Docker demo. Do not copy host `mailpit` into the live config.

## 5. PayPal and the pledge

- PayPal names stay empty until Ken chooses a payee. Empty means the pages say the link is not set up. Do not invent a PayPal name.
- The payment steps are unchanged. Members are asked to pay from a PayPal Business account under an alias. See `docs/PAYPAL_PRIVACY_OPTIONS.md`.
- Degree fee amounts stored in the database must match the Curriculum section at https://qabbalah.institute/#curriculum . Ken edits that section. Member pages link there and do not print the dollar amounts.

## 6. Smoke test, still before telling members

Use a brand-new test account that is not a real member. Then check:

- The sign-up page shows the pledge, and the box plus a typed name are required.
- A confirmation email arrives, the link works once, and a second click says it has expired or was used.
- Forgot-password sends one link, the new password works, and the link does not work twice.
- The test member can say they paid Part A. The admin page shows the sign-up count and the claim. Confirming it opens the lesson. A draft lesson stays hidden.
- Part B stays unconfirmed until a Facilitator or the admin confirms it. The next Degree stays locked until then.
- The admin can save a Member ID, and a duplicate ID is refused.
- A normal member who opens `/admin` is refused.

## 7. Rollback

- If the new files misbehave, put the previous `members` folder back.
- If the data misbehaves, import the SQL export from step 1 into the same database. Do that only with Ken's go-ahead, and only after the site is quiet.
- Do not run the ID migration again after a rollback unless the plan is read a second time.
