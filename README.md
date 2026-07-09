# JDITC Add Wordfence 2FA to Ultimate Member

This WordPress plugin adds Wordfence 2FA compatibility to Ultimate Member login forms. It keeps Ultimate Member in control of the form UX while deferring credential validation to Wordfence’s own login security flow when a second factor is required.

The login flow is designed to work in two steps:

1. The user submits their username and password through the Ultimate Member login form.
2. If Wordfence requires a second factor, the plugin switches the form into token-entry mode and prompts for the Wordfence 2FA code.

The plugin passes Wordfence login-security errors back through Ultimate Member so the original Wordfence messages remain visible instead of being swallowed by the form wrapper.

Bug reports and feature requests can be filed at the [GitHub repository](https://github.com/justdave/jditc-add-wordfence-2fa-to-ultimate-member/issues).