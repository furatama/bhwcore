<?php

if (!function_exists('bh_upload_file')) {
	function bh_upload_file($file_name, $config)
	{

		$up = $config['upload_path'];
		$config['upload_path'] = "uploads/sym/" . $config['upload_path'];
		$config['max_size'] = 100000;

		$CI = &get_instance();
		$CI->load->library('upload', $config);

		if (!is_dir($config['upload_path'])) {
			mkdir($config['upload_path'], 0777, true);
			try {
				$indexFile = fopen($config['upload_path'] . "/index.html", "w");
				$txt = "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>";
				fwrite($indexFile, $txt);
				fclose($indexFile);
			} catch (Exception $error) {
			}
		}

		$file_ext = pathinfo($_FILES[$file_name]['name'], PATHINFO_EXTENSION);
		$_FILES[$file_name]['name'] = $_FILES[$file_name]['name'] . "_" . date('n') . substr(base64_encode(date('H:i:s')), 0, 3) . '.' . $file_ext;

		if (!$CI->upload->do_upload($file_name)) {
			$error = array('error' => $CI->upload->error_msg);
			return $error;
		} else {
			$data = $CI->upload->data();
			$file_name = $data['file_name'];
			return $up . '/' . $file_name;
		}
	}
}

if (!function_exists('bh_open_csv')) {
	function bh_open_csv($file_name)
	{

		$upload_path = "uploads/sym/csv/";

		if (!is_dir($upload_path)) {
			mkdir($upload_path, 0777, true);
			try {
				$indexFile = fopen($upload_path . "/index.html", "w");
				$txt = "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>";
				fwrite($indexFile, $txt);
				fclose($indexFile);
			} catch (Exception $error) {
			}
		}

		return fopen($upload_path . "/" . $file_name . ".csv", "w");
	}
}

if (!function_exists('bh_open_xlsx')) {
	function bh_open_xlsx($file_name)
	{

		$upload_path = "uploads/sym/xlsx/";

		if (!is_dir($upload_path)) {
			mkdir($upload_path, 0777, true);
			try {
				$indexFile = fopen($upload_path . "/index.html", "w");
				$txt = "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>";
				fwrite($indexFile, $txt);
				fclose($indexFile);
			} catch (Exception $error) {
				return false;
			}
		}

		return $upload_path . $file_name . ".xlsx";

	}
}

if (!function_exists('bh_upload_file_ym')) {
	function bh_upload_file_ym($file_name, $config)
	{
		$year = date("Y");
		$month = date("m");
		$config['upload_path'] = $config['upload_path'] . "/$year/$month";

		return bh_upload_file($file_name, $config);
	}
}
