<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', (__DIR__));
include '../kernel/autoload.php';


class server
{
    public function test($v1, $v2, $v3)
    {
        echo $v1 . $v1;
//        return [$v1, $v2, $v3];
    }
}

$server = new server();
(new \laocc\plugs\Async($server))->listen();