<?php
use Symfony\Component\Debug\Debug;

if (php_sapi_name() === 'cli-server') {
    $filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
    if(is_file($filename)) {
        return false;
    }
}

if(
    in_array(
        @$_SERVER['REMOTE_ADDR'],
        array('127.0.0.1', '::1')
    )
) {
    require_once __DIR__ . "/../app/bootstrap.php";
    Debug::enable();
    $app = new Rswork\Silex\Application(array('debug' => true));
    $app->run();
} else {
    require_once __DIR__.'/index.php';
}
