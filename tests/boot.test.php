<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use MM\Foundations\Config\Stubs;
use MM\Foundations\Foundations;

// Minimal WordPress surface the stubs and runtime rely on.
$tmp = sys_get_temp_dir() . '/meros-foundations-' . getmypid();
define('ABSPATH', "{$tmp}/wp/");
define('WP_CONTENT_DIR', "{$tmp}/wp/wp-content");
define('WP_ENV', 'testing');
foreach (['DB_HOST' => 'localhost', 'DB_NAME' => 'wp', 'DB_USER' => 'root', 'DB_PASSWORD' => '', 'DB_CHARSET' => 'utf8mb4'] as $k => $v) {
    define($k, $v);
}
$_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = 'base64:' . base64_encode(str_repeat('k', 32));
$theme = WP_CONTENT_DIR . '/themes/demo';

function apply_filters($tag, $value) { return $value; }
function get_bloginfo($k) { return 'Demo'; }
function home_url() { return 'http://demo.test'; }
function get_locale() { return 'en_GB'; }
function get_template_directory() { return $GLOBALS['theme']; }
function get_stylesheet_directory() { return $GLOBALS['theme']; }
function get_theme_file_path($p = '') { return $GLOBALS['theme'] . '/' . ltrim($p, '/'); }
function get_parent_theme_file_path($p = '') { return get_theme_file_path($p); }
function wp_upload_dir() { return ['basedir' => WP_CONTENT_DIR . '/uploads', 'baseurl' => 'http://demo.test/wp-content/uploads']; }
function is_ssl() { return false; }

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

@mkdir("{$theme}/routes", 0777, true);
@mkdir("{$theme}/resources/views", 0777, true);
@mkdir("{$theme}/config", 0777, true);
@mkdir("{$tmp}/stubs", 0777, true);

file_put_contents("{$theme}/composer.json", '{"name":"demo/theme"}');
file_put_contents("{$theme}/routes/web.php", '<?php Illuminate\Support\Facades\Route::get("/hi", fn () => view("hello", ["who" => "Meros"]));');
file_put_contents("{$theme}/resources/views/hello.blade.php", 'Hello {{ $who }} from {{ config("app.name") }}');
file_put_contents("{$tmp}/stubs/view.php", '<?php return ["paths" => ["' . "{$theme}/resources/views" . '"], "framework_stub" => true];');
file_put_contents("{$theme}/config/app.php", '<?php return ["name" => "Overridden"];');

$app = Foundations::create($theme, [], ["{$tmp}/stubs"]);
$app->make(HttpKernel::class)->bootstrap();

$config = $app['config'];

check($config->get('app.env') === 'testing', 'foundations app stub should be applied');
check($config->get('app.locale') === 'en_GB', 'foundations stub values should be applied');
check($config->get('app.name') === 'Overridden', 'application config must win over stubs');
check($config->get('view.framework_stub') === true, 'registered stub directories must layer over foundations stubs');
check($config->get('database.connections.wordpress.driver') === 'mysql', 'wordpress db connection stub should exist');
check($config->get('cache.stores.file') !== null, 'cache stores should be configured');
$cached = eval('return ' . var_export(MM\Foundations\Bootstrap\LoadConfiguration::items($app), true) . ';');
check($cached['app']['name'] === 'Overridden', 'config must survive caching');

check($app->make('view')->exists('meros-test::x') === false, 'unknown namespaced view must not exist');
$response = $app->make(HttpKernel::class)->handle(Request::create('/hi'));
check(trim($response->getContent()) === 'Hello Meros from Overridden', 'blade view should render through a routed request: ' . $response->getContent());

$published = Stubs::publish("{$tmp}/published");
check(in_array('app', $published, true) && in_array('view', $published, true), 'publish should copy the winning stubs');
check(str_contains(file_get_contents("{$tmp}/published/view.php"), 'framework_stub'), 'publish should copy the highest-priority stub');

echo "meros-foundations boot: ok\n";
(new Illuminate\Filesystem\Filesystem())->deleteDirectory($tmp);
