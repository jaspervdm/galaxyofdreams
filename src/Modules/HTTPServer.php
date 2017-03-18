<?php
namespace Kebabtent\GalaxyOfDreams\Modules;

use React\Socket\Server as SocketServer;
use React\Http\Server;
use React\Http\Request;
use React\Http\Response;
use Exception;

class HTTPServer implements ModuleInterface {
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
   * List of servers
   * @var Server[] $servers
   */
  protected $servers;

  public function getName() {
    return "HTTPServer";
  }

  public function __construct($bot, $config, $logger) {
    $this->bot = $bot;
    $this->config = $config;
    $this->logger = $logger;
    $this->loop = $bot->getLoop();
    $this->servers = [];

    if (isset($config['listen'])) {
      $listen = is_array($config['listen']) ? $config['listen'] : [$config['listen']];
      foreach ($listen as $uri) {
        $this->listen($uri);
      }
    }
  }

  /**
   * Listen to a port
   * @param string $uri
   * @return Server
   */
  public function listen($uri) {
    if (isset($this->servers[$uri])) {
      return $this->servers[$uri];
    }

    $socket = new SocketServer($uri, $this->bot->getLoop());
    $server = new Server($socket);

    $this->logger->info("Listening on ".$uri, ["HTTPServer"]);

    $server->on("request", [$this, "request"]);

    $this->servers[$uri] = $server;

    return $server;
  }

  /**
   * @param Request $request
   * @param Response $response
   */
  public function request($request, $response) {
    $body = "";

    $this->logger->debug("Request ".$request->getPath(), ["HTTPServer"]);

    $timeout = $this->loop->addTimer(30, function() use ($request, $response) {
      $this->logger->warning("Request ".$request->getPath()." timed out", ["HTTPServer"]);
      $response->writeHead(400);
      $response->end();
    });

    $request->on("data", function ($data) use ($request, &$body) {
      $body .= $data;
      $this->bot->emit("http.request.data", [$request, $data]);
    });

    $request->on("error", function (Exception $e) use ($request, $response) {
      $this->bot->emit("http.request.error", [$request, $e]);
      $this->loop->futureTick(function () use ($request, $response, $e) {
        // If no one handled the error, return 400
        if ($response->isWritable()) {
          $this->logger->warning("Unhandled error ".$request->getPath().": ".$e->getMessage(), ["HTTPServer"]);
          $response->writeHead(400);
          $response->end();
        }
      });
    });

    $request->on("end", function () use ($request, $response, &$body, $timeout) {
      $this->logger->debug("Request ".$request->getPath()." ended", ["HTTPServer"]);
      if ($timeout) {
        $timeout->cancel();
        $timeout = null;
      }
      $this->bot->emit("http.request", [$request, $response, $body]);
      $this->loop->futureTick(function () use ($request, $response) {
        // If no one handled the request, return 404
        if ($response->isWritable()) {
          $this->logger->warning("Unhandled request ".$request->getPath(), ["HTTPServer"]);
          $response->writeHead(404);
          $response->end();
        }
      });
    });
  }

  public static function create($bot, $config, $logger) {
    return new self($bot, $config, $logger);
  }

  public static function requires() {
    return [];
  }
}
