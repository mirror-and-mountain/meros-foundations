<?php

// Mirrors what meros-crm needs: database queue + queue:work, session auth guard with an
// Eloquent user, hashed passwords, and Meros package discovery.

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use MM\Meros\Foundations\Foundations;
use MM\Meros\Foundations\PackageManifest;

$tmp = sys_get_temp_dir() . '/meros-foundations-pkg-' . getmypid();
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

class PackageUser extends Illuminate\Foundation\Auth\User
{
    protected $table   = 'package_users';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}

class PackageJob implements ShouldQueue
{
    public int $tries = 1;

    public function __construct(public string $value)
    {
    }

    public function handle(): void
    {
        $GLOBALS['handled'][] = $this->value;
    }
}

eval('namespace Acme\\Lara; class Provider extends \\Illuminate\\Support\\ServiceProvider { public function register() { $GLOBALS["laravel_provider"] = true; } }');

mkdir("{$theme}/vendor/composer", 0777, true);
file_put_contents("{$theme}/composer.json", '{"name":"demo/theme"}');
file_put_contents("{$theme}/vendor/composer/installed.json", json_encode(['packages' => [
    ['name' => 'acme/meros-pkg', 'extra' => ['meros' => ['provider' => 'Acme\\Pkg\\Provider']]],
    ['name' => 'acme/laravel-pkg', 'extra' => ['laravel' => ['providers' => ['Acme\\Lara\\Provider']]]],
    ['name' => 'acme/second', 'extra' => ['meros' => ['providers' => ['Acme\\Second\\Provider']]]],
]]));

$app = Foundations::create($theme);
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();

$manifest = $app->make(PackageManifest::class);
$manifest->flush();
check(!empty($GLOBALS['laravel_provider']), 'discovered laravel providers should register');
check($manifest->merosProviders() === ['Acme\\Pkg\\Provider', 'Acme\\Second\\Provider'], 'meros providers should be discovered from extra.meros.provider(s)');
check($manifest->providers() === ['Acme\\Lara\\Provider'], 'laravel providers must stay separate from meros providers');
check($manifest->package('meros-pkg') === ['name' => 'acme/meros-pkg', 'providers' => ['Acme\\Pkg\\Provider']], 'packages should resolve by short name');
check($manifest->package('acme/laravel-pkg')['providers'] === ['Acme\\Lara\\Provider'], 'packages should resolve by full name');
check($manifest->package('missing') === null, 'unknown packages should not resolve');

touch("{$theme}/vendor/composer/installed.json", time() + 10);
file_put_contents("{$theme}/vendor/composer/installed.json", json_encode(['packages' => []]));
touch("{$theme}/vendor/composer/installed.json", time() + 10);
$fresh = new PackageManifest(new Illuminate\Filesystem\Filesystem(), [$theme], $app->getCachedPackagesPath());
check($fresh->merosProviders() === [], 'manifest must rebuild when installed.json changes');

config([
    'database.default' => 'lite',
    'database.connections.lite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    'queue.connections.pkg' => ['driver' => 'database', 'connection' => null, 'table' => 'pkg_jobs', 'queue' => 'pkg', 'retry_after' => 90],
    'auth.defaults.guard' => 'portal',
    'auth.guards.portal' => ['driver' => 'session', 'provider' => 'portals'],
    'auth.providers.portals' => ['driver' => 'eloquent', 'model' => PackageUser::class],
]);

Schema::create('pkg_jobs', function ($table) {
    $table->bigIncrements('id');
    $table->string('queue')->index();
    $table->longText('payload');
    $table->unsignedTinyInteger('attempts');
    $table->unsignedInteger('reserved_at')->nullable();
    $table->unsignedInteger('available_at');
    $table->unsignedInteger('created_at');
});
Schema::create('package_users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});

// Queue + queue:work through Artisan, as the CRM cron does.
$GLOBALS['handled'] = [];
Queue::connection('pkg')->push(new PackageJob('one'), '', 'pkg');
$status = Artisan::call('queue:work', ['connection' => 'pkg', '--queue' => 'pkg', '--stop-when-empty' => true, '--max-time' => 5, '--sleep' => 0, '--tries' => 1, '--timeout' => 30]);
check($status === 0, 'queue:work should exit cleanly');
check($GLOBALS['handled'] === ['one'], 'queued job should run through queue:work: ' . json_encode($GLOBALS['handled']));
check(Illuminate\Support\Facades\DB::table('pkg_jobs')->count() === 0, 'processed job should be removed');

// Auth: Eloquent user on the session guard with hashed passwords.
$user = PackageUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
check($user->password !== 'secret', 'password cast should hash');

$request = Request::create('/login');
$request->setLaravelSession($app['session.store']);
$app->instance('request', $request);

check(Auth::attempt(['email' => 'ada@example.test', 'password' => 'secret']) === true, 'attempt should succeed with the right password');
check(Auth::attempt(['email' => 'ada@example.test', 'password' => 'nope']) === false, 'attempt should fail with the wrong password');
check(Auth::guard('portal')->check() && Auth::user()->name === 'Ada', 'guard should be logged in');
Auth::logout();
check(!Auth::check(), 'logout should clear the user');

// Publishing a Laravel package's config (e.g. Socialite) into the theme.
class WP_CLI
{
    public static array $out = [];
    public static function success($m) { self::$out[] = "success:$m"; }
    public static function warning($m) { self::$out[] = "warning:$m"; }
    public static function error($m) { throw new RuntimeException($m); }
    public static function line($m) { self::$out[] = $m; }
}

class VendorProvider extends Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/vendor-stub.php' => config_path('vendor-stub.php'),
            __DIR__ . '/vendor-stub.php ' => public_path('escape.php'),
        ], 'config');
    }
}
file_put_contents(__DIR__ . '/vendor-stub.php', "<?php return ['key' => 'value'];\n");
file_put_contents(__DIR__ . '/vendor-stub.php ', '');
$app->register(VendorProvider::class);
$publish = new MM\Meros\Foundations\Console\Commands\PublishCommand($app);
$publish([], ['provider' => VendorProvider::class, 'tag' => 'config']);
check(is_file(config_path('vendor-stub.php')), 'provider config should be published to the theme');
check(!file_exists(public_path('escape.php')), 'non-config destinations should be skipped');
unlink(config_path('vendor-stub.php'));
$publish([], ['all' => true]);
check(is_file(config_path('vendor-stub.php')), '--all should publish every provider that declares config');
unlink(__DIR__ . '/vendor-stub.php');
unlink(__DIR__ . '/vendor-stub.php ');

// Livewire boots against the Foundation shims and its assets are injected via RequestHandled.
$app['config']->set('app.env', 'local');
$app->register(Livewire\LivewireServiceProvider::class);
check(class_exists(Illuminate\Foundation\Http\Middleware\TrimStrings::class), 'Foundation middleware aliases should resolve');
check($app['events']->hasListeners(Illuminate\Foundation\Http\Events\RequestHandled::class), 'Livewire should listen for RequestHandled');

echo "meros-foundations packages: ok\n";
(new Illuminate\Filesystem\Filesystem())->deleteDirectory($tmp);
