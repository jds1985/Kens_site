# Kens site — public source preservation

This repository preserves reviewed public source for later study and a possible static preview. It is not a complete copy of the live sites, and it is not a deployment.

Repository visibility, licensing, DNS, and HostGator were left unchanged. No license is added for Ken's site content. Third-party font notices shipped with the collected Montserrat and Font Awesome files are in `LICENSES/` and apply only to those files.

The Famous AI redesign was only an idea. Its snapshots, images, rendered page, and compiled assets are not in this repository.

## What is here

| Directory | What it is |
| --- | --- |
| `public-current/qabbalah.institute/index.html` | Authoritative current standalone homepage. 83,168 bytes. SHA-256 `d130153a74f4a2812a2f6f36b6ded42d50028d383d42c641726b741f8122756d`. Provenance copy; do not edit it in place. |
| `preview/index.html` | Byte-identical copy of that homepage for a later static preview. No PHP and no historical pages. |
| `public-snapshots/` | Older saved public pages for Invisible Society, Qabbalah Institute, and Vau Qoppa Sampi, plus seven public images and `scripts.js`. |
| `live-public-verified-20261107/` | November 7, 2026 public check. Fresh Invisible Society page (74,596 bytes, different from the older snapshot). Qabbalah Institute and Vau Qoppa Sampi matched their snapshots. Also holds the collected Montserrat and Font Awesome files. W3.CSS is not here. |
| `original-builder-reference/` | Original Builder home body (80,129 bytes) and separate footer (3,356 bytes) that the current homepage was built from. |
| `historical-reference/hostgator-root/` | 25 screened Builder viewer wrappers, kept as `.source.txt` references, plus the HostGator promotional banner. Not routes. |
| `members-source-only/` | Local PHP application templates and structure-only SQL. Not verified against live PHP. Not a static site. |
| `provenance/` | Integrity manifest, public-check notes, and the screening record. |

The homepage has no iframe. Its section ids are `home`, `curriculum`, `lodges`, `membership`, `members`, `hebrew`, `greek`, `copyright`, and `zerodegree`. The members link is the absolute URL `https://qabbalah.institute/members/`. Remote stylesheet, font, and image URLs were not rewritten.

## What this is not

106 of the 110 HostGator root HTML files are Website Builder viewer wrappers, not standalone pages. 100 of those have no non-script body text. Six carry partial text. This import keeps 25 of them as `.source.txt` only. The other 81 shells were withheld and were not attached. The two production homepage copies match the authoritative file above and are not duplicated in the historical folder. One navigation-review copy was not imported.

Still missing, so this is not a working clone:

- Viewer runtime files (`viewer.js`, `viewer.css`) and the generated page datasets those wrappers load.
- Local viewer assets.
- W3.CSS 4. The direct request returned HTTP 403 and was not retried.
- A fresh live PHP export compared with this local members source.
- Live database rows, configuration, uploads, and member documents. Those were excluded on purpose.
- Public routes other than the known homepage and the three W3Spaces entry pages. After the qabbalah.institute root response was HTTP 406, no further requests were made to that host. That response is not proof the site is offline.

Historical `.source.txt` files are not byte-identical to the private originals. Generated storage URLs in the 25 wrappers contained one builder account id and site id. Those paths were replaced with `user-REDACTED` and `sites/REDACTED` (297 replacements). The scan report that listed the original URLs was not committed. Values are not reproduced in `provenance/SCREENING.txt`.

`members-source-only/members/config.sample.php` contains placeholders (`CHANGE-ME`, `YOURSITE.example`). No live `config.php` was in the handoff. SQL has `CREATE TABLE` statements and no data rows. `app/content/pledge.txt` is still a local placeholder and is not claimed to be the live pledge.

## Check

From the repository root:

```bash
python3 tests/verify_homepage.py
```

The test checks the authoritative homepage size and SHA-256, the nine section ids, the absence of an iframe, the absolute members link, the byte-identical preview file, and every hash in `provenance/integrity-manifest.json`.

## Later Vercel preview

Nothing was deployed. A later public preview can publish `preview/` only. Set the Vercel project Root Directory to `preview`. `.vercelignore` also tells the CLI to upload that directory alone. Do not deploy the repository root: that would expose PHP templates and historical references as static files.

The preview keeps the current design links, including `https://qabbalah.institute/members/`. Styling still depends on the remote W3.CSS URL that previously returned HTTP 403, plus Google Fonts and Font Awesome. Expect a homepage preview, not a members application and not the old Builder pages.

## Members demo

`members-fresh-20261107/` is the November 7, 2026 members source: 44 code and asset files, byte-identical to the earlier local copies, with no pledge file and no live data. The verified members routing file is the 662-byte `routing/members.htaccess` (cPanel breadcrumb `qabbalah.institute/members`, mtime November 3). `members-demo/` runs that PHP in a local container with synthetic data. The public homepage and its HostGator members link are unchanged. Nothing here is deployed. See `members-demo/README.md`.

## Comparison demo

`famous-demo/` is a separate static design for comparison. It does not replace `preview/` or the members demo. A later preview of this design uses Root Directory `famous-demo` on its own project. See `famous-demo/README.md`.

## Deployment roots

The repository-root `.vercelignore` is an allowlist. It keeps `preview/` and the public files in `famous-demo/`, and it ignores PHP, SQL, historical pages, provenance, and tests. Vercel applies that file to the whole Git tree, then the project Root Directory selects one of those folders. The current static project stays on Root Directory `preview`. The Famous project stays on Root Directory `famous-demo`, output directory `.`, with no install or build command. Neither root is the repository root.

A Git deployment of `main` is a production deployment when `main` is the project’s production branch. Asking for a preview target on that ref does not keep it a preview. A preview comes from a non-production branch, or from `vercel deploy` without `--prod`, using the operator’s existing Vercel credentials. This repository does not run that deploy.
