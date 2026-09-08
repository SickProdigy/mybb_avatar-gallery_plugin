<?php
/**
 * Avatar Gallery
 * Copyright (C) 2026 SickProdigy
 * SPDX-License-Identifier: GPL-3.0-only
 */
if(!defined('IN_MYBB')) { die('This file cannot be accessed directly.'); }

$plugins->add_hook('usercp_avatar_end', 'default_avatars_render_gallery');
$plugins->add_hook('usercp_do_avatar_start', 'default_avatars_save_selection');

function default_avatars_info()
{
    global $lang;
    $lang->load('default_avatars');
    return array(
        'name' => $lang->default_avatars_name,
        'description' => $lang->default_avatars_description,
        'website' => 'https://gitea.rcs1.top/sickprodigy/mybb_avatar-gallery_plugin',
        'author' => 'SickProdigy',
        'authorsite' => 'https://www.sickgaming.net/',
        'version' => '0.1.0',
        'compatibility' => '18*',
        'license' => 'GPL-3.0-only'
    );
}

function default_avatars_is_installed()
{
    global $db;
    return (bool)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='default_avatars'"), 'gid');
}

function default_avatars_install()
{
    default_avatars_remove_settings();
    default_avatars_ensure_settings();
}

function default_avatars_ensure_settings()
{
    global $db, $lang;
    $lang->load('default_avatars');
    $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='default_avatars'"), 'gid');

    if(!$gid) {
        $gid = (int)$db->insert_query('settinggroups', array(
            'name' => 'default_avatars', 'title' => $db->escape_string($lang->default_avatars_name),
            'description' => $db->escape_string($lang->default_avatars_settings_description), 'disporder' => 50, 'isdefault' => 0
        ));
    } else {
        $db->update_query('settinggroups', array(
            'title' => $db->escape_string($lang->default_avatars_name),
            'description' => $db->escape_string($lang->default_avatars_settings_description),
            'disporder' => 50,
            'isdefault' => 0
        ), "gid='{$gid}'", 1);
    }

    $settings = array(
        array('name' => 'default_avatars_directory', 'title' => $lang->default_avatars_directory, 'description' => $lang->default_avatars_directory_description, 'value' => 'images/avatars'),
        array('name' => 'default_avatars_url', 'title' => $lang->default_avatars_url, 'description' => $lang->default_avatars_url_description, 'value' => 'images/avatars'),
        array('name' => 'default_avatars_extensions', 'title' => $lang->default_avatars_extensions, 'description' => $lang->default_avatars_extensions_description, 'value' => 'png,jpg,jpeg,gif,webp'),
        array('name' => 'default_avatars_default_collection', 'title' => $lang->default_avatars_default_collection, 'description' => $lang->default_avatars_default_collection_description, 'value' => '')
    );
    foreach($settings as $order => $setting)
    {
        $setting['title'] = $db->escape_string($setting['title']);
        $setting['description'] = $db->escape_string($setting['description']);
        $setting['optionscode'] = 'text';
        $setting['disporder'] = $order + 1;
        $setting['gid'] = $gid;
        $sid = (int)$db->fetch_field($db->simple_select('settings', 'sid', "name='".$db->escape_string($setting['name'])."'", array('limit' => 1)), 'sid');

        if($sid) {
            unset($setting['value']);
            $db->update_query('settings', $setting, "sid='{$sid}'", 1);
        } else {
            $db->insert_query('settings', $setting);
        }
    }
    rebuild_settings();
}

function default_avatars_uninstall() { default_avatars_remove_settings(); rebuild_settings(); }
function default_avatars_activate() { default_avatars_ensure_settings(); }
function default_avatars_deactivate() {}

function default_avatars_remove_settings()
{
    global $db;
    $gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='default_avatars'"), 'gid');
    if($gid) {
        $db->delete_query('settings', "gid='{$gid}'");
        $db->delete_query('settinggroups', "gid='{$gid}'");
    }
}

function default_avatars_render_gallery()
{
    global $avatarupload, $lang;
    $lang->load('default_avatars');
    $collections = default_avatars_discover();
    $heading = htmlspecialchars_uni($lang->default_avatars_gallery_title);
    $content = '';

    if(!$collections) {
        $content = '<p class="smalltext">'.htmlspecialchars_uni($lang->default_avatars_empty).'</p>';
    } else {
        $default_collection = default_avatars_default_collection($collections);
        $select = '<label class="default-avatars-category-label" for="default_avatars_category">'.htmlspecialchars_uni($lang->default_avatars_category_label).'</label>'
            . '<select id="default_avatars_category" class="default-avatars-category" autocomplete="off">';
        $panels = '';
        $index = 0;

        foreach($collections as $collection => $avatars) {
            $panel = 'default_avatars_collection_'.$index++;
            $selected = $collection === $default_collection ? ' selected="selected"' : '';
            $hidden = $collection === $default_collection ? '' : ' hidden';
            $select .= '<option value="'.$panel.'"'.$selected.'>'.htmlspecialchars_uni(default_avatars_collection_label($collection)).'</option>';
            $panels .= '<div class="default-avatars-panel" id="'.$panel.'"'.$hidden.'><div class="default-avatars-grid">';

            foreach($avatars as $avatar) {
                $path = htmlspecialchars_uni($avatar['relative']);
                $url = htmlspecialchars_uni($avatar['url']);
                $name = htmlspecialchars_uni($avatar['name']);
                $panels .= '<label class="default-avatar-option"><input type="radio" name="default_avatar" value="'.$path.'"><span class="default-avatar-preview"><img src="'.$url.'" alt="'.$name.'" loading="lazy"></span><span class="default-avatar-name">'.$name.'</span></label>';
            }

            $panels .= '</div></div>';
        }

        $select .= '</select>';
        $content = '<div class="default-avatars-controls">'.$select.'</div>'.$panels;
    }

    $css = '<style>.default-avatars-wrap{padding:12px}.default-avatars-controls{display:flex;align-items:center;gap:8px;margin-bottom:10px}.default-avatars-category-label{font-weight:bold}.default-avatars-category{max-width:320px}.default-avatars-panel{max-height:260px;overflow:auto;border:1px solid #555;padding:12px}.default-avatars-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:12px}.default-avatar-option{display:flex;position:relative;flex-direction:column;align-items:center;gap:6px;min-height:142px;padding:8px;border:2px solid transparent;border-radius:4px;cursor:pointer;text-align:center}.default-avatar-option.default-avatar-selected{border-color:#3578b9;background:rgba(53,120,185,.08)}.default-avatar-option input{position:absolute;opacity:0}.default-avatar-option:focus-within{outline:2px solid #3578b9;outline-offset:2px}.default-avatar-preview{display:flex;align-items:center;justify-content:center;width:100px;height:100px}.default-avatar-preview img{max-width:100px;max-height:100px}.default-avatar-name{display:block;line-height:1.3}</style>';
    $script = '<script type="text/javascript">(function(){var select=document.getElementById("default_avatars_category");if(!select){return;}var panels=document.querySelectorAll(".default-avatars-panel");var options=document.querySelectorAll(".default-avatar-option input");function showPanel(){for(var i=0;i<panels.length;i++){panels[i].hidden=panels[i].id!==select.value;}}function markSelected(){for(var i=0;i<options.length;i++){var label=options[i].parentNode;if(label){if(options[i].checked){label.classList.add("default-avatar-selected");}else{label.classList.remove("default-avatar-selected");}}}}select.addEventListener("change",showPanel);for(var i=0;i<options.length;i++){options[i].addEventListener("change",markSelected);}showPanel();markSelected();}());</script>';
    $avatarupload .= '<tr><td class="tcat" colspan="2"><strong>'.$heading.'</strong></td></tr><tr><td class="trow1" colspan="2"><div class="default-avatars-wrap">'.$content.'</div>'.$css.$script.'</td></tr>';
}

function default_avatars_save_selection()
{
    global $mybb, $db, $lang;
    $selection = trim($mybb->get_input('default_avatar'));
    if($selection === '') { return; }
    $lang->load('default_avatars');
    $avatar = default_avatars_validate($selection);
    if(!$avatar || !($dimensions = @getimagesize($avatar['absolute']))) { error($lang->default_avatars_invalid); }
    $db->update_query('users', array(
        'avatar' => $db->escape_string($avatar['public_path']),
        'avatardimensions' => (int)$dimensions[0].'|'.(int)$dimensions[1],
        'avatartype' => 'default_avatar'
    ), "uid='".(int)$mybb->user['uid']."'");
    require_once MYBB_ROOT.'inc/functions_upload.php';
    remove_avatars((int)$mybb->user['uid']);
    redirect('usercp.php?action=avatar', $lang->redirect_avatarupdated);
}

function default_avatars_discover()
{
    $config = default_avatars_config();
    if(!$config) { return array(); }
    $collections = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($config['directory'], FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file) {
        if(!$file->isFile()) { continue; }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($config['directory']) + 1));
        if(!default_avatars_validate($relative, $config)) { continue; }
        $collection = dirname($relative) === '.' ? '' : dirname($relative);
        $collections[$collection][] = array('relative' => $relative, 'url' => default_avatars_public_url($relative, $config), 'name' => default_avatars_display_name(pathinfo($relative, PATHINFO_FILENAME)));
    }
    uksort($collections, 'strnatcasecmp');
    foreach($collections as &$avatars) { usort($avatars, function($a, $b) { return strnatcasecmp($a['name'], $b['name']); }); }
    return $collections;
}

function default_avatars_validate($relative, $config = null)
{
    if($config === null) { $config = default_avatars_config(); }
    if(!$config || $relative === '' || strpos($relative, "\0") !== false) { return false; }
    $relative = str_replace('\\', '/', $relative);
    if($relative[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $relative) || !in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), $config['extensions'], true)) { return false; }
    $absolute = realpath($config['directory'].'/'.$relative);
    $prefix = $config['directory'].DIRECTORY_SEPARATOR;
    if(!$absolute || !is_file($absolute) || !is_readable($absolute) || strncmp($absolute, $prefix, strlen($prefix)) !== 0 || !@getimagesize($absolute)) { return false; }
    return array('absolute' => $absolute, 'public_path' => rtrim($config['url_path'], '/').'/'.default_avatars_encode_path($relative));
}

function default_avatars_config()
{
    global $mybb;
    $directory = trim(isset($mybb->settings['default_avatars_directory']) ? $mybb->settings['default_avatars_directory'] : 'images/avatars');
    $url = trim(isset($mybb->settings['default_avatars_url']) ? $mybb->settings['default_avatars_url'] : 'images/avatars');
    $allowed = isset($mybb->settings['default_avatars_extensions']) ? $mybb->settings['default_avatars_extensions'] : 'png,jpg,jpeg,gif,webp';
    $directory = str_replace('\\', '/', $directory);
    if(!$directory || $directory[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $directory)) { return false; }
    $absolute = realpath(MYBB_ROOT.$directory);
    $root = rtrim(realpath(MYBB_ROOT), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if(!$absolute || !is_dir($absolute) || strncmp($absolute.DIRECTORY_SEPARATOR, $root, strlen($root)) !== 0) { return false; }
    $supported = array('png', 'jpg', 'jpeg', 'gif', 'webp');
    $extensions = array_values(array_intersect(array_unique(array_map('trim', explode(',', strtolower($allowed)))), $supported));
    if(!$extensions) { $extensions = $supported; }
    return array('directory' => rtrim($absolute, DIRECTORY_SEPARATOR), 'url_path' => trim(str_replace('\\', '/', $url), '/'), 'extensions' => $extensions);
}

function default_avatars_public_url($relative, $config)
{
    global $mybb;
    return rtrim($mybb->settings['bburl'], '/').'/'.rtrim($config['url_path'], '/').'/'.default_avatars_encode_path($relative);
}
function default_avatars_encode_path($path) { return implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path)))); }
function default_avatars_collection_label($collection)
{
    global $lang;
    return $collection === '' ? $lang->default_avatars_general_collection : implode(' / ', array_map('default_avatars_display_name', explode('/', $collection)));
}
function default_avatars_default_collection($collections)
{
    global $mybb;
    $configured = trim(isset($mybb->settings['default_avatars_default_collection']) ? $mybb->settings['default_avatars_default_collection'] : '');
    $configured = str_replace('\\', '/', $configured);
    $configured = trim($configured, '/');

    if($configured !== '' && isset($collections[$configured])) {
        return $configured;
    }

    foreach(array_keys($collections) as $collection) {
        if($collection !== '') {
            return $collection;
        }
    }

    $keys = array_keys($collections);
    return $keys ? $keys[0] : '';
}
function default_avatars_display_name($name) { return ucwords(trim(preg_replace('/\s+/', ' ', str_replace(array('-', '_'), ' ', $name)))); }
