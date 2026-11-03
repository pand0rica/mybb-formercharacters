# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Security
- **Debug mode could be switched on by anyone.** `?char_archive_debug=1` (and a matching
  cookie) enabled debug logging for any visitor/member, writing request data – including
  post-key fragments – to `cache/char_archive_debug.log`. Debug output now only follows the
  admin setting *Debug mode* (or the `CHAR_ARCHIVE_DEBUG` constant).
- **Stored XSS in ModCP and ACP.** The notes of an archive entry (entered by members in the
  UserCP) were output unescaped in the ModCP approval list and the ACP pending list. They
  are now escaped with `htmlspecialchars_uni()`.

### Fixed
- **ACP recovery could never be executed.** The ACP module read `$mybb->post[...]`, which
  does not exist in MyBB 1.8, so the confirmation form always reappeared. It now uses
  `$mybb->get_input()` and checks the return value of `verify_post_check()`.
- **ACP templates were double-escaped.** `addslashes()` was applied on top of
  `$templates->get()` (which already escapes for `eval()`), breaking quotes in the ACP output.
- Direct `$mybb->input[...]` reads in ACP, ModCP, UserCP and hooks replaced by `get_input()`.
- German text “einen Moderator informieren” → “das Team informieren”.

### Changed
- Plugin website set; README documents the debug settings; `.claudeignore` added.

## [0.2]

- Initial version: automatic archiving on account deletion, manual entries with ModCP
  approval, recovery from UserCP and ACP, MyAlerts, application_ucp and Spielerverzeichnis
  integration.
