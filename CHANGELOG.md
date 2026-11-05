# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [1.0.0] - 2026-11-05

### Fixed
- **UserCP menu icon.** The Character Archive row in the UserCP navigation now has an inline SVG icon in the link colour (`currentColor`); rows baked by earlier versions are removed by href and class.

### Security
- **No more temporary plaintext passwords.** If an entry had no main account with a
  password, recovery generated a random password, showed it in the ACP/UserCP and mailed
  it in plain text (hashed with legacy `md5`). Recovery is now refused in that case with a
  clear message; a recovered account always takes over the main account's password.
- **Debug mode could be switched on by anyone.** `?char_archive_debug=1` (and a matching
  cookie) enabled debug logging for any visitor/member, writing request data – including
  post-key fragments – to `cache/char_archive_debug.log`. Fixed by removing the debug code
  entirely (see *Removed*).
- **Stored XSS in ModCP and ACP.** The notes of an archive entry (entered by members in the
  UserCP) were output unescaped in the ModCP approval list and the ACP pending list. They
  are now escaped with `htmlspecialchars_uni()`.

### Fixed
- **ACP recovery could never be executed.** The ACP module read `$mybb->post[...]`, which
  does not exist in MyBB 1.8, so the confirmation form always reappeared. It now uses
  `$mybb->get_input()`; the post key is validated natively by `admin/index.php` (POST only).
- **ACP templates were double-escaped.** `addslashes()` was applied on top of
  `$templates->get()` (which already escapes for `eval()`), breaking quotes in the ACP output.
- Direct `$mybb->input[...]` reads in ACP, ModCP, UserCP and hooks replaced by `get_input()`.
- German text “einen Moderator informieren” → “das Team informieren”.
- ACP *Tools → Character Archive* uses MyBB's ACP `Table`/`Form` classes instead of front-end templates with a bare HTML table: theme-consistent layout, archive date in the forum's time zone and date format (`my_date()`) instead of a raw `Y-m-d H:i` server time, main account shown as profile link. The recovery confirmation is an ACP form as well.
- The “Open ModCP queue” link in the ACP pointed to `admin/modcp.php` (relative link); it now uses the board URL.
- UserCP list: the edit form of each entry is collapsed behind an *Edit* toggle instead of always being expanded.

### Removed
- **Debug code.** All debug logging (`char_archive_debug_*()` functions, ~50 log calls, the
  HTML debug output on `misc.php`, the log file `cache/char_archive_debug.log`) and the two
  settings *Debug mode* / *Debug: verbose field ID* are gone. Activating the new version
  deletes the old debug settings; an existing `cache/char_archive_debug.log` can be deleted
  by hand. Real errors during installation/activation are still written to the PHP error log.
- **newreply post-key workaround.** `char_archive_global_start()` rewrote
  `$_POST/$_REQUEST/$mybb->input['my_post_key']` on every reply POST to repair malformed keys
  from another source. It interfered with MyBB's own CSRF handling and had nothing to do with
  the archive; the hook and `char_archive_normalize_newreply_post_key()` are gone.
- Templates `char_archive_admin_main`, `char_archive_admin_row`, `char_archive_admin_pending_table` and `char_archive_admin_confirm` are no longer created (the ACP renders without them); uninstall still removes them from older installations.

### Changed
- Plugin website set; `.claudeignore` added.

## [0.2]

- Initial version: automatic archiving on account deletion, manual entries with ModCP
  approval, recovery from UserCP and ACP, MyAlerts, application_ucp and Spielerverzeichnis
  integration.
