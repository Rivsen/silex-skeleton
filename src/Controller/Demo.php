<?php

namespace Rswork\Silex\Controller;

use Rswork\Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Demo extends Base
{
    public function index( Request $request, Application $app )
    {
        return $this->render( 'demo/index.html.twig' );
    }

    public function hello( $name, Request $request, Application $app )
    {
        return $this->render( 'demo/hello.html.twig', array('name'=>$name) );
    }
}
