<?php
if(!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

function char_archive_ucp_render_kv_table($rows)
{
    if(!is_array($rows) || empty($rows))
    {
        return '';
    }

    $html = '<table border="0" cellspacing="1" cellpadding="6" class="tborder" style="width:100%;">';
    $html .= '<tr><td class="thead" style="width:32%;"><strong>Field</strong></td><td class="thead"><strong>Value</strong></td></tr>';
    foreach($rows as $label => $value)
    {
        $html .= '<tr>';
        $html .= '<td class="trow1" style="vertical-align:top;"><strong>' . htmlspecialchars_uni((string)$label) . '</strong></td>';
        $html .= '<td class="trow1" style="vertical-align:top;">' . $value . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';

    return $html;
}

function char_archive_ucp_table_has_column($table, $column)
{
    global $db;

    static $cache = array();
    $table = trim((string)$table);
    $column = trim((string)$column);
    if($table === '' || $column === '')
    {
        return false;
    }

    $cache_key = $table . ':' . $column;
    if(isset($cache[$cache_key]))
    {
        return $cache[$cache_key];
    }

    if(!$db->table_exists($table))
    {
        $cache[$cache_key] = false;
        return false;
    }

    $query = $db->write_query("SHOW COLUMNS FROM `" . TABLE_PREFIX . $db->escape_string($table) . "` LIKE '" . $db->escape_string($column) . "'");
    $cache[$cache_key] = ($query && $db->num_rows($query) > 0);
    return $cache[$cache_key];
}

function char_archive_ucp_profile_field_labels()
{
    global $db;

    static $labels = null;
    if(is_array($labels))
    {
        return $labels;
    }

    $labels = array();
    if(!$db->table_exists('profilefields') || !char_archive_ucp_table_has_column('profilefields', 'fid'))
    {
        return $labels;
    }

    $candidate_cols = array('name', 'title', 'description', 'fieldname', 'label');
    $select_cols = array('fid');
    foreach($candidate_cols as $col)
    {
        if(char_archive_ucp_table_has_column('profilefields', $col))
        {
            $select_cols[] = $col;
        }
    }

    $query = $db->simple_select('profilefields', implode(',', array_unique($select_cols)));
    while($row = $db->fetch_array($query))
    {
        $fid = intval($row['fid'] ?? 0);
        if($fid <= 0)
        {
            continue;
        }

        $label = '';
        foreach($candidate_cols as $col)
        {
            if(!empty($row[$col]))
            {
                $label = trim((string)$row[$col]);
                if($label !== '')
                {
                    break;
                }
            }
        }

        if($label !== '')
        {
            $labels[$fid] = $label;
        }
    }

    return $labels;
}

function char_archive_ucp_app_field_labels()
{
    global $db;

    static $labels = null;
    if(is_array($labels))
    {
        return $labels;
    }

    $labels = array();

    if(!$db->table_exists('application_ucp_fields'))
    {
        return $labels;
    }

    // application_ucp schema (katjalennartz/application_ucp):
    // application_ucp_fields: id (PK), label (display name), fieldname (internal name)
    $query = $db->simple_select('application_ucp_fields', 'id,label,fieldname');
    while($row = $db->fetch_array($query))
    {
        $field_id = intval($row['id'] ?? 0);
        if($field_id === 0)
        {
            continue;
        }

        $label = trim((string)($row['label'] ?? ''));
        if($label === '')
        {
            $label = trim((string)($row['fieldname'] ?? ''));
        }

        if($label !== '')
        {
            $labels[$field_id] = $label;
        }
    }

    return $labels;
}

function char_archive_ucp_old_logbook_info($old_uid)
{
    global $db;

    $old_uid = intval($old_uid);
    if($old_uid <= 0 || !$db->table_exists('logbook') || !char_archive_ucp_table_has_column('logbook', 'user_id'))
    {
        return '';
    }

    $logbook = $db->fetch_array($db->simple_select('logbook', '*', "user_id='" . $old_uid . "'", array('limit' => 1)));
    if(!$logbook)
    {
        return '';
    }

    $logbook_id = intval($logbook['id'] ?? 0);
    $entry_count = 0;
    if($logbook_id > 0 && $db->table_exists('logbook_entry') && char_archive_ucp_table_has_column('logbook_entry', 'logbook_id'))
    {
        $entry_count = intval($db->fetch_field($db->simple_select('logbook_entry', 'COUNT(*) AS c', "logbook_id='" . $logbook_id . "'"), 'c'));
    }

    $parts = array('ID ' . $logbook_id);
    if($entry_count > 0)
    {
        $parts[] = $entry_count . ' Entries';
    }

    return implode(' | ', $parts);
}

function char_archive_ucp_old_relations_info($old_uid)
{
    global $db;

    $old_uid = intval($old_uid);
    if($old_uid <= 0)
    {
        return '';
    }

    $counts = array(
        'infos' => 0,
        'kategorien' => 0,
        'beziehungen' => 0,
        'details' => 0,
    );

    if($db->table_exists('relations_infos') && char_archive_ucp_table_has_column('relations_infos', 'uid'))
    {
        $counts['infos'] = intval($db->fetch_field($db->simple_select('relations_infos', 'COUNT(*) AS c', "uid='" . $old_uid . "'"), 'c'));
    }
    if($db->table_exists('relations_kategorien') && char_archive_ucp_table_has_column('relations_kategorien', 'uid'))
    {
        $counts['kategorien'] = intval($db->fetch_field($db->simple_select('relations_kategorien', 'COUNT(*) AS c', "uid='" . $old_uid . "'"), 'c'));
    }
    if($db->table_exists('relations_beziehungen'))
    {
        $where_parts = array();
        if(char_archive_ucp_table_has_column('relations_beziehungen', 'uid1'))
        {
            $where_parts[] = "uid1='" . $old_uid . "'";
        }
        if(char_archive_ucp_table_has_column('relations_beziehungen', 'uid2'))
        {
            $where_parts[] = "uid2='" . $old_uid . "'";
        }
        if(!empty($where_parts))
        {
            $counts['beziehungen'] = intval($db->fetch_field($db->simple_select('relations_beziehungen', 'COUNT(*) AS c', '(' . implode(' OR ', $where_parts) . ')'), 'c'));
        }
    }
    if($db->table_exists('relations_beziehungen_details') && char_archive_ucp_table_has_column('relations_beziehungen_details', 'uid'))
    {
        $counts['details'] = intval($db->fetch_field($db->simple_select('relations_beziehungen_details', 'COUNT(*) AS c', "uid='" . $old_uid . "'"), 'c'));
    }

    $total = array_sum($counts);
    if($total <= 0)
    {
        return '';
    }

    return $total
        . ' entries (infos: ' . $counts['infos']
        . ', categories: ' . $counts['kategorien']
        . ', relations: ' . $counts['beziehungen']
        . ', details: ' . $counts['details'] . ')';
}

function char_archive_ucp_handle()
{
    global $mybb, $db, $templates, $header, $footer, $headerinclude, $usercpnav, $lang;

    if(empty($mybb->user['uid'])) {
        error_no_permission();
    }

    $uid = (int)$mybb->user['uid'];
    $lang->load('char_archive');

    $current_post_key = '';
    if(function_exists('generate_post_check')) {
        $current_post_key = (string)generate_post_check();
    }
    if($current_post_key === '' && isset($mybb->post_code)) {
        $current_post_key = (string)$mybb->post_code;
    }

    $status_message = '';
    if($mybb->get_input('ca_msg') !== '') {
        $status_message = htmlspecialchars_uni($mybb->get_input('ca_msg'));
    }

    $raw_post = (isset($_POST) && is_array($_POST)) ? $_POST : array();
    $is_post_request = ($mybb->request_method === 'post');
    if(isset($_SERVER['REQUEST_METHOD']) && strtolower((string)$_SERVER['REQUEST_METHOD']) === 'post') {
        $is_post_request = true;
    }

    if($is_post_request) {
        require_once MYBB_ROOT . 'inc/plugins/char_archive.php';
        if(!char_archive_table_exists()) {
            $msg = $lang->char_archive_table_missing;
            redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
        }

        $submitted_post_key = '';
        if(isset($raw_post['my_post_key'])) {
            $submitted_post_key = trim((string)$raw_post['my_post_key']);
        } else {
            $submitted_post_key = trim($mybb->get_input('my_post_key'));
        }
        $submitted_post_key = trim($submitted_post_key, "\"'\\ ");

        $post_key_valid = false;
        if($submitted_post_key !== '') {
            $post_key_valid = verify_post_check($submitted_post_key, true);
            if(!$post_key_valid && $current_post_key !== '' && $submitted_post_key === $current_post_key) {
                $post_key_valid = true;
            }
        }
        if(!$post_key_valid) {
            $msg = $lang->char_archive_invalid_post;
            redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
        }

        $action_type = isset($raw_post['ca_action']) ? trim((string)$raw_post['ca_action']) : '';

        if($action_type === 'delete_entry') {
            $aid = isset($raw_post['aid']) ? (int)$raw_post['aid'] : 0;
            if($aid <= 0) {
                $msg = $lang->char_archive_invalid_entry_id;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $entry = $db->fetch_array($db->simple_select('rpg_char_archive', '*', "aid={$aid} AND main_uid={$uid}"));
            if(!$entry) {
                $msg = $lang->char_archive_entry_not_found;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            if((int)$entry['recovered_uid'] > 0) {
                $msg = $lang->char_archive_recovery_locked;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $db->delete_query('rpg_char_archive', "aid={$aid} AND main_uid={$uid}");
            $msg = $lang->char_archive_entry_deleted;
            redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
        }

        if($action_type === 'edit_entry') {
            $aid = isset($raw_post['aid']) ? (int)$raw_post['aid'] : 0;
            $old_uid = isset($raw_post['old_uid']) ? (int)$raw_post['old_uid'] : 0;
            $username = trim((string)($raw_post['username'] ?? ''));
            $avatar = trim((string)($raw_post['avatar'] ?? ''));
            $notes = trim((string)($raw_post['notes'] ?? ''));
            $regdate = 0;

            if(preg_match('/<script/i', $notes)) {
                $msg = $lang->char_archive_notes_no_scripts;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            if($old_uid > 0)
            {
                $old_user = $db->fetch_array($db->simple_select('users', 'regdate', "uid={$old_uid}"));
                if($old_user && isset($old_user['regdate']))
                {
                    $regdate = (int)$old_user['regdate'];
                }
            }

            if($aid <= 0) {
                $msg = $lang->char_archive_invalid_entry_id;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }
            if($username === '') {
                $msg = $lang->char_archive_username_required;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }
            if(strlen($username) > 120) {
                $msg = $lang->char_archive_username_too_long;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $entry = $db->fetch_array($db->simple_select('rpg_char_archive', '*', "aid={$aid} AND main_uid={$uid}"));
            if(!$entry) {
                $msg = $lang->char_archive_entry_not_found;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            if((int)$entry['recovered_uid'] > 0) {
                $msg = $lang->char_archive_recovery_locked;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $update = array(
                'old_uid' => $old_uid,
                'username' => $db->escape_string($username),
                'avatar' => $db->escape_string($avatar),
                'notes' => $db->escape_string($notes)
            );

            if($db->field_exists('regdate', 'rpg_char_archive'))
            {
                $update['regdate'] = $regdate;
            }

            $computed_totals = array('postnum' => 0, 'threadnum' => 0);
            if(function_exists('char_archive_calculate_old_uid_inplay_totals'))
            {
                $computed_totals = char_archive_calculate_old_uid_inplay_totals($old_uid, $username, 5);
            }
            $update['postnum'] = intval($computed_totals['postnum']);
            $update['threadnum'] = intval($computed_totals['threadnum']);

            $db->update_query('rpg_char_archive', $update, "aid={$aid} AND main_uid={$uid}");

            $msg = $lang->char_archive_entry_updated;
            redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
        }

        if($action_type === 'recover_entry') {
            $aid = isset($raw_post['aid']) ? (int)$raw_post['aid'] : 0;
            if($aid <= 0) {
                $msg = $lang->char_archive_invalid_entry_id;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $entry = $db->fetch_array($db->simple_select('rpg_char_archive', '*', "aid={$aid} AND main_uid={$uid}"));
            if(!$entry) {
                $msg = $lang->char_archive_entry_not_found;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            if((int)$entry['approved'] !== 1) {
                $msg = $lang->char_archive_recovery_not_approved;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            if((int)$entry['recovered_uid'] > 0) {
                $msg = str_replace('{1}', (int)$entry['recovered_uid'], $lang->char_archive_recovery_already_done);
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $result = char_archive_recover_entry($aid);
            if(is_array($result) && isset($result['error']) && $result['error'] === 'no_main_account') {
                $msg = $lang->char_archive_recovery_no_main_account;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }
            if(!is_array($result) || (int)($result['new_uid'] ?? 0) <= 0) {
                $msg = $lang->char_archive_recovery_failed_ucp;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $msg = str_replace('{1}', (int)$result['new_uid'], $lang->char_archive_recovery_success_ucp_same_password);
            redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
        }

        $is_add_archive = ($action_type === 'add_entry') || isset($raw_post['add_archive']);
        if(!$is_add_archive) {
            foreach($raw_post as $post_key => $post_value) {
                if(strpos((string)$post_key, 'add_archive') !== false) {
                    $is_add_archive = true;
                    break;
                }
            }
        }

        if($is_add_archive) {
            $old_uid = isset($raw_post['old_uid']) ? (int)$raw_post['old_uid'] : 0;
            $username = trim((string)($raw_post['username'] ?? ''));
            $avatar = trim((string)($raw_post['avatar'] ?? ''));
            $notes = trim((string)($raw_post['notes'] ?? ''));
            $regdate = 0;

            if(preg_match('/<script/i', $notes)) {
                $msg = $lang->char_archive_notes_no_scripts;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            if($old_uid > 0)
            {
                $old_user = $db->fetch_array($db->simple_select('users', 'regdate', "uid={$old_uid}"));
                if($old_user && isset($old_user['regdate']))
                {
                    $regdate = (int)$old_user['regdate'];
                }
            }

            if($username === '') {
                $msg = $lang->char_archive_username_required;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }
            if(strlen($username) > 120) {
                $msg = $lang->char_archive_username_too_long;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $insert = array(
                'main_uid' => $uid,
                'old_uid' => $old_uid,
                'username' => $db->escape_string($username),
                'regdate' => $regdate,
                'avatar' => $db->escape_string($avatar),
                'profilefields' => '',
                'application_ucp_data' => '',
                'notes' => $db->escape_string($notes),
                'approved' => 0,
                'recovered_uid' => null,
                'archived_at' => TIME_NOW
            );

            $computed_totals = array('postnum' => 0, 'threadnum' => 0);
            if(function_exists('char_archive_calculate_old_uid_inplay_totals'))
            {
                $computed_totals = char_archive_calculate_old_uid_inplay_totals($old_uid, $username, 5);
            }
            $insert['postnum'] = intval($computed_totals['postnum']);
            $insert['threadnum'] = intval($computed_totals['threadnum']);

            $db->insert_query('rpg_char_archive', $insert);
            if((int)$db->insert_id() <= 0) {
                $msg = $lang->char_archive_insert_failed;
                redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
            }

            $msg = $lang->char_archive_entry_added_pending;
            redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
        }

        $msg = $lang->char_archive_no_action;
        redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($msg));
    }

    require_once MYBB_ROOT . 'inc/plugins/char_archive.php';
    if(!char_archive_table_exists()) {
        $msg = $lang->char_archive_table_missing;
        add_breadcrumb($lang->ucp_nav_home, 'usercp.php');
        add_breadcrumb($lang->char_archive_title);

        $output = '<div class="error"><p>' . htmlspecialchars_uni($msg) . '</p></div>';
        $page = '<html><head><title>' . htmlspecialchars_uni($mybb->settings['bbname']) . ' - ' . htmlspecialchars_uni($lang->char_archive_title) . '</title>' . $headerinclude . '</head><body>' . $header;
        $page .= '<table width="100%" border="0" align="center"><tr>' . $usercpnav . '<td valign="top">' . $output . '</td></tr></table>';
        $page .= $footer . '</body></html>';
        output_page($page);
        return;
    }

    $view_aid = $mybb->get_input('view', MyBB::INPUT_INT);
    if($view_aid > 0)
    {
        $entry = $db->fetch_array($db->simple_select('rpg_char_archive', '*', "aid={$view_aid} AND main_uid={$uid}", array('limit' => 1)));
        if(!$entry)
        {
            redirect('usercp.php?action=char_archive&ca_msg=' . rawurlencode($lang->char_archive_entry_not_found));
        }

        add_breadcrumb($lang->ucp_nav_home, 'usercp.php');
        add_breadcrumb($lang->char_archive_title, 'usercp.php?action=char_archive');
        $detail_crumb = trim((string)($entry['username'] ?? ''));
        if($detail_crumb === '')
        {
            $detail_crumb = $lang->char_archive_detail_title;
        }
        add_breadcrumb($detail_crumb);

        $entry_username = htmlspecialchars_uni((string)$entry['username']);
        $entry_status = ((int)$entry['approved'] === 1)
            ? htmlspecialchars_uni($lang->char_archive_status_approved)
            : htmlspecialchars_uni($lang->char_archive_status_pending);

        $general_rows = array(
            $lang->char_archive_col_aid => htmlspecialchars_uni((string)(int)$entry['aid']),
            $lang->char_archive_col_username => $entry_username,
            $lang->char_archive_col_old_uid => htmlspecialchars_uni((string)(int)$entry['old_uid']),
            $lang->char_archive_col_status => $entry_status,
            $lang->char_archive_col_archived_at => htmlspecialchars_uni(my_date('normal', (int)$entry['archived_at'])),
            $lang->char_archive_detail_regdate => htmlspecialchars_uni(((int)$entry['regdate'] > 0 ? my_date('normal', (int)$entry['regdate']) : $lang->na)),
            $lang->char_archive_col_notes => (string)$entry['notes'],
        );

        $avatar_value = trim((string)($entry['avatar'] ?? ''));
        $avatar_backup = trim((string)($entry['avatar_backup'] ?? ''));
        $avatar_show = $avatar_backup !== '' ? $avatar_backup : $avatar_value;
        if($avatar_show === '')
        {
            $avatar_html = htmlspecialchars_uni($lang->na);
        }
        else
        {
            $avatar_html = '<div style="margin-bottom:6px;"><img src="' . htmlspecialchars_uni($avatar_show) . '" alt="avatar" style="max-width:180px;max-height:180px;border:1px solid #ccc;padding:2px;background:#fff;"></div>';
            $avatar_html .= '<div class="smalltext">' . htmlspecialchars_uni($avatar_show) . '</div>';
        }

        $general_rows[$lang->char_archive_col_avatar] = $avatar_html;

        $stored_postnum = isset($entry['postnum']) ? (int)$entry['postnum'] : 0;
        $stored_threadnum = isset($entry['threadnum']) ? (int)$entry['threadnum'] : 0;
        if(function_exists('char_archive_calculate_old_uid_inplay_totals'))
        {
            $computed_totals = char_archive_calculate_old_uid_inplay_totals((int)$entry['old_uid'], (string)($entry['username'] ?? ''), 5);
            if(intval($computed_totals['postnum']) > 0 || intval($computed_totals['threadnum']) > 0)
            {
                $stored_postnum = intval($computed_totals['postnum']);
                $stored_threadnum = intval($computed_totals['threadnum']);
            }
        }
        $content_uid = ((int)$entry['recovered_uid'] > 0) ? (int)$entry['recovered_uid'] : (int)$entry['old_uid'];

        $live_postnum = -1;
        $live_threadnum = -1;
        if($content_uid > 0)
        {
            $user_row = $db->fetch_array($db->simple_select('users', 'postnum,threadnum', "uid={$content_uid}", array('limit' => 1)));
            if($user_row)
            {
                $live_postnum = isset($user_row['postnum']) ? (int)$user_row['postnum'] : -1;
                $live_threadnum = isset($user_row['threadnum']) ? (int)$user_row['threadnum'] : -1;
            }
        }

        $general_rows[$lang->char_archive_detail_posts] = htmlspecialchars_uni((string)$stored_postnum)
            . (($live_postnum >= 0) ? ' <span class="smalltext">(Live: ' . htmlspecialchars_uni((string)$live_postnum) . ')</span>' : '');
        $general_rows[$lang->char_archive_detail_threads] = htmlspecialchars_uni((string)$stored_threadnum)
            . (($live_threadnum >= 0) ? ' <span class="smalltext">(Live: ' . htmlspecialchars_uni((string)$live_threadnum) . ')</span>' : '');

        $old_logbook_info = char_archive_ucp_old_logbook_info((int)$entry['old_uid']);
        if($old_logbook_info !== '')
        {
            $logbook_label = isset($lang->char_archive_detail_old_logbook) ? $lang->char_archive_detail_old_logbook : 'Altes Logbook';
            $logbook_url = 'logbook.php?action=popup_view&userId=' . (int)$entry['old_uid'];
            $logbook_link_text = isset($lang->char_archive_detail_open_old_logbook)
                ? $lang->char_archive_detail_open_old_logbook
                : 'Altes Logbook aufrufen';
            $general_rows[$logbook_label] = '<a href="' . htmlspecialchars_uni($logbook_url) . '" target="_blank" rel="noopener">' . htmlspecialchars_uni($logbook_link_text) . '</a>'
                . '<br><span class="smalltext">' . htmlspecialchars_uni($old_logbook_info) . '</span>';
        }

        $old_relas_info = char_archive_ucp_old_relations_info((int)$entry['old_uid']);
        if($old_relas_info !== '')
        {
            $relas_label = isset($lang->char_archive_detail_old_relas) ? $lang->char_archive_detail_old_relas : 'Alte Relas';
            $relas_url = rtrim((string)$GLOBALS['mybb']->settings['bburl'], '/') . '/relationcp.php?action=view&id=' . (int)$entry['old_uid'];
            $relas_link_text = isset($lang->char_archive_detail_open_old_relas)
                ? $lang->char_archive_detail_open_old_relas
                : 'Alte Relas aufrufen';
            $general_rows[$relas_label] = '<a href="' . htmlspecialchars_uni($relas_url) . '" target="_blank" rel="noopener">' . htmlspecialchars_uni($relas_link_text) . '</a>'
                . '<br><span class="smalltext">' . htmlspecialchars_uni($old_relas_info) . '</span>';
        }

        $profile_rows = array();
        $profile_labels = char_archive_ucp_profile_field_labels();
        $profilefields = char_archive_decode_profilefields((string)($entry['profilefields'] ?? ''));
        if(is_array($profilefields))
        {
            foreach($profilefields as $field => $value)
            {
                if((string)$field === 'ufid')
                {
                    continue;
                }
                $display_value = (string)$value;
                if(function_exists('char_archive_fix_bare_unicode_escapes'))
                {
                    $display_value = char_archive_fix_bare_unicode_escapes($display_value);
                }

                $field_label = (string)$field;
                if(preg_match('/^fid(\d+)$/i', (string)$field, $m))
                {
                    $fid = intval($m[1]);
                    if($fid > 0 && isset($profile_labels[$fid]) && $profile_labels[$fid] !== '')
                    {
                        $field_label = (string)$profile_labels[$fid];
                    }
                    else
                    {
                        $field_label = 'fid ' . $fid;
                    }
                }

                $profile_rows[$field_label] = nl2br(htmlspecialchars_uni($display_value));
            }
        }

        $ucp_rows = array();
        $ucp_labels = char_archive_ucp_app_field_labels();
        $appdata = char_archive_decode_application_ucp_data((string)($entry['application_ucp_data'] ?? ''));
        if(isset($appdata['tables']['application_ucp_userfields']) && is_array($appdata['tables']['application_ucp_userfields']))
        {
            foreach($appdata['tables']['application_ucp_userfields'] as $field_row)
            {
                if(!is_array($field_row))
                {
                    continue;
                }
                $field_id = isset($field_row['fieldid']) ? (int)$field_row['fieldid'] : 0;
                if($field_id === 0)
                {
                    continue;
                }

                $field_value = '';
                foreach(array('fieldvalue', 'value', 'content', 'data') as $vc)
                {
                    if(array_key_exists($vc, $field_row))
                    {
                        $field_value = (string)$field_row[$vc];
                        break;
                    }
                }

                if(function_exists('char_archive_fix_bare_unicode_escapes'))
                {
                    $field_value = char_archive_fix_bare_unicode_escapes($field_value);
                }

                $field_label = '';
                foreach(array('fieldlabel', 'label', 'fieldname', 'title', 'name') as $label_key)
                {
                    if(!empty($field_row[$label_key]))
                    {
                        $field_label = trim((string)$field_row[$label_key]);
                        if($field_label !== '')
                        {
                            break;
                        }
                    }
                }
                if($field_label === '' && isset($ucp_labels[$field_id]) && $ucp_labels[$field_id] !== '')
                {
                    $field_label = (string)$ucp_labels[$field_id];
                }
                if($field_label === '')
                {
                    $field_label = 'fieldid ' . $field_id;
                }

                $ucp_rows[$field_label] = nl2br(htmlspecialchars_uni((string)$field_value));
            }
        }

        $detail_html = '';
        $detail_html .= '<h2>' . htmlspecialchars_uni($lang->char_archive_detail_title) . ': ' . $entry_username . '</h2>';
        $detail_html .= '<h3 style="margin-top:14px;">' . htmlspecialchars_uni($lang->char_archive_detail_section_general) . '</h3>';
        $detail_html .= char_archive_ucp_render_kv_table($general_rows);

        $detail_html .= '<h3 style="margin-top:14px;">' . htmlspecialchars_uni($lang->char_archive_detail_section_profilefields) . '</h3>';
        if(!empty($profile_rows))
        {
            $detail_html .= char_archive_ucp_render_kv_table($profile_rows);
        }
        else
        {
            $detail_html .= '<div class="trow1" style="padding:8px;">' . htmlspecialchars_uni($lang->char_archive_detail_no_profilefields) . '</div>';
        }

        $detail_html .= '<h3 style="margin-top:14px;">' . htmlspecialchars_uni($lang->char_archive_detail_section_ucpfields) . '</h3>';
        if(!empty($ucp_rows))
        {
            $detail_html .= char_archive_ucp_render_kv_table($ucp_rows);
        }
        else
        {
            $detail_html .= '<div class="trow1" style="padding:8px;">' . htmlspecialchars_uni($lang->char_archive_detail_no_ucpfields) . '</div>';
        }

        $page = '<html><head><title>' . htmlspecialchars_uni($mybb->settings['bbname']) . ' - ' . htmlspecialchars_uni($lang->char_archive_detail_title) . '</title>' . $headerinclude . '</head><body>' . $header;
        $page .= '<table width="100%" border="0" align="center"><tr>' . $usercpnav . '<td valign="top">' . $detail_html . '</td></tr></table>';
        $page .= $footer . '</body></html>';
        output_page($page);
        return;
    }

    $entries = array();
    $query = $db->simple_select('rpg_char_archive', '*', "main_uid={$uid}", array('order_by' => 'archived_at', 'order_dir' => 'DESC'));
    while($entry = $db->fetch_array($query)) {
        $entries[] = $entry;
    }

    add_breadcrumb($lang->ucp_nav_home, 'usercp.php');
    add_breadcrumb($lang->char_archive_title);

    $rows_html = '';
    $row_tpl = $templates->get('char_archive_row');
    foreach($entries as $entry) {
        $aid = (int)$entry['aid'];
        $username_plain = htmlspecialchars_uni($entry['username']);
        $username = '<a href="usercp.php?action=char_archive&amp;view=' . $aid . '">' . $username_plain . '</a>';
        $old_uid = (int)$entry['old_uid'];
        $archived_at = my_date('relative', (int)$entry['archived_at']);
        $notes = nl2br(htmlspecialchars_uni((string)$entry['notes']));
        $avatar = htmlspecialchars_uni($entry['avatar']);
        $status_badge = ((int)$entry['approved'] === 1)
            ? '<span class="smalltext"><strong>' . htmlspecialchars_uni($lang->char_archive_status_approved) . '</strong></span>'
            : '<span class="smalltext"><strong>' . htmlspecialchars_uni($lang->char_archive_status_pending) . '</strong></span>';

        // Theme-neutral styling: no hardcoded colours, the dark theme styles inputs itself.
        $actions = '<div style="min-width:240px;">';
        $actions .= '<a href="usercp.php?action=char_archive&amp;view=' . $aid . '">' . htmlspecialchars_uni($lang->char_archive_btn_view) . '</a>';
        if((int)$entry['recovered_uid'] === 0) {
            // Edit form collapsed by default, so the list stays compact.
            $actions .= '<details style="margin:6px 0;"><summary style="cursor:pointer;">' . htmlspecialchars_uni($lang->char_archive_btn_edit) . '</summary>';
            $actions .= '<form method="post" action="usercp.php?action=char_archive" class="trow2" style="margin:8px 0;padding:8px;">';
            $actions .= '<input type="hidden" name="my_post_key" value="' . htmlspecialchars_uni($current_post_key) . '">';
            $actions .= '<input type="hidden" name="ca_action" value="edit_entry">';
            $actions .= '<input type="hidden" name="aid" value="' . $aid . '">';
            $actions .= '<div class="smalltext" style="margin-bottom:2px;">' . htmlspecialchars_uni($lang->char_archive_col_username) . '</div><input type="text" name="username" value="' . $username_plain . '" style="width:98%;box-sizing:border-box;"><br>';
            $actions .= '<div class="smalltext" style="margin:5px 0 2px;">' . htmlspecialchars_uni($lang->char_archive_col_old_uid) . '</div><input type="text" name="old_uid" value="' . $old_uid . '" style="width:120px;box-sizing:border-box;"><br>';
            $actions .= '<div class="smalltext" style="margin:5px 0 2px;">' . htmlspecialchars_uni($lang->char_archive_col_avatar) . '</div><input type="text" name="avatar" value="' . $avatar . '" style="width:98%;box-sizing:border-box;"><br>';
            $actions .= '<div class="smalltext" style="margin:5px 0 2px;">' . htmlspecialchars_uni($lang->char_archive_col_notes) . '</div><textarea name="notes" rows="4" style="width:98%;box-sizing:border-box;">' . htmlspecialchars_uni($entry['notes']) . '</textarea>';
            $actions .= '<div style="margin-top:6px;"><input type="submit" class="button" value="' . htmlspecialchars_uni($lang->char_archive_btn_update) . '"></div>';
            $actions .= '</form>';
            $actions .= '</details>';
        }

        // Delete / recover buttons side by side in one row.
        $actions .= '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
        if((int)$entry['recovered_uid'] === 0) {
            $actions .= '<form method="post" action="usercp.php?action=char_archive" style="margin:0;">';
            $actions .= '<input type="hidden" name="my_post_key" value="' . htmlspecialchars_uni($current_post_key) . '">';
            $actions .= '<input type="hidden" name="ca_action" value="delete_entry">';
            $actions .= '<input type="hidden" name="aid" value="' . $aid . '">';
            $actions .= '<input type="submit" class="button" value="' . htmlspecialchars_uni($lang->char_archive_btn_delete) . '" onclick="return confirm(\'' . htmlspecialchars_uni($lang->char_archive_delete_confirm) . '\');">';
            $actions .= '</form>';
        }

        if((int)$entry['approved'] === 1 && (int)$entry['recovered_uid'] === 0) {
            $actions .= '<form method="post" action="usercp.php?action=char_archive" style="margin:0;">';
            $actions .= '<input type="hidden" name="my_post_key" value="' . htmlspecialchars_uni($current_post_key) . '">';
            $actions .= '<input type="hidden" name="ca_action" value="recover_entry">';
            $actions .= '<input type="hidden" name="aid" value="' . $aid . '">';
            $actions .= '<input type="submit" class="button" value="' . htmlspecialchars_uni($lang->char_archive_btn_recover) . '" onclick="return confirm(\'' . htmlspecialchars_uni($lang->char_archive_recover_confirm) . '\');">';
            $actions .= '</form>';
        }
        $actions .= '</div>';
        if((int)$entry['recovered_uid'] > 0) {
            $actions .= '<div class="smalltext" style="margin-top:6px;">' . str_replace('{1}', (int)$entry['recovered_uid'], htmlspecialchars_uni($lang->char_archive_recovery_already_done)) . '</div>';
        }
        $actions .= '</div>';

        eval("\$rows_html .= \"" . $row_tpl . "\";");
    }

    if($rows_html === '') {
        $rows_html = '<tr><td class="trow1" colspan="6">' . htmlspecialchars_uni($lang->char_archive_no_entries) . '</td></tr>';
    }

    $my_post_key = htmlspecialchars_uni($current_post_key);
    $addform_tpl = $templates->get('char_archive_addform');
    eval("\$addform = \"" . $addform_tpl . "\";");

    $status_message_html = '';
    if($status_message !== '') {
        $status_message_html = '<div class="trow1" style="margin-bottom:10px;padding:8px;">' . $status_message . '</div>';
        $status_message_html .= '<script>(function(){try{if(window.history&&window.history.replaceState){var url=new URL(window.location.href);if(url.searchParams.has("ca_msg")){url.searchParams.delete("ca_msg");window.history.replaceState({},document.title,url.pathname+(url.search?"?"+url.searchParams.toString():"")+url.hash);}}}catch(e){}})();</script>';
    }

    $list_tpl = $templates->get('char_archive_list');
    // Always render the "add archived character" form above the list table.
    // Keep template compatibility by clearing $addform before list eval and
    // prepending the generated form manually.
    $addform_top = $addform;
    $addform = '';
    eval("\$content = \"" . $list_tpl . "\";");
    $content = $addform_top . '<br>' . $content;

    $page = '<html><head><title>' . htmlspecialchars_uni($mybb->settings['bbname']) . ' - ' . htmlspecialchars_uni($lang->char_archive_title) . '</title>' . $headerinclude . '</head><body>' . $header;
    $page .= '<table width="100%" border="0" align="center"><tr>' . $usercpnav . '<td valign="top">' . $content . '</td></tr></table>';
    $page .= $footer . '</body></html>';
    output_page($page);
}
