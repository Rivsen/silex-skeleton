<?php

namespace Rswork\Silex;

use Silex;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Rswork\Silex\Controller;
use Rswork\Silex\Utils;

class RouterProvider implements ServiceProviderInterface
{
    public function register( Silex\Application $app )
    {
        /**
         * demo route
         */
        $rp = 'demo';
        $app[$rp.'.controller'] = $app->share(function() use ($app) {
            return new Controller\Demo($app);
        });

        $$rp = $app['controllers_factory'];

        $$rp->before(function( Request $request ) use ( $app, $rp ) {
            $app['routeprefix'] = $rp;
        });

        $$rp->match('/', $rp.'.controller:index')
            ->bind($rp.'.index')
            ->method('get')
        ;

        $$rp->match('/hello/{name}', $rp.'.controller:hello')
            ->value('name', 'Silex')
            ->bind($rp.'.hello')
            ->method('get')
        ;

        $$rp->before(function( Request $request ) use ($app) {
            // do something before controller
            // ...
        });

        $$rp->after(function( Request $request ) use ($app) {
            // do something after controller
            // ...
        });

        $app->mount('/demo', $$rp);
    }

    public function boot( Silex\Application $app )
    {
    }
}
