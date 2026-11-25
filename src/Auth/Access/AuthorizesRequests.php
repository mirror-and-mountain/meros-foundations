<?php

namespace MM\Meros\Foundations\Auth\Access;

use Illuminate\Contracts\Auth\Access\Gate;

trait AuthorizesRequests
{
    public function authorize($ability, $arguments = [])
    {
        return app(Gate::class)->authorize($ability, $arguments);
    }

    public function authorizeForUser($user, $ability, $arguments = [])
    {
        return app(Gate::class)->forUser($user)->authorize($ability, $arguments);
    }
}
