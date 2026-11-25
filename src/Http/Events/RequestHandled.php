<?php

namespace MM\Meros\Foundations\Http\Events;

class RequestHandled
{
    public function __construct(public $request, public $response)
    {
    }
}
