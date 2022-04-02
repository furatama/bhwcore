<?php
namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

class Join_collection
{
	public $join_tree;
	private $_mapper;
	public $all_array = false;

	function __construct()
	{
	} // CONSTRUCTOR

	function from($var, $all_array = false)
	{
		$this->_mapper = null;
		$this->join_tree = ["arr" => $var];
		$this->all_array = $all_array;
		return $this;
	}

	function array_joined()
	{
		$this->all_array = true;
		return $this;
	}

	function map($mapper) {
		$this->_mapper = $mapper;
		return $this;
	}

	function join($var, $label, $parent, $child, $next = null, &$current = null)
	{
		if ($current === NULL) {
			$current = &$this->join_tree["next_arrs"];
		}
		$current[] = [
			'arr' => $var,
			'arr_label' => $label,
			'p_key' => $parent,
			'c_key' => $child,
		];
		if ($next !== null) {
			$cur_idx = count($current) - 1;
			$current[$cur_idx]["next_arrs"] = array();
			$next($this, $current[$cur_idx]["next_arrs"]);
		}
		return $this;
	}

	private function _get_map_field($arr, $map) {
		$curmap = $map[0];
		if (!isset($arr[$curmap]))
			return null;
		$curobj = $arr[$curmap];
		if (count($map) === 1)
			return $curobj;
		array_splice($map, 0, 1);
		return $this->_get_map_field($curobj, $map);
	}

	private function _perform_mapping($arr) {
		$new_arr = [];
		foreach ($arr as $value) {
			$na = [];
			foreach ($this->_mapper as $field => $map) {
				$na[$field] = $this->_get_map_field($value, explode('.',$map));
			}
			$new_arr[] = $na;
		}
		return $new_arr;
	}

	// input a defined filter tree array and output a processed result
	function get()
	{
		$filter_tree = &$this->join_tree;

		if (empty($filter_tree['arr']))
			return [];

		$return_arr = [];
		// can feed either a single record a set of rows...
		if (!$this->is_assoc($filter_tree['arr']))
			$return_arr = $filter_tree['arr']; // root for return array
		else
			$return_arr[] = $filter_tree['arr']; // force a numeric array so return is consistent.

		$this->do_chain_filter($filter_tree['next_arrs'], $return_arr);

		if ($this->_mapper) {
			$return_arr = $this->_perform_mapping($return_arr);
		}

		return $return_arr;
	} // $this->chain_filter($filter_tree) // public


	function is_assoc($arr)
	{
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	function do_chain_filter(&$tree_arr, &$final_arr)
	{
		$cur_final_node = &$final_arr;

		if (!is_array($cur_final_node))
			return false;

		if (!isset($cur_final_node[0])) {
			foreach ($tree_arr as $n_key => $n_arr) {
				$cur_tree_node = $tree_arr[$n_key];
				// $final_cur_el['arr_label'] = 'true';
				$next_final_node = &$cur_final_node[$cur_tree_node['arr_label']];


				// data up hombre 
				// filter out array elements not related to parent array
				$result = $this->children_of_parent(
					$cur_final_node,
					$cur_tree_node['arr'],
					$cur_tree_node['p_key'],
					$cur_tree_node['c_key']
				);

				$next_final_node = $result;

				// now recurse if we have more depths to travel...
				if (!empty($cur_tree_node['next_arrs']))
					$this->do_chain_filter($cur_tree_node['next_arrs'], $next_final_node);
			}
		} else {
			// send the next_arrs
			foreach ($final_arr as $f_key => $f_arr) {
				$cur_final_node = &$final_arr[$f_key];

				foreach ($tree_arr as $n_key => $n_arr) {
					$cur_tree_node = $tree_arr[$n_key];
					// $final_cur_el['arr_label'] = 'true';
					$next_final_node = &$cur_final_node[$cur_tree_node['arr_label']];


					// data up hombre 
					// filter out array elements not related to parent array
					$result = $this->children_of_parent(
						$cur_final_node,
						$cur_tree_node['arr'],
						$cur_tree_node['p_key'],
						$cur_tree_node['c_key']
					);

					$next_final_node = $result;

					// now recurse if we have more depths to travel...
					if (!empty($cur_tree_node['next_arrs']))
						$this->do_chain_filter($cur_tree_node['next_arrs'], $next_final_node);
				}
			}
		}
	} // this->function chain_filter(&$tree_arr, &$final_arr)




	// take 2 arrays
	// first array is an associative array. 
	// second array is an array of associative arrays.
	// return children of second array that belong to parent array
	function children_of_parent($arr_parent, $arr_children, $key_parent, $key_child)
	{
		// parent   = a record
		// child    = multiple records
		// filter out children that don't apply to parent.
		// return the result
		$parent_id = $arr_parent[$key_parent];

		foreach ($arr_children as $arr_child) {
			$child_id = $arr_child[$key_child];

			if (is_array($parent_id)) {
				if (in_array($child_id, $parent_id))
					$return_arr[] = $arr_child;
			} else {
				if ($parent_id === $child_id)
					$return_arr[] = $arr_child;
			}

		}

		if (!empty($return_arr))
			return count($return_arr) === 1 && !$this->all_array ? $return_arr[0] : $return_arr;
	} // this->children_of_parent($arr_parent, $arr_children, $key_parent, $key_child )


} //
