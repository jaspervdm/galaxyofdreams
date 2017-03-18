<?php
namespace Kebabtent\GalaxyOfDreams;

use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use Noodlehaus\ConfigInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory;
use Kebabtent\GalaxyOfDreams\Modules\ModuleInterface;
use Discord\Discord;
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
      "loop" => $this->loop,
      "logger" => $this->logger
    ]);

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

  public function on($event, callable $listener) {
    if (preg_match("/discord.(.*)/i", $event, $match)) {
      $discordEventName = $match[1];
      $this->discord->on($discordEventName, function (...$args) use (&$discordEventName) {
        $this->discordEvent($discordEventName, $args);
      });
    }

    parent::on($event, $listener);
  }

  public function discordEvent($name, $args = []) {
    $this->logger->info("Triggered event ".$name." with ".count($args)." arguments");
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
    foreach ($this->config->get("modules") as $moduleConfig) {
      $this->loadModule($moduleConfig['name'], $moduleConfig['config']);
    }

    $this->loop->run();
  }
}
