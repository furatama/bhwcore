<?php

namespace bhw\BhawanaCore;

defined('BASEPATH') or exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Spreadsheet as SS;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

class Spreadsheet
{

	protected $spreadsheet;
	protected $header;
	protected $body;
	protected $footer;
	protected $row = 1;
	protected $col = 1;

	protected function getCol($col)
	{
		// $col = $col + 1;
		$alphabet = range('A', 'Z');
		$alp_count = count($alphabet);
		$col_alp = $col % $alp_count;
		$col_alp_x = $col / $alp_count;
		if (intval($col_alp_x) > 0) {
			if ($col_alp_x > $alp_count) {
				$col_alp_x = $col_alp_x % $alp_count;
			}
			return $alphabet[$col_alp_x - 1] . $alphabet[$col_alp];
		}
		return $alphabet[$col_alp];
	}

	protected function getRow($row)
	{
		return $row + 1;
	}

	protected function getCell($col, $row)
	{
		return $this->getCol($col) . $this->getRow($row);
	}

	protected function getCells($col1, $row1, $col2, $row2)
	{
		return $this->getCol($col1) . $this->getRow($row1) . ":" . $this->getCol($col2) . $this->getRow($row2);
	}

	public function __construct()
	{
		$this->spreadsheet = new SS();
	}

	public function render_model($model, $queries = [], $select_attributes = [], $column_alias = [], $callback = null)
	{
		if ($model == null)
			return;

		$CI = &get_instance();
		$CI->load->model($model, 'xmdl');
		$spreadsheet = $this->spreadsheet;
		$sheet = $spreadsheet->getActiveSheet();

		$start_col = $this->col;
		$start_row = $this->row;

		$cursor = $CI->xmdl->read_cursor($queries, $select_attributes);
		$columns = $CI->xmdl->retrieve_shown($queries);

		$this->col = $start_col;
		foreach ($columns as $col) {			
			if (isset($column_alias[$col]))
				$col = $column_alias[$col];
			else
				$col = ucwords(str_replace('_', ' ', $col));

			$sheet->setCellValue($this->getCell($this->col, $this->row), $col);
			$this->col++;
		}
		$this->row++;

		while ($cur = $cursor->unbuffered_row('array')) {
			if ($callback != null) {
				$callback($cur);
			}
			$this->col = $start_col;
			foreach ($cur as $key => $value) {
				if (str_contains($key, 'kode_')) 
					$sheet->setCellValueExplicit($this->getCell($this->col, $this->row), $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				else
					$sheet->setCellValue($this->getCell($this->col, $this->row), $value);
				$this->col++;
			}
			$this->row++;
		}

		$sheet->getStyle($this->getCells($start_col, $start_row, $this->col - 1, $start_row))
			->getFont()
			->setBold(true);

		$sheet->getStyle($this->getCells($start_col, $start_row, $this->col - 1, $this->row - 1))
			->getBorders()
			->getAllBorders()
			->setBorderStyle(Border::BORDER_THIN);

		foreach ($sheet->getColumnIterator() as $column) {
			$sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
		}
	}

	public function save_xlsx($filename)
	{
		$filename = bh_open_xlsx($filename);
		$writer = new Xlsx($this->spreadsheet);
		$writer->save($filename);
	}
}
