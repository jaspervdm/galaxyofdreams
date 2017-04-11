<?php
namespace Kebabtent\GalaxyOfDreams\Modules\FacebookNotify;

use Discord\Parts\Embed\Embed;
use Discord\Parts\Embed\Image;
use Kebabtent\GalaxyOfDreams\LargeInt;
use Psr\Log\LoggerInterface;
use Kebabtent\GalaxyOfDreams\Bot;
use Discord\Parts\Channel\Channel;
use Exception;

class Page {
  /**
   * @var LoggerInterface;
   */
  protected $logger;

  /**
   * Stores the page id
   * @var string
   */
  protected $id;

  /**
   * Stores the page name
   * @var string
   */
  protected $name;

  /**
   * Stores the id of the latest post
   * @var string
   */
  protected $latestPost;

  /**
   * Update events
   * @var bool
   */
  protected $updateEvents;

  /**
   * Stores the id of the latest event
   * @var string
   */
  protected $latestEvent;

  /**
   * @var Event[]
   */
  protected $events;

  /**
   * @var string[]
   */
  protected $channelConfig;

  /**
   * @var Channel[]
   */
  protected $channels;

  /**
   * @param LoggerInterface $logger
   * @param array $config
   */
  public function __construct($logger, $config) {
    $this->logger = $logger;
    $this->id = $config['id'];
    $this->name =  isset($config['name']) && !empty($config['name']) ? $config['name'] : null;

    $this->channelConfig = isset($config['channels']) && is_array($config['channels']) ? $config['channels'] : [];
    $this->channels = [];
    $this->latestPost = isset($config['latest_post']) && !empty($config['latest_post']) ? $config['latest_post'] : null;
    $this->updateEvents = isset($config['update_events']) ? ($config['update_events'] ? true : false) : false;
    $this->latestEvent = isset($config['latest_event']) && !empty($config['latest_event']) ? $config['latest_event'] : null;
    $this->events = [];
    if (isset($config['events'])) {
      foreach ($config['events'] as $eventConfig) {
        $event = new Event($this->logger, $eventConfig);
        $this->events[$event->getId()] = $event;
      }
    }
  }

  /**
   * Add discord channel to announce uploads in
   * @param Channel $channel
   */
  public function addChannel($channel) {
    $this->logger->info("Add announce channel ".$channel->name." to ".$this->id, ["FacebookNotify"]);
    $this->channels[] = $channel;
  }

  /**
   * Get page id
   * @return int
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Check if page has a name
   * @return bool
   */
  public function hasName() {
    return !is_null($this->name);
  }

  /**
   * Get page name
   * @return null|string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Set page name
   * @param string $name
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * Check if page has a latest post
   * @return bool
   */
  public function hasLatestPost() {
    return !is_null($this->latestPost);
  }

  /**
   * Get latest post of page
   * @return null|string
   */
  public function getLatestPost() {
    return $this->latestPost;
  }

  /**
   * @param string $latestPost
   */
  public function setLatestPost($latestPost) {
    $this->latestPost = $latestPost;
  }

  /**
   * Update events?
   * @return bool
   */
  public function updateEvents() {
    return $this->updateEvents;
  }

  /**
   * Check if page has a latest event
   * @return bool
   */
  public function hasLatestEvent() {
    return !is_null($this->latestEvent);
  }

  /**
   * Get latest event of page
   * @return null|string
   */
  public function getLatestEvent() {
    return $this->latestEvent;
  }

  /**
   * Set latest event of page
   * @param string $latestEvent
   */
  public function setLatestEvent($latestEvent) {
    $this->latestEvent = $latestEvent;
  }

  /**
   * Announce a message to a channel
   * @param Bot $bot
   * @param Channel $channel
   * @param string $message
   * @param null|Embed $embed
   */
  protected function announceChannel($bot, $channel, $message, $embed = null) {
    $bot->execute(function () use ($channel, &$message, $embed) {
      return $channel->sendMessage($message, false, $embed);
    }, function () use ($channel) {
      $this->logger->info("Announced to ".$channel->name, ["FacebookNotify"]);
    }, function (Exception $e, $timeout) use ($channel) {
      $this->logger->warning("Unable to announce to ".$channel->name." (".$e->getMessage().") retry in ".$timeout."s", ["FacebookNotify"]);
    }, function (Exception $e) use ($channel) {
      $this->logger->warning("Unable to announce to ".$channel->name." (".$e->getMessage().")", ["FacebookNotify"]);
    });
  }

  public function announce($bot, $message, $embed = null) {
    foreach ($this->channels as $channel) {
      $this->announceChannel($bot, $channel, $message, $embed);
    }
  }

  /**
   * Update page
   * @param Bot $bot
   * @param array $data
   */
  public function update($bot, $data) {
    if (!is_array($data) || !isset($data['name'])) {
      return;
    }

    $this->name = $data['name'];

    if (isset($data['posts']['data'])) {
      $latestPost = $this->hasLatestPost() ? LargeInt::fromString($this->latestPost) : null;
      $firstPost = true;

      $newPosts = [];

      foreach ($data['posts']['data'] as $postData) {
        $idParts = explode("_", $postData['id']);
        $id = array_pop($idParts);

        if (!$this->hasLatestPost()) {
          // No latest post stored, don't announce anything
          $this->logger->info("Set latest post to ".$id." on page ".$this->id, ["FacebookNotify"]);
          $this->latestPost = $id;
          break;
        }

        if ($latestPost->smallerThan($id)) {
          // New post
          if ($firstPost) {
            $this->latestPost = $id;
            $firstPost = false;
          }
          $newPosts[] = $postData;
        }
        else {
          // Stop checking
          break;
        }
      }

      $reversePosts = array_reverse($newPosts);
      foreach ($reversePosts as $postData) {
        $idParts = explode("_", $postData['id']);
        $id = array_pop($idParts);
        $this->logger->info("New post ".$id." on page ".$this->id, ["FacebookNotify"]);

        $image = isset($postData['full_picture']) ? $bot->getDiscord()->factory(Image::class, ["url" => $postData['full_picture']]) : null;

        $embed = $bot->getDiscord()->factory(Embed::class, [
          "title" => $this->name,
          "image" => $image,
          "url" => $postData['link'],
          "timestamp" => $postData['created_time'],
          "description" => $postData['message']
        ]); /** @var Embed $embed */

        $message = "**New ".$postData['type']."**";
        $this->announce($bot, $message, $embed);
      }

      if (!$this->updateEvents) {
        return;
      }

      $latestEvent = $this->hasLatestEvent() ? LargeInt::fromString($this->latestEvent) : null;
      $firstEvent = true;
      $announceEvents = true;

      foreach ($data['events']['data'] as $eventData) {
        $newEvent = false;
        $id = $eventData['id'];

        if (!$this->hasLatestEvent()) {
          // No latest event stored, don't announce anything
          $this->logger->info("Set latest event to ".$id." on page ".$this->id, ["FacebookNotify"]);
          $this->latestEvent = $id;
          $announceEvents = false;
        }

        $event = null;
        if (!isset($this->events[$id])) {
          // New event
          $this->logger->info("New event ".$id." on page ".$this->id, ["FacebookNotify"]);
          $event = new Event($this->logger, ["id" => $id]);
          $this->events[$id] = $event;
        }
        else {
          $event = $this->events[$eventData['id']];
        }

        if ($firstEvent && $announceEvents && $latestEvent->smallerThan($id)) {
          $this->latestEvent = $id;
          $firstEvent = false;
        }

        $event->update($this, $bot, $eventData, $announceEvents);

      }
    }
  }

  /**
   * Get configuration for storage
   * @return array
   */
  public function getConfig() {
    $events = [];
    foreach ($this->events as $id => $event) {
      $events[] = $event->getConfig();
    }

    return [
      "id" => $this->id,
      "name" => $this->name,
      "channels" => $this->channelConfig,
      "latest_post" => $this->latestPost,
      "update_events" => $this->updateEvents,
      "latest_event" => $this->latestEvent,
      "events" => $events
    ];
  }

  /**
   * Create new page
   * @param $logger
   * @param $id
   * @return Page
   */
  public static function create($logger, $id) {
    return new self($logger, ["id" => $id]);
  }
}