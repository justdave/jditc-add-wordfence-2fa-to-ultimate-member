#### unreleased changes

* Stability fixes.

#### 1.1 / 2026-08-12

* Added support for passkeys that was just added in WordFence 9.0.
* Added a Security tab to the UM Accounts page which contains the 2FA and Passkey setup if it's enabled, so end users can actually set them up.

#### 1.0.2 / 2026-07-13

* Update release process to push to WordPress Plugin Directory.

#### 1.0.1 / 2026-07-09

* Move inline Javascript to standalone js files to reduce complexity.
* Rename plugin to "JDITC Add Wordfence 2FA to Ultimate Member" and slug to "jditc-add-wordfence-2fa-to-ultimate-member" for WordPress.org compliance.

#### 1.0 / 2026-07-07

* Initial release.
* Adds Wordfence 2FA compatibility to Ultimate Member login forms.
* Preserves Wordfence login-security errors inside the Ultimate Member UI.
* Uses a two-step login flow so 2FA prompts appear only when Wordfence requires them.