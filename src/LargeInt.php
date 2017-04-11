<?php
namespace Kebabtent\GalaxyOfDreams;

use InvalidArgumentException;

class LargeInt {
  /**
   * @var int[]
   */
  protected $parts;

  /**
   * Class for storing a large positive integer
   * Input is split up in parts, each smaller than 1000
   * Element 0 represents the singles, 1 the thousands, 2 the millions etc
   * The actual value would be: sum(parts[i]*1000^i) where i=0,1,...,n with n=(num parts)-1
   * @param array $partsIn
   * @throws InvalidArgumentException
   */
  public function __construct($partsIn) {
    $parts = [];
    foreach ($partsIn as $part) {
      if (!is_numeric($part)) {
        throw new InvalidArgumentException("Not numeric");
      }
      $number = (int) $part;
      if ($number < 0 || $number > 999) {
        throw new InvalidArgumentException("Not in range");
      }
      $parts[] = $number;
    }

    if (!count($parts)) {
      $parts[] = 0;
    }

    $this->parts = $parts;
  }

  /**
   * Compare with a integer
   * @param $in
   * @return int -1: self < in, 0: self == in, 1: self > in
   */
  public function compare($in) {
    $obj = null;
    if ($in instanceof self) {
      $obj = $in;
    }
    elseif (is_numeric($in)) {
      $obj = self::fromInt($in);
    }
    else {
      try {
        $obj = self::fromString($in);
      }
      catch (InvalidArgumentException $e) {
        return 1;
      }
    }

    $numSelf = $this->getNumParts();
    $numIn = $obj->getNumParts();
    if ($numSelf > $numIn) {
      return 1;
    }
    if ($numSelf < $numIn) {
      return -1;
    }

    for ($i=$numSelf-1;$i>=0;$i--) {
      $a = $this->getPart($i);
      $b = $obj->getPart($i);
      if ($a > $b) {
        return 1;
      }
      if ($a < $b) {
        return -1;
      }
    }

    return 0;
  }

  /**
   * Check self == in
   * @param $in
   * @return bool
   */
  public function equalTo($in) {
    return $this->compare($in) == 0;
  }

  /**
   * Check self > in
   * @param $in
   * @return bool
   */
  public function largerThan($in) {
    return $this->compare($in) == 1;
  }

  /**
   * Check self < in
   * @param $in
   * @return bool
   */
  public function smallerThan($in) {
    return $this->compare($in) == -1;
  }

  /**
   * Check self >= in
   * @param $in
   * @return bool
   */
  public function largerEqualThan($in) {
    return $this->compare($in) != -1;
  }

  /**
   * Check self <= in
   * @param $in
   * @return bool
   */
  public function smallerEqualThan($in) {
    return $this->compare($in) != 1;
  }

  /**
   * Get number of parts
   * @return int
   */
  public function getNumParts() {
    return count($this->parts);
  }

  /**
   * Get a specific part
   * @param $i
   * @return null
   */
  public function getPart($i) {
    return isset($this->parts[$i]) ? $this->parts[$i] : null;
  }

  /**
   * Convert to a string
   * @return string
   */
  public function __toString() {
    $parts = array_reverse($this->parts);
    return implode("", $parts);
  }

  /**
   * Create LargeInt from a string
   * @param string $str
   * @return LargeInt
   */
  public static function fromString($str) {
    while (strlen($str)%3 != 0) {
      $str = "0".$str;
    }

    $parts = str_split($str, 3); // Split into thousands
    return new self(array_reverse($parts));
  }

  /**
   * Create LargeInt from an int
   * @param int $int
   * @return LargeInt
   */
  public static function fromInt($int) {
    return self::fromString((string) $int);
  }
}