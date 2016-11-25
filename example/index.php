<?php
ini_set('error_reporting', -1);
ini_set('display_errors', true);
ini_set('date.timezone', 'Asia/Shanghai');

define('_ROOT', (__DIR__));
include '../kernel/autoload.php';
$action = isset($_GET['action']) ? $_GET['action'] : null;


function nav()
{
    echo <<<HTML
    <style>
        html,body{width:90%;margin:0;padding:0;}
        a{color:#000;}
        ul,li{list-style: none;}
        ul{clear:both;display:block;width:100%;height:50px;}
        li{float:left;margin:10px;}
        img{margin:10px;padding:10px;border:1px solid #abc;}
        h4{color:#FF4C3B}
    </style>
    <title>LaoCC Plugs 测试</title>
    <ul>
        <li><a href="/?action=debug">Debug</a></li>
    </ul>
HTML;
}


nav();
if (in_array($action, ['debug'])) {
    $obj = new \demo\TestController();
    $obj->{$action}();
}



