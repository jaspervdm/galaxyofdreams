<?php
namespace Kebabtent\GalaxyOfDreams\Modules;

use WyriHaximus\React\GuzzlePsr7\HttpClientAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class HTTPClient implements ModuleInterface {
  /**
   * @var \Kebabtent\GalaxyOfDreams\Bot $bot
   */
  protected $bot;

  /**
   * @var array
   */
  protected $config;

  /**
   * @var \Psr\Log\LoggerInterface $logger
   */
  protected $logger;

  /**
   * @var \React\EventLoop\LoopInterface $loop
   */
  protected $loop;

  /**
   * @var Client
   */
  protected $client;


  public function getName() {
    return "HTTPClient";
  }

  public function __construct($bot, $config, $logger) {
    $this->bot = $bot;
    $this->config = $config;
    $this->logger = $logger;
    $this->loop = $bot->getLoop();

    $handler = new HttpClientAdapter($this->loop);
    $this->client = new Client(["handler" => HandlerStack::create($handler)]);
  }


  /**
   * @param string|\Psr\Http\Message\UriInterface $uri
   * @param array $options
   * @return \GuzzleHttp\Promise\PromiseInterface
   */
  public function postAsync($uri, $options = []) {
    return $this->client->postAsync($uri, $options);
  }

  /**
   * @param string|\Psr\Http\Message\UriInterface $uri
   * @param array $options
   * @return \GuzzleHttp\Promise\PromiseInterface
   */
  public function getAsync($uri, $options = []) {
    return $this->client->getAsync($uri, $options);
  }

  public static function create($bot, $config, $logger) {
    return new self($bot, $config, $logger);
  }

  public static function requires() {
    return [];
  }
}
