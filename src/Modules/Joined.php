<?php
namespace Kebabtent\GalaxyOfDreams\Modules;

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Member;
use Kebabtent\GalaxyOfDreams\Bot;
use Psr\Log\LoggerInterface;

class Joined implements ModuleInterface {
  /**
   * @var Bot
   */
  protected $bot;

  /**
   * @var array
   */
  protected $config;

  /**
   * @var string[]
   */
  protected $channelIds;

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
  * @inheritdoc
  */
  public function getName() {
    return "Joined";
  }

  /**
  * @inheritdoc
  */
  public function __construct($bot, $config, $logger) {
    $this->bot = $bot;
    $this->config = $config;
    $this->logger = $logger;

    foreach ($config['channels'] as $channelName) {
      $channelId = $this->bot->getChannelId($channelName);
      if (is_null($channelId)) {
        continue;
      }
      $this->channelIds[] = $channelId;
    }

    $this->bot->on("discord.message", $this->onMessage());
  }

  protected function onMessage() {
    return function (Message $message, Discord $discord) {
      $channelId = $message->channel_id;
      $parts = explode(" ", $message->content);
      if ($parts[0] == "!joined" && in_array($channelId, $this->channelIds)) {
        $this->logger->info("Incoming command", ["Joined"]);

        $userId = $message->author->id;
        $message->channel->guild->members->fetch($userId)->then(function (Member $member) use ($message, &$userId) {
          $joined = $member->joined_at;
          $joined->setTimezone(new \DateTimeZone("GMT"));
          $message->channel->sendMessage("<@".$userId."> joined **".$joined->diffForHumans()."** (".$joined->format("d-m-Y H:i:s").")");
        });
      }
    };
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
    return [];
  }
}