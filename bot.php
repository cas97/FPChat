<?php

require_once './config.php';

// Fuck you PHP, fuck you
// TODO: Make sure the account uses UTC
date_default_timezone_set('UTC');

require_once 'Library/Zend/Loader/StandardAutoloader.php';

$loader = new \Zend\Loader\StandardAutoloader();
$loader->registerNamespace('FPChat', 'Library/FPChat/')->register();

use FPChat\Bot;

$bot = new Bot();
$bot->login($config['username'], $config['password']);
$bot->registerPlugin('console', new FPChat\Plugin\ConsolePrint);
//$bot->registerPlugin('snarky', new FPChat\Plugin\Snarky);
$bot->registerPlugin('EightBallBot', new FPChat\Plugin\EightBallBot);
$bot->run();