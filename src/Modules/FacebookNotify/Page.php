<?php
namespace Kebabtent\GalaxyOfDreams\Modules\FacebookNotify;

use Discord\Parts\Embed\Embed;
use Discord\Parts\Embed\Image;
use Kebabtent\GalaxyOfDreams\LargeInt;
use Kebabtent\GalaxyOfDreams\Modules\FacebookNotify;
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
   * @var FacebookNotify
   */
  protected $module;

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
   * Stores the channels to ignore the latest announced video from (announcement done by YoutubeNotify)
   */
  protected $ignoreLastYoutubeFrom;

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
  protected $postChannels;

  /**
   * @var string[]
   */
  protected $eventChannels;

  /**
   * @var string[]
   */
  protected $eventPostChannels;

  /**
   * @var Channel[]
   */
  protected $channels;

  /**
   * @param LoggerInterface $logger
   * @param FacebookNotify $module
   * @param array $config
   */
  public function __construct($logger, $module, $config) {
    $this->logger = $logger;
    $this->module = $module;

    $this->id = $config['id'];
    $this->name =  isset($config['name']) && !empty($config['name']) ? $config['name'] : null;

    $this->postChannels = isset($config['channels']['posts']) && is_array($config['channels']['posts']) ? $config['channels']['posts'] : [];
    $this->eventChannels = isset($config['channels']['events']) && is_array($config['channels']['events']) ? $config['channels']['events'] : [];
    $this->eventPostChannels = isset($config['channels']['event_posts']) && is_array($config['channels']['event_posts']) ? $config['channels']['event_posts'] : [];

    $this->channels = [];

    $this->ignoreLastYoutubeFrom = isset($config['ignore_last_youtube_from']) && is_array($config['ignore_last_youtube_from']) ? $config['ignore_last_youtube_from'] : [];

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
   * @param string $channelName
   * @param Channel $channel
   */
  public function addChannel($channelName, $channel) {
    $this->logger->info("Add announce channel ".$channel->name." to ".$this->id, ["FacebookNotify"]);
    $this->channels[$channelName] = $channel;
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
   * Get array of channel name strings
   * @return string[]
   */
  public function getChannelNames() {
    return array_values(array_unique(array_merge($this->postChannels, $this->eventChannels, $this->eventPostChannels)));
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

  public function announcePost($bot, $message, $embed = null) {
    foreach ($this->postChannels as $channelName) {
      if (isset($this->channels[$channelName])) {
        $this->announceChannel($bot, $this->channels[$channelName], $message, $embed);
      }
      else {
        $this->logger->info("Channel ".$channelName." not found", ["FacebookNotify"]);
      }
    }
  }

  public function announceEvent($bot, $message, $embed = null) {
    foreach ($this->eventChannels as $channelName) {
      if (isset($this->channels[$channelName])) {
        $this->announceChannel($bot, $this->channels[$channelName], $message, $embed);
      }
      else {
        $this->logger->info("Channel ".$channelName." not found", ["FacebookNotify"]);
      }
    }
  }

  public function announceEventPost($bot, $message, $embed = null) {
    foreach ($this->eventPostChannels as $channelName) {
      if (isset($this->channels[$channelName])) {
        $this->announceChannel($bot, $this->channels[$channelName], $message, $embed);
      }
      else {
        $this->logger->info("Channel ".$channelName." not found", ["FacebookNotify"]);
      }
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

        $url = $postData['link'];
        if (count($this->ignoreLastYoutubeFrom) && (preg_match("~youtube.com/watch\?v=(?<id>[a-z0-9_-]+)~i", $url, $match) || preg_match("~youtu.be/(?<id>[a-z0-9_-]+)~i", $url, $match))) {
          $videoId = $match['id'];
          foreach ($this->ignoreLastYoutubeFrom as $videoChannelId) {
            if ($this->module->getLastYoutube($videoChannelId) == $videoId) {
              $this->logger->info("Skipped post ".$id." on page ".$this->id.", since it contains youtube ID ".$videoId, ["FacebookNotify"]);
              continue 2;
            }
          }
        }

        $this->logger->info("New post ".$id." on page ".$this->id, ["FacebookNotify"]);

        $image = isset($postData['full_picture']) ? $bot->getDiscord()->factory(Image::class, ["url" => $postData['full_picture']]) : null;

        $embed = $bot->getDiscord()->factory(Embed::class, [
          "title" => $this->name,
          "image" => $image,
          "url" => $url,
          "timestamp" => $postData['created_time'],
          "description" => $postData['message']
        ]); /** @var Embed $embed */

        $message = "**New ".$postData['type'].":**";
        $this->announcePost($bot, $message, $embed);
      }

      if (!$this->updateEvents) {
        return;
      }

      $latestEvent = $this->hasLatestEvent() ? LargeInt::fromString($this->latestEvent) : null;
      $firstEvent = true;
      $announceEvents = true;

      foreach ($data['events']['data'] as $eventData) {
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
          $event = $this->events[$id];
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

    /*usort($events, function ($a, $b) {
      return LargeInt::fromString($a->getId())->compare($b->getId());
    });*/

    return [
      "id" => $this->id,
      "name" => $this->name,
      "channels" => [
        "posts" => $this->postChannels,
        "events" => $this->eventChannels,
        "event_posts" => $this->eventPostChannels,
      ],
      "ignore_last_youtube_from" => $this->ignoreLastYoutubeFrom,
      "latest_post" => $this->latestPost,
      "update_events" => $this->updateEvents,
      "latest_event" => $this->latestEvent,
      "events" => $events
    ];
  }

  /**
   * Create new page
   * @param LoggerInterface $logger
   * @param FacebookNotify $module
   * @param string $id
   * @return Page
   */
  public static function create($logger, $module, $id) {
    return new self($logger, $module, ["id" => $id]);
  }
}