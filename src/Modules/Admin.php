<?php
namespace Kebabtent\GalaxyOfDreams\Modules;

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Embed\Image;
use Discord\Parts\Embed\Video;
use Discord\Parts\Embed\Author;
use Exception;

class Admin implements ModuleInterface {
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

  public function getName() {
    return "Admin";
  }

  public function __construct($bot, $config, $logger) {
    $this->bot = $bot;
    $this->config = $config;
    $this->logger = $logger;

    $this->bot->on("discord.message", function (Message $message, Discord $discord) {
      $admins = isset($this->config['admins']) ? $this->config['admins'] : [];
      if (in_array($message->author->id, $admins)) {
        $this->bot->emit("discord.admin.message", [$message, $discord]);
        $this->onAdminMessage($message, $discord);
      }
    });
  }

  /**
   * @param Message $message
   * @param Discord $discord
   */
  public function onAdminMessage($message, $discord) {
    $parts = explode(" ", $message->content);
    $command = reset($parts);

    if ($command == "!stop") {
      $message->channel->sendMessage("Goodbye!")->always(function () {
        $this->bot->emit("shutdown");
        $this->logger->info("Closing server..", ["Admin"]);
        $this->bot->on("discord.closed", function () {
          $this->logger->info("Goodbye!", ["Admin"]);
          exit(20);
        });
        $this->bot->getDiscord()->close();
      });
    }
    elseif ($command == "!restart") {
      $message->channel->sendMessage("BRB!")->always(function () {
        $this->bot->emit("shutdown");
        $this->logger->info("Closing server..", ["Admin"]);
        $this->bot->on("discord.closed", function () {
          $this->logger->info("BRB!", ["Admin"]);
          exit(0);
        });
        $this->bot->getDiscord()->close();
      });
    }
  }

  public static function create($bot, $config, $logger) {
    return new self($bot, $config, $logger);
  }

  public static function requires() {
    return [];
  }
}
