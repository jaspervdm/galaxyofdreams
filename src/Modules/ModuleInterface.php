<?php
namespace Kebabtent\GalaxyOfDreams\Modules;

interface ModuleInterface {
  /**
   * Get module name
   * @return string
   */
  public function getName();

  /**
   * Constructor
   * @param \Kebabtent\GalaxyOfDreams\Bot $bot
   * @param array $config
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct($bot, $config, $logger);

  /**
   * Create module instance
   * @param $bot
   * @param $config
   * @param \Psr\Log\LoggerInterface $logger
   * @return self
   */
  public static function create($bot, $config, $logger);

  /**
   * List of required mods
   * @return string[]
   */
  public static function requires();
}
