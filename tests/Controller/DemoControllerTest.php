<?php

namespace Rswork\Silex\Tests\Controller;

use Rswork\Silex\Application;
use Silex\WebTestCase;

class DemoControllerTest extends WebTestCase
{

    public function createApplication()
    {
        $app = new Application(array('debug'=>true));

        $app['exception_handler']->disable();
        $app['session.test'] = true;

        return $app;
    }

    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/demo/');

        $this->assertTrue($crawler->filter('html:contains("Hello Silex Demo!")')->count() > 0);
    }

    public function testHello()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/demo/hello');

        $this->assertTrue($crawler->filter('html:contains("Hello, Silex!")')->count() > 0);

        $crawler = $client->request('GET', '/demo/hello/PHPUnit');

        $this->assertTrue($crawler->filter('html:contains("Hello, PHPUnit!")')->count() > 0);
    }
}
