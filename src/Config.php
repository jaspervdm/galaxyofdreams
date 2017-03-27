<?php
namespace Kebabtent\GalaxyOfDreams;

use Exception;

class Config extends \Noodlehaus\Config {
  protected $path;

  public static function load($path) {
    return new self($path);
  }

  public function __construct($path) {
    $this->path = $path;
    parent::__construct($path);
  }

  public function save() {
    if (file_put_contents($this->path, json_encode($this->data, JSON_PRETTY_PRINT)) === false) {
      throw new Exception("Unable to write file");
    }
  }
}