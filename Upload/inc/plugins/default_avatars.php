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
    global $db, $lang;
    $lang->load('default_avatars');
    default_avatars_remove_settings();
    $gid = (int)$db->insert_query('settinggroups', array(
        'name' => 'default_avatars', 'title' => $db->escape_string($lang->default_avatars_name),
        'description' => $db->escape_string($lang->default_avatars_settings_description), 'disporder' => 50, 'isdefault' => 0
    ));
    $settings = array(
        array('name' => 'default_avatars_directory', 'title' => $lang->default_avatars_directory, 'description' => $lang->default_avatars_directory_description, 'value' => 'images/avatars'),
        array('name' => 'default_avatars_url', 'title' => $lang->default_avatars_url, 'description' => $lang->default_avatars_url_description, 'value' => 'images/avatars'),
        array('name' => 'default_avatars_extensions', 'title' => $lang->default_avatars_extensions, 'description' => $lang->default_avatars_extensions_description, 'value' => 'png,jpg,jpeg,gif,webp')
    );
    foreach($settings as $order => $setting)
    {
        $setting['title'] = $db->escape_string($setting['title']);
        $setting['description'] = $db->escape_string($setting['description']);
        $setting['optionscode'] = 'text';
        $setting['disporder'] = $order + 1;
        $setting['gid'] = $gid;
        $db->insert_query('settings', $setting);
    }
    rebuild_settings();
}

function default_avatars_uninstall() { default_avatars_remove_settings(); rebuild_settings(); }
function default_avatars_activate() {}
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
    $content = '';
    if(!$collections) {
        $content = '<p>'.htmlspecialchars_uni($lang->default_avatars_empty).'</p>';
    } else {
        foreach($collections as $collection => $avatars) {
            $content .= '<details class="default-avatars-collection" open><summary>'.htmlspecialchars_uni(default_avatars_collection_label($collection)).'</summary><div class="default-avatars-grid">';
            foreach($avatars as $avatar) {
                $path = htmlspecialchars_uni($avatar['relative']);
                $url = htmlspecialchars_uni($avatar['url']);
                $name = htmlspecialchars_uni($avatar['name']);
                $choose = htmlspecialchars_uni($lang->sprintf($lang->default_avatars_choose, $avatar['name']));
                $content .= '<label class="default-avatar-option"><input type="radio" name="default_avatar" value="'.$path.'"><span class="default-avatar-preview"><img src="'.$url.'" alt="'.$name.'" loading="lazy"></span><span>'.$name.'</span><small>'.$choose.'</small></label>';
            }
            $content .= '</div></details>';
        }
    }
    $css = '<style>.default-avatars-wrap{padding:12px}.default-avatars-collection{margin:8px 0;border:1px solid #ccc}.default-avatars-collection>summary{padding:9px 12px;font-weight:bold;cursor:pointer}.default-avatars-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;padding:12px}.default-avatar-option{display:flex;position:relative;flex-direction:column;align-items:center;gap:6px;padding:8px;border:2px solid transparent;border-radius:4px;cursor:pointer;text-align:center}.default-avatar-option:has(input:checked){border-color:#3578b9;background:rgba(53,120,185,.08)}.default-avatar-option input{position:absolute;opacity:0}.default-avatar-option:focus-within{outline:2px solid #3578b9}.default-avatar-preview{display:flex;align-items:center;justify-content:center;width:100px;height:100px}.default-avatar-preview img{max-width:100px;max-height:100px}</style>';
    $avatarupload = '<tr><td class="trow1" colspan="2"><div class="default-avatars-wrap"><strong>'.htmlspecialchars_uni($lang->default_avatars_gallery_title).'</strong><p class="smalltext">'.htmlspecialchars_uni($lang->default_avatars_gallery_description).'</p>'.$content.'</div>'.$css.'</td></tr>'.$avatarupload;
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
function default_avatars_display_name($name) { return ucwords(trim(preg_replace('/\s+/', ' ', str_replace(array('-', '_'), ' ', $name)))); }
