<?php

namespace MM\Meros\Foundations\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\ParameterBag;

/** Applies a transformation to every query and request input value. */
abstract class TransformsRequest
{
    /** @var array<class-string, callable[]> */
    protected static array $skipCallbacks = [];

    public function handle($request, Closure $next)
    {
        foreach (static::$skipCallbacks[static::class] ?? [] as $callback) {
            if ($callback($request)) {
                return $next($request);
            }
        }

        $this->clean($request);

        return $next($request);
    }

    public static function skipWhen(Closure $callback): void
    {
        static::$skipCallbacks[static::class][] = $callback;
    }

    public static function flushState(): void
    {
        static::$skipCallbacks = [];
    }

    protected function clean($request): void
    {
        $this->cleanParameterBag($request->query);

        if ($request->isJson()) {
            $this->cleanParameterBag($request->json());
        } elseif ($request->request !== $request->query) {
            $this->cleanParameterBag($request->request);
        }
    }

    protected function cleanParameterBag(ParameterBag $bag): void
    {
        $bag->replace($this->cleanArray($bag->all()));
    }

    protected function cleanArray(array $data, string $keyPrefix = ''): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = is_array($value)
                ? $this->cleanArray($value, "{$keyPrefix}{$key}.")
                : $this->transform("{$keyPrefix}{$key}", $value);
        }

        return $data;
    }

    abstract protected function transform(string $key, mixed $value): mixed;
}
