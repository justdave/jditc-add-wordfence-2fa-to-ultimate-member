#### unreleased changes

* New features:
  * Improved compatibility with the "Simple CAPTCHA with Cloudflare Turnstile" plugin, including security checks for password, 2FA, and passkey logins.
* Bug fixes:
  * Sanitize Wordfence preflight notices before rendering their limited formatting and links.
  * Display authentication errors and return to the login fields when the Wordfence preflight fails.
  * Prevent duplicate login submissions while the preflight or security check is in progress.
  * Prompt users to refresh an expired Turnstile security check during 2FA.
* Under the hood:
  * Added the `jditc_w2fa_allow_reused_turnstile_token` filter for controlled test integrations. It is disabled by default.
  * Build and track the bundled DOMPurify browser asset through npm and Dependabot.
  * Fixed an issue with how passkey credentials are processed to prevent potential data loss.

#### 1.1.3 / 2026-11-11

* Passkey button is no longer shown if the feature is not enabled in Wordfence. Thanks to Wes Butler for the bug report!
* Compatibility fix for the "Simple CAPTCHA with Cloudflare Turnstile" plugin. Thanks to Wes Butler for the bug report!

#### 1.1.2 / 2026-08-12

* Updated screenshots and text description for plugin directory to include Passkey support.

#### 1.1.1 / 2026-08-12

* Clean up PHPCS warnings (code stability)

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
