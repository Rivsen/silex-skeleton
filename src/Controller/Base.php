<?php

namespace Rswork\Silex\Controller;

use Rswork\Silex\Application;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Base
{
    public $app;

    public function __construct( Application $app )
    {
        $this->app = $app;
    }

    /**
     * proxy twig render method
     */
    public function render()
    {
        $args = func_get_args();
        return call_user_func_array(array($this->app['twig'], 'render'), $args);
    }

    /**
     * Generates a path from the given parameters.
     *
     * @param string $route      The name of the route
     * @param mixed  $parameters An array of parameters
     *
     * @return string The generated path
     */
    public function path($route, $parameters = array())
    {
        return $this->app['url_generator']->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Generates an absolute URL from the given parameters.
     *
     * @param string $route      The name of the route
     * @param mixed  $parameters An array of parameters
     *
     * @return string The generated URL
     */
    public function url($route, $parameters = array())
    {
        return $this->app['url_generator']->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
