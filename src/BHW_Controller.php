<?php

namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

class BHW_Controller extends RestController
{
	public $model;
	public $model_name;
	public $module;
	public $my_auth_if = [];
	public $view_model;

	public function __construct()
	{
		parent::__construct();
	}

	//Inisialisasi model dan modul untuk dijalankan di program
	public function init($module, $model_name, $vmodel = null)
	{
		$this->load->model($model_name);
		$this->model = $this->{$model_name};
		$this->module = $module;
		$this->model_name = $model_name;
		if ($vmodel) {
		  $this->load->model($vmodel);
		  $this->view_model = $this->{$vmodel};
		}
	}

	//Fungsi untuk mutasi input
	protected function mutate_input(&$params)
	{
	}

	//Membuat rules untuk validasi
	protected function set_rules()
	{
	}

	/*	Mengakomodir GET request dari /<modul>
	|	
	|
	*/
	public function index_get()
	{
		$this->authenticate();

		$get_queries = $this->get();
		$result = $this->model->read($get_queries);

		$this->error_check($result);

		// if (count($result) === 0 && !empty($get_queries))
		// 	return $this->response([
		// 		'status' => false,
		// 		'message' => "data tidak ditemukan",
		// 	], BHW_Controller::HTTP_NOT_FOUND);

		return $this->response([
			'status' => true,
			'message' => "data ditemukan",
			'data' => $result
		], BHW_Controller::HTTP_OK);
	}

	/*	Mengakomodir GET request dari /<modul>, responsenya single result
	|
	|
	*/
	public function single_get()
	{
		$this->authenticate();

		$get_queries = $this->get();
		$result = $this->model->read_single($get_queries);

		$this->error_check($result);

		if (empty($result) || $result == null)
			return $this->response([
				'status' => false,
				'message' => "data tidak ditemukan",
			], BHW_Controller::HTTP_NOT_FOUND);

		return $this->response([
			'status' => true,
			'message' => "data ditemukan",
			'data' => $result
		], BHW_Controller::HTTP_OK);
	}
	
	public function check_duplicate_get()
	{
		$this->authenticate();

		$get_queries = $this->get();
		
		if ($this->view_model) {
		    $result = $this->view_model->read_single($get_queries);
		} else {
		    $result = $this->model->read_single($get_queries);
		}

		$this->error_check($result);

		if (empty($result) || $result == null)
			return $this->response([
				'status' => true,
				'message' => "data tidak duplikat",
			], BHW_Controller::HTTP_OK);

		return $this->response([
			'status' => false,
			'message' => "data duplikat"
		], BHW_Controller::HTTP_BAD_REQUEST);
	}

	/*	Mengakomodir GET request dari /<modul>, responsenya single result
	|
	|
	*/
	public function page_get()
	{
		$this->authenticate();

		$get_queries = $this->get();
		$result = $this->model->read_page($get_queries);

		$this->error_check($result);

		if (empty($result))
			return $this->response([
				'status' => false,
				'message' => "data tidak ditemukan",
			], BHW_Controller::HTTP_NOT_FOUND);

		return $this->response([
			'status' => true,
			'message' => "data ditemukan",
			'data' => $result['data'],
			'data_count' => $result['data_count'],
			'total_page' => $result['total_page'],
		], BHW_Controller::HTTP_OK);
	}

	
	/*	Mengakomodir GET request dari /<modul>, responsenya single result
	|
	|
	*/
	public function csv_get()
	{
		$this->authenticate();

		$get_queries = $this->get();
		$file_name = $get_queries['file_name'] ?? $this->module ?? "csv_report";
		$result = $this->model->to_csv($file_name, $get_queries);
		$this->error_check($result);
		if ($result === true) {
			return $this->response([
				'status' => true,
				'message' => "data ditemukan",
				'file' => "csv/" . $file_name . ".csv",
			], BHW_Controller::HTTP_OK);
		}

		return $this->response([
			'status' => false,
			'message' => "data tidak ditemukan",
		], BHW_Controller::HTTP_NOT_FOUND);
	}

	public function xlsx_get()
	{
		$get_queries = $this->get();

		$this->authenticate();
		$file_name = strtolower(MODUL ?? '') . "_" . $this->module . "_" . date("Ymd");

		$this->load->library('Spreadsheet');
		$this->spreadsheet->render_model($this->xlsx_model ?? $this->model_name, $get_queries);
		$this->spreadsheet->save_xlsx($file_name);

		return $this->response([
			'status' => true,
			'message' => "data ditemukan",
			'file' => "xlsx/" . $file_name . ".xlsx",
		], BHW_Controller::HTTP_OK);
	}

	/*	Mengakomodir GET request dari /<modul>, responsenya single result
	|
	|
	*/
	public function xapi_post()
	{
		// $this->authenticate(['api' => true]);

		$select = $this->post('select');
		$where = $this->post('where');
		$opts = $this->post('opts');
		$result = $this->model->read_xapi($select, $where, $opts);

		$this->error_check($result);

		if (empty($result))
			return $this->response([
				'status' => false,
				'message' => "data tidak ditemukan",
			], BHW_Controller::HTTP_NOT_FOUND);

		return $this->response([
			'status' => true,
			'message' => "data ditemukan",
			'data' => $result,
		], BHW_Controller::HTTP_OK);
	}

	/*	Mengakomodir POST request dari /<modul>
	|
	|
	*/
	public function index_post()
	{
		$this->authenticate();

		$post_params = $this->post();

		$this->set_rules();
		$this->validate($post_params);
		$this->mutate_input($post_params);

		$result = $this->model->create($post_params);

		if ($result === 0)
			return $this->response([
				'status' => false,
				'message' => "data tidak ditambahkan",
			], BHW_Controller::HTTP_BAD_REQUEST);

		$this->error_check($result);

		$return = $this->model->read_single([$this->model->primary_key => $result]);

		$this->error_check($return);

		return $this->response([
			'status' => true,
			'message' => "data berhasil ditambahkan",
			'data' => $return,
		], BHW_Controller::HTTP_CREATED);
	}

	/*	Mengakomodir PUT request dari /<modul>
	|
	|
	*/
	public function index_put()
	{
		$this->authenticate();

		$put_params = $this->put();
		$where_params = $put_params['w'] ?? [];

		$this->set_rules();
		$this->validate($put_params);
		$this->mutate_input($put_params);

		$result = $this->model->update($put_params, $where_params);

		$this->error_check($result);

		if ($result === 0)
			return $this->response([
				'status' => false,
				'message' => "data tidak diubah",
			], BHW_Controller::HTTP_BAD_REQUEST);

		return $this->response([
			'status' => true,
			'message' => "data berhasil diubah",
		], BHW_Controller::HTTP_OK);
	}

	/*	Mengakomodir DELETE request dari /<modul>
	|	
	|
	*/
	public function index_delete()
	{
		$this->authenticate();

		$delete_params = $this->delete();

		$result = $this->model->delete($delete_params);

		if ($result === 0)
			return $this->response([
				'status' => false,
				'message' => "data tidak dihapus",
			], BHW_Controller::HTTP_BAD_REQUEST);

		$this->error_check($result);

		return $this->response([
			'status' => true,
			'message' => "data berhasil dihapus",
		], BHW_Controller::HTTP_OK);
	}
	/*	Mengakomodir DELETE request dari /<modul>
	|	
	|
	*/
	public function delete_post()
	{
		$this->authenticate();

		$delete_params = $this->post();

		$result = $this->model->delete($delete_params);

		if ($result === null)
			return $this->response([
				'status' => false,
				'message' => "data tidak dihapus karena dipakai oleh tabel lain",
			], BHW_Controller::HTTP_BAD_REQUEST);

		if ($result === 0)
			return $this->response([
				'status' => false,
				'message' => "data tidak dihapus",
			], BHW_Controller::HTTP_BAD_REQUEST);

		$this->error_check($result);

		return $this->response([
			'status' => true,
			'message' => "data berhasil dihapus",
		], BHW_Controller::HTTP_OK);
	}

	//Check validasi jika tak valid langsung kirim response 400 (BAD REQUEST)
	protected function validate($input_params)
	{
		$this->form_validation->set_data($input_params);
		if ($this->form_validation->run() == FALSE && !empty($this->form_validation->error_array())) {
			return $this->response([
				'status' => false,
				'message' => "request tak valid",
				'error' => $this->form_validation->error_array(),
			], BHW_Controller::HTTP_BAD_REQUEST);
		}
	}

	//Check error query jika terdapat error langsung kirim response 500 (SERVER INTERNAL ERROR)
	protected function error_check($error, $error_code = 500)
	{
		if ($error && !is_array($error) && !is_object($error) && substr($error, 0, 4) === "ERR:") {
			$error = substr($error, 4, strlen($error));
			return $this->response([
				'status' => false,
				'message' => "$error",
			], $error_code);
		}
	}

	protected function get_identity()
	{
		$auth_token = $this->head('Authorization') ?? $this->head('authorization') ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
		if ($auth_token == null || str_contains($auth_token, 'Bearer') === FALSE) {
			return $this->response([
				'status' => false,
				'message' => "tidak memiliki hak akses",
			], BHW_Controller::HTTP_UNAUTHORIZED);
		}
		$auth_token = str_replace('Bearer ', '', $auth_token);
		$this->load->library('jwt_auth');
		return json_decode(json_encode($this->jwt_auth->parse_token($auth_token)), TRUE);
	}

	protected function authenticate($auth_if = null)
	{
		try {
			$auth = $this->get_identity();
			$auth_if = $auth_if ?? $this->my_auth_if;
			foreach ($auth_if as $key => $value) {
				if (!in_array($auth[$key], $value)) {
					return $this->response([
						'status' => false,
						'message' => "hak akses belum cukup",
					], BHW_Controller::HTTP_UNAUTHORIZED);
				}
			}
			return $auth;
		} catch (\Throwable $t) {
			return $this->response([
				'status' => false,
				'message' => $t->getMessage(),
			], BHW_Controller::HTTP_UNAUTHORIZED);
		}
	}

	public function rate_limit_start($key, $dur = 2) {
		$limited = bh_cache_load($key);
		if ($limited && $limited != 0) {
			return $this->response([
				'status' => false,
				'message' => 'Input dibatasi',
			], 500);
		}

		bh_cache_save($key, 1, $dur); //cooldown selama $dur
		return true;
	}

	public function rate_limit_complete($key) {
		bh_cache_save($key, 0, 1);
	}
}
