<?php

namespace MM\Meros\Foundations\Tests\Unit;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use MM\Meros\Foundations\Auth\User;
use MM\Meros\Foundations\Testing\TestCase;

final class QueueAuthTest extends TestCase
{
    public function test_database_jobs_are_processed_by_artisan(): void
    {
        $app = $this->createApplication(config: ['queue' => ['connections' => ['pkg' => [
            'driver' => 'database', 'connection' => null, 'table' => 'pkg_jobs', 'queue' => 'pkg', 'retry_after' => 90,
        ]]]]);
        Schema::create('pkg_jobs', function ($table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        PackageJob::$handled = [];
        Queue::connection('pkg')->push(new PackageJob('one'), '', 'pkg');
        $status = Artisan::call('queue:work', [
            'connection' => 'pkg', '--queue' => 'pkg', '--stop-when-empty' => true,
            '--max-time' => 5, '--sleep' => 0, '--tries' => 1, '--timeout' => 30,
        ]);
        $this->assertSame(0, $status);
        $this->assertSame(['one'], PackageJob::$handled);
        $this->assertSame(0, $app['db']->table('pkg_jobs')->count());
    }

    public function test_session_auth_uses_hashed_eloquent_passwords(): void
    {
        $app = $this->createApplication(config: ['auth' => [
            'defaults' => ['guard' => 'portal'],
            'guards' => ['portal' => ['driver' => 'session', 'provider' => 'portals']],
            'providers' => ['portals' => ['driver' => 'eloquent', 'model' => PackageUser::class]],
        ]]);
        Schema::create('package_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        $user = PackageUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
        $this->assertNotSame('secret', $user->password);
        $request = Request::create('/login');
        $request->setLaravelSession($app['session.store']);
        $app->instance('request', $request);
        $this->assertTrue(Auth::attempt(['email' => 'ada@example.test', 'password' => 'secret']));
        $this->assertFalse(Auth::attempt(['email' => 'ada@example.test', 'password' => 'wrong']));
        $this->assertTrue(Auth::guard('portal')->check());
        $this->assertSame('Ada', Auth::user()->name);
        Auth::logout();
        $this->assertFalse(Auth::check());
    }
}

class PackageUser extends User
{
    protected $table = 'package_users';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}

class PackageJob implements ShouldQueue
{
    public int $tries = 1;
    public static array $handled = [];

    public function __construct(public string $value) {}

    public function handle(): void
    {
        self::$handled[] = $this->value;
    }
}
