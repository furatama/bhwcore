<?php

if (!function_exists('bh_generate_nomor_kode')) {
    function bh_generate_nomor_kode($table, $nomor_fld, $extra = '', $left_pad = 2)
    {
        $CI = &get_instance();
        return $CI->db->query("SELECT generate_nomor_kode('$table', '$nomor_fld', '$left_pad', '$extra')")->row()->generate_nomor_kode;
    }
}

if (!function_exists('bh_generate_nomor_surat')) {
    //Patokan dengan row_count
    function bh_generate_nomor_surat($table, $nomor_fld, $tanggal, $tanggal_fld, $extra, $left_pad = 4)
    {
        $CI = &get_instance();
        return $CI->db->query("SELECT generate_nomor_surat('$tanggal', '$table', '$tanggal_fld', '$nomor_fld', '$left_pad', '$extra')")->row()->generate_nomor_surat;
    }
}

if (!function_exists('bh_generate_nomor_surat2')) {
    //Patokan dengan nomer
    function bh_generate_nomor_surat2($table, $nomor_fld, $tanggal, $tanggal_fld, $extra, $left_pad = 4)
    {
        $CI = &get_instance();
        return $CI->db->query("SELECT generate_nomor_surat2('$tanggal', '$table', '$tanggal_fld', '$nomor_fld', '$left_pad', '$extra')")->row()->generate_nomor_surat2;
    }
}

if (!function_exists('model_error_check')) {
    function model_error_check($error)
    {
        if ($error && !is_array($error) && !is_object($error) && substr($error, 0, 4) === "ERR:") {
            throw new Exception($error);
        }
    }
}

if (!function_exists('collection_pluck_field')) {
    function collection_pluck_field($collection, $field)
    {
        $plucked = [];
        for ($i = 0; $i < count($collection); $i++) {
            $val = $collection[$i][$field];
            if (!in_array($val, $plucked)) {
                $plucked[] = $val;
            }
        }
        return $plucked;
    }
}

if (!function_exists('collection_pluck_query')) {
    function collection_pluck_query($collection, $field)
    {
        $plucked = [];
        for ($i = 0; $i < count($collection); $i++) {
            $val = $collection[$i][$field];
            if (!in_array($val, $plucked)) {
                $plucked[] = $val;
            }
        }
        return [$field => "[" . implode(',', $plucked) . "]"];
    }
}

if (!function_exists('collection_pluck_json_query')) {
    function collection_pluck_json_query($collection, $field)
    {
        $plucked = [];
        for ($i = 0; $i < count($collection); $i++) {
            $val = $collection[$i][$field];
            if (!in_array($val, $plucked)) {
                $plucked[] = $val;
            }
        }
        return [$field => $plucked];
    }
}

if (!function_exists('array_to_queries')) {
    function array_to_queries($arr)
    {
        $qry = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $qry[] = "[" . implode(',', $v) . "]";
            } else {
                $qry[] = $v;
            }
        }
        return implode('&', $qry);
    }
}

if (!function_exists('bh_hak_akses_cek')) {
    function bh_hak_akses_cek(array $akses_list, string $akses)
    {
        return in_array($akses, $akses_list) || in_array('SUPA', $akses_list);
    }
}

if (!function_exists('bh_log')) {
    function bh_log(array $data)
    {
        $date = date('Y-m-d H:i:s');
		$log = "[" . $date . "] " . implode(" ", $data) . "\n";
		$file = fopen('log.txt', 'a');
		fwrite($file, $log);
		fclose($file);
    }
}
