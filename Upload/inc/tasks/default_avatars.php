<?php
if(!defined('IN_MYBB')) { die('Direct initialization of this file is not allowed.'); }

function task_default_avatars($task)
{
    global $db, $cache;

    $job = $cache->read('default_avatars_job');
    if(empty($job) || !in_array($job['status'], array('queued', 'running'), true)) {
        return;
    }

    require_once MYBB_ROOT.'inc/plugins/default_avatars.php';
    $job['status'] = 'running';
    $job['updated'] = TIME_NOW;
    $cache->update('default_avatars_job', $job);

    $users = array();
    $batch_size = 500;

    if($job['operation'] === 'repair_broken') {
        $config = default_avatars_config();
        $query = $db->simple_select('users', 'uid,avatar,avatartype', "avatar<>'' AND avatar IS NOT NULL");
        while(count($users) < $batch_size && $user = $db->fetch_array($query)) {
            if(default_avatars_avatar_needs_repair($user['avatar'], $user['avatartype'], $config)) { $users[] = $user; }
        }
    } elseif($job['operation'] === 'fix_no_avatar') {
        $query = $db->simple_select('users', 'uid', "avatar='' OR avatar IS NULL", array('limit' => $batch_size));
        while($user = $db->fetch_array($query)) { $users[] = $user; }
    } elseif($job['operation'] === 'randomize_existing') {
        $match = $db->escape_string($job['match']);
        $query = $db->simple_select('users', 'uid', "avatar='{$match}'", array('limit' => $batch_size));
        while($user = $db->fetch_array($query)) { $users[] = $user; }
    }

    $updated = default_avatars_randomize_users($users);
    $job["updated"] = TIME_NOW;

    if($job["operation"] === "repair_broken") {
        $remaining = 0;
        $config = default_avatars_config();
        $query = $db->simple_select("users", "avatar,avatartype", "avatar<>'' AND avatar IS NOT NULL");
        while($user = $db->fetch_array($query)) {
            if(default_avatars_avatar_needs_repair($user["avatar"], $user["avatartype"], $config)) { ++$remaining; }
        }
    } elseif($job["operation"] === "fix_no_avatar") {
        $remaining = (int)$db->fetch_field($db->simple_select("users", "COUNT(uid) AS total", "avatar='' OR avatar IS NULL"), "total");
    } else {
        $match = $db->escape_string($job["match"]);
        $remaining = (int)$db->fetch_field($db->simple_select("users", "COUNT(uid) AS total", "avatar='".$match."'"), "total");
    }

    $job["remaining"] = $remaining;
    $job["processed"] = max(0, $job["total"] - $remaining);
    $job["status"] = $remaining === 0 ? "complete" : ($updated === 0 ? "stalled" : "queued");

    $cache->update('default_avatars_job', $job);
    add_task_log($task, "Avatar Gallery processed {$updated} user(s); {$job['remaining']} remain.");
}
