<?php

namespace Niktux\DDD\Analyzer\Controllers\Api;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;

class Provider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $app['controller.api'] = function() use($app) {
            return new Controller($app['var.path']);
        };

        $controllers = $app['controllers_factory'];

        $controllers
            ->match('/report', 'controller.api:reportAction')
            ->method('GET')
            ->bind('report');

        return $controllers;
    }
}
