<?php
require_once("vendor/autoload.php");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Noodlehaus\Config;
use Kebabtent\GalaxyOfDreams\Bot;

$logger = new Logger("GoD");
$logger->pushHandler(new StreamHandler("php://stdout", Logger::DEBUG));
$logger->pushHandler(new StreamHandler(__DIR__."/logs/".date("Y-m-d").".log", Logger::WARNING));


$config = Config::load("config.json");
$bot = new Bot($config, $logger);
$bot->run();
