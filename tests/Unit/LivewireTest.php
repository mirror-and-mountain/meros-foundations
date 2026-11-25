<?php

namespace MM\Meros\Foundations\Tests\Unit;

use MM\Meros\Foundations\Testing\TestCase;

final class LivewireTest extends TestCase
{
    public function test_livewire_boots_with_foundation_compatibility_services(): void
    {
        $app = $this->createApplication([\Livewire\LivewireServiceProvider::class, \Carbon\Laravel\ServiceProvider::class], [
            'app' => ['env' => 'testing'],
        ]);
        $this->assertSame('en_GB', $app['translator']->getLocale());
        $this->assertFalse($app['validator']->make(['a' => ''], ['a' => 'required'])->passes());
        $this->assertTrue(class_exists(\Illuminate\Foundation\Http\Middleware\TrimStrings::class));
        $this->assertTrue($app['events']->hasListeners(\Illuminate\Foundation\Http\Events\RequestHandled::class));
        $this->assertTrue(is_subclass_of(\Illuminate\Foundation\Auth\User::class, \Illuminate\Database\Eloquent\Model::class));
        $this->assertTrue(\Illuminate\Testing\TestResponse::hasMacro('assertSeeLivewire'));
    }
}
