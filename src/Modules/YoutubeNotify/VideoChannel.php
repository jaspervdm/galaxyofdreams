<?php
namespace Kebabtent\GalaxyOfDreams\Modules\YoutubeNotify;

use Kebabtent\GalaxyOfDreams\Bot;
use Kebabtent\GalaxyOfDreams\Modules\FacebookNotify;
use Discord\Parts\Channel\Channel;
use Psr\Log\LoggerInterface;
use Exception;

class VideoChannel {
  static $STATUS_UNSUBSCRIBED = 1;
  static $STATUS_SUBSCRIBED = 2;
  static $STATUS_VERIFY = 3;

  /**
   * @var LoggerInterface;
   */
  protected $logger;

  /**
   * @var string
   */
  protected $id;

  /**
   * @var int
   */
  protected $status;

  /**
   * @var string
   */
  protected $verifyToken;

  /**
   * @var int
   */
  protected $expire;

  /**
   * @var Channel[]
   */
  protected $channels;

  /**
   * @var string
   */
  protected $message;

  /**
   * @var string
   */
  protected $last;

  public function __construct($logger, $id) {
    $this->logger = $logger;
    $this->id = $id;
    $this->setStatus(self::$STATUS_UNSUBSCRIBED);
    $this->expire = 0;
    $this->channels = [];
    $this->message = "New upload: https://youtu.be/%ID%";
    $this->last = "";
  }

  /**
   * Get last announced video ID
   * @return string
   */
  public function getLast() {
    return $this->last;
  }

  /**
   * Announce new upload
   * @param Bot $bot
   * @param int $id
   */
  public function announce($bot, $id) {
    if ($this->last == $id) {
      $this->logger->warning("Duplicated announcement for ".$id, ["YoutubeNotify"]);
      return;
    }
    $this->last = $id;

    foreach ($this->channels as $channel) {
      $bot->execute(function () use ($channel, &$id) {
        return $channel->sendMessage(str_replace("%ID%", $id, $this->message));
      }, function () use (&$id, $channel) {
        $this->logger->info("Announced ".$id." to ".$channel->name, ["YoutubeNotify"]);
      }, function (Exception $e, $timeout) use (&$id, $channel) {
        $this->logger->warning("Unable to announce ".$id." to ".$channel->name." (".$e->getMessage().") retry in ".$timeout."s", ["YoutubeNotify"]);
      }, function (Exception $e) use (&$id, $channel) {
        $this->logger->warning("Unable to announce ".$id." to ".$channel->name." (".$e->getMessage().")", ["YoutubeNotify"]);
      });
    }
  }

  /**
   * Add discord channel to announce uploads in
   * @param Channel $channel
   */
  public function addChannel($channel) {
    $this->logger->info("Add announce channel ".$channel->name." to ".$this->id, ["YoutubeNotify"]);
    $this->channels[] = $channel;
  }

  /**
   * @return Channel[]
   */
  public function getChannels() {
    return $this->channels;
  }

  public function setMessage($message) {
    $this->message = $message;
  }

  /**
   * Get channel id
   * @return string
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Get subscription status
   * @return int
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * Set subscription status, generate verify token if necessary
   * @param int $status
   */
  public function setStatus($status) {
    $this->status = $status;
    if ($this->status == self::$STATUS_UNSUBSCRIBED) {
      $this->verifyToken = str_random();
    }
  }

  /**
   * Set subscription expire time
   * @param int $lease
   */
  public function setExpire($lease) {
    $this->expire = time()+$lease;
  }

  /**
   * Get time in seconds before expiration
   * @return int
   */
  public function getTimeToExpire() {
    return $this->expire-time();
  }

  /**
   * Check if subscription is expired
   * @return bool
   */
  public function isExpired() {
    return $this->status != self::$STATUS_SUBSCRIBED || $this->getTimeToExpire() <= 0;
  }

  /**
   * Get verify token
   * @return string
   */
  public function getVerifyToken() {
    return $this->verifyToken;
  }
}
