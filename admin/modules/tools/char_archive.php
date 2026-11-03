<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) die('This file cannot be accessed directly.');

/*
 * Admin Tools module: Character Archive
 */

// Require plugin functions if not loaded
if(!function_exists('char_archive_table_exists'))
{
    require_once MYBB_ROOT . 'inc/plugins/char_archive.php';
}

$lang->load('char_archive');

$page->add_breadcrumb_item($lang->char_archive_title, 'index.php?module=tools-char_archive');

// Access control: rely on MyBB admin permissions UI to restrict this module

$sync_feedback_html = '';

function char_archive_admin_render_ucp_snapshot_html($raw_snapshot)
{
    global $lang;

    $raw_snapshot = (string)$raw_snapshot;
    if(trim($raw_snapshot) === '')
    {
        return '';
    }

    $decoded = char_archive_decode_application_ucp_data($raw_snapshot);
    $tables  = $decoded['tables'] ?? array();
    $display_fields = $tables['application_ucp_userfields'] ?? array();

    if(empty($display_fields) && empty($tables))
    {
        return '';
    }

    $field_count = count($display_fields);
    $row_count   = 0;
    foreach($tables as $rows) { if(is_array($rows)) $row_count += count($rows); }

    $summary = str_replace(
        array('{1}', '{2}', '{3}'),
        array((int)$field_count, (int)count($tables), (int)$row_count),
        $lang->char_archive_admin_snapshot_summary
    );

    $parts = array();
    $parts[] = '<details style="margin-top:6px;"><summary><strong>' . htmlspecialchars_uni($lang->char_archive_admin_snapshot_title) . '</strong> - ' . htmlspecialchars_uni($summary) . '</summary>';

    if(!empty($display_fields))
    {
        $parts[] = '<ul style="margin:4px 0 6px 18px;">';
        $shown = 0;
        foreach($display_fields as $field)
        {
            if(!is_array($field)) continue;
            // Use fieldlabel/fieldname if available (legacy), otherwise show field ID
            if(!empty($field['fieldlabel']))     $label = (string)$field['fieldlabel'];
            elseif(!empty($field['fieldname']))  $label = (string)$field['fieldname'];
            elseif(isset($field['fieldid']))     $label = 'field_' . intval($field['fieldid']);
            else                                  $label = '?';
            // Find value under any known column name
            $value = '';
            foreach(array('value', 'fieldvalue', 'content', 'data') as $vc)
            {
                if(array_key_exists($vc, $field)) { $value = (string)$field[$vc]; break; }
            }
            if(strlen($value) > 220) $value = substr($value, 0, 220) . '...';
            $parts[] = '<li><strong>' . htmlspecialchars_uni($label) . ':</strong> ' . nl2br(htmlspecialchars_uni($value)) . '</li>';
            $shown++;
            if($shown >= 12) break;
        }
        if($field_count > 12)
        {
            $parts[] = '<li><em>' . htmlspecialchars_uni(str_replace('{1}', $field_count - 12, $lang->char_archive_admin_snapshot_more_fields)) . '</em></li>';
        }
        $parts[] = '</ul>';
    }

    $parts[] = '</details>';
    return implode('', $parts);
}

// Sync button removed: application_ucp data is now stored as JSON,
// no column synchronisation is needed.
$sync_html = '';

// We'll render the admin main template and inject the sync form and pending table

// --- Pending approvals UI ---
// (super-admin requirement already enforced above)

// Handle approval actions
if($mybb->get_input('approve') && $mybb->get_input('aid', MyBB::INPUT_INT))
{
    flash_message($lang->char_archive_admin_approve_via_modcp, 'error');
    admin_redirect('index.php?module=tools-char_archive');
}

// Recover confirmation and action
if($mybb->get_input('recover', MyBB::INPUT_INT) && $mybb->get_input('aid', MyBB::INPUT_INT))
{
    $aid = $mybb->get_input('aid', MyBB::INPUT_INT);
    require_once MYBB_ROOT . 'inc/plugins/char_archive.php';
    if(!char_archive_table_exists())
    {
        flash_message($lang->char_archive_table_missing, 'error');
        admin_redirect('index.php?module=tools-char_archive');
    }
    $table = 'rpg_char_archive';
    $entry = $db->fetch_array($db->simple_select($table, '*', "aid={$aid}"));
    if(!$entry)
    {
        flash_message($lang->char_archive_entry_not_found, 'error');
        admin_redirect('index.php?module=tools-char_archive');
    }

    // Show confirmation form if not posted
    if($mybb->request_method != 'post' || $mybb->get_input('do_recover') === '')
    {
        if(intval($entry['approved']) == 0)
        {
            flash_message($lang->char_archive_admin_entry_not_approved, 'error');
            admin_redirect('index.php?module=tools-char_archive');
        }
        if(intval($entry['recovered_uid'])>0)
        {
            flash_message($lang->char_archive_admin_entry_already_recovered, 'error');
            admin_redirect('index.php?module=tools-char_archive');
        }

        // render confirm template via eval inside ACP layout
        $confirm_tpl = $templates->get('char_archive_admin_confirm');
        $username = htmlspecialchars_uni($entry['username']);
        $old_uid = intval($entry['old_uid']);
        $my_post_key = $mybb->post_code;
        eval("\$confirm = \"" . $confirm_tpl . "\";");
        $page->output_header($lang->char_archive_title);
        echo $confirm;
        $page->output_footer();
        return;
    }

    // POST: perform recovery – my_post_key was already validated by admin/index.php
    // (an invalid key turns the request into GET and lands in the confirmation above).
    // Rely on MyBB admin permissions

    require_once MYBB_ROOT . 'inc/plugins/char_archive.php';
    $res = char_archive_recover_entry($aid);
    if($res && is_array($res) && isset($res['new_uid']))
    {
        $new_uid = $res['new_uid'];
        $password = isset($res['password']) ? (string)$res['password'] : '';
        $uses_main_password = !empty($res['uses_main_password']);

        // Notify main user via MyAlerts if available
        if($entry['main_uid'] && $db->table_exists('myalerts_alerts'))
        {
            $ins = array();
            $ins['user_to'] = intval($entry['main_uid']);
            $ins['dateline'] = TIME_NOW;
            $ins['title'] = $db->escape_string($lang->char_archive_admin_alert_recovered_title);
            $ins['message'] = $db->escape_string(str_replace(
                array('{1}', '{2}'),
                array($entry['username'], $new_uid),
                $lang->char_archive_admin_alert_recovered_message
            ));
            $db->insert_query('myalerts_alerts', $ins);
        }

        // Send email to main account if available
        if(!empty($entry['main_uid']))
        {
            $main = $db->fetch_array($db->simple_select('users', 'email,uid', "uid=" . intval($entry['main_uid'])));
            if($main && !empty($main['email']))
            {
                require_once MYBB_ROOT . 'inc/functions_mail.php';
                $subject = $lang->char_archive_admin_mail_subject;
                if($uses_main_password)
                {
                    $message = str_replace(
                        array('{1}', '{2}'),
                        array($entry['username'], $new_uid),
                        $lang->char_archive_admin_mail_message_same_password
                    );
                }
                else
                {
                    $message = str_replace(
                        array('{1}', '{2}', '{3}'),
                        array($entry['username'], $new_uid, $password),
                        $lang->char_archive_admin_mail_message
                    );
                }
                my_mail($main['email'], $subject, $message);
            }
        }

        // Also show success message to admin with credentials
        if($uses_main_password)
        {
            $msg = str_replace('{1}', $new_uid, $lang->char_archive_recovery_success_admin_same_password);
        }
        else
        {
            $msg = $lang->char_archive_recovery_success_admin;
            $msg = str_replace('{1}', $new_uid, $msg);
            $msg = str_replace('{2}', htmlspecialchars_uni($password), $msg);
        }
        flash_message($msg, 'success');
    }
    else
    {
        flash_message($lang->char_archive_admin_recovery_failed, 'error');
    }
    admin_redirect('index.php?module=tools-char_archive');
}

// List pending entries
$pending = array();
require_once MYBB_ROOT . 'inc/plugins/char_archive.php';
if(!char_archive_table_exists())
{
    $page->output_header($lang->char_archive_title);
    echo '<div class="error">' . htmlspecialchars($lang->char_archive_table_missing, ENT_QUOTES, 'UTF-8') . '</div>';
    $page->output_footer();
    exit;
}
$table = 'rpg_char_archive';
$q = $db->simple_select($table, '*', 'approved=0', array('limit' => 50, 'order_by' => 'archived_at', 'order_dir' => 'DESC'));
while($r = $db->fetch_array($q)) $pending[] = $r;

// load language
$lang->load('char_archive');

// Build pending rows HTML using templates
$rows_html = '';
if(!empty($pending))
{
    $row_tpl = $templates->get('char_archive_admin_row');
    foreach($pending as $p)
    {
        $aid = intval($p['aid']);
        $username = htmlspecialchars_uni($p['username']);
        $main_uid = intval($p['main_uid']);
        $old_uid = intval($p['old_uid']);
        $archived_at = date('Y-m-d H:i', $p['archived_at']);
        $notes = nl2br(htmlspecialchars_uni((string)$p['notes']));
        $snapshot_html = char_archive_admin_render_ucp_snapshot_html((string)$p['application_ucp_data']);
        if($snapshot_html !== '')
        {
            $notes .= $snapshot_html;
        }
        $actions = '<a href="modcp.php?action=char_archive">' . htmlspecialchars($lang->char_archive_admin_open_modcp, ENT_QUOTES, 'UTF-8') . '</a>';
        eval("\$rows_html .= \"" . $row_tpl . "\";");
    }
    $pending_table_tpl = $templates->get('char_archive_admin_pending_table');
    $rows = $rows_html;
    eval("\$pending_table = \"" . $pending_table_tpl . "\";");
}
else
{
    $pending_table = '<p>' . $lang->char_archive_pending_none . '</p>';
}

// Render main admin template via eval
$main_tpl = $templates->get('char_archive_admin_main');
$sync_form = $sync_html;
eval("\$out = \"" . $main_tpl . "\";");
$page->output_header($lang->char_archive_title);
echo $out;
$page->output_footer();


?>
