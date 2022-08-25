<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

class BHW_Model extends BHW_ViewModel {

	public $fillable_fields = []; //masukkan array dari field yang bisa diisi ke table (abaikan isian otomatis seperti id, created_at, deleted_at)
	
	public function __construct()
	{
		if ($this->searchable_fields === '$fillable_fields')
			$this->searchable_fields = $this->fillable_fields;
			
		if ($this->whereable_fields === '$fillable_fields')
			$this->whereable_fields = $this->fillable_fields;
		
		parent::__construct();
	}
	
	public function create($data_params)
	{
		try {
			$this->filter_fillable_data_params($data_params);
			if (empty($data_params)) {
				throw new \Exception("tidak ada data untuk ditambahkan");
			}

			$result = $this->dbx->insert($this->table, $data_params, $this->primary_key);
			bh_log(["INSERT", count($data_params), "| INSERTED: ", $result['ok'] ? 1 : 0]);
			$this->get_db_error();
			if (isset($result['error'])) {
				throw new \Exception($result['error']);
			}
			
			$this->refresh_materialized_view();
			return $result['ok'];
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function create_batch($data_params, $constant_params = [])
	{
		try {
			$data_params = array_map(function($each_data) use ($constant_params) {
				$each_data = array_merge($each_data, $constant_params);
				$this->filter_fillable_data_params($each_data);
				if (empty($each_data)) {
					throw new \Exception("tidak ada data untuk ditambahkan");
				}
				return $each_data;
			}, $data_params);

			$result = $this->dbx->insert_batch($this->table, $data_params, $constant_params);
			bh_log(["INSERT BATCH", count($data_params), "| INSERTED: ", $result['ok'] ?? 0]);
			$this->get_db_error();
			if (isset($result['error'])) {
				throw new \Exception($result['error']);
			}

			$this->refresh_materialized_view();
			return $result['ok'];
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function update($data_params, $where_queries = [])
	{
		try {
			$pk_value = isset($data_params[$this->primary_key]) ? $data_params[$this->primary_key] : null;
			$this->filter_fillable_data_params($data_params);
			if (empty($data_params)) {
				throw new \Exception("tidak ada data untuk diupdate");
			}

			if (!isset($where_queries) || $where_queries === 0 || empty($where_queries)) {
				if ($pk_value != null) {
					$where_queries[$this->primary_key] = $pk_value;
				} else {
					throw new \Exception("tidak ada referensi untuk update data");
				}
			}

			if ($this->soft_delete === TRUE) {
				$where_queries['deleted_at ='] = NULL;
			}

			$result = $this->dbx->update_single($this->table, $data_params, $where_queries);
			$this->get_db_error();
			if (isset($result['error'])) {
				throw new \Exception($result['error']);
			}

			$this->refresh_materialized_view();
			return $result['ok'];
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function delete($where_queries = [])
	{
		try {
			if (empty($where_queries)) {
				throw new \Exception("tidak ada referensi untuk delete data");
			}

			if ($this->soft_delete === TRUE) {
				$where_queries['deleted_at ='] = NULL;
				$result = $this->dbx->update_single($this->table, ['deleted_at' => date('Y-m-d H:i:s')], $where_queries);
			} else {
				$result = $this->dbx->delete($this->table, $where_queries);
			}
			$this->get_db_error();
			if (isset($result['error'])) {
				throw new \Exception($result['error']);
			}

			$this->refresh_materialized_view();
			return $result['ok'];
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}	

	//Menyaring data param agar yang bisa dimasukkan hanya yang fieldnya filllable saja
	public function filter_fillable_data_params(&$data_params)
	{
		foreach ($data_params as $data_field => $dp) {
			if (in_array($data_field, $this->fillable_fields) === FALSE) {
				unset($data_params[$data_field]);
			}
		}
	}

	public $refresh_materialized_view_list = [];
	public function refresh_materialized_view() {
		if (count($this->refresh_materialized_view) == 0) {
			return;
		}

		$this->db->trans_start();
		foreach ($this->refresh_materialized_view_list as $mv => $query) {
			$this->add_to_refresh_materialized_view_queue($mv, $query);
		}
		$this->db->trans_complete();

		return;
	}

}
