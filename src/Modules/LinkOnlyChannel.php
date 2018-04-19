<?php
namespace Kebabtent\GalaxyOfDreams\Modules;

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Exception;
use Kebabtent\GalaxyOfDreams\Bot;
use Psr\Log\LoggerInterface;

class LinkOnlyChannel implements ModuleInterface {
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
   * @var Channel
   */
  protected $logChannel;

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * @inheritdoc
   */
  public function getName() {
    return "LinkOnlyChannel";
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

    if (isset($config['log_channel'])) {
      $this->addLogChannel();
    }

    $this->bot->on("discord.message", $this->onMessage());
  }

  public function addLogChannel() {
    $this->bot->fetchChannel($this->config['log_channel'], function(Channel $channel) {
      $this->logChannel = $channel;
    }, function (Exception $e) {
      $this->logger->warning("Unable to fetch announce channel ".$this->config['log_channel']." (".$e->getMessage()."), retry in 10 seconds", ["YoutubeNotify"]);
      $this->bot->getLoop()->addTimer(10, function () {
        $this->addLogChannel();
      });
    });
  }

  protected function onMessage() {
    return function (Message $message) {
      $channelId = $message->channel_id;
      $content = $message->content;

      if (in_array($channelId, $this->channelIds) && !preg_match("~(http|https)\://~i", $content)) {
        $username = $message->author->username;
        $channel = $message->channel->name;
        $message->channel->messages->delete($message)->then(function () use ($content, $username, $channel) {
          $log = "Deleted message `".$content."` from **".$username."** in #".$channel." because it did not contain a link";
          $this->logger->info($log);
          if ($this->logChannel) {
            $this->logChannel->sendMessage($log);
          }
        }, function (Exception $e) {
          $this->logger->warning("Unable to delete message: ".$e->getMessage());
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