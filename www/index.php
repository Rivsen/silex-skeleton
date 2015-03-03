<?php
if (php_sapi_name() === 'cli-server') {
    $filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
    if(is_file($filename)) {
        return false;
    }
}

require_once __DIR__ . "/../app/bootstrap.php";

$app = new Rswork\Silex\Application(array('debug' => false));
$app->run();
