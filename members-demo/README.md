# Isolated members demo

Local container for the members PHP in `members-fresh-20261107/`. That tree started as the November 7, 2026 capture. The admin pages now include a sign-up count that the server shows only to role `admin`. `members-source-only/` stays the earlier baseline, including the placeholder pledge and `config.sample.php`, which the fresh capture did not contain. This is not a change to qabbalah.institute, HostGator, or the Vercel static preview, and it is not a hosted demo.

The image copies `members-fresh-20261107/members`, including the admin sign-up count, then installs `members-fresh-20261107/routing/members.htaccess` as `.htaccess`. That 662-byte file is the verified live members rules (cPanel breadcrumb `qabbalah.institute/members`, mtime November 3, SHA-256 `892055490f500709d23bf69cb440f7f35e9c786eb2648e3829a30c1c13694ba5`). The earlier 1,598-byte Builder capture is not in the repository and is not used. Writable state is only the `storage` volume. No `pledge.txt` is added.

## What the application requires

Read from the fresh PHP, which matches the earlier local code:

- PHP with PDO. `db.php` opens only a `mysql:` DSN (`utf8mb4`, native prepared statements, `SET time_zone = '+00:00'`).
- MySQL 5.7+ or MariaDB 10.3+, InnoDB. The SQL uses `ENUM`, `AUTO_INCREMENT`, `MEDIUMTEXT`, `ON DUPLICATE KEY UPDATE`, `INSERT IGNORE`, and `VALUES()` in upserts. No live schema was captured. The demo loads the earlier structure-only `members-source-only/sql-structure-only/schema.sql` as a local template. The upgrade scripts overlap it and are not applied. This does not prove the live database matches that file.
- The verified 662-byte Apache rules: `DirectoryIndex index.php`, forbid `app/` and `storage/`, HTTPS except a host beginning with `localhost`, send other non-file URLs to `index.php`, and deny `config.php`, `config.sample.php`, and `.htaccess`.
- PHP file sessions today (`session_start()`, cookie name `ismem`, HttpOnly, SameSite=Lax, Secure when the base URL is https, 4-hour idle timeout). The session holds the user id, a hash of the password hash, and the CSRF token. Rate limits are rows in MySQL, not session files.
- Lesson PDFs under `members/storage/pdfs`, served only by `c_lesson_pdf()` after `lesson_access()`. The directory is not a public URL.
- Passwords are `password_hash()` / `password_verify()`. Tokens in the database are SHA-256 hashes.
- Mail drivers are `mail`, `smtp`, or `log`. PayPal is an outbound link built from `paypal.me_name` or `paypal.link_template`. Empty values make the app show “not set up yet” and send nothing.

## Least-change runtime

Keep this PHP. Do not replace it with a static mock or a Node app.

| Concern | Local demo now | Later hosted demo |
| --- | --- | --- |
| PHP | `php:8.3-apache` in `members-demo/php/Dockerfile` | OCI image via `Dockerfile.vercel`. Current Vercel docs: container images are supported for PHP, must listen on port 80 or `$PORT`, and are stateless. https://vercel.com/docs/functions/container-images |
| Database | MariaDB 10.11 on the compose network only | A durable MySQL 5.7+ or MariaDB 10.3+ server reachable from the container. Not container disk. |
| Sessions | `demo_sessions` table, registered by `auto_prepend_file` so the imported PHP stays unchanged | Same MySQL table. File sessions are lost on scale-to-zero and are not shared across instances. |
| PDFs | Docker volume on `storage/` | Durable private object storage. Container disk is not durable. |
| Mail | Existing `log` driver, `mail()` disabled | Keep `log` or a no-op. No SMTP account. |
| PayPal | Names forced empty | Same, until someone deliberately configures a payee outside this demo. |
| Public homepage | Untouched. Members link stays `https://qabbalah.institute/members/` | Do not point the static preview at this app. A hosted demo still needs the durable database and file store below. |

There is no `Dockerfile.vercel` in this repository. Vercel treats that filename as a function image and would route the static preview into it. This local container is not that image. A later hosted copy must be a separate service from `preview/` and is not claimed here.

### SQLite is not a full demo

`db.php` cannot open SQLite. The schema is not SQLite SQL. A per-request SQLite database, in memory or on the container filesystem, would also disappear between instances and after scale-to-zero. That would be a disposable stub, not this application.

### External resources the parent still has to provide

This run did not create accounts, prices, or Vercel resources.

1. One durable MySQL 5.7+ or MariaDB 10.3+ database for the demo, empty of live rows.
2. Private durable storage for PDFs (the imported code expects a directory path).
3. When a hosted copy is wanted: a PHP container from this fresh source, listening on `$PORT`, with the verified 662-byte members rules. Not done in this run. Local Docker is the only runtime exercised.
4. Nothing else. No mail provider and no payment provider.

Vercel storage docs retrieved for this run list Neon (Postgres), Upstash (Redis), Supabase, and Blob. They do not document a MySQL or MariaDB product. Neon is not a substitute: the DSN and SQL are MySQL. Plan, cost, and marketplace support were not checked. The parent chooses the MySQL host after those facts are known.

## Off by default

`members-demo/php/demo-guard.php` runs before the application on every web request. The container exits unless all of these are true:

- `MEMBERS_DEMO=1`
- `base_url` is `http://localhost` with an optional port
- database host is `db` and the name is `members_demo`
- `app_secret` is the synthetic demo string in `config/demo-config.php`
- mail driver is `log` and SMTP host, user, and password are empty
- PayPal name and link template are empty
- `admin_notify_email` is empty
- `degrees_coming_soon` is false and `debug` is false

`php.ini` disables `mail`, `exec`, and outbound socket helpers. Mail is simulated only by the existing `log` driver, which appends to `storage/mail.log` inside the demo volume. PayPal URLs are simulated as absent: the config names are empty and the synthetic facilitator has no PayPal recipient, so the pages do not link out. The earlier local `config.sample.php` is not part of the fresh 44 and is not in the image.

Synthetic accounts, all `@example.test`, passwords only for this local process:

| Role | Email | Password |
| --- | --- | --- |
| Admin | demo-admin@example.test | demo-admin-pass |
| Member | demo-member@example.test | demo-member-pass |
| Facilitator | demo-facilitator@example.test | demo-facilitator-pass |

`demo-pending@example.test` is a fourth synthetic row. The email was never confirmed, and the account was dated 30 days before the container started, so sign-in is refused. It exists so the admin count can show “email not confirmed yet” separately from “signed up in the last 7 days.” Do not reuse these on any host that is not localhost.

The four seeded Member IDs are the old name-based examples (`DEMADM-0001` and so on). A new sign-up receives `QDI-` plus the row number, which does not contain the person's name. Those seeded IDs stay as they are until someone runs `php app/migrate-member-ids.php --apply` from the command line. Without `--apply` the script only prints a plan. The web server forbids that file.

## Run the test

Docker is required. From the repository root:

```bash
sudo docker compose -f members-demo/docker-compose.yml up -d --build --wait
python3 members-demo/test_demo.py
sudo docker compose -f members-demo/docker-compose.yml down -v
```

The test signs in as member, admin, and facilitator; confirms Part A; reads the synthetic lesson and PDF; chooses the synthetic facilitator; confirms Part B; checks the next degree unlocks; restarts the web container and checks the session is still in MariaDB; checks the mail log stayed local; and checks the public homepage hash. It checks that an admin sees total sign-ups 4, with 3 email-confirmed, 1 not confirmed, and 3 in the last 7 days, including on a member search that matches nobody. It then signs up one more synthetic member and checks those numbers become 5, 4, 1, and 4. A member, a facilitator, a new member, and a logged-out visitor do not receive the count. Direct requests to `/admin`, `/admin/members`, `/admin/signups`, and `POST /admin` are refused and do not include it. It checks that a new sign-up receives a `QDI-` ID, that an admin can save `LODGE-3`, and that a duplicate or a bad format is refused. A member and a logged-out visitor cannot set an ID, including by a direct POST. The migration script's dry run writes nothing. `--apply` replaces only the old name-based IDs and leaves `LODGE-3` in place. `app/`, `storage/`, and `config.sample.php` must return 403. A login POST without the CSRF field must return 403.
