<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

class Dbx { //sync-query

	const QUERY_TRIAL = 3; //jumlah eksekusi query sebelum rollback

	private $CI;
	private $query_trial;
	private $rollback_list = [];
	private $rollback_mode = false;

	public function __construct()
	{
		$this->CI = &get_instance();
		$this->query_trial = static::QUERY_TRIAL;
	}

	public function start_sync() {
		$this->rollback_list = [];
		$this->query_trial = static::QUERY_TRIAL;
	}

	public function insert($table, $data, $pk_field) {
		$CI = $this->CI;
		for ($i=$this->query_trial; $i > 0; $i--) { 
			// $CI->db->reset_query();
			$CI->db->insert($table, $data);
			$db_error = $this->_get_db_error();
			if ($db_error === FALSE) {
				break;
			}
		}
		if ($db_error === FALSE) {
			try {
				$last_id = @$CI->db->insert_id();
			} catch (\Throwable $th) {
				$last_id = $data[$pk_field];
			}
			$this->_add_to_rollback([
				"query" => "delete",
				"table" => $table,
				"where" => [$pk_field => $last_id],
			]);
			return ['ok' => $last_id];
		} else {
			return ['error' => $db_error];
		}
	}

	public function insert_batch($table, $data, $where) {
		$CI = $this->CI;
		for ($i=$this->query_trial; $i > 0; $i--) { 
			$CI->db->insert_batch($table, $data);
			$db_error = $this->_get_db_error();
			if ($db_error === FALSE) {
				break;
			}
		}
		if ($db_error === FALSE) {
			$this->_add_to_rollback([
				"query" => "delete",
				"table" => $table,
				"where" => $where,
			]);
			return ['ok' => $CI->db->affected_rows()];
		} else {
			return ['error' => $db_error];
		}
	}

	public function update_single($table, $data, $where) {
		$CI = $this->CI;

		if ($this->rollback_mode === FALSE) {
			$CI->db->reset_query();
			$this->_queries_to_where($where);
			$prev_data = $CI->db->get($table)->row_array();
		}

		for ($i = $this->query_trial; $i > 0; $i--) { 
			// $CI->db->reset_query();
			$this->_queries_to_where($where);
			$CI->db->update($table, $data);
			$db_error = $this->_get_db_error();
			if ($db_error === FALSE) {
				break;
			}
		}
		if ($db_error === FALSE) {
			$this->_add_to_rollback([
				"query" => "update_single",
				"table" => $table,
				"data" => $prev_data ?? [],
				"where" => $data,
			]);
			return ['ok' => $CI->db->affected_rows()];
		} else {
			return ['error' => $db_error];
		}
	}

	public function delete($table, $where) {
		$CI = $this->CI;

		if ($this->rollback_mode === FALSE) {
			$CI->db->reset_query();
			$this->_queries_to_where($where);
			$prev_data = $CI->db->get($table)->result_array();
		}

		for ($i = $this->query_trial; $i > 0; $i--) { 
			// $CI->db->reset_query();
			$this->_queries_to_where($where);
			@$CI->db->delete($table);
			$db_error = $this->_get_db_error();
			if ($db_error === FALSE) {
				break;
			}
		}
		if ($db_error === FALSE) {
			$this->_add_to_rollback([
				"query" => "insert_batch",
				"table" => $table,
				"data" => $prev_data ?? [],
			]);
			return ['ok' => @$CI->db->affected_rows()];
		} else {
			return ['error' => $db_error];
		}
	}

	public function rollback() {
		$rbl = $this->rollback_list;
		$this->rollback_mode = true;
		$result['ok'] = [];
		$result['error'] = [];
		for ($i=count($rbl); $i > 0; $i--) { 
			$rb = $rbl[$i-1];
			switch ($rb['query']) {
				case 'delete':
					$rs = $this->delete($rb['table'],$rb['where']);
					break;
				case 'insert_batch':
					$rs = $this->insert_batch($rb['table'], $rb['data'], $rb['where']);
					break;
				case 'update_single':
					$rs = $this->update_single($rb['table'], $rb['data'], $rb['where']);
					break;
				default:
					$rs = [];
					break;
			}
			if (isset($rs['ok'])) $result['ok'][] = $rs['ok'];
			if (isset($rs['error'])) $result['error'][] = $rs['error'];
		}
		$this->rollback_list = [];
		$this->rollback_mode = false;
		return $result;
	}

	private function _add_to_rollback($data) {
		if ($this->rollback_mode)
			return;
		$this->rollback_list[] = $data;
	}

	private function _get_db_error() {
		$db_error = $this->CI->db->error();
		if (!empty($db_error) && $db_error['code'] > 0) {
			return $db_error;
		}
		return false;
	}
	
	//Mengconvert query string menjadi klausa where
	private function _queries_to_where($queries)
	{
		foreach ($queries as $field => $value) {
			if (is_array(($value))) {
				$this->CI->db->where_in($field, ($value));
			} else if (is_array(json_decode($value))) {
				$this->CI->db->where_in($field, json_decode($value));
			} else {
				$this->CI->db->where($field, $value);
			}
		}
	}


}
