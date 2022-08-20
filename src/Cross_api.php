<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Cross_api {

	protected $uri;
	protected $modul;
	protected $_token;
	protected $_client;
	protected $_timeout = 45;

	public function __construct()
	{
		$this->_client = new Client([
			'base_uri' => $this->uri,
			'verify' => false
		]);
		$key = COMPANY . $this->modul . "JWTKEY" . JWT_SALT;
		$jwt = new Jwt_auth($key);
		$this->_token = $jwt->get_token([
			"from" => $this->modul,
			"api" => true,
			"made" => time(),
		]);
	}

	protected function headers() {
		return [
			'Content-Type' => "application/json",
			'Authorization' => "Bearer {$this->_token}"
		];
	}
	
	protected function _parse_error($ex) {
		$message = "";
		try {
			$message = json_decode($ex, true);
			$message = $message["message"];
		} catch(\Throwable $th) {
			$message = "tak terdefinisi";
		}
		return "ERR:$message";
	}

	public function get_request($uri, $query = []) {
		try {
			$response = $this->_client->request('GET', $uri, [
				"query" => $query,
				"headers" => $this->headers(),
				"connect_timeout" => $this->_timeout,
				"timeout" => $this->_timeout,
			]);
			$body = $response->getBody();
			$body_data = json_decode($body, true);
			return $body_data;
		} catch (ClientException $ex) {
			return $this->_parse_error($ex->getResponse()->getBody());
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
		
	}

	public function post_request($uri, $req_body = []) {
		try {
			$response = $this->_client->request('POST', $uri, [
				"json" => $req_body,
				"headers" => $this->headers(),
				"connect_timeout" => $this->_timeout,
				"timeout" => $this->_timeout,
				'allow_redirects'=> ['strict'=> true]
			]);
			$body = $response->getBody()->getContents();
			$body_data = json_decode($body, true);
			return $body_data;
		} catch (ClientException $ex) {
			return $this->_parse_error($ex->getResponse()->getBody()->getContents());
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}		
	}

	public function put_request($uri, $req_body = []) {
		try {
			$response = $this->_client->request('PUT', $uri, [
				"json" => $req_body,
				"headers" => $this->headers(),
				"connect_timeout" => $this->_timeout,
				"timeout" => $this->_timeout,
				'allow_redirects'=> ['strict'=> true]
			]);
			$body = $response->getBody()->getContents();
			$body_data = json_decode($body, true);
			return $body_data;
		} catch (ClientException $ex) {
			return $this->_parse_error($ex->getResponse()->getBody()->getContents());
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
				"connect_timeout" => $this->_timeout,
				"timeout" => $this->_timeout,
			]);
			$body = $response->getBody()->getContents();
			$body_data = json_decode($body, true);
			return $body_data;
		} catch (ClientException $ex) {
			return $this->_parse_error($ex->getResponse()->getBody()->getContents());
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}		
	}
}
