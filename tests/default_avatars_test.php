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

class DefaultAvatarsTestQuery
{
    public $row;

    public function __construct($row)
    {
        $this->row = $row;
    }
}

class DefaultAvatarsTestDatabase
{
    public $group = array();
    public $settings = array();
    private $next_setting_id = 1;

    public function simple_select($table, $fields, $where, $options = array())
    {
        if($table === 'settinggroups') {
            return new DefaultAvatarsTestQuery($this->group);
        }

        preg_match("/name='([^']+)'/", $where, $matches);
        $name = isset($matches[1]) ? stripslashes($matches[1]) : '';

        return new DefaultAvatarsTestQuery(isset($this->settings[$name]) ? $this->settings[$name] : array());
    }

    public function fetch_field($query, $field)
    {
        return isset($query->row[$field]) ? $query->row[$field] : null;
    }

    public function insert_query($table, $values)
    {
        if($table === 'settinggroups') {
            $values['gid'] = 7;
            $this->group = $values;
            return 7;
        }

        $values['sid'] = $this->next_setting_id++;
        $this->settings[$values['name']] = $values;

        return $values['sid'];
    }

    public function update_query($table, $values, $where, $limit = 0)
    {
        if($table === 'settinggroups') {
            $this->group = array_merge($this->group, $values);
            return;
        }

        preg_match("/sid='([0-9]+)'/", $where, $matches);
        $sid = isset($matches[1]) ? (int)$matches[1] : 0;

        foreach($this->settings as $name => $setting) {
            if((int)$setting['sid'] === $sid) {
                $this->settings[$name] = array_merge($setting, $values);
                return;
            }
        }
    }

    public function delete_query($table, $where)
    {
        if($table === 'settinggroups') {
            $this->group = array();
        }

        if($table === 'settings') {
            $this->settings = array();
        }
    }

    public function escape_string($value)
    {
        return addslashes($value);
    }
}

class DefaultAvatarsTestLang
{
    public $default_avatars_name = 'Avatar Gallery';
    public $default_avatars_settings_description = 'Configure where gallery images are discovered and published.';
    public $default_avatars_directory = 'Avatar gallery directory';
    public $default_avatars_directory_description = 'Filesystem directory.';
    public $default_avatars_url = 'Avatar gallery URL path';
    public $default_avatars_url_description = 'Public URL path.';
    public $default_avatars_extensions = 'Allowed image extensions';
    public $default_avatars_extensions_description = 'Allowed extensions.';
    public $default_avatars_default_collection = 'Default collection';
    public $default_avatars_default_collection_description = 'Optional folder path to show first.';
    public $default_avatars_gallery_title = 'Default Avatars';
    public $default_avatars_gallery_description = 'Select an avatar from one of the collections below.';
    public $default_avatars_category_label = 'Category';
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

function rebuild_settings()
{
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
        'default_avatars_default_collection' => '',
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

$db = new DefaultAvatarsTestDatabase();
default_avatars_ensure_settings();
default_avatars_test_assert(
    count($db->settings) === 4,
    'setting synchronization should create all settings'
);
default_avatars_test_assert(
    isset($db->settings['default_avatars_default_collection']),
    'setting synchronization should create the default collection setting'
);
$db->settings['default_avatars_directory']['value'] = 'custom/avatars';
default_avatars_activate();
default_avatars_test_assert(
    $db->settings['default_avatars_directory']['value'] === 'custom/avatars',
    'activation setting synchronization should preserve existing setting values'
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
    strpos($avatarupload, '<option value="default_avatars_collection_1" selected="selected">Fantasy</option>') !== false,
    'gallery should show the first folder automatically when no default collection is configured'
);
default_avatars_test_assert(
    strpos($avatarupload, 'id="default_avatars_collection_1"><div class="default-avatars-grid">') !== false,
    'the automatic default folder panel should be visible'
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

$mybb->settings['default_avatars_default_collection'] = 'space set';
$avatarupload = '<tr><td class="trow1">Upload Avatar:</td></tr>';
default_avatars_render_gallery();
default_avatars_test_assert(
    strpos($avatarupload, '<option value="default_avatars_collection_2" selected="selected">Space Set</option>') !== false,
    'configured default collection should be selected'
);
default_avatars_test_assert(
    strpos($avatarupload, 'id="default_avatars_collection_2"><div class="default-avatars-grid">') !== false,
    'configured default collection panel should be visible'
);

$mybb->settings['default_avatars_directory'] = '../avatars';
default_avatars_test_assert(
    default_avatars_config() === false,
    'config should reject directories outside MYBB_ROOT'
);

default_avatars_test_remove_tree($default_avatars_test_root);

echo "Avatar Gallery tests passed.\n";
