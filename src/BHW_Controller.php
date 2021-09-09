<?php

namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

class BHW_Controller extends RestController
{
	public $model;
	public $module;
	public $my_auth_if = [];

	public function __construct()
	{
		parent::__construct();
		bh_log(["CONTROLLER START"]);
	}

	//Inisialisasi model dan modul untuk dijalankan di program
	public function init($module, $model_name)
	{
		$this->load->model($model_name);
		$this->model = $this->{$model_name};
		$this->module = $module;
		bh_log($this->input->request_headers());
		bh_log([$this->module ?? "", "CONTROLLER INIT"]);
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

		bh_log([$this->module ?? "", "GET END"]);
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

		bh_log([$this->module ?? "", "GET SINGLE END"]);
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

		bh_log([$this->module ?? "", "GET PAGE END"]);
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
	public function xapi_post()
	{
		// $this->authenticate(['api' => true]);

		$select = $this->post('select');
		$where = $this->post('where');
		$opts = $this->post('opts');
		$result = $this->model->read_xapi($select, $where, $opts);

		$this->error_check($result);

		bh_log([$this->module ?? "", "XAPI POST END"]);
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

		bh_log([$this->module ?? "", "POST END"]);
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

		bh_log([$this->module ?? "", "PUT END"]);
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

		bh_log([$this->module ?? "", "DELETE DELETE END"]);
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

		bh_log([$this->module ?? "", "POST DELETE END"]);
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
		$auth_token = $this->head('Authorization');
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
}
