<?php if ( !defined('COREPATH') ) exit;

class Text {

	public static function convert_to_ascii($str, $replace = array(), $delimiter = '-') {
		if( !empty($replace) ) {
			$str = str_replace((array)$replace, ' ', $str);
		}

		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
		$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
		$clean = strtolower(trim($clean, '-'));
		$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

		return $clean;
	}

	public static function convert_to_utf8($str) {
		return ( !mb_check_encoding($str, 'UTF-8')) ?  utf8_encode($str) : $str;
	}

	public static function remove_line_break($str) {
		return preg_replace("/\r\n+|\r+|\n+|\t+/i", ' ', $str);
	}
	
}
