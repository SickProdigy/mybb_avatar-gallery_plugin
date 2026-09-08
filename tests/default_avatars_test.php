<?php

define('IN_MYBB', 1);

$default_avatars_test_root = sys_get_temp_dir() . '/default_avatars_test_' . uniqid('', true);
mkdir($default_avatars_test_root . '/images/avatars/fantasy', 0777, true);
mkdir($default_avatars_test_root . '/images/avatars/space set', 0777, true);

define('MYBB_ROOT', $default_avatars_test_root . '/');

function default_avatars_test_write_png($path)
{
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    file_put_contents($path, base64_decode($png));
}

function default_avatars_test_remove_tree($path)
{
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

function default_avatars_test_assert($condition, $message)
{
    global $default_avatars_test_root;

    if (!$condition) {
        default_avatars_test_remove_tree($default_avatars_test_root);
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

class DefaultAvatarsTestPlugins
{
    public $hooks = array();

    public function add_hook($hook, $callback)
    {
        $this->hooks[$hook] = $callback;
    }
}

class DefaultAvatarsTestLang
{
    public $default_avatars_gallery_title = 'Default Avatars';
    public $default_avatars_gallery_description = 'Select an avatar from one of the collections below.';
    public $default_avatars_category_label = 'Category';
    public $default_avatars_select_collection = 'Select a collection...';
    public $default_avatars_general_collection = 'General';
    public $default_avatars_empty = 'No default avatars are currently available.';

    public function load($name)
    {
    }
}

function htmlspecialchars_uni($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

default_avatars_test_write_png($default_avatars_test_root . '/images/avatars/general.png');
default_avatars_test_write_png($default_avatars_test_root . '/images/avatars/fantasy/blue_knight.png');
default_avatars_test_write_png($default_avatars_test_root . '/images/avatars/space set/red pilot.png');
file_put_contents($default_avatars_test_root . '/images/avatars/not-image.png', 'not an image');
file_put_contents($default_avatars_test_root . '/images/avatars/readme.txt', 'ignore me');

$plugins = new DefaultAvatarsTestPlugins();
$lang = new DefaultAvatarsTestLang();
$mybb = (object)array(
    'settings' => array(
        'bburl' => 'https://example.com/forum',
        'default_avatars_directory' => 'images/avatars',
        'default_avatars_url' => 'images/avatars',
        'default_avatars_extensions' => 'png,jpg',
    ),
);

require dirname(__DIR__) . '/Upload/inc/plugins/default_avatars.php';

default_avatars_test_assert(
    isset($plugins->hooks['usercp_avatar_end']) && isset($plugins->hooks['usercp_do_avatar_start']),
    'plugin hooks should be registered'
);

$config = default_avatars_config();
default_avatars_test_assert(
    $config && $config['directory'] === realpath($default_avatars_test_root . '/images/avatars'),
    'config should resolve the avatar directory inside MYBB_ROOT'
);
default_avatars_test_assert(
    $config['url_path'] === 'images/avatars',
    'config should normalize the public URL path'
);
default_avatars_test_assert(
    $config['extensions'] === array('png', 'jpg'),
    'config should keep the configured supported extension subset'
);

$avatar = default_avatars_validate('fantasy/blue_knight.png', $config);
default_avatars_test_assert(
    $avatar && $avatar['public_path'] === 'images/avatars/fantasy/blue_knight.png',
    'valid avatars should return an absolute path and public path'
);
default_avatars_test_assert(
    default_avatars_validate('../config.php', $config) === false,
    'validation should reject parent-directory traversal'
);
default_avatars_test_assert(
    default_avatars_validate('/images/avatars/general.png', $config) === false,
    'validation should reject absolute paths'
);
default_avatars_test_assert(
    default_avatars_validate('readme.txt', $config) === false,
    'validation should reject unsupported extensions'
);
default_avatars_test_assert(
    default_avatars_validate('not-image.png', $config) === false,
    'validation should reject files that are not images'
);

$collections = default_avatars_discover();
default_avatars_test_assert(
    isset($collections['']) && isset($collections['fantasy']) && isset($collections['space set']),
    'discovery should group root and subdirectory avatars'
);
default_avatars_test_assert(
    $collections['fantasy'][0]['name'] === 'Blue Knight',
    'display names should be cleaned up from filenames'
);
default_avatars_test_assert(
    $collections['space set'][0]['url'] === 'https://example.com/forum/images/avatars/space%20set/red%20pilot.png',
    'public URLs should encode path segments'
);

$avatarupload = '<tr><td class="trow1">Upload Avatar:</td></tr>';
default_avatars_render_gallery();
default_avatars_test_assert(
    strpos($avatarupload, '<td class="trow1">Upload Avatar:</td>') < strpos($avatarupload, 'Default Avatars'),
    'gallery should render after the custom avatar rows'
);
default_avatars_test_assert(
    strpos($avatarupload, '<select id="default_avatars_category"') !== false,
    'gallery should use a category dropdown'
);
default_avatars_test_assert(
    strpos($avatarupload, 'class="default-avatars-panel"') !== false
        && strpos($avatarupload, 'max-height:260px;overflow:auto') !== false,
    'gallery should render a scrollable avatar panel'
);
default_avatars_test_assert(
    strpos($avatarupload, '<details') === false && strpos($avatarupload, '<summary>') === false,
    'gallery should not render expanded details sections'
);
default_avatars_test_assert(
    strpos($avatarupload, 'Use Blue Knight') === false,
    'avatar names should not be repeated with separate use labels'
);

$mybb->settings['default_avatars_directory'] = '../avatars';
default_avatars_test_assert(
    default_avatars_config() === false,
    'config should reject directories outside MYBB_ROOT'
);

default_avatars_test_remove_tree($default_avatars_test_root);

echo "Avatar Gallery tests passed.\n";
