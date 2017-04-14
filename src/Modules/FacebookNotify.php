<?php
namespace Kebabtent\GalaxyOfDreams\Modules;

use Kebabtent\GalaxyOfDreams\Config;
use Kebabtent\GalaxyOfDreams\Modules\FacebookNotify\Client;
use Kebabtent\GalaxyOfDreams\Modules\FacebookNotify\Page;
use Discord\Parts\Channel\Channel;
use Exception;

class FacebookNotify implements ModuleInterface {
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
   * @var Config $storage
   */
  protected $storage;

  /**
   * @var Page[]
   */
  protected $pages;

  /**
   * @var string[]
   */
  protected $lastYoutube;

  /**
   * @var Client
   */
  protected $client;

  /**
   * @inheritdoc
   */
  public function getName() {
    return "FacebookNotify";
  }

  /**
   * @inheritdoc
   */
  public function __construct($bot, $config, $logger) {
    $this->bot = $bot;
    $this->config = $config;
    $this->logger = $logger;
    $this->loop = $this->bot->getLoop();

    $this->bot->on("shutdown", [$this, "shutdown"]);

    if (!isset($this->config['storage']) || empty($this->config['storage'])) {
      throw new Exception("Storage not found");
    }
    $this->storage = Config::load($this->config['storage']);

    $module = $this->bot->getModule("HTTPClient"); /** @var HTTPClient $module */
    $this->client = new Client($module, $this->config['access_token']);

    $this->pages = [];
    $pages = $this->storage->has("pages") && is_array($this->storage->get("pages")) ? $this->storage->get("pages") : [];
    foreach ($pages as $pageConfig) {
      $page = new Page($this->logger, $this, $pageConfig);

      $this->pages[$page->getId()] = $page;

      foreach ($page->getChannelNames() as $channelName) {
        $this->addAnnounceChannel($page, $channelName);
      }
    }

    $this->lastYoutube = $this->storage->has("last_youtube") && is_array($this->storage->get("last_youtube")) ? $this->storage->get("last_youtube") : [];

    $this->bot->on("youtube.upload", [$this, "onYoutubeUpload"]);

    $this->bot->whenReady(function () {
      $this->bot->getLoop()->addTimer(10, [$this, "check"]);
    });
  }

  /**
   * This gets called when the YoutubeNotify module finds a new upload
   * @param string $videoChannelId
   * @param string $videoId
   */
  public function onYoutubeUpload($videoChannelId, $videoId) {
    $this->logger->debug("Caught video ".$videoId." to channel ".$videoChannelId);
    $this->lastYoutube[$videoChannelId] = $videoId;
    $this->saveStorage();
  }

  /**
   * Get array of last ID for a specific channel
   * @param string $videoChannelName
   * @return string|null
   */
  public function getLastYoutube($videoChannelName) {
    return isset($this->lastYoutube[$videoChannelName]) ? $this->lastYoutube[$videoChannelName] : null;
  }

  /**
   * Add a discord announce channel to a facebook page
   * @param Page $page
   * @param string $channelName
   */
  public function addAnnounceChannel($page, $channelName) {
    $this->bot->fetchChannel($channelName, function(Channel $channel) use ($page, &$channelName) {
      $page->addChannel($channelName, $channel);
    }, function (Exception $e) use ($page, &$channelName) {
      $this->logger->warning("Unable to fetch announce channel ".$channelName." (".$e->getMessage()."), retry in 10 seconds", ["FacebookNotify"]);
      $this->bot->getLoop()->addTimer(10, function () use ($page, &$channelName) {
        $this->addAnnounceChannel($page, $channelName);
      });
    });
  }

  /**
   * Gets triggered on shutdown
   * @throws Exception
   */
  public function shutdown() {
    $this->saveStorage();
  }

  public function saveStorage() {
    $this->logger->debug("Saving to storage..", ["FacebookNotify"]);

    $pages = [];
    foreach ($this->pages as $page) {
      $pages[] = $page->getConfig();
    }
    $this->storage->set("pages", $pages);
    $this->storage->set("last_youtube", $this->lastYoutube);
    $this->storage->save();
  }

  public function check() {
    foreach ($this->pages as $page) {
      $this->logger->debug("Check page ".$page->getId(), ["FacebookNotify"]);
      $fields = ["name", ["posts", ["message", "created_time", "type", "link", "full_picture"]]];
      //"picture.type(large)",

      if ($page->updateEvents()) {
        $fields[] = ["events", ["id", "name", "cover", "description", "start_time", "end_time", "place", ["feed", ["message", "story", "from"]]]];
      }

      $this->client->getAsync($page->getId(), $fields)->then(function ($response) use ($page) {
        $page->update($this->bot, $response);
        $this->saveStorage();
      }, function (Exception $e) use ($page) {
        $this->logger->warning("Unable to update page ".$page->getId()." (".$e->getMessage().")", ["FacebookNotify"]);
      });
    }

    $this->loop->addTimer(120, [$this, "check"]);
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
    return ["HTTPClient"];
  }
}