<?php

namespace MM\Meros\Foundations\Console;

use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Events\Dispatcher;
use MM\Meros\Foundations\Application;
use MM\Meros\Foundations\Http\Kernel as HttpKernel;

/**
 * Minimal Artisan kernel so `Artisan::call()` and provider `commands()` work.
 * There is no terminal entry point; WP-CLI wraps it where needed.
 */
class Kernel implements KernelContract
{
    protected ?Artisan $artisan = null;

    public function __construct(protected Application $app, protected Dispatcher $events)
    {
    }

    public function bootstrap()
    {
        $this->app->make(HttpKernel::class)->bootstrap();
    }

    public function handle($input, $output = null)
    {
        $this->bootstrap();

        return $this->getArtisan()->run($input, $output);
    }

    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        $this->bootstrap();

        return $this->getArtisan()->call($command, $parameters, $outputBuffer);
    }

    public function queue($command, array $parameters = [])
    {
        throw new \LogicException('Queued Artisan commands are not supported.');
    }

    public function all()
    {
        $this->bootstrap();

        return $this->getArtisan()->all();
    }

    public function output()
    {
        $this->bootstrap();

        return $this->getArtisan()->output();
    }

    public function terminate($input, $status)
    {
        $this->app->terminate();
    }

    protected function getArtisan(): Artisan
    {
        return $this->artisan ??= (new Artisan($this->app, $this->events, $this->app->version()))
            ->setContainerCommandLoader();
    }
}
