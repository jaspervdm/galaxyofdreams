<?php
namespace Kebabtent\GalaxyOfDreams\Modules\FacebookNotify;

use DateTime;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Embed\Image;
use Kebabtent\GalaxyOfDreams\Bot;
use Psr\Log\LoggerInterface;

class Event {
  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * @var string
   */
  protected $id;

  /**
   * @var bool
   */
  protected $expired;

  /**
   * @var string
   */
  protected $name;

  /**
   * @var string
   */
  protected $description;

  /**
   * @var string
   */
  protected $place;

  /**
   * @var string
   */
  protected $time;

  /**
   * @var array
   */
  protected $lastMessage;

  /**
   * @param LoggerInterface $logger
   * @param array $config
   */
  public function __construct($logger, $config) {
    $this->logger = $logger;
    $this->id = $config['id'];
    $this->expired = isset($config['expired']) ? $config['expired'] : false;
    $this->name =  isset($config['name']) && !empty($config['name']) ? $config['name'] : null;
    $this->description =  isset($config['description']) && !empty($config['description']) ? $config['description'] : null;
    $this->place = isset($config['place']) && !empty($config['place']) ? $config['place'] : null;
    $this->time = isset($config['time']) && !empty($config['time']) ? $config['time'] : null;
    $this->lastMessage =  isset($config['last_message']) && is_array($config['last_message']) ? $config['last_message'] : null;
  }

  /**
   * Get event id
   * @return string
   */
  public function getId() {
    return $this->id;
  }

  public function isExpired() {
    return $this->expired;
  }

  public function hasName() {
    return !is_null($this->name);
  }

  /**
   * Get event name
   * @return null|string
   */
  public function getName() {
    return $this->name;
  }

  public function hasDescription() {
    return !is_null($this->description);
  }

  /**
   * Get event description
   * @return null|string
   */
  public function getDescription() {
    return $this->description;
  }

  public function hasPlace() {
    return !is_null($this->place);
  }

  /**
   * Get event place
   * @return null|string
   */
  public function getPlace() {
    return $this->place;
  }

  public function hasTime() {
    return !is_null($this->time);
  }

  /**
   * Get event time
   * @return null|string
   */
  public function getTime() {
    return $this->time;
  }

  public function expire() {
    $this->expired = true;
  }

  /**
   * @param Page $page
   * @param Bot $bot
   * @param array $data
   * @param bool $announceNew
   */
  public function update($page, $bot, $data, $announceNew = true) {
    if ($this->expired) {
      return;
    }

    $isNew = !$this->hasName();
    $descrUpdated = false;
    $placeUpdated = false;
    $timeUpdated = false;

    $this->name = $data['name'];
    $descrHash = sha1($data['description']);
    if ($this->description != $descrHash) {
      $this->description = $descrHash;
      $descrUpdated = true;
    }

    $placeStr = null;
    if (isset($data['place']) && is_array($data['place'])) {
      $placeStr = $data['place']['name'];
      if (isset($data['place']['location']) && is_array($data['place']['location'])) {
        $placeStr .= ", ".$data['place']['location']['city'].", ".$data['place']['location']['country'];
      }
    }

    if ($this->place != $placeStr) {
      $this->place = $placeStr;
      $placeUpdated = true;
    }

    $timeStr = null;
    if (isset($data['start_time'])) {
      $start = new DateTime($data['start_time']);
      $timeStr = $start->format("d-m-Y H:i");
      if (isset($data['end_time'])) {
        $end = new DateTime($data['end_time']);
        $diff = $start->diff($end, true);
        $timeStr .= " - ".($diff->d > 0 ? $end->format("d-m-Y H:i") : $end->format("H:i"));
      }
      $timeStr .= " (".$start->format("T").")";
    }

    if ($this->time != $timeStr) {
      $this->time = $timeStr;
      $timeUpdated = true;
    }

    if ($isNew && !$announceNew) {
      return;
    }

    if (!$isNew && !$descrUpdated && !$placeUpdated && !$timeUpdated) {
      return;
    }

    $message = "";
    if ($isNew) {
      $message = "@here New event";
    }
    elseif ($descrUpdated) {
      $message = "Event description updated";
    }
    elseif ($placeUpdated) {
      $message = "Event location updated";
    }
    elseif ($timeUpdated) {
      $message = "Event time updated";
    }
    $message = "**".$message.":**";

    $image = isset($data['cover']) && is_array($data['cover']) ? $bot->getDiscord()->factory(Image::class, ["url" => $data['cover']['source']]) : null; /** @var Image $image */

    $fields = [];
    if ($this->hasPlace()) {
      $fields[] = ["name" => "Location", "value" => $this->place];
    }
    if ($this->hasTime()) {
      $fields[] = ["name" => "Date", "value" => $this->time];
    }

    $embed = $bot->getDiscord()->factory(Embed::class, [
      "title" => $data['name'],
      "image" => $image,
      "url" => "https://www.facebook.com/events/".$this->id."/",
      "timestamp" => null,
      "description" => $data['description'],
      "fields" => $fields
    ]); /** @var Embed $embed */

    if ($isNew) {
      $page->announceEvent($bot, $message, $embed);
    }
    else {
      $page->announceEventPost($bot, $message, $embed);
    }
  }

  /**
   * Get configuration for storage
   * @return array
   */
  public function getConfig() {
    return [
      "id" => $this->id,
      "expired" => $this->expired ? true : false,
      "name" => $this->name,
      "description" => $this->description,
      "place" => $this->place,
      "time" => $this->time,
      "last_message" => $this->lastMessage
    ];
  }
}