<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

use bhw\BhawanaCore\Cache;

class BHW_ViewModel extends BHW_Hub
{
	public $table; //masukkan value dari table yang dipakai
	public $materialized_view;
	public $primary_key; //masukkan value dari primary_key table tersebut
	public $whereable_fields = '$shown_fields'; //masukkan array dari field yang bisa diisi ke table (abaikan isian otomatis seperti id, created_at, deleted_at)
	public $shown_fields = []; //masukkan array dari field yang bisa diperlihatkan dari table
	public $searchable_fields = '$shown_fields'; //masukkan array dari field yang bisa disearch dari table via page, jika valuenya $shown_fields/$fillable_fields maka akan mengikuti variable tsb
	public $hidden_fields = []; //masukkan array dari field yang bisa diperlihatkan dari table
	public $soft_delete; //aktifkan mode soft-delete (soft delete tidak menghapus data dari table tersebut)
	public $is_hidden_enabled; //aktifkan mode show-hide
	public $order_dir = 'DESC';
	public $order_by;
	public $use_cache = false;
	public $materialized_view_refresh_duration = 20;

	public function __construct()
	{
		parent::__construct();
		if (empty($this->shown_fields))
			$this->shown_fields = $this->db->list_fields($this->table);

		if ($this->searchable_fields === '$shown_fields')
			$this->searchable_fields = $this->shown_fields;

		if ($this->whereable_fields === '$shown_fields')
			$this->whereable_fields = $this->shown_fields;
		elseif (empty($this->whereable_fields))
			$this->whereable_fields = $this->db->list_fields($this->table);

		if ($this->materialized_view === true) {
			$this->materialized_view = $this->table . "_materialized";
		}
		
	}

	public function db_conditioning($conditions) {
		foreach ($conditions as $c) {
			if (is_callable($c)) {
				$c();
			}
		}
	}

	public function db_get($conditions) {
		$in = null;
		if ($this->materialized_view && is_string($this->materialized_view)) {
			$this->db_conditioning($conditions);
			$db_get = @$this->db->get($this->materialized_view);
			if (!$db_get) {
				$in = 1;
				$this->drop_materialized_view($this->materialized_view);
			}
		}
		if (!isset($db_get) || $db_get === false) {
			$this->db_conditioning($conditions);
			$db_get = @$this->db->get($this->table);
		}
		if ($db_get === false) {
			$this->get_db_error();
			//direget lagi tanpa @ supaya errornya keluar
			$this->db_conditioning($conditions);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
		}
		if ($this->materialized_view && is_string($this->materialized_view)) {
			$this->add_to_refresh_materialized_view_queue($this->materialized_view, $this->table, $in);
		}
		return $db_get;
	}

	public function db_get_count($conditions) {
		if ($this->materialized_view && is_string($this->materialized_view)) {
			$this->db_conditioning($conditions);
			$db_get = @$this->db->get($this->materialized_view);
			if ($db_get) {
				$db_get = $db_get->num_rows();
			}
		}
		if (!isset($db_get) || $db_get === false) {
			$this->db_conditioning($conditions);
			$db_get = @$this->db->get($this->table);
			if ($db_get) {
				$db_get = $db_get->num_rows();
			}
		}
		if ($db_get === false) {
			$this->get_db_error();
			//direget lagi tanpa @ supaya errornya keluar
			$this->db_conditioning($conditions);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
		}
		return $db_get;
	}

	public function db_get_w_count($conditions) {
		$in = null;
		if ($this->materialized_view && is_string($this->materialized_view)) {
			$this->db_conditioning($conditions);
			$db_get = @$this->db->get($this->materialized_view);
			if ($db_get) {
				$db_count = $db_get->num_rows();
			} else {
				$in = 1;
				$this->drop_materialized_view($this->materialized_view);
			}
		}
		if (!isset($db_get) || $db_get === false) {
			$this->db_conditioning($conditions);
			$db_get = @$this->db->get($this->table);
			if ($db_get) {
				$db_count = $db_get->num_rows();
			}
		}
		if ($db_get === false) {
			$this->get_db_error();
			$this->db_conditioning($conditions);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
		}
		if ($this->materialized_view && is_string($this->materialized_view)) {
			$this->add_to_refresh_materialized_view_queue($this->materialized_view, $this->table, $in);
		}
		return [
			'count' => $db_count,
			'cursor' => $db_get
		];
	}
	
	public function get_cache_key($func, $queries)
	{
		return get_class($this) . "__" . $this->table . "__" . $func . "__" . json_encode($queries);
	}


	public function read($queries, $select_attributes = [])
	{
		if ($this->use_cache)
			return $this->read_cached($queries, $select_attributes);
			
		try {
			$db_get = $this->db_get([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where($queries),
				fn() => $this->parse_attributes($select_attributes)
			]);
			$this->get_db_error();
			return $this->_fetch($db_get);
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_cached($queries, $select_attributes = [], $cache_config = null)
	{
		try {
			$key = $cache_config['key'] ?? $this->get_cache_key(__FUNCTION__, $queries);
			if ($data = Cache::instance($key)->load()) {
				return $data;
			}
			$db_get = $this->db_get([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where($queries),
				fn() => $this->parse_attributes($select_attributes)
			]);
			$this->get_db_error();
			$data = $this->_fetch($db_get);
			Cache::instance($key)->save($data, $cache_config['duration'] ?? null);
			return $data;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_single($queries, $select_attributes = [])
	{
		if ($this->use_cache)
			return $this->read_cached($queries, $select_attributes);

		try {
			$db_get = $this->db_get([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where($queries),
				fn() => $this->parse_attributes($select_attributes),
				fn() => $this->db->limit(1),
			]);
			$this->get_db_error();
			return $this->_fetch_single($db_get);
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_single_cached($queries, $select_attributes = [], $cache_config = null)
	{
		try {
			$key = $cache_config['key'] ?? $this->get_cache_key(__FUNCTION__, $queries);
			if ($data = Cache::instance($key)->load()) {
				return $data;
			}
			$db_get = $this->db_get([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where($queries),
				fn() => $this->parse_attributes($select_attributes),
				fn() => $this->db->limit(1),
			]);
			$this->get_db_error();
			$data = $this->_fetch_single($db_get);
			Cache::instance($key)->save($data, $cache_config['duration'] ?? null);
			return $data;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_count($queries)
	{
		if ($this->use_cache)
			return $this->read_count_cached($queries);

		try {
			$db_count = $this->db_get_count([
				fn() => $this->convert_queries_into_where($queries)
			]);
			$this->get_db_error();
			return $db_count;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_count_cached($queries, $cache_config = null)
	{
		try {
			$key = $cache_config['key'] ?? $this->get_cache_key(__FUNCTION__, $queries);
			if ($data = Cache::instance($key)->load()) {
				return $data;
			}
			$db_count = $this->db_get_count([
				fn() => $this->convert_queries_into_where($queries)
			]);
			$this->get_db_error();
			Cache::instance($key)->save($db_count, $cache_config['duration'] ?? null);
			return $db_count;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_page($queries, $select_attributes = [])
	{
		if ($this->use_cache)
			return $this->read_page_cached($queries, $select_attributes);

		try {
			$db_count = $this->db_get_count([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where_page_count($queries),
				fn() => $this->parse_attributes($select_attributes),
			]);
			$this->get_db_error();
			$query[] = $this->db->last_query();

			$db_get = $this->db_get([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where_page($queries),
				fn() => $this->parse_attributes($select_attributes),
			]);
			$this->get_db_error();
			$query[] = $this->db->last_query();
			return [
				"data" => $this->_fetch($db_get),
				"data_count" => $db_count,
				"total_page" => ceil($db_count / $queries['per_page']),
				"query" => $query
			];
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_page2($queries, $select_attributes = [])
	{
		if ($this->use_cache)
			return $this->read_page_cached($queries, $select_attributes);

		try {
			$get = $this->db_get_w_count([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where_page_count($queries),
				fn() => $this->parse_attributes($select_attributes),
			]);
			$this->get_db_error();
			$query[] = $this->db->last_query();

			$db_get = $get['cursor'];
			$db_count = $get['count'];

			$per_page = $queries['per_page'] ?? $this->per_page ?? 10;
			$page = $queries['page'] ?? 1;

			return [
				"data" => $this->_fetch_paginated($db_get, $page, $per_page),
				"data_count" => $db_count,
				"total_page" => ceil($db_count / $per_page),
				"query" => $query
			];
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_page_cached($queries, $select_attributes = [], $cache_config = null)
	{
		try {
			$key = $cache_config['key'] ?? $this->get_cache_key(__FUNCTION__, $queries);
			$key_cnt = $key . "_cnt";

			if (!$db_count = Cache::instance($key_cnt)->load()) {
				$db_count = $this->db_get_count([
					fn() => $this->db->select($this->select_shown()),
					fn() => $this->convert_queries_into_where_page_count($queries),
					fn() => $this->parse_attributes($select_attributes),
				]);
				$this->get_db_error();
				$query[] = $this->db->last_query();
				Cache::instance($key_cnt)->save($db_count);
			}

			if (!$db_data = Cache::instance($key)->load()) {
				$db_get = $this->db_get([
					fn() => $this->db->select($this->select_shown()),
					fn() => $this->convert_queries_into_where_page($queries),
					fn() => $this->parse_attributes($select_attributes),
				]);
				$this->get_db_error();
				$db_data = $this->_fetch($db_get);
				$query[] = $this->db->last_query();
				Cache::instance($key)->save($db_data, $cache_config['duration'] ?? null);
			}

			return [
				"data" => $db_data,
				"data_count" => $db_count,
				"total_page" => ceil($db_count / $queries['per_page']),
				"query" => $query ?? 'LOADED CACHE FROM ' . $key
			];
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function to_csv($file_name, $queries, $select_attributes = [])
	{
		try {
			$col = $this->retrieve_shown();
			$this->db->select($col);
			$this->convert_queries_into_where($queries);
			$this->parse_attributes($select_attributes);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
			$file = bh_open_csv($file_name);
			fputcsv($file, $col, ",");
			while ($row = $db_get->unbuffered_row('array')) {
				$rw = [];
				foreach ($col as $c) {
					$rw[] = $row[$c];
				}
				fputcsv($file, $rw, ",");
			}			
			fclose($file);
			return true;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_xapi($select = [], $where = [], $opts = [])
	{
		try {
			$this->shown_fields = $select;
			$db_get = $this->db_get([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where($where),
				fn() => $this->parse_attributes($opts),
			]);
			$this->get_db_error();
			return $this->_fetch($db_get);
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_pluck($field, $queries = [], $mutator = null)
	{
		if ($this->use_cache)
			return $this->read_pluck_cached($field, $queries = [], $mutator = null);

		try {
			$db_get = $this->db_get([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where($queries),
			]);
			$this->get_db_error();
			$data = [];
			if (is_array($field)) {
				$field2 = array_key_first($field);
				$field = $field[$field2];
			}
			while ($row = $db_get->unbuffered_row('array')) {
				if (!isset($field2)) {
					if (!in_array($row[$field], $data)) {
						$data[] = $mutator && is_callable($mutator) ? $mutator($row[$field]) : $row[$field];
					}
				} else {
					$rf2 = $row[$field2];
					$d = $mutator && is_callable($mutator) ? $mutator($row[$field], $rf2) : $row[$field];
					$data[$rf2] = $d;
				}
			}
			return $data;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_pluck_cached($field, $queries = [], $mutator = null, $cache_config = null)
	{
		try {
			$key = $cache_config['key'] ?? $this->get_cache_key(__FUNCTION__, $queries);
			if ($data = Cache::instance($key)->load()) {
				return $data;
			}
			$db_get = $this->db_get([
				fn() => $this->db->select($this->select_shown()),
				fn() => $this->convert_queries_into_where($queries),
			]);
			$this->get_db_error();
			$data = [];
			if (is_array($field)) {
				$field2 = array_key_first($field);
				$field = $field[$field2];
			}
			while ($row = $db_get->unbuffered_row('array')) {
				if (!isset($field2)) {
					if (!in_array($row[$field], $data)) {
						$data[] = $mutator && is_callable($mutator) ? $mutator($row[$field]) : $row[$field];
					}
				} else {
					$rf2 = $row[$field2];
					$d = $mutator && is_callable($mutator) ? $mutator($row[$field], $rf2) : $row[$field];
					$data[$rf2] = $d;
				}
			}
			Cache::instance($key)->save($data, $cache_config['duration'] ?? null);
			return $data;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_cursor($queries, $select_attributes = [])
	{
		try {
			$col = $this->retrieve_shown();
			$db_get = $this->db_get([
				fn() => $this->db->select($col),
				fn() => $this->convert_queries_into_where($queries),
				fn() => $this->parse_attributes($select_attributes),
			]);
			$this->get_db_error();
			return $db_get;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function select_shown()
	{
		$shown_fields = $this->shown_fields;
		if (empty($shown_fields))
			return '*';

		if (!empty($this->hidden_fields))
			$shown_fields = array_diff($shown_fields, $this->hidden_fields);

		return implode(',', $shown_fields);
	}

	public function retrieve_shown()
	{
		$shown_fields = $this->shown_fields;
		if (empty($shown_fields))
			return $this->db->list_fields($this->table);

		if (!empty($this->hidden_fields))
			$shown_fields = array_diff($shown_fields, $this->hidden_fields);

		return $shown_fields;
	}

	//Mengconvert query string menjadi klausa where
	public function convert_queries_into_where($queries)
	{
		if (isset($queries['q']) && !empty($queries['q']) && !empty($this->searchable_fields)) {
			$search_q = $queries['q'];
			$this->db->group_start();
			foreach ($this->searchable_fields as $field) {
				$this->db->or_where("$field::varchar ILIKE '%$search_q%'");
			}
			$this->db->group_end();
		}
		
		$converted_count = 0;
		foreach ($queries as $field => $value) {
			$fexp = explode(" ", $field);
			if (count($fexp) <= 2) {
				if (preg_match("/[^\w]/", $fexp[0]) === 0) {
					$fd = $fexp[0];
					$param = isset($fexp[1]) ? $fexp[1] : "";
				} else {
					$pref = isset($fexp[0]) ? $fexp[0] : "";
					$fd = isset($fexp[1]) ? $fexp[1] : "";
					$param = "";
				}
			} else {
				$pref = $fexp[0];
				$fd = $fexp[1];
				$param = isset($fexp[2]) ? $fexp[2] : "";
			}

			if (!in_array($fd, $this->whereable_fields))
				continue;

			$hasor = isset($pref) && $pref === "||";

			$fn_where_each = function ($value) use ($field, $fd, $param, $hasor) {
				switch ($param) {
					case '~':
					case '~~':
						$hasor ? $this->db->or_like($fd, $value) : $this->db->like($fd, $value);
						break;
					case '!~':
					case '!~~':
						$hasor ? $this->db->or_not_like($fd, $value) : $this->db->not_like($fd, $value);
						break;
					case '%~':
						$hasor ? $this->db->or_like($fd, $value, 'before') : $this->db->like($fd, $value, 'before');
						break;
					case '!%~':
						$hasor ? $this->db->or_not_like($fd, $value, 'before') : $this->db->not_like($fd, $value, 'before');
						break;
					case '~%':
						$hasor ? $this->db->or_like($fd, $value, 'after') : $this->db->like($fd, $value, 'after');
						break;
					case '!~%':
						$hasor ? $this->db->or_not_like($fd, $value, 'after') : $this->db->not_like($fd, $value, 'after');
						break;
					default:
						$hasor ? $this->db->or_where($field, $value) : $this->db->where($field, $value);
						break;
				}
			};

			$fn_where = function ($value) use ($fd, $param, $hasor, $fn_where_each) {
				switch ($param) {
					case '<>':
					case '!=':
						if (is_array($value))
							$hasor ? $this->db->or_where_not_in($fd, $value) : $this->db->where_not_in($fd, $value);
						else
							$hasor ? $this->db->or_where($fd . " != ", $value) : $this->db->where($fd . " != ", $value);
						break;
					case '=':
					case '==':
					case '':
						if (is_array($value))
							$hasor ? $this->db->or_where_in($fd, $value) : $this->db->where_in($fd, $value);
						else
							$hasor ? $this->db->or_where($fd, $value) : $this->db->where($fd, $value);
						break;
					default:
						if (is_array($value))
							foreach ($value as $v)
								$fn_where_each($v);
						else
							$fn_where_each($value);
						break;
				}
			};

			if (is_array($value)) {
				$fn_where($value);
			} else if (is_array(json_decode($value))) {
				$fn_where(json_decode($value));
			} else {
				$fn_where($value);
			}
			$converted_count++;
		}
		return $converted_count;

	}

	//Mengconvert query string menjadi klausa where pada sistem page
	public function convert_queries_into_where_page(&$queries)
	{
		$page = isset($queries['page']) ? $queries['page'] : 1;
		$per_page = isset($queries['per_page']) ? $queries['per_page'] : 10;
		$queries['page'] = $page;
		$queries['per_page'] = $per_page;
		$this->db->limit($per_page, $per_page * ($page - 1));
		$this->convert_queries_into_where($queries);
	}

	//Mengconvert query string menjadi klausa where pada sistem page (untuk counting)
	public function convert_queries_into_where_page_count(&$queries)
	{
		$this->convert_queries_into_where($queries);
	}

	//Mengecheck apakah query ada error dan jika ada langsung di catch
	public function get_db_error()
	{
		$db_error = $this->db->error();
		if (!empty($db_error) && $db_error['code'] > 0) {
			throw new \Exception("{$db_error['code']} - '{$db_error['message']}'");
		}
	}

	public function parse_attributes($atrs)
	{
		if ((isset($atrs['soft_delete']) && $atrs['soft_delete'] === TRUE) || ($this->soft_delete === TRUE && !isset($atrs['soft_delete']))) {
			$this->db->where('deleted_at =', NULL);
		}
		if ((isset($atrs['is_hidden']) && $atrs['is_hidden'] === FALSE) || ($this->is_hidden_enabled === TRUE && !isset($atrs['is_hidden']))) {
			$this->db->where('is_hidden =', FALSE);
		}
		
		if ((isset($this->skip_order) && $this->skip_order === TRUE && !isset($atrs['order_by']) && !isset($atrs['order_dir'])) || (isset($atrs['skip_order']) && $atrs['skip_order'] === TRUE)) {
		   return;
		}

		$this->order_by = $this->order_by ?? $this->primary_key;
		$order_by = isset($atrs['order_by']) ? $atrs['order_by'] : $this->order_by;
		$order_dir = isset($atrs['order_dir']) ? $atrs['order_dir'] : $this->order_dir;
		if (is_array($order_by)) {
			foreach ($order_by as $key => $ob) {
				if (is_array($order_dir)) {
					$this->db->order_by($ob, $order_dir[$key]);
				} else {
					$this->db->order_by($ob, $order_dir);
				}
			}
		} else {
			if (is_array($order_dir)) {
				$this->db->order_by($order_by, $order_dir[0]);
			} else {
				$this->db->order_by($order_by, $order_dir);
			}
		}
	}

	public function mutate_output(&$row, $index = 0) {
		foreach ($row as $key => $value) {
			$this->mutate_output_each_field($row, $key, $value, $index);
		}
	}

	public function mutate_output_each_field(&$row, $field, $value, $index) {
		$this->_check_if_mutated_field_is_bool($row, $field, $value, $index);
	}

	private function _check_if_mutated_field_is_bool(&$row, $field, $value, $index) {
		if ($value === "t") {
			$row[$field] = true;
			return true;
		}
		if ($value === "f") {
			$row[$field] = false;
			return true;
		}
	}

	private function _check_and_redo_query_if_false($query) {
		if (!is_bool($query))
			return;

		throw new \Exception("Terdapat masalah pada query \"read_\" yang dijalankan");	
	}

	protected function _fetch($query)
	{
		$data = [];
		$index = 0;
		$this->_check_and_redo_query_if_false($query);
		while ($row = $query->unbuffered_row('array')) {
			$this->mutate_output($row, $index);
			$data[] = $row;
			$index++;
		}
		return $data;
	}
	
	protected function _fetch_paginated($query, $page, $per_page)
	{
		$row_start = ($page - 1) * $per_page;
		$row_end = $row_start + $per_page;
		$data = [];
		$index = 0;
		$page_index = 0;
		$this->_check_and_redo_query_if_false($query);
		while ($row = $query->unbuffered_row('array')) {
			if ($index >= $row_start && $index < $row_end) {
				$this->mutate_output($row, $page_index);
				$data[] = $row;
				$page_index++;
			}
			if ($index >= $row_end)
				break;
			$index++;
		}
		return $data;
	}

	protected function _fetch_single($query)
	{
		$data = $this->_fetch($query);
		if (!empty($data))
			return $data[0];
		return [];
	}

	private function add_schema_to_materialized_view($str) {
		if (substr_count($str, '.') == 0) {
			return isset(DATABASE_ENV['schema']) ? (DATABASE_ENV['schema'] . "." . $str) : $str;
		}
		return $str;
	}

	public function drop_materialized_view($mv, $in = null) {
		$query = "drop materialized view if exists " . $this->add_schema_to_materialized_view($mv);

		$this->db->query($query);
	}

	public function add_to_refresh_materialized_view_queue($mv, $query, $in = null) {
		if (!str_starts_with(strtolower($query), "select") && substr_count(trim($query), ' ') == 0)
			$query = "SELECT * from " . $this->add_schema_to_materialized_view($query);

		$this->db->insert("utility.materialized_view_refresh_queue", [
			"mv_name" => $this->add_schema_to_materialized_view($mv),
			"mv_query" => $query,
			"refreshes_in" => $in ?? $this->materialized_view_refresh_duration ?? 30,
		]);
	}

	public function read_notification_count($queries, $key, $refresh = false) {
		if (!$refresh)
			return $this->read_count_cached($queries, [
				'key' => $key,
				'duration' => 300
			]);

		try {
			$key = $key ?? $this->get_cache_key('read_count_cached', $queries);
			$db_count = $this->db_get_count([
				fn() => $this->convert_queries_into_where($queries)
			]);
			$this->get_db_error();
			Cache::instance($key)->save($db_count, 300);
			return $db_count;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}

	}


}
