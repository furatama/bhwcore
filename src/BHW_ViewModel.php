<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

class BHW_ViewModel extends BHW_Hub
{
	public $table; //masukkan value dari table yang dipakai
	public $primary_key; //masukkan value dari primary_key table tersebut
	public $whereable_fields = '$shown_fields'; //masukkan array dari field yang bisa diisi ke table (abaikan isian otomatis seperti id, created_at, deleted_at)
	public $shown_fields = []; //masukkan array dari field yang bisa diperlihatkan dari table
	public $searchable_fields = '$shown_fields'; //masukkan array dari field yang bisa disearch dari table via page, jika valuenya $shown_fields/$fillable_fields maka akan mengikuti variable tsb
	public $hidden_fields = []; //masukkan array dari field yang bisa diperlihatkan dari table
	public $soft_delete; //aktifkan mode soft-delete (soft delete tidak menghapus data dari table tersebut)
	public $is_hidden_enabled; //aktifkan mode show-hide
	public $order_dir = 'DESC';

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
	}

	public function read($queries, $select_attributes = [])
	{
		try {
			$this->db->select($this->select_shown());
			$this->convert_queries_into_where($queries);
			$this->parse_attributes($select_attributes);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
			return $this->_fetch($db_get);
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_single($queries, $select_attributes = [])
	{
		try {
			$this->db->select($this->select_shown());
			$this->convert_queries_into_where($queries);
			$this->parse_attributes($select_attributes);
			$this->db->limit(1);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
			return $this->_fetch_single($db_get);
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_count($queries)
	{
		try {
			$this->convert_queries_into_where($queries);
			$db_count = $this->db->count_all_results($this->table);
			$this->get_db_error();
			return $db_count;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_page($queries, $select_attributes = [])
	{
		try {
			$this->db->select($this->select_shown());
			$this->convert_queries_into_where_page_count($queries);
			$this->parse_attributes($select_attributes);
			$db_count = $this->db->count_all_results($this->table);
			$this->get_db_error();

			$this->db->select($this->select_shown());
			$this->convert_queries_into_where_page($queries);
			$this->parse_attributes($select_attributes);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
			return [
				"data" => $this->_fetch($db_get),
				"data_count" => $db_count,
				"total_page" => ceil($db_count / $queries['per_page'])
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
			$this->db->select($this->select_shown());
			$this->convert_queries_into_where($where);
			$this->parse_attributes($opts);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
			return $this->_fetch($db_get);
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_pluck($field, $queries = [])
	{
		try {
			$this->db->select($this->select_shown());
			$this->convert_queries_into_where($queries);
			$db_get = $this->db->get($this->table);
			$this->get_db_error();
			$data = [];
			while ($row = $db_get->unbuffered_row('array')) {
				if (!in_array($row[$field], $data))
					$data[] = $row[$field];
			}
			return $data;
		} catch (\Throwable $th) {
			return "ERR:{$th->getMessage()}";
		}
	}

	public function read_cursor($queries, $select_attributes = [])
	{
		try {
			$col = $this->retrieve_shown();
			$this->db->select($col);
			$this->convert_queries_into_where($queries);
			$this->parse_attributes($select_attributes);
			$db_get = $this->db->get($this->table);
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
		$converted_count = 0;
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

			if (!in_array($fd, $this->whereable_fields))
				continue;

			if (is_array($value)) {
				if (!empty($value))
					if ($param == "<>" || $param =="!=")
						$this->db->where_not_in($fd, $value);
					else
						$this->db->where_in($fd, $value);
			} else if (is_array(json_decode($value))) {
				if ($param == "<>" || $param =="!=")
					$this->db->where_not_in($fd, json_decode($value));
				else
					$this->db->where_in($fd, json_decode($value));
			} else {
				switch ($param) {
					case '~':
					case '~~':
						$this->db->like($fd, $value);
						break;					
					case '!~':
					case '!~~':
						$this->db->not_like($fd, $value);
						break;					
					case '%~':
						$this->db->like($fd, $value, 'before');
						break;					
					case '!%~':
						$this->db->not_like($fd, $value, 'before');
						break;					
					case '~%':
						$this->db->like($fd, $value, 'after');
						break;					
					case '!~%':
						$this->db->not_like($fd, $value, 'after');
						break;					
					default:
						$this->db->where($field, $value);
						break;
				}
			}
			$converted_count++;
		}
		return $converted_count;
	}

	//Mengconvert query string menjadi klausa where pada sistem page
	public function convert_queries_into_where_page(&$queries)
	{
		if (isset($queries['q']) && !empty($this->searchable_fields)) {
			$search_q = $queries['q'];
			$this->db->group_start();
			foreach ($this->searchable_fields as $field) {
				$this->db->or_like("LOWER($field::varchar)", strtolower($search_q));
			}
			$this->db->group_end();
		}
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
		if (isset($queries['q']) && !empty($this->searchable_fields)) {
			$search_q = $queries['q'];
			$this->db->group_start();
			foreach ($this->searchable_fields as $field) {
				$this->db->or_like("LOWER($field::varchar)", strtolower($search_q));
			}
			$this->db->group_end();
		}
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

		$order_by = isset($atrs['order_by']) ? $atrs['order_by'] : $this->primary_key;
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
			$this->db->order_by($order_by, $order_dir);
		}
	}

	public function mutate_output(&$row) {
		foreach ($row as $key => $value) {
			if ($value === "t") {
				$row[$key] = true;
				continue;
			}
			if ($value === "f") {
				$row[$key] = false;
				continue;
			}
		}
	}

	protected function _fetch($query)
	{
		$data = [];
		while ($row = $query->unbuffered_row('array')) {
			$this->mutate_output($row);
			$data[] = $row;
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
}
