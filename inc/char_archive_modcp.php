<?php
if(!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

function char_archive_modcp_handle()
{
    global $mybb, $db, $templates, $header, $footer, $headerinclude, $modcp_nav, $lang;

    if(empty($mybb->user['uid'])) {
        error_no_permission();
    }

    require_once MYBB_ROOT . 'inc/plugins/char_archive.php';
    $lang->load('char_archive');

    if(!char_archive_user_can_approve()) {
        error_no_permission();
    }

    if(!char_archive_table_exists()) {
        error($lang->char_archive_table_missing);
    }

    $status_message_html = '';

    $is_post_request = (strtolower((string)$mybb->request_method) === 'post');
    $ca_action = isset($mybb->input['ca_action']) ? (string)$mybb->input['ca_action'] : '';

    if($is_post_request && $ca_action === 'approve_entry') {
        $submitted_post_key = isset($mybb->input['my_post_key']) ? (string)$mybb->input['my_post_key'] : '';
        if($submitted_post_key === '' || !verify_post_check($submitted_post_key, true)) {
            redirect('modcp.php?action=char_archive', $lang->char_archive_invalid_post, 'error');
        }

        $aid = isset($mybb->input['aid']) ? intval($mybb->input['aid']) : 0;
        if($aid <= 0) {
            redirect('modcp.php?action=char_archive', $lang->char_archive_invalid_entry_id, 'error');
        }

        $entry = $db->fetch_array($db->simple_select('rpg_char_archive', '*', "aid={$aid} AND approved='0'"));
        if(!$entry) {
            redirect('modcp.php?action=char_archive', $lang->char_archive_entry_not_found, 'error');
        }

        $db->update_query('rpg_char_archive', array('approved' => 1), "aid={$aid} AND approved='0'");
        if(method_exists($db, 'affected_rows') && intval($db->affected_rows()) < 1) {
            redirect('modcp.php?action=char_archive', $lang->char_archive_modcp_entry_approve_failed, 'error');
        }
        redirect('modcp.php?action=char_archive', $lang->char_archive_modcp_entry_approved, 'success');
    }

    $rows_html = '';
    $query = $db->simple_select('rpg_char_archive', '*', 'approved=0', array('order_by' => 'archived_at', 'order_dir' => 'DESC', 'limit' => 100));
    $row_tpl = $templates->get('char_archive_modcp_row');

    while($row = $db->fetch_array($query)) {
        $aid = intval($row['aid']);
        $username = htmlspecialchars_uni($row['username']);
        $old_uid = intval($row['old_uid']);
        $archived_at = my_date('relative', intval($row['archived_at']));
        $notes = (string)$row['notes'];

        $actions = '';
        $actions .= '<form method="post" action="modcp.php?action=char_archive" style="margin:0;">';
        $actions .= '<input type="hidden" name="my_post_key" value="' . htmlspecialchars_uni($mybb->post_code) . '">';
        $actions .= '<input type="hidden" name="ca_action" value="approve_entry">';
        $actions .= '<input type="hidden" name="aid" value="' . $aid . '">';
        $actions .= '<input type="submit" class="button" value="' . htmlspecialchars_uni($lang->char_archive_modcp_btn_approve) . '">';
        $actions .= '</form>';

        eval("\$rows_html .= \"" . $row_tpl . "\";");
    }

    if($rows_html === '') {
        $rows_html = '<tr><td class="trow1" colspan="5">' . htmlspecialchars_uni($lang->char_archive_pending_none) . '</td></tr>';
    }

    $list_tpl = $templates->get('char_archive_modcp_list');
    eval("\$content = \"" . $list_tpl . "\";");

    add_breadcrumb($lang->nav_modcp, 'modcp.php');
    add_breadcrumb($lang->char_archive_modcp_title, 'modcp.php?action=char_archive');

    $page = '<html><head><title>' . htmlspecialchars_uni($mybb->settings['bbname']) . ' - ' . htmlspecialchars_uni($lang->char_archive_modcp_title) . '</title>' . $headerinclude . '</head><body>' . $header;
    $page .= '<table width="100%" border="0" align="center"><tr>' . $modcp_nav . '<td valign="top">' . $content . '</td></tr></table>';
    $page .= $footer . '</body></html>';
    output_page($page);
}
