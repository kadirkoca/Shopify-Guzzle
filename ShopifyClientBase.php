<?php

namespace App\Engine\Shopify;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

abstract class ShopifyClientBase {
	private $BASE_URL;
	public static $VERIFY_SSL= true;
	protected $version = NULL;
	protected $os = NULL;
	protected $timeout = 30;
	protected $client;

	protected function __construct($apikey, $apipassword, $storename, $timeout = 30) {
		$this->BASE_URL = 'https://'.$apikey.':'.$apipassword.'@'.$storename.'.myshopify.com/admin';

		$this->version = phpversion();
		$this->os = PHP_OS;
		$this->timeout = $timeout;
	}

	protected function getClient() {
		if(!$this->client) {
			$this->client = new Client([
				RequestOptions::VERIFY  => self::$VERIFY_SSL,
				RequestOptions::TIMEOUT => $this->timeout,
			]);
		}
		return $this->client;
	}

	public function setClient(Client $client) {
		$this->client = $client;
	}

	protected function processRestRequest($method = NULL, $path = NULL, array $body = []) {
		$client = $this->getClient();
		$options = [
			RequestOptions::HTTP_ERRORS => false,
			RequestOptions::HEADERS => [
				'User-Agent' => "Postmark-PHP (PHP Version:{$this->version}, OS:{$this->os})",
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
			],
		];

		if(!empty($body)) {
			$cleanParams = array_filter($body, function($value) {
				return $value !== null;
			});

			switch ($method) {
				case 'GET':
				case 'HEAD':
				case 'DELETE':
				case 'OPTIONS':
					$options[RequestOptions::QUERY] = $cleanParams;
					break;
				case 'PUT':
				case 'POST':
				case 'PATCH':
					$options[RequestOptions::JSON] = $cleanParams;
					break;
			}
		}
        $response = $client->request($method, $this->BASE_URL . $path, $options);
        return json_decode($response->getBody(), true, 512, JSON_BIGINT_AS_STRING);;
	}
}
