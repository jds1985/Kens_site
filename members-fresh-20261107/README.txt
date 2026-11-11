AUTHORITATIVE FRESH MEMBERS SOURCE — capture reported November 7, 2026, about 9:12 PM America/New_York.

members/ is the members application the local demo runs. It started as the 44 screened November 7, 2026 files. Independent check: each file matches source-sha256.txt. These files are not byte-identical to members-source-only: app/admin.php, app/controllers.php, app/migrate-member-ids.php, app/views/admin/index.php, app/views/admin/member.php, app/views/admin/members.php, app/views/admin/_signup_count.php, app/views/register.php, and assets/members.css. They add the administrator-only sign-up count and Member ID controls. New sign-ups receive a Member ID of QDI- plus the row number. app/migrate-member-ids.php can replace old name-based IDs when someone runs it with --apply. It does not run by itself. Every other file here is still byte-identical to the same relative path in members-source-only/members. No redactions were required. This directory does not contain config.php, a database, rows, member records, storage, or PDFs.

app/content/pledge.txt is a clearly marked placeholder for Ken's final wording. It is not the live pledge. The sign-up page reads that one file.

routing/members.htaccess is the separate verified members routing file, not one of the 44. A read-only cPanel breadcrumb for qabbalah.institute/members showed the live file is 662 bytes, mtime November 3, and this download matches that baseline (SHA-256 892055490f500709d23bf69cb440f7f35e9c786eb2648e3829a30c1c13694ba5). It is also byte-identical to members-source-only/members/.htaccess. An earlier 1,598-byte download matched the document-root Builder prefix and is not imported.

The 662-byte rules are what the local demo installs as .htaccess. They are not the public site root rules. members-source-only/ remains the earlier local baseline, including files the fresh capture did not contain (config.sample.php, app/.htaccess, and the placeholder pledge).

No live schema was captured. members-demo uses the earlier structure-only SQL as a local template, not as proof of the live database.
