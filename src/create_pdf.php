<?php
//Based upon: https://github.com/catseye/pdf.lua/blob/master/pdf.lua
//(I just need basic PDF creation... nothing fancy.)

class PDF_Page {
	public $contents = array();
	public $used_fonts = array();

	function use_font($font) {
		$already_made = array_search($font, $this->used_fonts);
		if ($already_made) {
			return "/F" . $already_made;
		} else {
			$this->used_fonts[] = $font;
			return "/F" . count($this->used_fonts);
		}
	}

	function begin_text() {
		$this->contents[] = "BT";
	}

	function end_text() {
		$this->contents[] = "ET";
	}

	function set_font($font, $size) {
		$this->contents[] = sprintf("%s %f Tf", $this->use_font($font), $size);
	}

	function set_text_pos($x, $y) {
		$this->contents[] = sprintf("%s %f Td", $x, $y);
	}

	function show($str) {
		$this->contents[] = sprintf("(%s) Tj", $str);
	}

	function set_char_spacing($spacing) {
		$this->contents[] = sprintf("%f Tc", $spacing);
	}

	//Graphics

	function move_to($x, $y) {
		$this->contents[] = sprintf("%f %f m", $x, $y);
	}

	function line_to($x, $y) {
		$this->contents[] = sprintf("%f %f l", $x, $y);
	}

	function curve_to($x1, $y1, $x2, $y2, $x3, $y3) {
		if ($x3 <> null && $y3 <> null) {
			$str = sprintf("%f %f %f %f %f %f c", $x1, $y1, $x2, $y2, $x3, $y3);
		} else {
			$str = sprintf("%f %f %f %f c", $x1, $y1, $x2, $y2);
		}
		$this->contents[] = $str;
	}

	function rectangle($x, $y, $w, $h) {
		$this->contents[] = sprintf("%f %f %f %f re", $x, $y, $w, $h);
	}

	//Colors

	function set_gray($which, $gray) {
		if ($which == "fill") {
			$this->contents[] = sprintf("%d g", $gray);
		} else {
			$this->contents[] = sprintf("%d G", $gray);
		}
	}

	function set_rgb($which, $r, $g, $b) {
		if ($which == "fill") {
			$this->contents[] = sprintf("%f %f %f rg", $r, $g, $b);
		} else {
			$this->contents[] = sprintf("%f %f %f RG", $r, $g, $b);
		}
	}

	function set_cmyk($which, $c, $m, $y, $k) {
		if ($which == "fill") {
			$this->contents[] = sprintf("%f %f %f %f k", $c, $m, $y, $k);
		} else {
			$this->contents[] = sprintf("%f %f %f %f K", $c, $m, $y, $k);
		}
	}

	//Line options

	function set_flat($i) {
		$this->contents[] = sprintf("%d i", $i);
	}

	function set_line_cap($j) {
		$this->contents[] = sprintf("%d J", $j);
	}

	function set_line_join($j) {
		$this->contents[] = sprintf("%d j", $j);
	}

	function set_line_width($w) {
		$this->contents[] = sprintf("%d w", $w);
	}

	function set_miter_limit($m) {
		$this->contents[] = sprintf("%d M", $m);
	}

	function set_dash($arr, $phase) {
		$str = "";

		foreach ($arr as $v) {
			$str = $str . $v . " ";
		}

		$this->contents[] = sprintf("[%s] %d d", $str, $phase);
	}

	//Path-termination

	function stroke() {
		$this->contents[] = "S";
	}

	function close_path() {
		$this->contents[] = "h";
	}

	function fill() {
		$this->contents[] = "f";
	}

	function new_path() {
		$this->contents[] = "n";
	}

	function clip() {
		$this->contents[] = "W";
	}

	//Save/restore

	function save() {
		$this->contents[] = "q";
	}

	function restore() {
		$this->contents[] = "Q";
	}

	//CTM funcs

	function transform($a, $b, $c, $d, $e, $f) {
		$this->contents[] = sprintf("%f %f %f %f %f %f cm", $a, $b, $c, $d, $e, $f);
	}

	function translate($x, $y) {
		$this->transform(1, 0, 0, 1, $x, $y);
	}

	function scale($x, $y) {
		if (!$y) {
			$y = $x;
		}
		$this->transform($x, 0, 0, $y, 0, 0);
	}

	function rotate($theta) {
		$c = cos($theta);
		$s = sin($theta);
		$this->transform($c, $s, -1 * $s, $c, 0, 0);
	}

	function skew($tha, $thb) {
		$tana = tan($tha);
		$tanb = tan($thb);
		$this->transform(1, $tana, $tanb, 1, 0, 0);
	}

	function add() {

	}
}

class PDF {
	public $page = array(); //page descriptors
	public $object = array(); //obj contents
	public $xref_table_offset;

	function add($obj) {
		$this->object.array_push($obj);
		$temp = count($this->object);
		$obj.number = $temp;
		return $obj;
	}
}