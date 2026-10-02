<?php
if(!defined('IN_MYBB') || !defined('IN_ADMINCP')) { die('Direct initialization of this file is not allowed.'); }

$lang->load('default_avatars');
$base_url = 'index.php?module=tools-avatar_gallery_repair';
$page->add_breadcrumb_item($lang->default_avatars_repair, $base_url);
$config = default_avatars_config();
$replacements = default_avatars_avatar_pool();
$broken = array();
$batch_size = 500;

if($config) {
    $query = $db->simple_select('users', 'uid,avatar,avatartype', "avatar<>'' AND avatar IS NOT NULL");
    while($user = $db->fetch_array($query)) {
        if(default_avatars_avatar_needs_repair($user['avatar'], $user['avatartype'], $config)) { $broken[] = $user; }
    }
}

$default_count = (int)$db->fetch_field(
    $db->simple_select('users', 'COUNT(uid) AS total', "avatar='' OR avatar IS NULL"),
    'total'
);


default_avatars_ensure_task();
$active_job = $cache->read("default_avatars_job");
$job_running = !empty($active_job) && in_array($active_job["status"], array("queued", "running"), true);

if($mybb->request_method === "post") {
    verify_post_check($mybb->get_input("my_post_key"));
    $users = array();
    $match = "";

    if($mybb->get_input("repair_broken") !== "") {
        $operation = "repair_broken";
        $confirm_name = "confirm_broken";
        $total = count($broken);
        $users = $broken;
    } elseif($mybb->get_input("fix_no_avatar") !== "") {
        $operation = "fix_no_avatar";
        $confirm_name = "confirm_no_avatar";
        $total = $default_count;
    } elseif($mybb->get_input("randomize_existing") !== "") {
        $operation = "randomize_existing";
        $confirm_name = "confirm_existing";
        $match = trim($mybb->get_input("avatar_match"));
        if($match === "") { flash_message($lang->default_avatars_replace_required, "error"); admin_redirect($base_url); }
        $total = (int)$db->fetch_field($db->simple_select("users", "COUNT(uid) AS total", "avatar='".$db->escape_string($match)."'"), "total");
    } else {
        flash_message($lang->default_avatars_invalid_operation, "error");
        admin_redirect($base_url);
    }

    if(!$mybb->get_input($confirm_name, MyBB::INPUT_INT)) {
        flash_message($lang->default_avatars_repair_confirm, "error");
        admin_redirect($base_url);
    }

    if($total > $batch_size) {
        if(!empty($active_job) && in_array($active_job["status"], array("queued", "running"), true)) {
            flash_message($lang->default_avatars_job_active, "error");
            admin_redirect($base_url);
        }
        $job = array("operation" => $operation, "match" => $match, "total" => $total, "processed" => 0, "remaining" => $total, "status" => "queued", "created" => TIME_NOW, "updated" => TIME_NOW);
        $cache->update("default_avatars_job", $job);
        $db->update_query("tasks", array("nextrun" => TIME_NOW), "file='default_avatars'");
        $cache->update_tasks();
        log_admin_action($lang->default_avatars_repair_log, $operation, "queued", $total);
        flash_message($lang->sprintf($lang->default_avatars_job_queued, $total), "success");
        admin_redirect($base_url);
    }

    if($operation === "fix_no_avatar") {
        $query = $db->simple_select("users", "uid", "avatar='' OR avatar IS NULL", array("limit" => $batch_size));
        while($user = $db->fetch_array($query)) { $users[] = $user; }
    } elseif($operation === "randomize_existing") {
        $query = $db->simple_select("users", "uid", "avatar='".$db->escape_string($match)."'", array("limit" => $batch_size));
        while($user = $db->fetch_array($query)) { $users[] = $user; }
    }

    $updated = default_avatars_randomize_users($users, $replacements);
    log_admin_action($lang->default_avatars_repair_log, $operation, $updated);
    flash_message($lang->sprintf($lang->default_avatars_repair_success, $updated, $batch_size), "success");
    admin_redirect($base_url);
}

$page->output_header($lang->default_avatars_repair);
$page->output_nav_tabs(array('repair' => array('title' => $lang->default_avatars_repair, 'link' => $base_url, 'description' => $lang->default_avatars_repair_description)), 'repair');
if(!empty($active_job)) {
    $status_container = new FormContainer($lang->default_avatars_job_status_title);
    $status_container->output_row($lang->sprintf($lang->default_avatars_job_status, htmlspecialchars_uni($active_job["operation"]), (int)$active_job["processed"], (int)$active_job["remaining"], htmlspecialchars_uni($active_job["status"])), $lang->default_avatars_job_status_description);
    $status_container->end();
}

$form = new Form($base_url, 'post');

$container = new FormContainer($lang->default_avatars_broken_title);
$broken_button_options = array("name" => "repair_broken");
if(empty($broken) || empty($replacements) || $job_running) { $broken_button_options["disabled"] = true; }
$broken_controls = $form->generate_check_box("confirm_broken", 1, $lang->default_avatars_repair_confirm)
    ."<br /><br />".$form->generate_submit_button($lang->default_avatars_broken_submit, $broken_button_options);
$broken_count = ($job_running && $active_job["operation"] === "repair_broken") ? (int)$active_job["remaining"] : count($broken);
$broken_heading = $lang->sprintf($lang->default_avatars_broken_count, $broken_count);
if($job_running && $active_job["operation"] === "repair_broken") { $broken_heading .= " ".$lang->default_avatars_job_inline; }
$container->output_row($broken_heading, $lang->default_avatars_broken_description, $broken_controls);
$container->end();

$container = new FormContainer($lang->default_avatars_default_title);
$default_button_options = array("name" => "fix_no_avatar");
if($default_count === 0 || empty($replacements) || $job_running) { $default_button_options["disabled"] = true; }
$default_controls = $form->generate_check_box("confirm_no_avatar", 1, $lang->default_avatars_repair_confirm)
    ."<br /><br />".$form->generate_submit_button($lang->default_avatars_default_submit, $default_button_options);
$default_count_display = ($job_running && $active_job["operation"] === "fix_no_avatar") ? (int)$active_job["remaining"] : $default_count;
$default_heading = $lang->sprintf($lang->default_avatars_default_count, $default_count_display);
if($job_running && $active_job["operation"] === "fix_no_avatar") { $default_heading .= " ".$lang->default_avatars_job_inline; }
if($job_running && $active_job["operation"] === "fix_no_avatar") { $default_heading .= " ".$lang->default_avatars_job_inline; }
$container->output_row($default_heading, $lang->sprintf($lang->default_avatars_default_description, $batch_size), $default_controls);
$container->end();

$container = new FormContainer($lang->default_avatars_replace_title);
$existing_button_options = array("name" => "randomize_existing");
if(empty($replacements) || $job_running) { $existing_button_options["disabled"] = true; }
$existing_controls = $form->generate_text_box("avatar_match", "", array("style" => "width: 100%;"))
    ."<br /><br />".$form->generate_check_box("confirm_existing", 1, $lang->default_avatars_repair_confirm)
    ."<br /><br />".$form->generate_submit_button($lang->default_avatars_replace_submit, $existing_button_options);
$existing_heading = $lang->default_avatars_replace_label;
if($job_running && $active_job["operation"] === "randomize_existing") { $existing_heading .= " ".$lang->default_avatars_job_inline; }
$container->output_row($existing_heading, $lang->sprintf($lang->default_avatars_replace_description, $batch_size), $existing_controls);
$container->end();

$form->end();
$page->output_footer();
