# DOMPurify

This directory contains the DOMPurify production browser build used to sanitize Wordfence
preflight notices before they are added to the page.

`npm run sync:dompurify` copies the readable and minified JavaScript builds, their source
maps, and `LICENSE` from the version locked in `package-lock.json`, then generates
`version.php` for WordPress asset versioning. Do not edit those generated files manually.

The PHP integration loads the minified asset before the JDITC login script, unless
`SCRIPT_DEBUG` is enabled, when it loads the readable build. If the sanitizer cannot run,
the login script uses text-only notices instead of inserting untrusted HTML.

