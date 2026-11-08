Verified members routing. Not the public homepage rules.

Read-only cPanel breadcrumb: qabbalah.institute/members
Live file: 662 bytes, mtime November 3
SHA-256: 892055490f500709d23bf69cb440f7f35e9c786eb2648e3829a30c1c13694ba5
Byte-identical to the screened normal download and to the prior local members/.htaccess.

The demo image copies members.htaccess to .htaccess. Semantics:

- DirectoryIndex index.php
- Requests under app/ or storage/ are forbidden
- HTTPS redirect, except when HTTP_HOST begins with localhost
- A URL that is not a real file or directory is handled by index.php
- config.php, config.sample.php, and .htaccess are denied

The earlier 1,598-byte capture is not in this repository.
