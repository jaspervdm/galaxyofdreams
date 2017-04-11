<?php
namespace Kebabtent\GalaxyOfDreams\Modules\FacebookNotify;

use Kebabtent\GalaxyOfDreams\Modules\HTTPClient;
use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Client {
  const VERSION = "v2.8";
  const BASE_URI = "https://graph.facebook.com";

  protected $client;
  protected $token;

  /**
   * @param HTTPClient $client
   * @param string $token
   */
  public function __construct($client, $token) {
    $this->client = $client;
    $this->token = $token;
  }

  /**
   * Serialize field array
   * @param string|array $fields
   * @return string
   */
  protected function serializeFields($fields) {
    if (!is_array($fields)) {
      return $fields;
    }

    $out = "";
    foreach ($fields as $field) {
      $out .= (empty($out) ? "" : ",").(is_array($field) ? $field[0]."{".$this->serializeFields($field[1])."}" : $field);
    }

    return $out;
  }

  /**
   * Get a page asynchronously
   * @param string $url
   * @param array $fields
   * @param array $params
   * @return \GuzzleHttp\Promise\PromiseInterface
   */
  public function getAsync($url, $fields = [], $params = []) {
    if (count($fields)) {
      $params['fields'] = $this->serializeFields($fields);
    }
    if (!isset($params['access_token'])) {
      $params['access_token'] = $this->token;
    }

    $promise = new Promise();

    $req = $this->client->getAsync(self::BASE_URI."/".self::VERSION.(substr($url, 0, 1) != "/" ? "/" : "").$url.(count($params) ? "?".http_build_query($params) : ""));

    $req->then(function (ResponseInterface $response) use ($promise) {
      $body = $response->getBody()->getContents();
      if ($body === "") {
        $promise->resolve(null);
      }
      else {
        $decode = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          $promise->reject(new RuntimeException("Unable to decode response: ".json_last_error_msg()));
        }
        else {
          $promise->resolve($decode);
        }
      }
    }, function ($exception) use ($promise) {
      $promise->reject($exception);
    });

    return $promise;
  }

  /**
   * Get a page synchronously
   * @param string $url
   * @param array $fields
   * @param array $params
   * @return mixed
   */
  public function get($url, $fields = [], $params = []) {
    return $this->getAsync($url, $fields, $params)->wait();
  }
}