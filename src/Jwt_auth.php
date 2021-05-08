<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

use Firebase\JWT\JWT;

class Jwt_auth {

	private $jwt_key;

	public function __construct($key = null)
	{
		$key = $key !== null ? $key : (COMPANY . MODUL . "JWTKEY" . JWT_SALT);
		$this->jwt_key = hash("sha256", $key);
	}

	public function get_token($payload)
	{
		return JWT::encode($payload, $this->jwt_key);
	}

	public function parse_token($jwt)
	{
		return JWT::decode($jwt, $this->jwt_key, array('HS256'));
	}
}
