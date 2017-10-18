<?php
namespace Kebabtent\GalaxyOfDreams;

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use Noodlehaus\ConfigInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory;
use Kebabtent\GalaxyOfDreams\Modules\ModuleInterface;
use Discord\Discord;
use Discord\Repository\GuildRepository;
use Discord\Parts\Guild\Guild;
use Exception;

class Bot extends EventEmitter {
  /**
   * Stores the configuration
   * @var ConfigInterface
   */
  protected $config;

  /**
   * Stores the logger
   * @var LoggerInterface;
   */
  protected $logger;

  /**
   * Stores the event loop
   * @var LoopInterface
   */
  protected $loop;

  /**
   * Stores the Discord instance
   * @var Discord
   */
  protected $discord;

  /**
   * Stores all loaded modules
   * @var ModuleInterface[]
   */
  protected $modules;

  /**
   * @var string[]
   */
  protected $discordEvents;

  /**
   * @var bool
   */
  protected $isReady;

  /**
   * Create bot instance
   * @param ConfigInterface $config
   * @param LoggerInterface $logger
   */
  public function __construct($config, $logger) {
    $this->config = $config;
    $this->loop = Factory::create();
    $this->logger = $logger;

    $this->discord = new Discord([
      "token" => $config->get("token"),
      "loadAllMembers" => true,
      "loop" => $this->loop,
      "logger" => $this->logger
    ]);

    $this->discordEvents = [];

    $this->isReady = false;
    $this->on("discord.ready", function () {
      $this->ready();
    });


    $this->logger->debug("Bot constructed");
  }

  /**
   * Get the event loop
   * @return LoopInterface
   */
  public function getLoop() {
    return $this->loop;
  }

  /**
   * Get the discord api instance
   * @return Discord
   */
  public function getDiscord() {
    return $this->discord;
  }

  /**
   * Checks whether module $name is already loaded
   * @param $name
   * @return bool
   */
  public function isModuleLoaded($name) {
    return isset($this->modules[$name]);
  }

  /**
   * Get the instance of module $name
   * @param $name
   * @return ModuleInterface
   * @throws Exception
   */
  public function getModule($name) {
    if (!$this->isModuleLoaded($name)) {
      throw new Exception("Module ".$name." not loaded");
    }

    return $this->modules[$name];
  }

  protected function ready() {
    $this->isReady = true;
  }

  /**
   * Execute $callback when Discord instance is ready
   * @param callable $callback
   */
  public function whenReady(callable $callback) {
    if ($this->isReady) {
      // Is ready? Call immediately
      call_user_func($callback, $this);
    }
    else {
      // Else, trigger on ready
      $this->once("discord.ready", $callback);
    }
  }

  /**
   * Get ID from a channel name
   * @param string $name Format guildName/channelName
   * @return string|null
   */
  public function getChannelId($name) {
    @list($guildName, $channelName) = explode("/", $name);
    if (empty($guildName) || is_null($channelName) || empty($channelName)) {
      return null;
    }

    $guilds = $this->config->get("guilds");
    if (!isset($guilds[$guildName]) || !isset($guilds[$guildName]['channels'])) {
      return null;
    }

    $channels = $guilds[$guildName]['channels'];
    if (!isset($channels[$channelName])) {
      return null;
    }

    return $channels[$channelName]['channel_id'];
  }

  /**
   * Fetch a discord channel async
   * @param string $name Format guildName/channelName
   * @param callable $onFulfilled
   * @param callable $onRejected
   */
  public function fetchChannel($name, callable $onFulfilled, callable $onRejected) {
    try {
      @list($guildName, $channelName) = explode("/", $name);
      if (empty($guildName) || is_null($channelName) || empty($channelName)) {
        throw new Exception("Channel name not of the form GuildName/ChannelName");
      }

      $guilds = $this->config->get("guilds");
      if (!isset($guilds[$guildName]) || !isset($guilds[$guildName]['channels'])) {
        throw new Exception("Guild ".$guildName." not found in config");
      }

      $guildId = $guilds[$guildName]['guild_id'];
      $channels = $guilds[$guildName]['channels'];

      if (!isset($channels[$channelName])) {
        throw new Exception("Channel ".$channelName." not found in config");
      }

      $channelId = $channels[$channelName]['channel_id'];

      $this->whenReady(function (Discord $discord) use (&$guildId, &$channelId, $onFulfilled, $onRejected) {
        $guilds = $discord->guilds; /** @var GuildRepository $guilds */

        $guilds->fetch($guildId)->then(function (Guild $guild) use (&$channelId, $onFulfilled, $onRejected) {
          $guild->channels->fetch($channelId)->then(function (Channel $channel) use ($onFulfilled) {
            call_user_func($onFulfilled, $channel);
          }, function (Exception $e) use ($onRejected) {
            call_user_func($onRejected, $e);
          });
        }, function (Exception $e) use ($onRejected) {
          call_user_func($onRejected, $e);
        });
      });
    }
    catch (Exception $e) {
      call_user_func($onRejected, $e);
    }
  }

  /**
   * Execute a command
   * @param callable $callable Should return a promise
   * @param callable $onFulfilled To be caleld when executed
   * @param callable $onRetry To be called every time execution fails and will retry
   * @param callable $onRejected To be called when execution failed $attempts times
   * @param int $timeout Timeout in seconds between attempts
   * @param int $attempts Number of attempts
   */
  public function execute(callable $callable, callable $onFulfilled, callable $onRetry, callable $onRejected, $timeout = 10, $attempts = 3) {
    $promise = call_user_func($callable);
    $promise->then($onFulfilled, function (Exception $e) use ($callable, $onFulfilled, $onRetry, $onRejected, &$timeout, &$attempts) {
      if ($attempts <= 1) {
        call_user_func($onRejected, $e);
        return;
      }
      call_user_func($onRetry, $e, $timeout);
      $this->loop->addTimer($timeout, function () use ($callable, $onFulfilled, $onRetry, $onRejected, &$timeout, &$attempts) {
        $this->execute($callable, $onFulfilled, $onRetry, $onRejected, $timeout, $attempts-1);
      });
    });
  }

  /**
   * Callback on event
   * @param string $event
   * @param callable $listener
   */
  public function on($event, callable $listener) {
    if (preg_match("/discord.(.*)/i", $event, $match)) {
      $discordEventName = $match[1];

      if (!in_array($discordEventName, $this->discordEvents)) {
        $this->discordEvents[] = $discordEventName;

        $this->discord->on($discordEventName, function (...$args) use (&$discordEventName) {
          $this->discordEvent($discordEventName, $args);
        });
      }
    }

    parent::on($event, $listener);
  }

  /**
   * Gets triggered on discord event
   * @param $name
   * @param array $args
   */
  public function discordEvent($name, $args = []) {
    $this->logger->debug("Triggered discord.".$name." with ".count($args)." arguments");
    $this->emit("discord.".$name, $args);
  }

  /**
   * Load a specific module
   * @param string $name
   * @param array $config
   * @throws Exception
   */
  protected function loadModule($name, $config = []) {
    $class = "\\Kebabtent\\GalaxyOfDreams\\Modules\\".$name;
    $requires = call_user_func([$class, "requires"]);
    foreach ($requires as $require) {
      if (!$this->isModuleLoaded($require)) {
        $this->loadModule($require);
      }
    }

    if (isset($this->modules[$name])) {
      throw new Exception("Module ".$name." already loaded");
    }

    $module = call_user_func([$class, "create"], $this, $config, $this->logger);
    $this->modules[$name] = $module;
    $this->logger->debug($module->getName()." module loaded");
  }

  /**
   * Run bot instance
   */
  public function run() {
    $this->logger->info("Run bot instance");
    $this->loop->nextTick(function () {
      foreach ($this->config->get("modules") as $moduleConfig) {
        $this->loadModule($moduleConfig['name'], $moduleConfig['config']);
      }
    });
    $this->loop->run();
  }
}
