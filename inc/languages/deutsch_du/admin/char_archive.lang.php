<?php
if(!defined('IN_MYBB')) {
	die('This file cannot be accessed directly.');
}

$l['char_archive_title'] = 'Charakterarchiv';
$l['char_archive_admin_tools_title'] = 'Charakterarchiv - Werkzeuge';
$l['char_archive_admin_pending_title'] = 'Ausstehende archivierte Charaktere';
$l['char_archive_admin_col_aid'] = 'AID';
$l['char_archive_admin_col_main_uid'] = 'Haupt-UID';
$l['char_archive_admin_entry_approved'] = 'Eintrag freigegeben.';
$l['char_archive_admin_entry_not_approved'] = 'Eintrag ist nicht freigegeben.';
$l['char_archive_admin_entry_already_recovered'] = 'Eintrag wurde bereits wiederhergestellt.';
$l['char_archive_admin_action_approve'] = 'Freigeben';
$l['char_archive_admin_action_recover'] = 'Wiederherstellen';
$l['char_archive_admin_confirm_intro'] = 'Archivierten Charakter wiederherstellen';
$l['char_archive_admin_confirm_button'] = 'Wiederherstellung bestätigen';
$l['char_archive_admin_cancel'] = 'Abbrechen';
$l['char_archive_admin_recovery_failed'] = 'Wiederherstellung fehlgeschlagen.';
$l['char_archive_admin_approve_via_modcp'] = 'Bitte Archiv-Einträge über das ModCP freigeben.';
$l['char_archive_admin_open_modcp'] = 'ModCP-Warteschlange öffnen';
$l['char_archive_pending_none'] = 'Keine ausstehenden Einträge.';
$l['char_archive_recovery_success_admin'] = 'Eintrag wiederhergestellt, neue UID: {1}. Temporäres Passwort: {2}';
$l['char_archive_recovery_success_admin_same_password'] = 'Eintrag wiederhergestellt, neue UID: {1}. Der wiederhergestellte Charakter nutzt jetzt dasselbe Passwort wie der Hauptaccount.';
$l['char_archive_admin_alert_approved_title'] = 'Charakter freigegeben';
$l['char_archive_admin_alert_approved_message'] = 'Dein archivierter Charakter "{1}" wurde vom Team freigegeben.';
$l['char_archive_admin_alert_recovered_title'] = 'Charakter wiederhergestellt';
$l['char_archive_admin_alert_recovered_message'] = 'Dein archivierter Charakter "{1}" wurde als neue UID {2} wiederhergestellt. Du kannst dich mit deinen Account-Zugangsdaten anmelden.';
$l['char_archive_admin_mail_subject'] = 'Dein Charakter wurde wiederhergestellt';
$l['char_archive_admin_mail_message'] = "Hallo,\n\nDein archivierter Charakter '{1}' wurde als neue Benutzer-ID {2} wiederhergestellt.\n\nTemporäres Passwort: {3}\nBitte ändere das Passwort nach dem ersten Login.\n\nViele Grüße\n";
$l['char_archive_admin_mail_message_same_password'] = "Hallo,\n\nDein archivierter Charakter '{1}' wurde als neue Benutzer-ID {2} wiederhergestellt.\n\nDer wiederhergestellte Charakter nutzt jetzt dasselbe Passwort wie dein Hauptaccount.\n\nViele Grüße\n";
$l['char_archive_admin_snapshot_title'] = 'application_ucp-Snapshot';
$l['char_archive_admin_snapshot_summary'] = '{1} dynamische(s) Feld(er), {2} Tabelle(n), {3} Zeile(n)';
$l['char_archive_admin_snapshot_more_fields'] = '+ {1} weitere(s) Feld(er)';

$l['char_archive_table_missing'] = 'Archiv-Tabelle nicht gefunden. Bitte Plugin aktivieren oder neu installieren.';
$l['char_archive_invalid_post'] = 'Ungültige Beitragsprüfung.';
$l['char_archive_entry_not_found'] = 'Eintrag nicht gefunden oder keine Berechtigung.';

$l['char_archive_col_username'] = 'Benutzername';
$l['char_archive_col_old_uid'] = 'Alte UID';
$l['char_archive_col_archived_at'] = 'Archiviert am';
$l['char_archive_col_notes'] = 'Notizen';
$l['char_archive_col_action'] = 'Aktion';
$l['char_archive_confirm_title'] = 'Wiederherstellung bestätigen';
