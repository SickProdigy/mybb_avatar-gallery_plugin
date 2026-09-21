<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) { die('Direct initialization of this file is not allowed.'); }

$lang->load('default_avatars');
$base_url = 'index.php?module=tools-avatar_gallery_repair';
$page->add_breadcrumb_item($lang->default_avatars_repair, $base_url);
$config = default_avatars_config();
$broken = array();
$replacements = default_avatars_avatar_pool();

if($config) {
    $query = $db->simple_select('users', 'uid,avatar,avatartype', "avatartype='default_avatar'");
    while($user = $db->fetch_array($query)) {
        if(default_avatars_broken_gallery_avatar($user['avatar'], $user['avatartype'], $config)) { $broken[] = $user; }
    }
}

if($mybb->request_method === 'post') {
    verify_post_check($mybb->get_input('my_post_key'));
    if(!$mybb->get_input('confirm', MyBB::INPUT_INT)) {
        flash_message($lang->default_avatars_repair_confirm, 'error');
        admin_redirect($base_url);
    }
    $repaired = 0;
    foreach($broken as $user) {
        $avatar = default_avatars_random_avatar($replacements);
        $dimensions = $avatar ? @getimagesize($avatar['absolute']) : false;
        if(!$dimensions) { continue; }
        $db->update_query('users', array(
            'avatar' => $db->escape_string($avatar['public_path']),
            'avatardimensions' => (int)$dimensions[0].'|'.(int)$dimensions[1],
            'avatartype' => 'default_avatar'
        ), 'uid='.(int)$user['uid'], 1);
        ++$repaired;
    }
    log_admin_action($lang->default_avatars_repair_log, $repaired);
    flash_message($lang->sprintf($lang->default_avatars_repair_success, $repaired), 'success');
    admin_redirect($base_url);
}

$page->output_header($lang->default_avatars_repair);
$page->output_nav_tabs(array('repair' => array('title' => $lang->default_avatars_repair, 'link' => $base_url, 'description' => $lang->default_avatars_repair_description)), 'repair');
$form = new Form($base_url, 'post');
$container = new FormContainer($lang->default_avatars_repair);
$container->output_row($lang->sprintf($lang->default_avatars_repair_count, count($broken)), $lang->default_avatars_repair_description,
    $form->generate_check_box('confirm', 1, $lang->default_avatars_repair_confirm));
$container->end();
$buttons = array($form->generate_submit_button($lang->default_avatars_repair_submit, array('disabled' => empty($broken) || empty($replacements))));
$form->output_submit_wrapper($buttons);
$form->end();
$page->output_footer();
