<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

class BHW_Hub extends \CI_Model
{
	//Check error query jika terdapat error langsung kirim response 500 (SERVER INTERNAL ERROR)
	protected function error_check($error)
	{
		if ($error && !is_array($error) && !is_object($error) && substr($error, 0, 4) === "ERR:") {
			throw new \Exception($error);
		}
	}
}
