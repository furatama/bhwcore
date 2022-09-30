<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

class Dbx { //sync-query

	const QUERY_TRIAL = 3; //jumlah eksekusi query sebelum rollback
	
	public $trans_count = 0;

	private $CI;
	private $query_trial;
	private $rollback_list = [];
	private $rollback_mode = false;

	public $rollback_bullshit = false;

	public function __construct()
	{
		$this->CI = &get_instance();
		$this->query_trial = static::QUERY_TRIAL;
	}

	public function trans_start() {
		if ($this->trans_count <= 0) {
			$this->CI->db->trans_start();
			$this->trans_count = 0;
		}
		$this->trans_count++;
	}

	public function trans_complete() {
		$this->trans_count--;
		if ($this->trans_count <= 0) {
			$this->CI->db->trans_complete();
			$this->trans_count = 0;
		}
	}

	public function start_sync() {
		$this->rollback_list = [];
		$this->query_trial = static::QUERY_TRIAL;
	}

	public function insert($table, $data, $pk_field) {
		$CI = $this->CI;
		for ($i=$this->query_trial; $i > 0; $i--) { 
			// $CI->db->reset_query();
			@$CI->db->insert($table, $data);
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
			@$CI->db->insert_batch($table, $data);
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
			return ['ok' => @$CI->db->affected_rows()];
		} else {
			return ['error' => $db_error];
		}
	}

	public function update_single($table, $data, $where) {
		$CI = $this->CI;

		if ($this->rollback_mode === FALSE && $this->rollback_bullshit) {
			$CI->db->reset_query();
			$this->_queries_to_where($where);
			$prev_data = $CI->db->get($table)->row_array();
		}

		for ($i = $this->query_trial; $i > 0; $i--) { 
			// $CI->db->reset_query();
			$this->_queries_to_where($where);
			@$CI->db->update($table, $data);
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
			return ['ok' => @$CI->db->affected_rows()];
		} else {
			return ['error' => $db_error];
		}
	}

	public function delete($table, $where) {
		$CI = $this->CI;

		if ($this->rollback_mode === FALSE && $this->rollback_bullshit) {
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
		if (!$this->rollback_bullshit)
			return;
		if ($this->rollback_mode)
			return;
		$this->rollback_list[] = $data;
	}
	
	//Mengconvert query string menjadi klausa where
	private function _queries_to_where($queries)
	{
		foreach ($queries as $field => $value) {
			$qry_param = "";
			$preg_result = preg_match("/_([^a-zA-Z0-9]+)$/", $field, $qry_param);
			if ($preg_result != 0) {
				$fd = str_replace($qry_param[0] ?? "", "", $field);
				$param = $qry_param[1] ?? "";
			} else {
				$fexp = explode(" ", $field);
				$fd = $fexp[0];
				$param = isset($fexp[1]) ? $fexp[1] : "";
			}

			if (is_array($value)) {
				if (!empty($value))
					if ($param == "<>" || $param =="!=")
						$this->CI->db->where_not_in($fd, $value);
					else
						$this->CI->db->where_in($fd, $value);
			} else if (is_array(json_decode($value))) {
				if ($param == "<>" || $param =="!=")
					$this->CI->db->where_not_in($fd, json_decode($value));
				else
					$this->CI->db->where_in($fd, json_decode($value));
			} else {
				switch ($param) {
					case '~':
					case '~~':
						$this->CI->db->like($fd, $value);
						break;					
					case '!~':
					case '!~~':
						$this->CI->db->not_like($fd, $value);
						break;					
					case '%~':
						$this->CI->db->like($fd, $value, 'before');
						break;					
					case '!%~':
						$this->CI->db->not_like($fd, $value, 'before');
						break;					
					case '~%':
						$this->CI->db->like($fd, $value, 'after');
						break;					
					case '!~%':
						$this->CI->db->not_like($fd, $value, 'after');
						break;					
					default:
						$this->CI->db->where($field, $value);
						break;
				}
			}
		}
	}
	
	public function query($query, $params) {
		if (!is_array($params)) {
			$params = [$params];
		}
		foreach ($params as $key => $value) {
			$params[$key] = $this->CI->db->escape($value);
		}
		return $this->CI->db->query($query, $params);
	}

	private function _get_db_error() {
		$db_error = $this->CI->db->error();
		if (!empty($db_error) && isset($db_error['message']) && !empty($db_error['message'])) {
			$msg = $db_error['message'];
			if (strpos($msg, 'duplicate') !== false) {
				$detail = strpos($msg, 'DETAIL');
				if ($detail != FALSE) {
					preg_match_all('~\(([^()]*)\)~', $msg, $matches);
					$key = str_replace(['-', '_'], ' ', $matches[1][0] ?? '');
					$value = $matches[1][1] ?? '';
					$db_error['n_message'] = "data dengan {$key} \"{$value}\" sudah ada";
				} else {
					$db_error['n_message'] = "data ini sudah ada";
				}
			} else if (strpos($msg, 'violates not-null constraint')) {
				$detail = strpos($msg, 'DETAIL');
				if ($detail != FALSE) {
					preg_match_all('/(\\")([^(\\")]+)(\\")/', $msg, $matches);
					$key = str_replace(['-', '_'], ' ', $matches[2][0] ?? '');
					$db_error['n_message'] = "nilai {$key} tidak boleh kosong";
				} else {
					$db_error['n_message'] = "ada nilai yang masih kosong";
				}
			} else if (strpos($msg, 'violates foreign key constraint')) {
				$detail = strpos($msg, 'DETAIL');
				if ($detail != FALSE) {
					preg_match_all('/(\\")([^(\\")]+)(\\")/', $msg, $matches);
					$key = str_replace(['-', '_'], ' ', $matches[2][0] ?? '');
					$value = str_replace(['-', '_'], ' ', $matches[2][2] ?? '');
					$db_error['n_message'] = "data {$key} ini masih dipakai di modul lain [{$value}]";
				} else {
					$db_error['n_message'] = "data ini masih dipakai di modul lain";
				}
			} else {
				$db_error['n_message'] = $msg;
			}
			throw new \Exception($db_error['n_message']);
		}
		return false;
	}


}
