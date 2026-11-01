# Character Archive – Plugin Notes

## Plugin Identity
- **Plugin prefix:** `char_archive`
- **Main file:** `inc/plugins/char_archive.php`
- **DB table:** `TABLE_PREFIX . 'rpg_char_archive'`
- **Version:** 0.2

## File Layout
```
inc/plugins/char_archive.php          # Main plugin (hooks, install, activate, uninstall)
inc/char_archive_ucp.php              # UserCP handler (required at runtime, not in inc/plugins/)
inc/char_archive_modcp.php            # ModCP handler
admin/modules/tools/char_archive.php  # ACP Tools module (Sync, Recover)
inc/languages/english/char_archive.lang.php
inc/languages/english/admin/char_archive.lang.php
inc/languages/deutsch_du/char_archive.lang.php
inc/languages/deutsch_du/admin/char_archive.lang.php
docs/playerdirectory-integration.md  # Integration guide for Spielerverzeichnis plugin
```

## Key Design Decisions
- `inc/char_archive_ucp.php` and `inc/char_archive_modcp.php` live at `inc/` level (not `inc/plugins/char_archive/`) because the main plugin requires them with `MYBB_ROOT . 'inc/char_archive_...'`. Do not move them without updating all require_once calls.
- The archive table uses the prefix `rpg_` (not `mybb_`) intentionally – it's a custom RPG table.
- Settings group name: `char_archive`; settings are rebuilt via `rebuild_settings()` on activate.

## External Integrations
- **MyAlerts** – best-effort DB insert, no native API. Different MyAlerts schema versions may require adjustment.
- **Spielerverzeichnis / Playerdirectory** – hook-based; hooks `playerdirectory_directory_user_stats` and `playerdirectory_playerstat_characters_html`.
- **application_ucp** tables – auto-detected at runtime; sync via ACP Tools → Character Archive.

## Approval Workflow
1. User adds former character in UserCP → status `approved=0` (pending)
2. Approver group (configurable via setting `char_archive_approver_groups`) sees pending items in ModCP
3. ModCP approves → `approved=1`
4. Admin in ACP Tools can recover → creates new MyBB user, remaps posts/logbook entries
