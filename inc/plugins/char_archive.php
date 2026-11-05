<?php
if(!defined('IN_MYBB')) die('Direct initialization of this file is not allowed.');

/**
 * Character Archive Plugin for MyBB 1.8
 * Clean, consolidated implementation (install/activate/uninstall, templates, hooks)
 */

function char_archive_info()
{
    return array(
        'name' => 'Character Archive',
        'description' => 'Archive former RPG characters and allow restore.',
        'website' => 'https://github.com/pand0rica/mybb-formercharacters',
        'author' => 'pand0rica',
        'authorsite' => 'https://github.com/pand0rica',
        'version' => '0.2',
        'compatibility' => '18*'
    );
}

/* Register hooks */
global $plugins;
if(isset($plugins))
{
    // Fires inside UserDataHandler::delete_user() before any data is removed.
    // Covers all deletion paths: ACP single, ACP bulk inline, user merge, frontend.
    $plugins->add_hook('datahandler_user_delete_start', 'char_archive_hook_datahandler_delete_start');
    // ACP single-user delete: fires after confirmation, before delete_user() — kept as early-capture fallback.
    $plugins->add_hook('admin_user_users_delete_commit', 'char_archive_hook_admin_user_delete');
    $plugins->add_hook('admin_tools_menu', 'char_archive_admin_menu');
    $plugins->add_hook('admin_tools_action_handler', 'char_archive_admin_action');
    $plugins->add_hook('usercp_menu', 'char_archive_usercp_menu');
    $plugins->add_hook('usercp_start', 'char_archive_usercp_start');
    $plugins->add_hook('index_start', 'char_archive_index_start');
    $plugins->add_hook('modcp_nav', 'char_archive_modcp_nav');
    $plugins->add_hook('modcp_start', 'char_archive_modcp_start');

    // Optional integration hooks provided by external plugin "Spielerverzeichnis".
    $plugins->add_hook('playerdirectory_directory_user_stats', 'char_archive_playerdirectory_directory_user_stats');
    $plugins->add_hook('playerdirectory_playerstat_characters_html', 'char_archive_playerdirectory_playerstat_characters_html');
    // Fires after Spielerverzeichnis recalculates post/scene totals from registered chars.
    $plugins->add_hook('playerdirectory_playerstat_totals', 'char_archive_playerdirectory_playerstat_totals');
}

function char_archive_table_exists()
{
    global $db;
    return $db->table_exists('rpg_char_archive');
}

/* Admin menu/action registration */
function char_archive_admin_menu(&$sub_menu)
{
    $sub_menu[] = array(
        'id' => 'char_archive',
        'title' => 'Character Archive',
        'link' => 'index.php?module=tools-char_archive'
    );
}

function char_archive_admin_action(&$actions)
{
    // Provide both forms; some MyBB setups expect the file name with .php
    $actions['char_archive'] = array('active' => 'char_archive', 'file' => 'char_archive.php');
}

function char_archive_usercp_start()
{
    global $mybb;

    if($mybb->get_input('action') === 'char_archive')
    {
        require_once MYBB_ROOT . 'inc/char_archive_ucp.php';
        char_archive_ucp_handle();
        exit;
    }
}

function char_archive_usercp_menu()
{
    global $lang;

    if(!isset($lang->char_archive_title) || $lang->char_archive_title === '')
    {
        $lang->load('char_archive');
    }
}

function char_archive_install()
{
    global $db;

    if(!$db->table_exists('rpg_char_archive'))
    {
        $cols = array(
            'aid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT',
            'main_uid INT(10) UNSIGNED NOT NULL',
            'old_uid INT(10) UNSIGNED NOT NULL',
            'username VARCHAR(120) NOT NULL',
            'regdate INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'postnum INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'threadnum INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'avatar TEXT NULL',
            'avatartype VARCHAR(20) NOT NULL DEFAULT \'\'',
            'avatardimensions VARCHAR(20) NOT NULL DEFAULT \'\'',
            'avatar_backup TEXT NULL',
            'profilefields TEXT NULL',
            'application_ucp_data TEXT NULL',
            'notes TEXT NULL',
            'approved TINYINT(1) NOT NULL DEFAULT 0',
            'recovered_uid INT(10) UNSIGNED NULL',
            'archived_at INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'PRIMARY KEY (aid)'
        );

        $table = TABLE_PREFIX . 'rpg_char_archive';
        // Use IF NOT EXISTS to avoid SQL errors if table already exists on some systems
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (" . implode(', ', $cols) . ") ENGINE=MyISAM DEFAULT CHARSET=utf8mb4";
        $res = $db->write_query($sql);
        if($res === false)
        {
            if(method_exists($db, 'error'))
            {
                error_log('[char_archive] install: DB error: ' . $db->error());
            }
            else
            {
                error_log('[char_archive] install: CREATE TABLE query failed');
            }
        }
    }

    char_archive_settings_install();
}

function char_archive_is_installed()
{
    global $db;
    return $db->table_exists('rpg_char_archive');
}

function char_archive_uninstall()
{
    global $db;

    $db->write_query("DROP TABLE IF EXISTS " . TABLE_PREFIX . "rpg_char_archive");
    char_archive_templates_uninstall();
    char_archive_remove_usercp_nav_link();
    char_archive_remove_index_alert_placeholder();
    char_archive_remove_modcp_nav_link();
    char_archive_settings_uninstall();
    if(class_exists('cache')) $GLOBALS['cache']->delete('char_archive_data');

}

function char_archive_activate()
{
    $errors = array();

    try {
        char_archive_install();
    } catch(Exception $e) {
        $errors[] = 'install: ' . $e->getMessage();
    }
    try {
        // Always reset plugin templates so deactivate/activate restores canonical HTML.
        char_archive_templates_uninstall();
        char_archive_templates_install();
    } catch(Exception $e) {
        $errors[] = 'templates: ' . $e->getMessage();
    }
    try {
        char_archive_remove_usercp_nav_link();
        char_archive_add_usercp_nav_link();
    } catch(Exception $e) {
        $errors[] = 'usercp_nav: ' . $e->getMessage();
    }
    try {
        char_archive_remove_index_alert_placeholder();
        char_archive_remove_modcp_nav_link();
        char_archive_add_index_alert_placeholder();
        char_archive_add_modcp_nav_link();
    } catch(Exception $e) {
        $errors[] = 'nav/placeholder: ' . $e->getMessage();
    }

    if(!empty($errors))
    {
        foreach($errors as $err)
        {
            error_log('[char_archive] activate error: ' . $err);
        }
        flash_message('Character Archive: activation completed with errors – check server error log. (' . implode('; ', $errors) . ')', 'error');
    }

}

function char_archive_deactivate()
{
    char_archive_templates_uninstall();
    char_archive_remove_usercp_nav_link();
    char_archive_remove_index_alert_placeholder();
    char_archive_remove_modcp_nav_link();
    // Rebuild the AccountSwitcher cache in case it was corrupted by this plugin
    char_archive_refresh_accountswitcher_cache();

}

function char_archive_add_usercp_nav_link()
{
    // h4w: the menu row lives in the RPG category templates (usercp_nav_rpg_*), not in usercp_nav_misc.
    // Only clean up rows baked into usercp_nav_misc by earlier versions.
    char_archive_remove_usercp_nav_link();
}

function char_archive_remove_usercp_nav_link()
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
    find_replace_templatesets(
        'usercp_nav_misc',
        // Match by href + class so rows baked by older versions are removed as well.
        '#<tr><td[^>]*><a href="usercp\.php\?action=char_archive" class="usercp_nav_item usercp_nav_char_archive"[^>]*>.*?</a></td></tr>#i',
        '',
        0
    );
}

function char_archive_usercp_nav_template()
{
    return '<tr><td class="trow1 smalltext"><a href="usercp.php?action=char_archive" class="usercp_nav_item usercp_nav_char_archive" style="padding-left: 0;"><svg class="usercp_nav_icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" aria-hidden="true" style="vertical-align: -3px; margin-right: 6px;"><rect x="1.5" y="2.5" width="13" height="3.5" rx=".8" stroke-width="1.3"/><path d="M2.5 6v7a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1V6M6 9h4" stroke-width="1.3"/></svg>{$lang->char_archive_title}</a></td></tr>';
}

function char_archive_settings_install()
{
    global $db;

    $group_name = 'char_archive';
    $group = $db->fetch_array($db->simple_select('settinggroups', '*', "name='" . $db->escape_string($group_name) . "'"));
    if(!$group)
    {
        $insert_group = array(
            'name' => $group_name,
            'title' => 'Character Archive',
            'description' => 'Settings for Character Archive plugin.',
            'disporder' => 90,
            'isdefault' => 0
        );
        $db->insert_query('settinggroups', $insert_group);
        $gid = intval($db->insert_id());
    }
    else
    {
        $gid = intval($group['gid']);
    }

    $setting_name = 'char_archive_approver_groups';
    $exists = $db->fetch_field($db->simple_select('settings', 'COUNT(*) as c', "name='" . $db->escape_string($setting_name) . "'"), 'c');
    if(intval($exists) === 0)
    {
        $setting = array(
            'name' => $setting_name,
            'title' => 'Groups allowed to approve archive entries',
            'description' => 'Select groups that can see pending archive alerts and approve entries in ModCP.',
            'optionscode' => 'groupselect',
            'value' => '3,4,6',
            'disporder' => 1,
            'gid' => $gid,
            'isdefault' => 0
        );
        $db->insert_query('settings', $setting);
    }

    $summary_setting_name = 'char_archive_playerstat_summary_fieldid';
    $summary_options = char_archive_build_ucp_field_select_options();
    $summary_exists = intval($db->fetch_field($db->simple_select('settings', 'COUNT(*) as c', "name='" . $db->escape_string($summary_setting_name) . "'"), 'c'));
    if($summary_exists === 0)
    {
        $summary_setting = array(
            'name' => $summary_setting_name,
            'title' => 'Archived playerstat text: Steckbrieffeld',
            'description' => 'Waehle ein Steckbrieffeld (Application UCP), dessen Inhalt unter den Posts/Szenen archivierter Charaktere angezeigt werden soll. Wenn kein Wert gefunden wird, wird stattdessen "Notizen" aus dem Archiv-Eintrag genutzt.',
            'optionscode' => $summary_options,
            'value' => '0',
            'disporder' => 2,
            'gid' => $gid,
            'isdefault' => 0
        );
        $db->insert_query('settings', $summary_setting);
    }
    else
    {
        $db->update_query('settings', array('optionscode' => $summary_options, 'gid' => $gid), "name='" . $db->escape_string($summary_setting_name) . "'");
    }

    // Debug-Settings früherer Versionen entfernen (Debug-Code ist ausgebaut)
    $db->delete_query('settings', "name IN ('char_archive_debug_enabled','char_archive_debug_verbose_fieldid')");

    if(function_exists('rebuild_settings'))
    {
        rebuild_settings();
    }
}

function char_archive_settings_uninstall()
{
    global $db;
    $db->delete_query('settings', "name='char_archive_approver_groups'");
    $db->delete_query('settings', "name='char_archive_playerstat_summary_fieldid'");
    $db->delete_query('settings', "name='char_archive_debug_enabled'");
    $db->delete_query('settings', "name='char_archive_debug_verbose_fieldid'");
    $db->delete_query('settinggroups', "name='char_archive'");
    if(function_exists('rebuild_settings'))
    {
        rebuild_settings();
    }
}

function char_archive_build_ucp_field_select_options()
{
    global $db;

    $options = array();
    $options[] = '0=-- kein Feld / Notizen verwenden --';

    if($db->table_exists('application_ucp_fields'))
    {
        $id_col = char_archive_table_has_column('application_ucp_fields', 'id') ? 'id' : (char_archive_table_has_column('application_ucp_fields', 'fieldid') ? 'fieldid' : '');
        if($id_col !== '')
        {
            $label_col = '';
            foreach(array('label', 'fieldname', 'title', 'name') as $candidate)
            {
                if(char_archive_table_has_column('application_ucp_fields', $candidate))
                {
                    $label_col = $candidate;
                    break;
                }
            }

            $select_cols = ($label_col !== '') ? ($id_col . ',' . $label_col) : $id_col;
            $query = $db->simple_select('application_ucp_fields', $select_cols);
            while($row = $db->fetch_array($query))
            {
                $field_id = intval($row[$id_col] ?? 0);
                if($field_id <= 0)
                {
                    continue;
                }

                $field_label = ($label_col !== '') ? trim((string)($row[$label_col] ?? '')) : '';
                if($field_label === '')
                {
                    $field_label = 'Field ' . $field_id;
                }

                $field_label = str_replace(array("\r", "\n", '='), array(' ', ' ', '-'), $field_label);
                $options[] = $field_id . '=' . $field_label;
            }
        }
    }

    return 'select\n' . implode('\n', $options);
}

function char_archive_extract_ucp_field_value_from_snapshot($application_ucp_data, $field_id)
{
    $field_id = intval($field_id);
    if($field_id <= 0)
    {
        return '';
    }

    $decoded = char_archive_decode_application_ucp_data((string)$application_ucp_data);
    if(!is_array($decoded))
    {
        return '';
    }

    $rows = $decoded['tables']['application_ucp_userfields'] ?? array();
    if(!is_array($rows))
    {
        return '';
    }

    foreach($rows as $row)
    {
        if(!is_array($row) || intval($row['fieldid'] ?? 0) !== $field_id)
        {
            continue;
        }

        foreach(array('fieldvalue', 'value', 'content', 'data') as $value_key)
        {
            if(!array_key_exists($value_key, $row))
            {
                continue;
            }

            $value = trim((string)$row[$value_key]);
            if($value !== '')
            {
                return $value;
            }
        }
    }

    return '';
}

function char_archive_get_playerstat_archived_text($row)
{
    global $mybb;

    $normalize_text = function($value)
    {
        $value = trim((string)$value);
        if($value === '')
        {
            return '';
        }

        if(function_exists('char_archive_fix_bare_unicode_escapes'))
        {
            $fixed = char_archive_fix_bare_unicode_escapes($value);
            // Some snapshots contain escaped unicode like "\\u00f6".
            if($fixed === $value && preg_match('/\\\\u[0-9a-fA-F]{4}/', $value))
            {
                $fixed = char_archive_fix_bare_unicode_escapes(str_replace('\\u', 'u', $value));
            }
            $value = $fixed;
        }

        return $value;
    };

    $field_id = intval($mybb->settings['char_archive_playerstat_summary_fieldid'] ?? 0);
    if($field_id > 0)
    {
        $from_snapshot = char_archive_extract_ucp_field_value_from_snapshot((string)($row['application_ucp_data'] ?? ''), $field_id);
        if($from_snapshot !== '')
        {
            return $normalize_text($from_snapshot);
        }
    }

    return $normalize_text((string)($row['notes'] ?? ''));
}

function char_archive_get_approver_groups()
{
    global $mybb;
    $raw = isset($mybb->settings['char_archive_approver_groups']) ? (string)$mybb->settings['char_archive_approver_groups'] : '';
    $parts = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
    $groups = array();
    foreach($parts as $part)
    {
        $gid = intval($part);
        if($gid > 0)
        {
            $groups[] = $gid;
        }
    }
    return array_values(array_unique($groups));
}

function char_archive_user_can_approve($user = null)
{
    global $mybb;

    if($user === null)
    {
        $user = $mybb->user;
    }
    if(empty($user['uid']))
    {
        return false;
    }

    $allowed_groups = char_archive_get_approver_groups();
    if(empty($allowed_groups))
    {
        return false;
    }

    $user_groups = array(intval($user['usergroup']));
    if(!empty($user['additionalgroups']))
    {
        foreach(explode(',', (string)$user['additionalgroups']) as $g)
        {
            $gid = intval(trim($g));
            if($gid > 0)
            {
                $user_groups[] = $gid;
            }
        }
    }

    return count(array_intersect($allowed_groups, array_unique($user_groups))) > 0;
}

function char_archive_pending_count()
{
    global $db;
    if(!char_archive_table_exists())
    {
        return 0;
    }
    $count = $db->fetch_field($db->simple_select('rpg_char_archive', 'COUNT(*) as c', 'approved=0'), 'c');
    return intval($count);
}

function char_archive_index_start()
{
    global $mybb, $templates, $char_archive_index_alert, $lang;

    $char_archive_index_alert = '';
    if(!char_archive_user_can_approve())
    {
        return;
    }

    $pending_count = char_archive_pending_count();
    if($pending_count <= 0)
    {
        return;
    }

    $lang->load('char_archive');
    $pending_text = str_replace('{1}', $pending_count, $lang->char_archive_pending_alert_text);
    $modcp_link = 'modcp.php?action=char_archive';
    eval('$char_archive_index_alert = "' . $templates->get('char_archive_index_alert') . '";');
}

function char_archive_modcp_start()
{
    global $mybb, $lang;

    // Load language for all ModCP pages so the injected nav label is always available.
    $lang->load('char_archive');

    if($mybb->get_input('action') === 'char_archive')
    {
        require_once MYBB_ROOT . 'inc/char_archive_modcp.php';
        char_archive_modcp_handle();
        exit;
    }
}

function char_archive_modcp_nav()
{
    global $templates, $lang, $modcp_nav_users, $modcp_nav_user, $modcp_nav_forums_posts, $modcp_nav_misc;

    $lang->load('char_archive');
    if(!char_archive_user_can_approve())
    {
        return;
    }

    $targets = array('modcp_nav_users', 'modcp_nav_user', 'modcp_nav_forums_posts', 'modcp_nav_misc');
    $target_var = 'modcp_nav_users';
    foreach($targets as $candidate)
    {
        if(isset($GLOBALS[$candidate]))
        {
            $target_var = $candidate;
            if(strpos((string)$GLOBALS[$candidate], 'action=char_archive') !== false)
            {
                return;
            }
            break;
        }
    }

    $current_nav = (string)$GLOBALS[$target_var];
    if(strpos($current_nav, 'action=char_archive') !== false)
    {
        return;
    }

    $is_list_layout = false;
    if(isset($templates))
    {
        $modcp_nav_tpl = (string)$templates->get('modcp_nav');
        $is_list_layout = (strpos($modcp_nav_tpl, '<li') !== false);
    }

    if($is_list_layout || strpos($current_nav, '<li') !== false)
    {
        $GLOBALS[$target_var] .= '<li><a href="modcp.php?action=char_archive" class="modcp_nav_item">'
            . $lang->char_archive_modcp_nav
            . '</a></li>';
    }
    else
    {
        $GLOBALS[$target_var] .= '<tr><td class="trow1 smalltext"><a href="modcp.php?action=char_archive" class="modcp_nav_item">'
            . $lang->char_archive_modcp_nav
            . '</a></td></tr>';
    }
}

function char_archive_add_index_alert_placeholder()
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
    find_replace_templatesets(
        'index',
        '#' . preg_quote('{$header}') . '#i',
        '{$header}' . "\n" . '{$char_archive_index_alert}'
    );
}

function char_archive_remove_index_alert_placeholder()
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
    find_replace_templatesets(
        'index',
        '#' . preg_quote('{$char_archive_index_alert}') . '#i',
        '',
        0
    );
}

function char_archive_modcp_nav_template()
{
    return '<tr><td class="trow1 smalltext"><a href="modcp.php?action=char_archive" class="modcp_nav_item">{$lang->char_archive_modcp_nav}</a></td></tr>';
}

function char_archive_modcp_nav_template_list()
{
    return '<li><a href="modcp.php?action=char_archive" class="modcp_nav_item">{$lang->char_archive_modcp_nav}</a></li>';
}

function char_archive_add_modcp_nav_link()
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    // Remove both possible nav variants before adding a fresh entry.
    char_archive_remove_modcp_nav_link();

    // Classic table-based ModCP nav templates.
    find_replace_templatesets(
        'modcp_nav',
        '#</table>#i',
        char_archive_modcp_nav_template() . '</table>'
    );

    // Modern/list-based ModCP nav templates used by some themes.
    find_replace_templatesets(
        'modcp_nav',
        '#</ul>#i',
        char_archive_modcp_nav_template_list() . '</ul>'
    );
}

function char_archive_remove_modcp_nav_link()
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';
    find_replace_templatesets(
        'modcp_nav',
        '#' . preg_quote(char_archive_modcp_nav_template()) . '#i',
        '',
        0
    );
    find_replace_templatesets(
        'modcp_nav',
        '#' . preg_quote(char_archive_modcp_nav_template_list()) . '#i',
        '',
        0
    );
}

/* Templates management (basic templates for UCP + admin) */
function char_archive_templates_install()
{
    global $db;
    $tpls = array(
        'char_archive_list' => '{$status_message_html}' . "\n"
            . '<table border="0" cellspacing="1" cellpadding="6" class="tborder" style="width:100%;">' . "\n"
            . '<tr><td class="thead" colspan="6"><strong>{$lang->char_archive_title}</strong></td></tr>' . "\n"
            . '<tr>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_username}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_old_uid}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_status}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_archived_at}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_notes}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_action}</strong></td>' . "\n"
            . '</tr>' . "\n"
            . '{$rows_html}' . "\n"
            . '</table>' . "\n"
            . '<br>' . "\n"
            . '{$addform}',
        'char_archive_row' => '<tr>' . "\n"
            . '<td class="trow1">{$username}</td>' . "\n"
            . '<td class="trow1">{$old_uid}</td>' . "\n"
            . '<td class="trow1">{$status_badge}</td>' . "\n"
            . '<td class="trow1">{$archived_at}</td>' . "\n"
            . '<td class="trow1">{$notes}</td>' . "\n"
            . '<td class="trow1">{$actions}</td>' . "\n"
            . '</tr>',
        'char_archive_addform' => '<table border="0" cellspacing="1" cellpadding="6" class="tborder" style="width:100%;">' . "\n"
            . '<tr><td class="thead" colspan="2"><strong>{$lang->char_archive_add_form_title}</strong></td></tr>' . "\n"
            . '<tr><td class="trow1" colspan="2">' . "\n"
            . '<form method="post" action="usercp.php?action=char_archive">' . "\n"
            . '<input type="hidden" name="my_post_key" value="{$my_post_key}">' . "\n"
            . '<input type="hidden" name="ca_action" value="add_entry">' . "\n"
            . '<div><label>{$lang->char_archive_col_old_uid}</label><br><input type="text" name="old_uid" style="width:320px;"></div><br>' . "\n"
            . '<div><label>{$lang->char_archive_col_username}</label><br><input type="text" name="username" style="width:320px;"></div><br>' . "\n"
            . '<div><label>{$lang->char_archive_col_avatar}</label><br><input type="text" name="avatar" style="width:320px;"></div><br>' . "\n"
            . '<div><label>{$lang->char_archive_col_notes}</label><br><textarea name="notes" rows="4" cols="60"></textarea></div><br>' . "\n"
            . '<input type="submit" name="add_archive" value="{$lang->char_archive_btn_add}">' . "\n"
            . '</form>' . "\n"
            . '</td></tr>' . "\n"
            . '</table>',

        // admin templates

        // index/modcp templates
        'char_archive_index_alert' => '<div class="red_alert" style="margin:10px auto;max-width:1200px;">' . "\n"
            . '<a href="{$modcp_link}">{$pending_text}</a>' . "\n"
            . '</div>',
        'char_archive_modcp_list' => '{$status_message_html}' . "\n"
            . '<table border="0" cellspacing="1" cellpadding="6" class="tborder" style="width:100%;">' . "\n"
            . '<tr><td class="thead" colspan="5"><strong>{$lang->char_archive_modcp_title}</strong></td></tr>' . "\n"
            . '<tr>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_username}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_old_uid}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_archived_at}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_notes}</strong></td>' . "\n"
            . '<td class="tcat"><strong>{$lang->char_archive_col_action}</strong></td>' . "\n"
            . '</tr>' . "\n"
            . '{$rows_html}' . "\n"
            . '</table>',
        'char_archive_modcp_row' => '<tr>' . "\n"
            . '<td class="trow1" style="min-width:180px;">{$username}</td>' . "\n"
            . '<td class="trow1" style="white-space:nowrap;">{$old_uid}</td>' . "\n"
            . '<td class="trow1" style="white-space:nowrap;">{$archived_at}</td>' . "\n"
            . '<td class="trow1" style="min-width:160px;">{$notes}</td>' . "\n"
            . '<td class="trow1" style="white-space:nowrap;">{$actions}</td>' . "\n"
            . '</tr>',
    );

    foreach($tpls as $title => $content)
    {
        $where = "title='" . $db->escape_string($title) . "'";
        // Root-fix for legacy/stale variants: keep exactly one canonical master template.
        $db->delete_query('templates', $where);
        $ins = array(
            'title' => $title,
            'template' => $content,
            'sid' => -2,
            'version' => '',
            'dateline' => TIME_NOW
        );
        $db->insert_query('templates', $ins);
    }
}

function char_archive_templates_uninstall()
{
    global $db;
    $titles = array('char_archive_list','char_archive_row','char_archive_addform','char_archive_admin_main','char_archive_admin_row','char_archive_admin_pending_table','char_archive_admin_confirm','char_archive_index_alert','char_archive_modcp_list','char_archive_modcp_row');
    foreach($titles as $t) $db->delete_query('templates', "title='" . $db->escape_string($t) . "'");
}

function char_archive_users_has_column($column)
{
    global $db;

    static $cache = array();
    $column = trim((string)$column);
    if($column === '')
    {
        return false;
    }
    if(isset($cache[$column]))
    {
        return $cache[$column];
    }

    $users_table = TABLE_PREFIX . 'users';
    $safe_column = $db->escape_string($column);
    $q = $db->write_query("SHOW COLUMNS FROM `{$users_table}` LIKE '{$safe_column}'");
    $has_column = ($q && $db->num_rows($q) > 0);
    $cache[$column] = $has_column;

    return $has_column;
}

function char_archive_resolve_main_uid($uid)
{
    global $db;

    $uid = intval($uid);
    if($uid <= 0)
    {
        return 0;
    }

    // Account Switcher default schema: users.as_uid points from character to main account.
    if(char_archive_users_has_column('as_uid'))
    {
        $mapped_uid = intval($db->fetch_field($db->simple_select('users', 'as_uid', "uid={$uid}"), 'as_uid'));
        if($mapped_uid > 0 && $mapped_uid !== $uid)
        {
            return $mapped_uid;
        }
    }

    // Fallback schema: dedicated mapping table (e.g. mybb_users_as).
    if($db->table_exists('users_as'))
    {
        $candidate_fields = array('main_uid', 'as_uid', 'uid_master', 'master_uid', 'parent_uid');
        foreach($candidate_fields as $field)
        {
            $safe_field = $db->escape_string($field);
            $q = $db->write_query("SHOW COLUMNS FROM `" . TABLE_PREFIX . "users_as` LIKE '{$safe_field}'");
            if(!$q || $db->num_rows($q) === 0)
            {
                continue;
            }

            $mapped_uid = intval($db->fetch_field($db->simple_select('users_as', $field, "uid={$uid}"), $field));
            if($mapped_uid > 0 && $mapped_uid !== $uid)
            {
                return $mapped_uid;
            }
        }
    }

    return 0;
}

function char_archive_table_has_column($table_name, $column)
{
    global $db;

    static $cache = array();
    $table_name = trim((string)$table_name);
    $column = trim((string)$column);
    if($table_name === '' || $column === '')
    {
        return false;
    }
    $cache_key = $table_name . ':' . $column;
    if(isset($cache[$cache_key]))
    {
        return $cache[$cache_key];
    }

    if(!$db->table_exists($table_name))
    {
        $cache[$cache_key] = false;
        return false;
    }

    $table = TABLE_PREFIX . $table_name;
    $safe_column = $db->escape_string($column);
    $q = $db->write_query("SHOW COLUMNS FROM `{$table}` LIKE '{$safe_column}'");
    $has_column = ($q && $db->num_rows($q) > 0);
    $cache[$cache_key] = $has_column;

    return $has_column;
}

function char_archive_refresh_accountswitcher_cache()
{
    $class_file = defined('MYBB_ROOT')
        ? MYBB_ROOT . 'inc/plugins/accountswitcher/class_accountswitcher.php'
        : '';

    if($class_file === '' || !file_exists($class_file))
    {
        return false;
    }

    if(!isset($GLOBALS['mybb']) || !isset($GLOBALS['db']) || !isset($GLOBALS['cache']) || !isset($GLOBALS['templates']))
    {
        return false;
    }

    if(!class_exists('AccountSwitcher'))
    {
        require_once $class_file;
    }

    if(!class_exists('AccountSwitcher'))
    {
        return false;
    }

    $eas = new AccountSwitcher($GLOBALS['mybb'], $GLOBALS['db'], $GLOBALS['cache'], $GLOBALS['templates']);
    $eas->update_accountswitcher_cache();
    return true;
}

function char_archive_force_stats_cache_newest_member($new_uid, $username, $numusers)
{
    global $db;

    $new_uid = intval($new_uid);
    $username = (string)$username;
    $numusers = intval($numusers);
    if($new_uid <= 0 || $username === '')
    {
        return false;
    }

    $payload = array(
        'lastuid' => $new_uid,
        'lastusername' => $username,
        'numusers' => $numusers,
        // Compatibility aliases used by some custom templates/plugins.
        'newestuid' => $new_uid,
        'newestuser' => $username,
    );

    if(isset($GLOBALS['cache']) && is_object($GLOBALS['cache']))
    {
        $stats_cache = $GLOBALS['cache']->read('stats');
        if(!is_array($stats_cache))
        {
            $stats_cache = array();
        }

        $stats_cache = array_merge($stats_cache, $payload);
        $GLOBALS['cache']->update('stats', $stats_cache);
        return true;
    }

    if($db->table_exists('datacache'))
    {
        $encoded = serialize($payload);
        $exists = $db->fetch_field($db->simple_select('datacache', 'title', "title='stats'", array('limit' => 1)), 'title');
        if($exists)
        {
            $db->update_query('datacache', array('cache' => $encoded, 'dateline' => TIME_NOW), "title='stats'");
        }
        else
        {
            $db->insert_query('datacache', array('title' => 'stats', 'cache' => $encoded, 'dateline' => TIME_NOW));
        }
        return true;
    }

    return false;
}

function char_archive_refresh_newest_user_stats($new_uid, $username)
{
    global $db, $cache;
    $new_uid = intval($new_uid);
    $username = (string)$username;

    if(!function_exists('rebuild_stats') && defined('MYBB_ROOT') && file_exists(MYBB_ROOT . 'inc/functions_rebuild.php'))
    {
        require_once MYBB_ROOT . 'inc/functions_rebuild.php';
    }

    // Follow MyBB core pattern after user changes:
    // 1) rebuild stats, 2) run update_stats with numusers +0 to refresh lastuid/lastusername.
    if(function_exists('rebuild_stats'))
    {
        rebuild_stats();
    }
    if(function_exists('update_stats'))
    {
        update_stats(array('numusers' => '+0'), true);
    }

    $stats = array();
    if(isset($cache) && is_object($cache))
    {
        $stats = $cache->read('stats');
        if(!is_array($stats) && method_exists($cache, 'update_stats'))
        {
            $cache->update_stats();
            $stats = $cache->read('stats');
        }
    }
    if(!is_array($stats))
    {
        $stats = array();
    }

    $newest_uid = intval($stats['lastuid'] ?? 0);
    $newest_username = (string)($stats['lastusername'] ?? '');

    // Fallback in case cache is still empty/incomplete.
    if($newest_uid <= 0 || $newest_username === '')
    {
        $latest = $db->fetch_array($db->simple_select('users', 'uid,username', '', array('order_by' => 'regdate', 'order_dir' => 'DESC', 'limit' => 1)));
        if(!$latest || empty($latest['uid']))
        {
            return false;
        }

        $newest_uid = intval($latest['uid']);
        $newest_username = (string)$latest['username'];

        if(isset($cache) && is_object($cache))
        {
            $stats['lastuid'] = $newest_uid;
            $stats['lastusername'] = $newest_username;
            $cache->update('stats', $stats);
        }
    }

    $settings_updated = array();
    if($db->table_exists('settings'))
    {
        if($db->fetch_field($db->simple_select('settings', 'name', "name='newestmemberuid'", array('limit' => 1)), 'name'))
        {
            $db->update_query('settings', array('value' => (string)$newest_uid), "name='newestmemberuid'");
            $settings_updated[] = 'newestmemberuid';
        }
        if($db->fetch_field($db->simple_select('settings', 'name', "name='newestmember'", array('limit' => 1)), 'name'))
        {
            $db->update_query('settings', array('value' => $db->escape_string($newest_username)), "name='newestmember'");
            $settings_updated[] = 'newestmember';
        }
    }

    if(!empty($settings_updated))
    {
        if(function_exists('rebuild_settings'))
        {
            rebuild_settings();
        }

        if(isset($GLOBALS['mybb']) && is_object($GLOBALS['mybb']) && isset($GLOBALS['mybb']->settings) && is_array($GLOBALS['mybb']->settings))
        {
            $GLOBALS['mybb']->settings['newestmemberuid'] = (string)$newest_uid;
            $GLOBALS['mybb']->settings['newestmember'] = $newest_username;
        }

        if(isset($GLOBALS['cache']) && is_object($GLOBALS['cache']))
        {
            $settings_cache = $GLOBALS['cache']->read('settings');
            if(is_array($settings_cache))
            {
                $settings_cache['newestmemberuid'] = (string)$newest_uid;
                $settings_cache['newestmember'] = $newest_username;
                $GLOBALS['cache']->update('settings', $settings_cache);
            }
        }
    }

    return true;
}

function char_archive_resolve_local_avatar_path($avatar)
{
    global $mybb;

    $avatar = trim((string)$avatar);
    if($avatar === '' || !defined('MYBB_ROOT'))
    {
        return '';
    }

    if(preg_match('#^(?:https?:)?//#i', $avatar))
    {
        return '';
    }

    $avatar_no_query = preg_replace('/[?#].*$/', '', $avatar);
    if(!is_string($avatar_no_query))
    {
        $avatar_no_query = $avatar;
    }

    $bburl = isset($mybb->settings['bburl']) ? rtrim((string)$mybb->settings['bburl'], '/') : '';
    if($bburl !== '' && strpos($avatar_no_query, $bburl . '/') === 0)
    {
        $avatar_no_query = substr($avatar_no_query, strlen($bburl) + 1);
    }

    $avatar_no_query = ltrim(str_replace('\\', '/', $avatar_no_query), '/');
    if(strpos($avatar_no_query, './') === 0)
    {
        $avatar_no_query = substr($avatar_no_query, 2);
    }

    if($avatar_no_query === '' || strpos($avatar_no_query, '..') !== false)
    {
        return '';
    }

    $absolute = rtrim(MYBB_ROOT, '\\/') . '/' . $avatar_no_query;
    if(!is_file($absolute))
    {
        return '';
    }

    return $absolute;
}

function char_archive_backup_avatar_file($avatar, $uid)
{
    $source_file = char_archive_resolve_local_avatar_path($avatar);
    if($source_file === '' || !defined('MYBB_ROOT'))
    {
        return '';
    }

    $uid = intval($uid);
    if($uid <= 0)
    {
        return '';
    }

    $backup_dir_rel = './uploads/char_archive_avatars';
    $backup_dir_abs = rtrim(MYBB_ROOT, '\\/') . '/' . $backup_dir_rel;
    if(!is_dir($backup_dir_abs))
    {
        @mkdir($backup_dir_abs, 0777, true);
    }
    if(!is_dir($backup_dir_abs))
    {
        return '';
    }

    $ext = strtolower(pathinfo($source_file, PATHINFO_EXTENSION));
    if($ext === '')
    {
        $ext = 'img';
    }

    $filename = 'ca_uid' . $uid . '_' . TIME_NOW . '_' . substr(md5($source_file . microtime(true) . mt_rand()), 0, 10) . '.' . $ext;
    $target_abs = $backup_dir_abs . '/' . $filename;
    if(!@copy($source_file, $target_abs))
    {
        return '';
    }

    @chmod($target_abs, 0644);
    $backup_rel = $backup_dir_rel . '/' . $filename;
    return $backup_rel;
}

function char_archive_calculate_avatar_dimensions($avatar)
{
    $file = char_archive_resolve_local_avatar_path($avatar);
    if($file === '')
    {
        return '';
    }

    $size = @getimagesize($file);
    if(!is_array($size) || empty($size[0]) || empty($size[1]))
    {
        return '';
    }

    return intval($size[0]) . '|' . intval($size[1]);
}

function char_archive_fix_bare_unicode_escapes($value)
{
    $value = (string)$value;
    if($value === '' || !preg_match('/(?<!\\\\)u[0-9a-fA-F]{4}/', $value))
    {
        return $value;
    }

    $converted = preg_replace_callback(
        '/(?<!\\\\)u([0-9a-fA-F]{4})/',
        function($m)
        {
            $hex = strtoupper((string)$m[1]);
            $bytes = @pack('H*', $hex);
            if($bytes === false)
            {
                return $m[0];
            }

            if(function_exists('mb_convert_encoding'))
            {
                $char = @mb_convert_encoding($bytes, 'UTF-8', 'UCS-2BE');
                if(is_string($char) && $char !== '')
                {
                    return $char;
                }
            }

            if(function_exists('iconv'))
            {
                $char = @iconv('UCS-2BE', 'UTF-8//IGNORE', $bytes);
                if(is_string($char) && $char !== '')
                {
                    return $char;
                }
            }

            return $m[0];
        },
        $value
    );

    if(!is_string($converted) || $converted === '')
    {
        return $value;
    }

    return $converted;
}

function char_archive_restore_avatar_for_user_with_meta($uid, $avatar, $avatartype = '', $avatardimensions = '', $avatar_backup = '')
{
    global $db;

    $uid = intval($uid);
    $avatar = trim((string)$avatar);
    $avatartype = trim((string)$avatartype);
    $avatardimensions = trim((string)$avatardimensions);
    $avatar_backup = trim((string)$avatar_backup);

    if($uid <= 0 || !$db->table_exists('users') || !char_archive_users_has_column('avatar'))
    {
        return false;
    }

    $selected_avatar = $avatar;
    if($selected_avatar === '' || char_archive_resolve_local_avatar_path($selected_avatar) === '')
    {
        if($avatar_backup !== '' && char_archive_resolve_local_avatar_path($avatar_backup) !== '')
        {
            $selected_avatar = $avatar_backup;
        }
    }

    if($selected_avatar === '')
    {
        return false;
    }

    $update = array('avatar' => $selected_avatar);

    if(char_archive_users_has_column('avatartype'))
    {
        if($avatartype !== '')
        {
            $update['avatartype'] = $avatartype;
        }
        else
        {
            $is_remote = (strpos($selected_avatar, 'http://') === 0 || strpos($selected_avatar, 'https://') === 0 || strpos($selected_avatar, '//') === 0);
            $update['avatartype'] = $is_remote ? 'remote' : 'upload';
        }
    }

    if(char_archive_users_has_column('avatardimensions'))
    {
        if($avatardimensions === '')
        {
            $avatardimensions = char_archive_calculate_avatar_dimensions($selected_avatar);
        }
        if($avatardimensions !== '')
        {
            $update['avatardimensions'] = $avatardimensions;
        }
    }

    $db->update_query('users', $update, "uid={$uid}");
    return true;
}

function char_archive_remap_relations_user($old_uid, $new_uid)
{
    global $db;

    $old_uid = intval($old_uid);
    $new_uid = intval($new_uid);
    if($old_uid <= 0 || $new_uid <= 0 || $old_uid === $new_uid)
    {
        return;
    }

    if($db->table_exists('relations_infos') && char_archive_table_has_column('relations_infos', 'uid'))
    {
        $db->update_query('relations_infos', array('uid' => $new_uid), "uid={$old_uid}");
    }

    if($db->table_exists('relations_kategorien') && char_archive_table_has_column('relations_kategorien', 'uid'))
    {
        $db->update_query('relations_kategorien', array('uid' => $new_uid), "uid={$old_uid}");
    }

    if($db->table_exists('relations_beziehungen'))
    {
        if(char_archive_table_has_column('relations_beziehungen', 'uid1'))
        {
            $db->update_query('relations_beziehungen', array('uid1' => $new_uid), "uid1={$old_uid}");
        }
        if(char_archive_table_has_column('relations_beziehungen', 'uid2'))
        {
            $db->update_query('relations_beziehungen', array('uid2' => $new_uid), "uid2={$old_uid}");
        }
    }

    if($db->table_exists('relations_beziehungen_details') && char_archive_table_has_column('relations_beziehungen_details', 'uid'))
    {
        $db->update_query('relations_beziehungen_details', array('uid' => $new_uid), "uid={$old_uid}");
    }
}

function char_archive_remap_logbook_user($old_uid, $new_uid)
{
    global $db;

    $old_uid = intval($old_uid);
    $new_uid = intval($new_uid);
    if($old_uid <= 0 || $new_uid <= 0 || $old_uid === $new_uid)
    {
        return;
    }

    if(!$db->table_exists('logbook'))
    {
        return;
    }

    $old_logbook = $db->fetch_array($db->simple_select('logbook', '*', "user_id={$old_uid}", array('limit' => 1)));
    $new_logbook = $db->fetch_array($db->simple_select('logbook', '*', "user_id={$new_uid}", array('limit' => 1)));

    if(!$old_logbook)
    {
        return;
    }

    $old_logbook_id = intval($old_logbook['id'] ?? 0);
    $new_logbook_id = intval($new_logbook['id'] ?? 0);

    if($new_logbook_id > 0 && $new_logbook_id !== $old_logbook_id)
    {
        if($db->table_exists('logbook_entry') && char_archive_table_has_column('logbook_entry', 'logbook_id'))
        {
            $db->update_query('logbook_entry', array('logbook_id' => $new_logbook_id), "logbook_id={$old_logbook_id}");
        }

        $logbook_update = array();
        foreach(array('description', 'template_id', 'show_icon') as $field)
        {
            if(array_key_exists($field, $old_logbook) && char_archive_table_has_column('logbook', $field))
            {
                $logbook_update[$field] = $old_logbook[$field];
            }
        }

        if(!empty($logbook_update))
        {
            $db->update_query('logbook', $logbook_update, "id={$new_logbook_id}");
        }

        $db->delete_query('logbook', "id={$old_logbook_id}");
        return;
    }

    $db->update_query('logbook', array('user_id' => $new_uid), "id={$old_logbook_id}");
}

function char_archive_remap_content_user($old_uid, $new_uid, $username, $old_username = '')
{
    global $db;

    $old_uid      = intval($old_uid);
    $new_uid      = intval($new_uid);
    $username     = (string)$username;
    $old_username = trim((string)$old_username);

    if($new_uid <= 0)
    {
        return;
    }

    if($old_uid > 0 && $old_uid === $new_uid)
    {
        return;
    }

    // WHERE for post/thread author
    $author_where_parts = array();
    if($old_uid > 0)
    {
        $author_where_parts[] = "uid={$old_uid}";
    }
    if($old_username !== '')
    {
        $author_where_parts[] = "username='" . $db->escape_string($old_username) . "'";
    }

    // WHERE for last-poster columns
    $lastposter_where_parts = array();
    if($old_uid > 0)
    {
        $lastposter_where_parts[] = "lastposteruid={$old_uid}";
    }
    if($old_username !== '')
    {
        $lastposter_where_parts[] = "lastposter='" . $db->escape_string($old_username) . "'";
    }

    // Escape the new username once — used directly in write_query strings
    $esc_username  = $db->escape_string($username);
    $posts_table   = TABLE_PREFIX . 'posts';
    $threads_table = TABLE_PREFIX . 'threads';
    $forums_table  = TABLE_PREFIX . 'forums';

    if(!empty($author_where_parts))
    {
        $author_where = '(' . implode(' OR ', $author_where_parts) . ')';

        if($db->table_exists('posts'))
        {
            $db->write_query("UPDATE {$posts_table} SET uid={$new_uid}, username='{$esc_username}' WHERE {$author_where}");
        }

        if($db->table_exists('threads'))
        {
            $db->write_query("UPDATE {$threads_table} SET uid={$new_uid}, username='{$esc_username}' WHERE {$author_where}");
        }
    }

    if(!empty($lastposter_where_parts))
    {
        $lp_where = '(' . implode(' OR ', $lastposter_where_parts) . ')';

        if($db->table_exists('threads'))
        {
            $db->write_query("UPDATE {$threads_table} SET lastposteruid={$new_uid}, lastposter='{$esc_username}' WHERE {$lp_where}");
        }

        if($db->table_exists('forums'))
        {
            $db->write_query("UPDATE {$forums_table} SET lastposteruid={$new_uid}, lastposter='{$esc_username}' WHERE {$lp_where}");
        }
    }

    // Rebuild post and thread counters for the recovered user
    $counter_update = array();
    if($db->table_exists('posts'))
    {
        $counter_update['postnum'] = (int)$db->fetch_field(
            $db->simple_select('posts', 'COUNT(*) AS cnt', "uid={$new_uid}"),
            'cnt'
        );
    }
    if($db->table_exists('threads'))
    {
        $counter_update['threadnum'] = (int)$db->fetch_field(
            $db->simple_select('threads', 'COUNT(*) AS cnt', "uid={$new_uid}"),
            'cnt'
        );
    }
    if(!empty($counter_update))
    {
        $db->update_query('users', $counter_update, "uid={$new_uid}");
    }
}

function char_archive_notify_main_account_archived($main_uid, $archived_username, $old_uid, $aid)
{
    global $db, $lang, $mybb;

    $main_uid = intval($main_uid);
    if($main_uid <= 0)
    {
        return false;
    }

    $lang->load('char_archive');

    $title = $lang->char_archive_alert_deleted_title;
    $link = rtrim((string)$mybb->settings['bburl'], '/') . '/usercp.php?action=char_archive';
    $message = str_replace(
        array('{1}', '{2}'),
        array((string)$archived_username, intval($old_uid)),
        $lang->char_archive_alert_deleted_message
    );
    $message .= ' ' . $link;

    // Preferred compatibility API used by many plugins.
    if(function_exists('myalerts_create_alert'))
    {
        $ok = myalerts_create_alert($main_uid, 'char_archive_deleted', $title . ': ' . $message, $link);
        if($ok !== false)
        {
            return true;
        }

        // Fallback to a guaranteed core type if custom code is rejected.
        $ok = myalerts_create_alert($main_uid, 'pm', $title . ': ' . $message, $link);
        if($ok !== false)
        {
            return true;
        }
    }

    // MyAlerts 2.x schema fallback.
    if($db->table_exists('alerts') && $db->table_exists('alert_types'))
    {
        $alert_type_id = intval($db->fetch_field(
            $db->simple_select('alert_types', 'id', "code='pm' AND enabled='1'", array('limit' => 1)),
            'id'
        ));

        if($alert_type_id > 0)
        {
            $extra_details = json_encode(array(
                'pm_title' => $title . ': ' . $message,
                'link' => $link
            ));

            $insert = array(
                'uid' => $main_uid,
                'unread' => 1,
                'dateline' => date('Y-m-d H:i:s'),
                'alert_type_id' => $alert_type_id,
                'object_id' => 0,
                'from_user_id' => 0,
                'forced' => 1,
                'extra_details' => $extra_details
            );

            $db->insert_query('alerts', $insert);
            if(intval($db->insert_id()) > 0)
            {
                return true;
            }
        }
    }

    // Legacy schema fallback.
    if($db->table_exists('myalerts_alerts'))
    {
        $insert = array(
            'user_to' => $main_uid,
            'dateline' => TIME_NOW,
            'title' => $title,
            'message' => $message,
        );

        if(char_archive_table_has_column('myalerts_alerts', 'user_from')) $insert['user_from'] = 0;
        if(char_archive_table_has_column('myalerts_alerts', 'user_from_id')) $insert['user_from_id'] = 0;
        if(char_archive_table_has_column('myalerts_alerts', 'from_user')) $insert['from_user'] = 0;
        if(char_archive_table_has_column('myalerts_alerts', 'uid')) $insert['uid'] = $main_uid;
        if(char_archive_table_has_column('myalerts_alerts', 'unread')) $insert['unread'] = 1;
        if(char_archive_table_has_column('myalerts_alerts', 'read')) $insert['read'] = 0;
        if(char_archive_table_has_column('myalerts_alerts', 'url')) $insert['url'] = $link;
        if(char_archive_table_has_column('myalerts_alerts', 'link')) $insert['link'] = $link;
        if(char_archive_table_has_column('myalerts_alerts', 'object_id')) $insert['object_id'] = intval($aid);
        if(char_archive_table_has_column('myalerts_alerts', 'content_id')) $insert['content_id'] = intval($aid);

        $db->insert_query('myalerts_alerts', $insert);
        if(intval($db->insert_id()) > 0)
        {
            return true;
        }
    }

    return false;
}

function char_archive_collect_application_ucp_snapshot($uid)
{
    global $db;

    $uid = intval($uid);
    if($uid <= 0)
    {
        return array();
    }

    if(!$db->table_exists('application_ucp_userfields'))
    {
        return array();
    }

    // Find which column holds the field value (name varies by plugin version)
    $value_column = '';
    foreach(array('value', 'fieldvalue', 'content', 'data') as $candidate)
    {
        if(char_archive_table_has_column('application_ucp_userfields', $candidate))
        {
            $value_column = $candidate;
            break;
        }
    }

    if($value_column === '' || !char_archive_table_has_column('application_ucp_userfields', 'fieldid'))
    {
        return array();
    }

    // Capture only the minimal essential data: fieldid + value.
    // Deliberately avoiding SELECT * to prevent auto-increment PKs and JOIN
    // metadata columns from polluting the snapshot and breaking re-insertion.
    // ALWAYS normalize to 'fieldvalue' key so restore can find it.
    $rows = array();
    $q = $db->simple_select('application_ucp_userfields', 'fieldid, ' . $value_column, "uid={$uid}");
    while($row = $db->fetch_array($q))
    {
        $rows[] = array(
            'fieldid'    => intval($row['fieldid']),
            'fieldvalue' => (string)($row[$value_column] ?? ''),
        );
    }

    return $rows;
}

function char_archive_decode_application_ucp_data($raw)
{
    $raw = (string)$raw;

    if(trim($raw) === '')
    {
        return array('tables' => array());
    }

    $decoded = json_decode($raw, true);
    if(is_string($decoded) && $decoded !== '')
    {
        // Some legacy rows contain double-encoded JSON strings.
        $decoded2 = json_decode($decoded, true);
        if(is_array($decoded2))
        {
            $decoded = $decoded2;
        }
    }

    if(!is_array($decoded))
    {
        $repaired_raw = $raw;

        // Normalize bare unicode markers like "u00e4" to proper JSON escapes.
        if(preg_match('/(?<!\\\\)u[0-9a-fA-F]{4}/', $repaired_raw))
        {
            $repaired_raw = preg_replace('/(?<!\\\\)u([0-9a-fA-F]{4})/', '\\\\u$1', $repaired_raw);
        }

        // Escape bare newlines/tabs inside JSON string values before stripping anything.
        $repaired_raw = preg_replace_callback(
            '/"(?:[^"\\\\]|\\\\.)*"/s',
            function($m)
            {
                return str_replace(
                    array("\n",  "\r",  "\t",  "\x0B", "\x0C"),
                    array('\\n', '\\r', '\\t', '\\u000b', '\\u000c'),
                    $m[0]
                );
            },
            (string)$repaired_raw
        );

        // Remove remaining disallowed control chars (outside strings or unpaired).
        $repaired_raw = preg_replace('/[\x00-\x08\x0E-\x1F]/', '', (string)$repaired_raw);

        if(function_exists('mb_check_encoding') && function_exists('mb_convert_encoding'))
        {
            if(!mb_check_encoding((string)$repaired_raw, 'UTF-8'))
            {
                $repaired_raw = @mb_convert_encoding((string)$repaired_raw, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
            }
        }

        $decoded = json_decode((string)$repaired_raw, true);
    }

    if(!is_array($decoded))
    {
        // Last-resort salvage for malformed legacy snapshots: extract UCP rows.
        $salvaged_rows = array();
        if(preg_match_all('/"fieldid"\s*:\s*(-?\d+)\s*,\s*"(?:fieldvalue|value|content|data)"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s', $raw, $matches, PREG_SET_ORDER))
        {
            foreach($matches as $match)
            {
                $field_id = intval($match[1] ?? 0);
                if($field_id === 0)
                {
                    continue;
                }

                $field_value = stripcslashes((string)($match[2] ?? ''));
                $salvaged_rows[] = array(
                    'fieldid' => $field_id,
                    'fieldvalue' => $field_value,
                );
            }
        }

        if(!empty($salvaged_rows))
        {
            return array('tables' => array('application_ucp_userfields' => $salvaged_rows));
        }

        return array('tables' => array());
    }

    // New flat format: [{"fieldid":1,"fieldvalue":"..."}, ...] – stored directly since v0.3.
    // Detect by checking for a numeric-indexed array whose first element has 'fieldid'.
    if(isset($decoded[0]) && is_array($decoded[0]) && array_key_exists('fieldid', $decoded[0]))
    {
        $rows = array();
        foreach($decoded as $row)
        {
            if(is_array($row) && !empty($row))
            {
                $rows[] = $row;
            }
        }
        return array('tables' => array('application_ucp_userfields' => $rows));
    }

    // Legacy nested format: {"format":...,"tables":{"application_ucp_userfields":[...]}}
    $tables = isset($decoded['tables']) && is_array($decoded['tables']) ? $decoded['tables'] : array();

    $normalized_tables = array();
    foreach($tables as $table => $rows)
    {
        if(!is_string($table) || $table === '' || !is_array($rows))
        {
            continue;
        }

        $is_row_list = isset($rows[0]) && is_array($rows[0]);
        $rows = $is_row_list ? $rows : array($rows);

        foreach($rows as $row)
        {
            if(is_array($row) && !empty($row))
            {
                $normalized_tables[$table][] = $row;
            }
        }
    }

    return array('tables' => $normalized_tables);
}

function char_archive_encode_profilefields($profilefields)
{
    if(!is_array($profilefields) || empty($profilefields))
    {
        return '';
    }

    return (string)json_encode($profilefields);
}

function char_archive_decode_profilefields($raw)
{
    $raw = (string)$raw;
    if(trim($raw) === '')
    {
        return array();
    }

    $decoded = json_decode($raw, true);

    // Handle legacy double-encoded JSON (stored as JSON string instead of array).
    if(is_string($decoded) && $decoded !== '')
    {
        $decoded2 = json_decode($decoded, true);
        if(is_array($decoded2))
        {
            $decoded = $decoded2;
        }
    }

    // If decode failed (e.g. bare newlines/control chars in field values), attempt repair.
    if(!is_array($decoded))
    {
        // Escape raw control characters that appear inside JSON string values.
        // Matches a JSON string (including already-escaped sequences) and escapes
        // any bare \n, \r, \t that are not already escaped.
        $repaired = preg_replace_callback(
            '/"(?:[^"\\\\]|\\\\.)*"/s',
            function($m)
            {
                return str_replace(
                    array("\n",  "\r",  "\t",  "\x0B", "\x0C"),
                    array('\\n', '\\r', '\\t', '\\u000b', '\\u000c'),
                    $m[0]
                );
            },
            $raw
        );
        // Strip remaining disallowed control chars (outside strings or unpaired).
        $repaired = preg_replace('/[\x00-\x08\x0E-\x1F]/', '', (string)$repaired);
        if(function_exists('mb_check_encoding') && function_exists('mb_convert_encoding'))
        {
            if(!mb_check_encoding((string)$repaired, 'UTF-8'))
            {
                $repaired = @mb_convert_encoding((string)$repaired, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
            }
        }
        $decoded = json_decode((string)$repaired, true);
    }

    return is_array($decoded) ? $decoded : array();
}

/* Archive a user before deletion */
function char_archive_archive_user($uid, $prefetched_user = array(), $options = array())
{
    global $db;
    static $processed_uids = array();

    $uid = intval($uid); if($uid<=0) return false;
    if(isset($processed_uids[$uid])) return true;
    if(!char_archive_table_exists()) return false;

    $user = array();
    if(is_array($prefetched_user) && !empty($prefetched_user))
    {
        $user = $prefetched_user;
        if(!isset($user['uid']))
        {
            $user['uid'] = $uid;
        }
    }
    else
    {
        $user = $db->fetch_array($db->simple_select('users','*',"uid={$uid}"));
    }

    if(!$user) return false;

    // try profilefields
    $profilefields = null;
    if($db->table_exists('userfields'))
    {
        $res = $db->simple_select('userfields','*',"ufid={$uid}");
        if($res) $profilefields = $db->fetch_array($res);
    }

    $appdata = char_archive_collect_application_ucp_snapshot($uid);

    $main_uid = char_archive_resolve_main_uid($uid);
    $auto_approve = !empty($options['auto_approve']) ? 1 : 0;
    $notify_main = array_key_exists('notify_main', $options) ? !empty($options['notify_main']) : true;

    // Safely encode application data (flat rows array: [{"fieldid":1,"fieldvalue":"..."}, ...])
    $encoded_appdata = (!empty($appdata) && is_array($appdata)) ? json_encode($appdata) : '';
    if($encoded_appdata === false) $encoded_appdata = '';

    $avatar = (string)($user['avatar'] ?? '');
    $avatartype = (string)($user['avatartype'] ?? '');
    $avatardimensions = (string)($user['avatardimensions'] ?? '');
    if($avatardimensions === '' && $avatar !== '')
    {
        $avatardimensions = char_archive_calculate_avatar_dimensions($avatar);
    }
    $avatar_backup = char_archive_backup_avatar_file($avatar, $uid);

    $data = array(
        'main_uid' => $main_uid,
        'old_uid' => $uid,
        'username' => $db->escape_string((string)($user['username'] ?? '')),
        'regdate' => intval($user['regdate'] ?? 0),
        'postnum' => intval($user['postnum'] ?? 0),
        'threadnum' => intval($user['threadnum'] ?? 0),
        'avatar' => $db->escape_string($avatar),
        'profilefields' => $db->escape_string(char_archive_encode_profilefields($profilefields)),
        'application_ucp_data' => $db->escape_string($encoded_appdata),
        'notes' => '',
        'approved' => $auto_approve,
        'recovered_uid' => null,
        'archived_at' => TIME_NOW
    );

    if(char_archive_table_has_column('rpg_char_archive', 'avatartype'))
    {
        $data['avatartype'] = $db->escape_string($avatartype);
    }
    if(char_archive_table_has_column('rpg_char_archive', 'avatardimensions'))
    {
        $data['avatardimensions'] = $db->escape_string($avatardimensions);
    }
    if(char_archive_table_has_column('rpg_char_archive', 'avatar_backup'))
    {
        $data['avatar_backup'] = $db->escape_string($avatar_backup);
    }

    $db->insert_query('rpg_char_archive', $data);
    $aid = intval($db->insert_id());
    if($aid <= 0)
    {
        return false;
    }

    if($notify_main && $main_uid > 0)
    {
        char_archive_notify_main_account_archived($main_uid, (string)($user['username'] ?? ''), $uid, $aid);
    }

    $processed_uids[$uid] = true;
    return true;
}

function char_archive_archive_from_hook_payload($payload, $options = array())
{
    $uids = array();
    $users_by_uid = array();

    if(is_array($payload))
    {
        if(isset($payload['uid']))
        {
            $uids[] = intval($payload['uid']);
        }

        foreach(array('deleteuid', 'deluid', 'useruid', 'id') as $key)
        {
            if(isset($payload[$key]))
            {
                $uids[] = intval($payload[$key]);
            }
        }

        if(isset($payload['uids']))
        {
            if(is_array($payload['uids']))
            {
                foreach($payload['uids'] as $candidate_uid)
                {
                    $uids[] = intval($candidate_uid);
                }
            }
            else
            {
                $raw_uids = explode(',', (string)$payload['uids']);
                foreach($raw_uids as $candidate_uid)
                {
                    $uids[] = intval(trim($candidate_uid));
                }
            }
        }

        if(isset($payload['users']) && is_array($payload['users']))
        {
            foreach($payload['users'] as $u)
            {
                if(!is_array($u) || !isset($u['uid']))
                {
                    continue;
                }
                $candidate_uid = intval($u['uid']);
                if($candidate_uid > 0)
                {
                    $uids[] = $candidate_uid;
                    $users_by_uid[$candidate_uid] = $u;
                }
            }
        }

        if(isset($payload['username']) && isset($payload['uid']))
        {
            $candidate_uid = intval($payload['uid']);
            if($candidate_uid > 0)
            {
                $users_by_uid[$candidate_uid] = $payload;
            }
        }

        if(isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['uid']))
        {
            $candidate_uid = intval($payload[0]['uid']);
            if($candidate_uid > 0)
            {
                $uids[] = $candidate_uid;
                $users_by_uid[$candidate_uid] = $payload[0];
            }
        }

        if(isset($payload[0]) && is_numeric($payload[0]))
        {
            $uids[] = intval($payload[0]);
        }
    }
    elseif(is_numeric($payload))
    {
        $uids[] = intval($payload);
    }

    if(empty($uids) && isset($GLOBALS['mybb']) && is_object($GLOBALS['mybb']))
    {
        foreach(array('uid', 'deleteuid', 'deluid', 'id') as $input_key)
        {
            if(isset($GLOBALS['mybb']->input[$input_key]))
            {
                $uids[] = intval($GLOBALS['mybb']->input[$input_key]);
            }
        }

        if(isset($GLOBALS['mybb']->input['uids']))
        {
            $raw_uids = explode(',', (string)$GLOBALS['mybb']->input['uids']);
            foreach($raw_uids as $candidate_uid)
            {
                $uids[] = intval(trim($candidate_uid));
            }
        }

        if(empty($uids)
            && defined('THIS_SCRIPT')
            && THIS_SCRIPT === 'member.php'
            && !empty($GLOBALS['mybb']->user['uid']))
        {
            $uids[] = intval($GLOBALS['mybb']->user['uid']);
            $users_by_uid[intval($GLOBALS['mybb']->user['uid'])] = $GLOBALS['mybb']->user;
        }
    }

    $normalized_uids = array();
    foreach($uids as $candidate_uid)
    {
        $candidate_uid = intval($candidate_uid);
        if($candidate_uid > 0)
        {
            $normalized_uids[] = $candidate_uid;
        }
    }
    $uids = array_values(array_unique($normalized_uids));

    foreach($uids as $uid)
    {
        $user = isset($users_by_uid[$uid]) ? $users_by_uid[$uid] : array();
        char_archive_archive_user($uid, $user, $options);
    }
}

/* Recover archived entry: create user, restore data, remap posts/logs, mark recovered. Returns array(new_uid,password) or false */
function char_archive_recover_entry($aid)
{
    global $db, $mybb;
    $aid = intval($aid); if($aid<=0) return false;

    $table = 'rpg_char_archive';
    $entry = $db->fetch_array($db->simple_select($table,'*',"aid={$aid}"));
    if(!$entry)
    {
        return false;
    }
    if(intval($entry['recovered_uid'])>0)
    {
        return false;
    }
    if(intval($entry['approved'])==0)
    {
        return false;
    }

    $desired_username = $entry['username'] ?: ('recovered_'.$aid);
    $base = $desired_username; $i=0;
    while(true)
    {
        $cnt = $db->fetch_field($db->simple_select('users','COUNT(*) as c',"username='".$db->escape_string($desired_username)."'"),'c');
        if(intval($cnt)==0) break; $i++; $desired_username = $base . '_' . $i;
    }

    // email/password: prefer main account credentials
    $email = '';
    $main_password_hash = '';
    $main_salt = '';
    if(!empty($entry['main_uid'])){
        $main = $db->fetch_array($db->simple_select('users','email,password,salt','uid='.intval($entry['main_uid'])));
        if(!empty($main['email'])) $email = $main['email'];
        if(!empty($main['password'])) $main_password_hash = (string)$main['password'];
        if(!empty($main['salt'])) $main_salt = (string)$main['salt'];
    }
    if(empty($email)) $email = 'recovered+' . $aid . '+' . TIME_NOW . '@local.invalid';

    // The recovered account takes over the main account's password. Without a main
    // account password there is no safe way to hand out credentials (no temporary
    // plaintext passwords by mail or on screen) – refuse the recovery instead.
    if($main_password_hash === '' || $main_salt === '')
    {
        return array('error' => 'no_main_account');
    }
    $salt = $main_salt;
    $hashed_pw = $main_password_hash;

    // Generate a loginkey — MyBB uses this for session/switch validation.
    // Without it the AccountSwitcher cannot switch to this account and logs the user out.
    $loginkey = function_exists('generate_loginkey')
        ? generate_loginkey()
        : substr(str_repeat(md5(uniqid(mt_rand(), true)), 2), 0, 50);

    $user_insert = array(
        'username' => $db->escape_string($desired_username),
        'password' => $db->escape_string($hashed_pw),
        'salt' => $db->escape_string($salt),
        'loginkey' => $db->escape_string($loginkey),
        'email' => $db->escape_string($email),
        
        'usergroup' => 2,
        // Use current registration timestamp like MyBB user registration flow.
        'regdate' => TIME_NOW,
        'lastactive' => TIME_NOW
    );

    if(!empty($entry['main_uid']) && char_archive_users_has_column('as_uid'))
    {
        $user_insert['as_uid'] = intval($entry['main_uid']);
    }

    $db->insert_query('users', $user_insert);
    $new_uid = $db->insert_id(); if(!$new_uid) return false;

    if(!empty($entry['avatar']) || !empty($entry['avatar_backup']))
    {
        char_archive_restore_avatar_for_user_with_meta(
            $new_uid,
            (string)($entry['avatar'] ?? ''),
            (string)($entry['avatartype'] ?? ''),
            (string)($entry['avatardimensions'] ?? ''),
            (string)($entry['avatar_backup'] ?? '')
        );
    }

    if(!empty($entry['main_uid']) && char_archive_users_has_column('as_uid'))
    {
        char_archive_refresh_accountswitcher_cache();
    }

    // restore profilefields
    if(isset($entry['profilefields']) && $entry['profilefields'] !== null && (string)$entry['profilefields'] !== '' && $db->table_exists('userfields'))
    {
        $pf = char_archive_decode_profilefields($entry['profilefields']);
        if(is_array($pf) && !empty($pf))
        {
            $pf['ufid'] = $new_uid;
            $db->delete_query('userfields', "ufid={$new_uid}");
            $db->insert_query('userfields', $pf);
        }
    }

    // restore application_ucp rows
    if(isset($entry['application_ucp_data']) && $entry['application_ucp_data'] !== null && (string)$entry['application_ucp_data'] !== '' && $db->table_exists('application_ucp_userfields') && char_archive_table_has_column('application_ucp_userfields', 'fieldid'))
    {
        $decoded  = char_archive_decode_application_ucp_data($entry['application_ucp_data']);
        $ucp_rows = array();

        if(!empty($decoded['tables']['application_ucp_userfields']) && is_array($decoded['tables']['application_ucp_userfields']))
        {
            $ucp_rows = $decoded['tables']['application_ucp_userfields'];
        }

        if(!empty($ucp_rows))
        {
            // Determine the value column name in the live table
            $value_column = '';
            foreach(array('value', 'fieldvalue', 'content', 'data') as $candidate)
            {
                if(char_archive_table_has_column('application_ucp_userfields', $candidate))
                {
                    $value_column = $candidate;
                    break;
                }
            }

            if($value_column !== '')
            {
                $db->delete_query('application_ucp_userfields', "uid={$new_uid}");
                $insert_count = 0;
                $skip_empty_row = 0;
                $skip_bad_fieldid = 0;
                $skip_zero_fieldid = 0;
                $skip_no_value = 0;
                foreach($ucp_rows as $field_row)
                {
                    if(!is_array($field_row) || empty($field_row))
                    {
                        $skip_empty_row++;
                        continue;
                    }
                    if(!array_key_exists('fieldid', $field_row) || !is_numeric($field_row['fieldid']))
                    {
                        $skip_bad_fieldid++;
                        continue;
                    }
                    $field_id = intval($field_row['fieldid']);
                    if($field_id === 0)
                    {
                        $skip_zero_fieldid++;
                        continue;
                    }
                    // Accept values under any known column name and prefer
                    // the first non-empty value to handle mixed legacy rows
                    // that may carry both value + fieldvalue keys.
                    $field_value = null;
                    $fallback_value = null;
                    foreach(array($value_column, 'fieldvalue', 'value', 'content', 'data') as $vc)
                    {
                        if(!array_key_exists($vc, $field_row))
                        {
                            continue;
                        }

                        $candidate_value = (string)$field_row[$vc];
                        if($fallback_value === null)
                        {
                            $fallback_value = $candidate_value;
                        }

                        if($candidate_value !== '')
                        {
                            $field_value = $candidate_value;
                            break;
                        }
                    }
                    if($field_value === null)
                    {
                        $field_value = $fallback_value;
                    }
                    if($field_value === null)
                    {
                        $skip_no_value++;
                        continue;
                    }

                    $field_value = char_archive_fix_bare_unicode_escapes($field_value);

                    $db->insert_query('application_ucp_userfields', array(
                        'uid'        => $new_uid,
                        'fieldid'    => $field_id,
                        $value_column => $field_value,
                    ));
                    $insert_count++;
                }
            }
        }
    }

    $old_uid = intval($entry['old_uid']);
    char_archive_remap_content_user($old_uid, $new_uid, $desired_username, (string)$entry['username']);
    if($old_uid>0){
        char_archive_remap_logbook_user($old_uid, $new_uid);
        char_archive_remap_relations_user($old_uid, $new_uid);
    }

    char_archive_refresh_newest_user_stats($new_uid, $desired_username);
    char_archive_refresh_accountswitcher_cache();

    // mark recovered
    $table = 'rpg_char_archive';
    $db->update_query($table, array('recovered_uid'=>$new_uid), "aid={$aid}");

    return array('new_uid'=>$new_uid);
}

/* Hook callbacks for deletion */

// Fires inside UserDataHandler::delete_user() before any rows are touched.
// Covers all deletion paths: ACP single, ACP bulk inline, user merge, frontend.
// delete_uids is still an array here; implode() runs after this hook.
function char_archive_hook_datahandler_delete_start(&$handler)
{
    if(!is_object($handler) || !isset($handler->delete_uids) || !is_array($handler->delete_uids))
    {
        return;
    }
    foreach($handler->delete_uids as $uid)
    {
        $uid = intval($uid);
        if($uid > 0)
        {
            char_archive_archive_user($uid, array(), array('auto_approve' => true, 'notify_main' => true));
        }
    }
}

// ACP single-user delete: fires before delete_user() — kept as early-capture fallback.
// static $processed_uids in char_archive_archive_user prevents double-archiving.
function char_archive_hook_admin_user_delete(&$user){
    char_archive_archive_from_hook_payload($user, array('auto_approve' => true, 'notify_main' => true));
}

/* Public compatibility helpers for external plugins (e.g. Spielerverzeichnis). */
function char_archive_get_archived_characters_for_main($main_uid, $options = array())
{
    global $db;

    $main_uid = intval($main_uid);
    if($main_uid <= 0 || !char_archive_table_exists())
    {
        return array();
    }

    $include_pending = !empty($options['include_pending']);
    $include_recovered = !empty($options['include_recovered']);

    $where = "main_uid={$main_uid}";
    if(!$include_pending)
    {
        $where .= " AND approved='1'";
    }
    if(!$include_recovered)
    {
        $where .= " AND (recovered_uid IS NULL OR recovered_uid='0')";
    }

    $query = $db->simple_select(
        'rpg_char_archive',
        'aid, main_uid, old_uid, username, postnum, threadnum, avatar, avatar_backup, avatartype, avatardimensions, notes, application_ucp_data, archived_at, approved, recovered_uid',
        $where,
        array('order_by' => 'archived_at', 'order_dir' => 'DESC')
    );

    $rows = array();
    while($row = $db->fetch_array($query))
    {
        $rows[] = array(
            'aid' => intval($row['aid']),
            'main_uid' => intval($row['main_uid']),
            'old_uid' => intval($row['old_uid']),
            'username' => (string)$row['username'],
            'postnum' => intval($row['postnum']),
            'threadnum' => intval($row['threadnum']),
            'avatar' => (string)($row['avatar'] ?? ''),
            'avatar_backup' => (string)($row['avatar_backup'] ?? ''),
            'avatartype' => (string)($row['avatartype'] ?? ''),
            'avatardimensions' => (string)($row['avatardimensions'] ?? ''),
            'notes' => (string)($row['notes'] ?? ''),
            'application_ucp_data' => (string)($row['application_ucp_data'] ?? ''),
            'archived_at' => intval($row['archived_at']),
            'approved' => intval($row['approved']),
            'recovered_uid' => intval($row['recovered_uid']),
        );
    }

    return $rows;
}

function char_archive_get_archived_totals_for_main($main_uid, $options = array())
{
    $rows = char_archive_get_archived_characters_for_main($main_uid, $options);

    $totals = array(
        'count' => 0,
        'postnum' => 0,
        'threadnum' => 0,
        // Alias used by some plugins naming "scenes" instead of threads.
        'scenenum' => 0,
        'characters' => $rows,
    );

    foreach($rows as $row)
    {
        $totals['count']++;
        $totals['postnum'] += intval($row['postnum']);
        $totals['threadnum'] += intval($row['threadnum']);
    }

    $totals['scenenum'] = $totals['threadnum'];
    return $totals;
}

function char_archive_playerdirectory_collect_scene_tids_for_character($old_uid, $username, $inplaytrackersystem)
{
    global $db;

    $old_uid = intval($old_uid);
    $username = trim((string)$username);
    $inplaytrackersystem = intval($inplaytrackersystem);
    $scene_tids = array();

    if($old_uid <= 0)
    {
        return $scene_tids;
    }

    // sparks fly 2.0
    if($inplaytrackersystem === 0)
    {
        $query = $db->query("SELECT tid FROM " . TABLE_PREFIX . "threads WHERE (concat(',',partners,',') LIKE '%," . $old_uid . ",%') ORDER BY tid ASC");
        while($row = $db->fetch_array($query))
        {
            $scene_tids[] = intval($row['tid']);
        }
    }
    // sparks fly 3.0
    else if($inplaytrackersystem === 1 && $db->table_exists('ipt_scenes') && $db->table_exists('ipt_scenes_partners'))
    {
        $query = $db->query("SELECT ips.tid FROM " . TABLE_PREFIX . "ipt_scenes ips LEFT JOIN " . TABLE_PREFIX . "ipt_scenes_partners ipsp ON ips.tid = ipsp.tid WHERE ipsp.uid='" . $old_uid . "' ORDER BY ips.tid ASC");
        while($row = $db->fetch_array($query))
        {
            $scene_tids[] = intval($row['tid']);
        }
    }
    // risuena scenetracker
    else if($inplaytrackersystem === 2 && $db->table_exists('scenetracker'))
    {
        $query = $db->query("SELECT tid FROM " . TABLE_PREFIX . "scenetracker WHERE uid='" . $old_uid . "' ORDER BY tid ASC");
        while($row = $db->fetch_array($query))
        {
            $scene_tids[] = intval($row['tid']);
        }
    }
    // little.evil.genius tracker
    else if($inplaytrackersystem === 3 && $db->table_exists('inplayscenes'))
    {
        $query = $db->query("SELECT tid FROM " . TABLE_PREFIX . "inplayscenes WHERE (concat(',',partners,',') LIKE '%," . $old_uid . ",%') ORDER BY tid ASC");
        while($row = $db->fetch_array($query))
        {
            $scene_tids[] = intval($row['tid']);
        }
    }
    // Ales tracker (username-based)
    else if($inplaytrackersystem === 4 && $username !== '')
    {
        $esc_username = $db->escape_string($username);
        $query = $db->query("SELECT tid FROM " . TABLE_PREFIX . "threads WHERE (concat(', ',spieler,',') LIKE '%, " . $esc_username . ",%') ORDER BY tid ASC");
        while($row = $db->fetch_array($query))
        {
            $scene_tids[] = intval($row['tid']);
        }
    }
    // Sarah tracker via LogbookController (if available in runtime)
    else if($inplaytrackersystem === 5)
    {
        if(class_exists('LogbookController'))
        {
            $controller = new LogbookController($db);
            if(method_exists($controller, 'getCompleteLogbook'))
            {
                $scenes = $controller->getCompleteLogbook($old_uid);
                if(is_array($scenes))
                {
                    foreach($scenes as $scene)
                    {
                        if(isset($scene['tid']))
                        {
                            $scene_tids[] = intval($scene['tid']);
                        }
                    }
                }
            }
        }

        // Fallback for archived/deleted characters: read logbook data directly.
        // Some tracker implementations return empty via controller for deleted UIDs,
        // while DB rows still exist and contain the scene thread IDs.
        $scene_column = '';
        if(char_archive_table_has_column('logbook_entry', 'tid'))
        {
            $scene_column = 'tid';
        }
        else if(char_archive_table_has_column('logbook_entry', 'scene_id'))
        {
            $scene_column = 'scene_id';
        }

        if(empty($scene_tids)
            && $scene_column !== ''
            && $db->table_exists('logbook')
            && $db->table_exists('logbook_entry')
            && char_archive_table_has_column('logbook', 'id')
            && char_archive_table_has_column('logbook', 'user_id')
            && char_archive_table_has_column('logbook_entry', 'logbook_id'))
        {
            if($scene_column === 'scene_id' && $db->table_exists('scene')
                && char_archive_table_has_column('scene', 'id')
                && char_archive_table_has_column('scene', 'tid'))
            {
                $query = $db->query(
                    "SELECT s.tid AS scene_tid FROM " . TABLE_PREFIX . "logbook l "
                    . "LEFT JOIN " . TABLE_PREFIX . "logbook_entry le ON le.logbook_id = l.id "
                    . "LEFT JOIN " . TABLE_PREFIX . "scene s ON s.id = le.scene_id "
                    . "WHERE l.user_id='" . $old_uid . "' "
                    . "ORDER BY s.tid ASC"
                );
            }
            else
            {
                $query = $db->query(
                    "SELECT le." . $scene_column . " AS scene_tid FROM " . TABLE_PREFIX . "logbook l "
                    . "LEFT JOIN " . TABLE_PREFIX . "logbook_entry le ON le.logbook_id = l.id "
                    . "WHERE l.user_id='" . $old_uid . "' "
                    . "ORDER BY le." . $scene_column . " ASC"
                );
            }

            while($row = $db->fetch_array($query))
            {
                $scene_tids[] = intval($row['scene_tid']);
            }
        }
    }

    $scene_tids = array_values(array_unique(array_filter(array_map('intval', $scene_tids))));
    return $scene_tids;
}

function char_archive_count_all_posts_for_character($old_uid, $username)
{
    global $db;

    if(!$db->table_exists('posts'))
    {
        return array('postnum' => 0, 'threadnum' => 0);
    }

    $old_uid = intval($old_uid);
    $username = trim((string)$username);
    $where_author = array();

    if($old_uid > 0)
    {
        $where_author[] = "uid='" . $old_uid . "'";
    }
    if($username !== '')
    {
        $where_author[] = "username='" . $db->escape_string($username) . "'";
    }

    if(empty($where_author))
    {
        return array('postnum' => 0, 'threadnum' => 0);
    }

    $author_where = '(' . implode(' OR ', $where_author) . ')';
    $query = $db->query(
        "SELECT COUNT(pid) AS post_count, COUNT(DISTINCT tid) AS scene_count FROM " . TABLE_PREFIX . "posts "
        . "WHERE " . $author_where . " AND visible='1'"
    );
    $row = $db->fetch_array($query);

    return array(
        'postnum' => intval($row['post_count'] ?? 0),
        'threadnum' => intval($row['scene_count'] ?? 0),
    );
}

function char_archive_calculate_old_uid_inplay_totals($old_uid, $username = '', $inplaytrackersystem = 5)
{
    global $db;

    $old_uid = intval($old_uid);
    $username = trim((string)$username);
    $inplaytrackersystem = intval($inplaytrackersystem);

    $scene_tids = char_archive_playerdirectory_collect_scene_tids_for_character($old_uid, $username, $inplaytrackersystem);
    $scene_count = count($scene_tids);
    $post_count = char_archive_playerdirectory_count_posts_for_character($old_uid, $username, $scene_tids, $inplaytrackersystem);
    $source = 'tracker';

    // Manual archive fallback for tracker 5: if scenes are known via logbook
    // but post authors are no longer linked by UID, count by stored username.
    if($inplaytrackersystem === 5 && $scene_count > 0 && $post_count <= 0 && $username !== '' && $db->table_exists('posts'))
    {
        $tids_string = implode(',', array_values(array_unique(array_filter(array_map('intval', $scene_tids)))));
        if($tids_string !== '')
        {
            $query = $db->query(
                "SELECT COUNT(pid) AS cnt FROM " . TABLE_PREFIX . "posts "
                . "WHERE username='" . $db->escape_string($username) . "' "
                . "AND tid IN (" . $tids_string . ") "
                . "AND visible='1'"
            );
            $username_post_count = intval($db->fetch_field($query, 'cnt'));
            if($username_post_count > 0)
            {
                $post_count = $username_post_count;
                $source = 'tracker_username_fallback';
            }
        }
    }

    // Tracker 5 fallback for manually archived characters:
    // if old logbook is unavailable, treat all visible posts as inplay content.
    if($inplaytrackersystem === 5 && $scene_count <= 0)
    {
        $fallback = char_archive_count_all_posts_for_character($old_uid, $username);
        if(intval($fallback['postnum']) > 0 || intval($fallback['threadnum']) > 0)
        {
            $post_count = intval($fallback['postnum']);
            $scene_count = intval($fallback['threadnum']);
            $source = 'posts_fallback';
        }
    }

    return array(
        'postnum' => max(0, intval($post_count)),
        'threadnum' => max(0, intval($scene_count)),
        'source' => $source,
    );
}

function char_archive_playerdirectory_count_posts_for_character($old_uid, $username, $scene_tids, $inplaytrackersystem = null)
{
    global $db;

    $old_uid = intval($old_uid);
    $username = trim((string)$username);
    if(!is_array($scene_tids) || empty($scene_tids) || !$db->table_exists('posts'))
    {
        return 0;
    }

    $scene_tids = array_values(array_unique(array_filter(array_map('intval', $scene_tids))));
    if(empty($scene_tids))
    {
        return 0;
    }

    $tids_string = implode(',', $scene_tids);
    $inplaytrackersystem = ($inplaytrackersystem === null) ? -1 : intval($inplaytrackersystem);
    $where_author = array();

    // For tracker 5, count by archived old UID only as requested.
    if($inplaytrackersystem === 5)
    {
        if($old_uid > 0)
        {
            $where_author[] = "p.uid='" . $old_uid . "'";
        }
    }
    else
    {
        if($old_uid > 0)
        {
            $where_author[] = "p.uid='" . $old_uid . "'";
        }
        if($username !== '')
        {
            $where_author[] = "p.username='" . $db->escape_string($username) . "'";
        }
    }

    if(empty($where_author))
    {
        return 0;
    }

    $author_where = '(' . implode(' OR ', $where_author) . ')';
    $query = $db->query(
        "SELECT COUNT(pid) AS cnt FROM " . TABLE_PREFIX . "posts p "
        . "WHERE " . $author_where . " "
        . "AND p.tid IN (" . $tids_string . ") "
        . "AND p.visible='1'"
    );

    return intval($db->fetch_field($query, 'cnt'));
}

function char_archive_playerdirectory_get_registered_character_rows_for_main($main_uid)
{
    global $db;

    $main_uid = intval($main_uid);
    if($main_uid <= 0 || !$db->table_exists('users'))
    {
        return array();
    }

    $rows = array();
    $query = $db->simple_select('users', 'uid,username', "uid='" . $main_uid . "' OR as_uid='" . $main_uid . "'");
    while($row = $db->fetch_array($query))
    {
        $uid = intval($row['uid']);
        if($uid <= 0)
        {
            continue;
        }
        $rows[$uid] = array(
            'uid' => $uid,
            'username' => (string)($row['username'] ?? ''),
        );
    }

    return array_values($rows);
}

function char_archive_playerdirectory_calculate_registered_inplay_totals($main_uid, $inplaytrackersystem)
{
    $inplaytrackersystem = intval($inplaytrackersystem);
    $characters = char_archive_playerdirectory_get_registered_character_rows_for_main($main_uid);
    if(empty($characters))
    {
        return array('count' => 0, 'postnum' => 0, 'threadnum' => 0);
    }

    $total_posts = 0;
    $total_scenes = 0;
    foreach($characters as $character)
    {
        $uid = intval($character['uid']);
        $username = (string)$character['username'];
        $scene_tids = char_archive_playerdirectory_collect_scene_tids_for_character($uid, $username, $inplaytrackersystem);
        $total_scenes += count($scene_tids);
        $total_posts += char_archive_playerdirectory_count_posts_for_character($uid, $username, $scene_tids, $inplaytrackersystem);
    }

    return array(
        'count' => count($characters),
        'postnum' => max(0, intval($total_posts)),
        'threadnum' => max(0, intval($total_scenes)),
    );
}

function char_archive_playerdirectory_calculate_archived_inplay_totals($archived_rows, $inplaytrackersystem)
{
    $inplaytrackersystem = intval($inplaytrackersystem);

    if(!is_array($archived_rows) || empty($archived_rows))
    {
        return array('count' => 0, 'postnum' => 0, 'threadnum' => 0);
    }

    $total_posts = 0;
    $total_scenes = 0;
    $count = 0;

    foreach($archived_rows as $row)
    {
        if(!is_array($row))
        {
            continue;
        }

        $count++;
        $old_uid = intval($row['old_uid'] ?? 0);
        $username = (string)($row['username'] ?? '');

        $scene_tids = array();
        $scene_count = 0;
        $post_count = 0;
        $calc_source = 'tracker';

        if($inplaytrackersystem === 5)
        {
            $calculated = char_archive_calculate_old_uid_inplay_totals($old_uid, $username, $inplaytrackersystem);
            $scene_count = intval($calculated['threadnum']);
            $post_count = intval($calculated['postnum']);
            $calc_source = (string)($calculated['source'] ?? 'tracker');
        }
        else
        {
            $scene_tids = char_archive_playerdirectory_collect_scene_tids_for_character($old_uid, $username, $inplaytrackersystem);
            $scene_count = count($scene_tids);
            $post_count = char_archive_playerdirectory_count_posts_for_character($old_uid, $username, $scene_tids, $inplaytrackersystem);
        }

        $fallback_scene_count = intval($row['threadnum'] ?? 0);
        $fallback_post_count = intval($row['postnum'] ?? 0);

        // Tracker 5: prefer archived snapshot values for former characters,
        // because logbook entry -> scene mappings can include additional rows
        // that were not part of the character's original playerdirectory stats.
        if($inplaytrackersystem === 5)
        {
            if($fallback_scene_count > 0)
            {
                $scene_count = $fallback_scene_count;
            }
        }

        // If tracker lookup cannot recover scenes for deleted characters,
        // use archived snapshot as fallback for this character.
        if($scene_count <= 0)
        {
            $scene_count = $fallback_scene_count;
        }

        if($inplaytrackersystem === 5)
        {
            if($fallback_post_count > 0)
            {
                $post_count = $fallback_post_count;
            }
        }
        if($post_count <= 0)
        {
            $post_count = $fallback_post_count;
        }

        $total_scenes += max(0, $scene_count);
        $total_posts += max(0, $post_count);
    }

    return array(
        'count' => $count,
        'postnum' => $total_posts,
        'threadnum' => $total_scenes,
    );
}

function char_archive_playerdirectory_directory_user_stats(&$payload)
{
    if(!is_array($payload))
    {
        return;
    }

    $main_uid = intval($payload['main_uid'] ?? 0);
    if($main_uid <= 0)
    {
        $main_uid = intval($payload['player_uid'] ?? 0);
    }
    if($main_uid <= 0)
    {
        return;
    }

    $archived_rows = char_archive_get_archived_characters_for_main($main_uid);
    if(empty($archived_rows))
    {
        return;
    }

    $inplaytrackersystem = isset($payload['inplaytrackersystem'])
        ? intval($payload['inplaytrackersystem'])
        : intval($GLOBALS['mybb']->settings['playerdirectory_inplaytracker'] ?? 0);

    $totals = char_archive_playerdirectory_calculate_archived_inplay_totals($archived_rows, $inplaytrackersystem);
    if(intval($totals['count']) <= 0)
    {
        return;
    }

    $payload['allinplayposts'] = intval($payload['allinplayposts'] ?? 0) + intval($totals['postnum']);
    $payload['allinplayscenes'] = intval($payload['allinplayscenes'] ?? 0) + intval($totals['threadnum']);

}

function char_archive_playerdirectory_playerstat_characters_html(&$payload)
{
    global $lang, $mybb, $theme;

    if(!is_array($payload))
    {
        return;
    }

    $main_uid = intval($payload['main_uid'] ?? 0);
    if($main_uid <= 0)
    {
        $main_uid = intval($payload['player_uid'] ?? 0);
    }
    if($main_uid <= 0)
    {
        return;
    }

    $archived = char_archive_get_archived_characters_for_main($main_uid);
    if(empty($archived))
    {
        return;
    }

    $inplaytrackersystem = isset($payload['inplaytrackersystem'])
        ? intval($payload['inplaytrackersystem'])
        : intval($mybb->settings['playerdirectory_inplaytracker'] ?? 0);

    if(!isset($lang->char_archive_pd_registered_characters))
    {
        $lang->load('char_archive');
    }

    $registered_title = isset($lang->char_archive_pd_registered_characters)
        ? $lang->char_archive_pd_registered_characters
        : 'Registered characters';
    $archived_title = isset($lang->char_archive_pd_archived_characters)
        ? $lang->char_archive_pd_archived_characters
        : 'Archived characters';
    $archived_suffix = isset($lang->char_archive_pd_archived_suffix)
        ? $lang->char_archive_pd_archived_suffix
        : '(Archived)';

    $registered_html = (string)($payload['characters_html'] ?? '');
    $archived_html = '';

    $avatar_default = isset($mybb->settings['playerdirectory_avatar_default'])
        ? trim((string)$mybb->settings['playerdirectory_avatar_default'])
        : '';
    $theme_imgdir = isset($theme['imgdir']) ? rtrim((string)$theme['imgdir'], '/') : 'images';
    $avatar_url = ($avatar_default !== '') ? ($theme_imgdir . '/' . ltrim($avatar_default, '/')) : ($theme_imgdir . '/default_avatar.png');

    foreach($archived as $row)
    {
        $single_totals = char_archive_playerdirectory_calculate_archived_inplay_totals(array($row), $inplaytrackersystem);
        $archived_avatar = trim((string)($row['avatar_backup'] ?? ''));
        if($archived_avatar === '')
        {
            $archived_avatar = trim((string)($row['avatar'] ?? ''));
        }
        $avatar_src = $avatar_url;
        if($archived_avatar !== '')
        {
            $avatar_src = $archived_avatar;
            if(!preg_match('#^(?:https?:)?//#i', $avatar_src) && stripos($avatar_src, 'data:') !== 0)
            {
                $bburl = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
                $avatar_src = ($bburl !== '')
                    ? ($bburl . '/' . ltrim($avatar_src, '/'))
                    : $avatar_src;
            }
        }
        $username = htmlspecialchars_uni((string)$row['username']);
        $label = $username . ' <em>' . htmlspecialchars_uni($archived_suffix) . '</em>';
        $posts = number_format(intval($single_totals['postnum']), 0, ',', '.');
        $scenes = number_format(intval($single_totals['threadnum']), 0, ',', '.');
        $inplay = '<b>' . $posts . '</b> Posts in <b>' . $scenes . '</b> Szenen';
        $archived_text = char_archive_get_playerstat_archived_text($row);
        $archived_text_html = '';
        if($archived_text !== '')
        {
            $archived_text_html = '<div class="playerdirectory_playerstat_fact">' . $archived_text . '</div>';
        }

        $archived_html .= '<div class="playerdirectory_playerstat_characters">';
        $archived_html .= '<div class="playerdirectory_playerstat_avatar"><img src="' . htmlspecialchars_uni($avatar_src) . '" alt="avatar"></div>';
        $archived_html .= '<div class="playerdirectory_playerstat_infos">';
        $archived_html .= '<div class="playerdirectory_playerstat_username">' . $label . '</div>';
        $archived_html .= '<div class="playerdirectory_playerstat_fact">' . $inplay . '</div>';
        $archived_html .= $archived_text_html;
        $archived_html .= '</div></div>';
    }

    $output = '';
    if($registered_html !== '')
    {
        $output .= '<div class="playerdirectory_subline">' . htmlspecialchars_uni($registered_title) . '</div>';
        $output .= $registered_html;
    }
    if($archived_html !== '')
    {
        $output .= '<div class="playerdirectory_subline">' . htmlspecialchars_uni($archived_title) . '</div>';
        $output .= $archived_html;
    }

    $payload['characters_html'] = $output;
    $payload['archived_count'] = count($archived);
}

function char_archive_playerdirectory_playerstat_totals(&$payload)
{
    if(!is_array($payload))
    {
        return;
    }

    $main_uid = intval($payload['main_uid'] ?? 0);
    if($main_uid <= 0)
    {
        $main_uid = intval($payload['player_uid'] ?? 0);
    }
    if($main_uid <= 0)
    {
        return;
    }

    $archived_rows = char_archive_get_archived_characters_for_main($main_uid);
    if(empty($archived_rows))
    {
        return;
    }

    $inplaytrackersystem = isset($payload['inplaytrackersystem'])
        ? intval($payload['inplaytrackersystem'])
        : intval($GLOBALS['mybb']->settings['playerdirectory_inplaytracker'] ?? 0);

    $totals = char_archive_playerdirectory_calculate_archived_inplay_totals($archived_rows, $inplaytrackersystem);
    if(intval($totals['count']) <= 0)
    {
        return;
    }

    $new_posts = intval($payload['allinplayposts'] ?? 0) + intval($totals['postnum']);
    $new_scenes = intval($payload['allinplayscenes'] ?? 0) + intval($totals['threadnum']);

    $payload['allinplayposts'] = $new_posts;
    $payload['allinplayscenes'] = $new_scenes;
    $payload['allinplayposts_formatted'] = $new_posts > 0
        ? number_format($new_posts, 0, ',', '.')
        : '0';
    $payload['allinplayscenes_formatted'] = $new_scenes > 0
        ? number_format($new_scenes, 0, ',', '.')
        : '0';

}

?>
