<?php

namespace MM\Meros\Foundations\Session;

use Illuminate\Session\Middleware\StartSession as BaseStartSession;

class StartSession extends BaseStartSession
{
    /**
     * For WordPress routes the save is deferred to WordPress' shutdown hook so
     * flash data stays available while the template renders afterwards.
     */
    protected function saveSession($request)
    {
        if ($request->route()?->getName() === 'wordpress' && function_exists('add_action')) {
            add_action('shutdown', fn () => parent::saveSession($request), 0);

            return;
        }

        parent::saveSession($request);
    }
}
