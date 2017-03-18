<?php
namespace Kebabtent\GalaxyOfDreams\Modules\YoutubeNotify;

use Discord\Parts\Channel\Channel as DiscordChannel;
use Psr\Log\LoggerInterface;
use Exception;

class Channel {
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
   * @var DiscordChannel[]
   */
  protected $announceChannels;

  /**
   * @var string
   */
  protected $message;

  public function __construct($logger, $id) {
    $this->logger = $logger;
    $this->id = $id;
    $this->setStatus(self::$STATUS_UNSUBSCRIBED);
    $this->expire = 0;
    $this->announceChannels = [];
    $this->message = "New upload: https://youtu.be/%ID%";
  }

  /**
   * Announce new upload
   * @param int $id
   */
  public function announce($id) {
//    $this->logger->debug("Announcing ".$id." to ".$this->id, ["YoutubeNotify"]);
    foreach ($this->announceChannels as $announceChannel) {
      $announceChannel->sendMessage(str_replace("%ID%", $id, $this->message))->then(function () use (&$id) {
        $this->logger->warning("Announced ".$id." to ".$this->id, ["YoutubeNotify"]);
      }, function (Exception $e) use (&$id) {
        $this->logger->warning("Unable to announce ".$id." to ".$this->id." (".$e->getMessage().")", ["YoutubeNotify"]);
      });
    }
  }

  /**
   * Add discord channel to announce uploads in
   * @param DiscordChannel $announceChannel
   */
  public function addAnnounceChannel($announceChannel) {
    $this->announceChannels[] = $announceChannel;
  }

  /**
   * @return DiscordChannel[]
   */
  public function getAnnounceChannels() {
    return $this->announceChannels;
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
