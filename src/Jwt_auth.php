<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

use Firebase\JWT\JWT;

class Jwt_auth {

	private $jwt_key;

	public function __construct($key = null)
	{
		$company = defined('COMPANY') ? COMPANY : 'BHAWANA';
		$modul = defined('MODUL') ? MODUL : 'MODUL';
		$salt = defined('JWT_SALT') ? JWT_SALT : 'bhawanaerp';
		$key = $key !== null ? $key : ($company . $modul . "JWTKEY" . $salt);
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
