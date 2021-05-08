<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

use GuzzleHttp\Client;

class Cross_api {

	protected $uri;
	protected $modul;
	private $_token;
	private $_client;

	public function __construct()
	{
		$this->_client = new Client([
			'base_uri' => $this->uri,
		]);
		$key = COMPANY . $this->modul . "JWTKEY" . JWT_SALT;
		$jwt = new Jwt_auth($key);
		$this->_token = $jwt->get_token([
			"from" => $this->modul,
			"api" => true,
			"made" => time(),
		]);
	}

	private function headers() {
		return [
			'Content-Type' => "application/json",
			'Authorization' => "Bearer {$this->_token}"
		];
	}

	public function get_request($uri, $query = []) {
		try {
			$response = $this->_client->request('GET', $uri, [
				"query" => $query,
				"headers" => $this->headers()
			]);
			$body = $response->getBody();
			$body_data = json_decode($body, true);
			return $body_data;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
		
	}

	public function post_request($uri, $req_body = []) {

		try {
			$response = $this->_client->request('POST', $uri, [
				"json" => $req_body,
				"headers" => $this->headers()
			]);
			$body = $response->getBody()->getContents();
			$body_data = json_decode($body, true);
			return $body_data;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
		
	}

	public function xapi_request($uri, $select = [], $where = [], $opts = []) {
		try {
			$response = $this->_client->request('POST', $uri . '/xapi', [
				"json" => [
					'select' => $select,
					'where' => $where,
					'opts' => $opts,
				],
				"headers" => $this->headers(),
			]);
			$body = $response->getBody()->getContents();
			$body_data = json_decode($body, true);
			return $body_data;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
		
	}
}
