<?php
namespace Kebabtent\GalaxyOfDreams\Modules;

use Kebabtent\GalaxyOfDreams\Modules\YoutubeNotify\VideoChannel;
use React\Http\Request;
use React\Http\Response;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Gt\Dom\XMLDocument;
use Discord\Discord;
use Discord\Repository\GuildRepository;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Channel\Channel;
use Exception;

class YoutubeNotify implements ModuleInterface {
  protected static $feedURL = "https://www.youtube.com/xml/feeds/videos.xml?channel_id=";

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
   * @var VideoChannel[]
   */
  protected $videoChannels;

  /**
   * @inheritdoc
   */
  public function getName() {
    return "YoutubeNotify";
  }

  /**
   * @inheritdoc
   */
  public function __construct($bot, $config, $logger) {
    $this->bot = $bot;
    $this->config = $config;
    $this->logger = $logger;
    $this->loop = $this->bot->getLoop();
    $this->videoChannels = [];

    if (isset($config['video_channels'])) {
      foreach ($config['video_channels'] as $videoChannelConfig) {
        $videoChannel = new VideoChannel($this->logger, $videoChannelConfig['id']);
        if (isset($videoChannelConfig['message'])) {
          $videoChannel->setMessage($videoChannelConfig['message']);
        }

        $this->videoChannels[$videoChannel->getId()] = $videoChannel;

        foreach ($videoChannelConfig['channels'] as $channelName) {
          $this->addAnnounceChannel($videoChannel, $channelName);
        }

        if (!isset($config['subscribe']) || $config['subscribe']) {
          $this->loop->futureTick(function () use ($videoChannel) {
            $this->subscribe($videoChannel);
          });
        }
      }
    }


    $server = $this->bot->getModule("HTTPServer"); /** @var HTTPServer $server*/
    $server->listen($config['listen_callback']);

    $this->bot->on("http.request", [$this, "request"]);
  }

  /**
   * Add a discord announce channel to a video channel
   * @param VideoChannel $videoChannel
   * @param string $channelName
   */
  public function addAnnounceChannel($videoChannel, $channelName) {
    $this->bot->fetchChannel($channelName, function(Channel $channel) use ($videoChannel) {
      $videoChannel->addChannel($channel);
    }, function (Exception $e) use ($videoChannel, &$channelName) {
      $this->logger->warning("Unable to fetch announce channel ".$channelName." (".$e->getMessage()."), retry in 10 seconds", ["YoutubeNotify"]);
      $this->bot->getLoop()->addTimer(10, function () use ($videoChannel, &$channelName) {
        $this->addAnnounceChannel($videoChannel, $channelName);
      });
    });
  }

  /**
   * @param VideoChannel $channel
   */
  public function subscribe($channel) {
    $this->logger->info("Subscribing to ".$channel->getId(), ["YoutubeNotify"]);

    $channel->setStatus(VideoChannel::$STATUS_VERIFY);

    $client = $this->bot->getModule("HTTPClient"); /** @var HTTPClient $client */
    $client->postAsync("https://pubsubhubbub.appspot.com/subscribe", ["form_params" => [
      "hub.mode" => "subscribe",
      "hub.callback" => "http://".$this->config['listen_callback']."/yt/callback",
      "hub.topic" => self::$feedURL.$channel->getId(),
      "hub.verify_token" => $channel->getVerifyToken()
    ]])->then(function (ResponseInterface $res) use ($channel) {
      if (floor($res->getStatusCode()/100) == 2) {
        $this->logger->debug("Subscription ".$channel->getId()." accepted", ["YoutubeNotify"]);
      }
      else {
        $this->logger->warning("Subscription ".$channel->getId()." failed (status ".$res->getStatusCode()."), retrying in 30 seconds", ["YoutubeNotify"]);
        $this->loop->addTimer(30, function () use ($channel) {
          $this->subscribe($channel);
        });
      }
    }, function (RequestException $e) use ($channel) {
      $this->logger->warning("Subscription ".$channel->getId()." failed (exception ".$e->getMessage()."), retrying in 30 seconds", ["YoutubeNotify"]);
      $this->loop->addTimer(30, function () use ($channel) {
        $this->subscribe($channel);
      });
    });
  }

  /**
   * Helper method for giving a response to http request
   * @param Response $response
   * @param int $status
   * @param string|null $body
   */
  protected function giveResponse($response, $status, $body = null) {
    $this->loop->futureTick(function () use ($response, $status, $body) {
      $response->writeHead($status);
      $response->end($body);
    });
  }

  /**
   * Catch request from HTTPServer module
   * @param Request $request
   * @param Response $response
   * @param string $body
   */
  public function request($request, $response, $body) {
    if ($request->getPath() != "/yt/callback") {
      return;
    }

    $query = $request->getQueryParams();

    if (isset($query['hub_mode']) && $query['hub_mode'] == "subscribe") {
      if (!isset($query['hub_topic']) || !preg_match("~^".str_replace("?", "\\?", self::$feedURL."(.*)~i"), $query['hub_topic'], $match)) {
        $this->logger->warning("Received invalid topic", ["YoutubeNotify"]);
        $this->giveResponse($response, 404);
        return;
      }

      $id = $match[1];
      if (!isset($this->videoChannels[$id])) {
        $this->logger->warning("Received unknown channel ".$id, ["YoutubeNotify"]);
        $this->giveResponse($response, 404);
        return;
      }

      $channel = $this->videoChannels[$id];

      if ($channel->getStatus() != VideoChannel::$STATUS_VERIFY) {
        $this->logger->warning("Channel ".$id." not in verify mode", ["YoutubeNotify"]);
        $this->giveResponse($response, 404);
        return;
      }

      if (!isset($query['hub_verify_token']) || $channel->getVerifyToken() != $query['hub_verify_token']) {
        $this->logger->warning("Channel ".$id." incorrect verify token", ["YoutubeNotify"]);
        $this->giveResponse($response, 404);
        return;
      }

      if (!isset($query['hub_challenge'])) {
        $this->logger->warning("Channel ".$id." no challenge", ["YoutubeNotify"]);
        $this->giveResponse($response, 404);
        return;
      }

      $lease = 432000;
      if (isset($query['hub_lease_seconds'])) {
        $lease = $query['hub_lease_seconds'];
      }

      $this->logger->info("Channel ".$id." subscribed, expire in ".$lease."s", ["YoutubeNotify"]);

      $channel->setStatus(VideoChannel::$STATUS_SUBSCRIBED);
      $channel->setExpire($lease);

      $this->loop->addTimer($channel->getTimeToExpire()-600, function() use ($channel) {
        $this->subscribe($channel);
      });

      $this->giveResponse($response, 200, $query['hub_challenge']);
      return;
    }

    $dom = new XMLDocument($body);
    $entries = $dom->getElementsByTagName("entry");

    if ($entries->length == 0) {
      file_put_contents("logs/".time().".log", $body);
      $this->logger->warning("Received unparsed callback", ["YoutubeNotify"]);
      $this->giveResponse($response, 404);
      return;
    }

    foreach ($entries as $entry) {
      /** @var \Gt\Dom\Element $entry */
      $channelIdNode = $entry->getElementsByTagName("channelId")->item(0);
      $videoIdNode = $entry->getElementsByTagName("videoId")->item(0);

      if (!$channelIdNode || !$videoIdNode) {
        $this->logger->warning("Received malformed entry", ["YoutubeNotify"]);
        continue;
      }

      $channelId = $channelIdNode->textContent;
      $videoId = $videoIdNode->textContent;

      $channel = $this->videoChannels[$channelId];
      if (!$channel) {
        $this->logger->warning("Received upload from unknown channel ".$channelId, ["YoutubeNotify"]);
        continue;
      }

      $this->loop->futureTick(function () use ($channel, &$channelId, &$videoId) {
        $channel->announce($this->bot, $videoId);
      });
    }

    $this->logger->info("Received ".$entries->length." new elements", ["YoutubeNotify"]);
    $this->giveResponse($response, 200);
  }

  /**
   * @inheritdoc
   */
  public static function create($bot, $config, $logger) {
    return new self($bot, $config, $logger);
  }

  /**
   * @inheritdoc
   */
  public static function requires() {
    return ["HTTPClient", "HTTPServer"];
  }
}
