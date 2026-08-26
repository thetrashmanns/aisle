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
}

class PDF_Object {
	public $number;
	public $datatype;
	public $contents;
	public $offset;

	function __construct($number, $datatype, $contents) {
		$this->number = $number;
		$this->datatype = $datatype;
		$this->contents = $contents;
		$this->offset = 0;
	}
}

class PDF {
	public $page = array(); //page descriptors
	public $object = array(); //obj contents
	public $xref_table_offset;
	public $catalog;
	public $procset;
	public $pages;

	function add_obj($type, $contents) {
		$obj = new PDF_Object(count($this->object), $type, $contents);
		$this->object[] = $obj;
		return $obj;
	}

	function get_ref($obj) {
		return sprintf("%d 0 R", $obj->number);
	}

	function write_obj($fh, $obj) {
		if ($obj instanceof PDF_Object && $obj->datatype == "stream") {
			$this->write_indirect($fh, $obj);
		} else {
			$this->write_direct($fh, $obj);
		}
	}

	function write_direct($fh, $obj) {
		if (!($obj instanceof PDF_Object)) {
			fwrite($fh, $obj . "\n");
		} else if ($obj->datatype == "dictionary") {
			fwrite($fh, "<<\n");
			foreach ($obj->contents as $k => $v) :
				fwrite($fh, sprintf("/%s ", $k));
				$this->write_obj($fh, $v);
			endforeach;
			fwrite($fh, ">>\n");
		} else if ($obj->datatype == "array") {
			fwrite($fh, "[\n");
			foreach ($obj->contents as $v) :
				$this->write_obj($fh, $v);
			endforeach;
			fwrite($fh, "]\n");
		} else if ($obj->datatype == "stream") {
			$len = 0;
			if (is_string($obj->contents)) {
				$len = strlen($obj->contents);
			} else {
				foreach ($obj->contents as $str) :
					$len +=	strlen($str) + 1;
				endforeach;
			}

			fwrite($fh, sprintf("<< /Length %d >>\n", $len));
			fwrite($fh, "stream\n");

			if (is_string($obj->contents)) {
				fwrite($fh, $obj->contents);
			} else {
				foreach ($obj->contents as $str) :
					fwrite($fh, $str);
					fwrite($fh, "\n");
				endforeach;
			}
			fwrite($fh, "endstream\n");
		}
	}

	function write_indirect($fh, $obj) {
		$obj->offset = ftell($fh);
		fwrite($fh, sprintf("%d %d obj\n", $obj->number, 0));
		$this->write_direct($fh, $obj);
		fwrite($fh, "endobj\n");
	}

	function write_header($fh) {
		fwrite($fh, "%PDF-1.0\n");
	}

	function write_body($fh) {
		foreach ($this->object as $value) :
			$this->write_indirect($fh, $value);
		endforeach;
	}

	function write_xref($fh) {
		$this->xref_table_offset = ftell($fh);
		fwrite($fh, "xref\n");
		fwrite($fh, sprintf("%d %d\n", 1, count($this->object)));
		foreach ($this->object as $value) :
			fwrite($fh, sprintf("%010d %05d n \n", $value->offset, 0));
		endforeach;
	}

	function write_trailer($fh) {
		fwrite($fh, "trailer\n");
		fwrite($fh, "<<\n");
		fwrite($fh, sprintf("/Size %d\n", count($this->object)));
		fwrite($fh, "/Root " . $this->get_ref($this->catalog) . "\n");
		fwrite($fh, ">>\n");
		fwrite($fh, "startxref\n");
		fwrite($fh, sprintf("%d\n", $this->xref_table_offset));
		fwrite($fh, "%%EOF\n");
	}

	function new_font($tab) {
		$sub_type = $tab->subtype <> null ? $tab->subtype : "Type1";
		$name = $tab->name <> null ? $tab->name : "Helvetica";
		return $this->add_obj("dictionary", array("Type" => "/Font", "Subtype" => ("/" . $sub_type), "BaseFont" => ("/" . $name)));
	}

	function add_page() {
		$page = new PDF_Page();
		$contents = $this->add_obj("stream", $page->contents);
		$resources = array("datatype" => "dictionary",
							"contents" => array("Font" => array("datatype" => "dictionary", "contents" => array())),
							"ProcSet" => $this->get_ref($this->procset));
		foreach ($page->used_fonts as $i => $font) :
			$resources["contents"]["Font"]["contents"]["F" . $i] = $this->get_ref($font);
		endforeach;

		$self = $this->add_obj("dictionary", array("Type" => "/Page",
														"Parent" => $this->get_ref($this->pages),
														"Contents" => $this->get_ref($contents),
														"Resources" => $resources));
		$this->pages["contents"]["Kids"]["contents"][] = $this->get_ref($self);
		$this->pages["contents"]["Count"]++;
	}

	function write($name) {
		$fh = fopen($name or "default.pdf", "w");

		$this->write_header($fh);
		$this->write_body($fh);
		$this->write_xref($fh);
		$this->write_trailer($fh);
		fclose($fh);
	}

	function __construct() {
		$this->pages = $this->add_obj("dictionary", array("Type" => "/Pages", "Kids" => array("datatype" => "array", "contents" => array()), "Count" => 0));
		$this->catalog = $this->add_obj("dictionary", array("Type" => "/Catalog", "Pages" => $this->get_ref($this->pages)));
		$this->procset = $this->add_obj("array", array("/PDF", "/Text"));
	}
}