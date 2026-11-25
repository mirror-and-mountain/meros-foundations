<?php

namespace MM\Meros\Foundations\Http\Middleware;

class TrimStrings extends TransformsRequest
{
    /** @var string[] */
    protected array $except = ['current_password', 'password', 'password_confirmation'];

    protected function transform(string $key, mixed $value): mixed
    {
        return is_string($value) && !in_array($key, $this->except, true) ? trim($value) : $value;
    }
}
