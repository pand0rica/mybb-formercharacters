<?php
if(!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

$l['char_archive_title'] = 'Character Archive';
$l['char_archive_admin_tools_title'] = 'Character Archive - Tools';
$l['char_archive_admin_pending_title'] = 'Pending Archived Characters';
$l['char_archive_admin_col_aid'] = 'AID';
$l['char_archive_admin_col_main_uid'] = 'Main UID';
$l['char_archive_admin_entry_approved'] = 'Entry approved.';
$l['char_archive_admin_entry_not_approved'] = 'Entry not approved.';
$l['char_archive_admin_entry_already_recovered'] = 'Entry already recovered.';
$l['char_archive_admin_action_approve'] = 'Approve';
$l['char_archive_admin_action_recover'] = 'Recover';
$l['char_archive_admin_confirm_intro'] = 'Recover archived character';
$l['char_archive_admin_confirm_button'] = 'Confirm Recover';
$l['char_archive_admin_cancel'] = 'Cancel';
$l['char_archive_admin_recovery_failed'] = 'Recovery failed.';
$l['char_archive_admin_approve_via_modcp'] = 'Please approve archive entries via ModCP.';
$l['char_archive_admin_open_modcp'] = 'Open ModCP Queue';
$l['char_archive_pending_none'] = 'No pending entries.';
$l['char_archive_recovery_success_admin'] = 'Entry recovered, new uid: {1}. Temporary password: {2}';
$l['char_archive_recovery_success_admin_same_password'] = 'Entry recovered, new uid: {1}. The recovered character now uses the same password as the main account.';
$l['char_archive_admin_alert_approved_title'] = 'Character approved';
$l['char_archive_admin_alert_approved_message'] = 'Your archived character "{1}" has been approved by staff.';
$l['char_archive_admin_alert_recovered_title'] = 'Character recovered';
$l['char_archive_admin_alert_recovered_message'] = 'Your archived character "{1}" was recovered as new uid {2}. You can log in with your account credentials.';
$l['char_archive_admin_mail_subject'] = 'Your character has been recovered';
$l['char_archive_admin_mail_message'] = "Hello,\n\nYour archived character '{1}' has been recovered as new user id: {2}\n\nTemporary password: {3}\nPlease change the password after first login.\n\nRegards\n";
$l['char_archive_admin_mail_message_same_password'] = "Hello,\n\nYour archived character '{1}' has been recovered as new user id: {2}.\n\nThe recovered character uses the same password as your main account.\n\nRegards\n";
$l['char_archive_admin_snapshot_title'] = 'application_ucp snapshot';
$l['char_archive_admin_snapshot_summary'] = '{1} dynamic field(s), {2} table(s), {3} row(s)';
$l['char_archive_admin_snapshot_more_fields'] = '+ {1} more field(s)';

$l['char_archive_table_missing'] = 'Archive table not found. Please activate or reinstall the plugin.';
$l['char_archive_invalid_post'] = 'Invalid post verification.';
$l['char_archive_entry_not_found'] = 'Entry not found or permission denied.';

$l['char_archive_col_username'] = 'Username';
$l['char_archive_col_old_uid'] = 'Old UID';
$l['char_archive_col_archived_at'] = 'Archived At';
$l['char_archive_col_notes'] = 'Notes';
$l['char_archive_col_action'] = 'Action';
$l['char_archive_confirm_title'] = 'Confirm Recovery';
