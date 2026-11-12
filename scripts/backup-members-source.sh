#!/bin/sh
# Build one dated zip of the members source plus a database-export how-to.
# Does not read a live site, does not connect to a database, and does not
# put config.php, .env files, or storage into the zip.
set -eu
ROOT=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
DATE=$(date -u +%Y%m%d)
OUT_DIR="$ROOT/backups"
OUT="$OUT_DIR/members-source-$DATE.zip"
STAGE=$(mktemp -d)
cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

mkdir -p "$STAGE/members" "$OUT_DIR"
cp -R "$ROOT/members-fresh-20261107/members/." "$STAGE/members/"
mkdir -p "$STAGE/members"
cp "$ROOT/members-fresh-20261107/routing/members.htaccess" "$STAGE/members.htaccess"
# Drop anything that could hold a secret or a runtime file if one was created locally.
find "$STAGE" -name 'config.php' -o -name '.env' -o -name '.env.*' -o -name '*.log' | while read -r secret; do
  rm -f "$secret"
done
rm -rf "$STAGE/members/storage"

cat > "$STAGE/DATABASE_EXPORT.txt" <<'EOF'
How to export the members database

This zip does not contain the database, member rows, or passwords.

When Ken is ready, and only then:

1. Sign in to cPanel for the members site.
2. Open phpMyAdmin.
3. Click the members database in the left column.
4. Click Export.
5. Leave the method on Quick and the format on SQL.
6. Press Go and store the downloaded file somewhere private, next to this zip but not inside a public folder.
7. To put that copy back later, use phpMyAdmin Import on the same database. Import only after the site is quiet and only with Ken's go-ahead.

Do not email the SQL file. Do not commit it to git.
EOF

rm -f "$OUT"
( cd "$STAGE" && zip -r -q "$OUT" . )
echo "Wrote $OUT"
zipinfo -1 "$OUT"
