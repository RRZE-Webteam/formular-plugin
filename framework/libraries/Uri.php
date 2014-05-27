<?php if ( !defined('COREPATH') ) exit;

class Uri extends pattern\Singleton {

	private $keyval	= array();
	private $uri_string;
	private $segments = array();
	private $rsegments = array();

	protected static $instance;

	protected static function instance() {
		return self::get_instance( get_class() );
	}

	protected function init() {
		$this->fetch_uri_string();
		$this->explode_segments();
		$this->reindex_segments();
	}

	private function fetch_uri_string() {
		if (is_array($_GET) && count($_GET) == 1 && trim(key($_GET), '/') != '') {
			$this->uri_string = key($_GET);
			return;
		}

		$path = (isset($_SERVER['PATH_INFO'])) ? $_SERVER['PATH_INFO'] : @getenv('PATH_INFO');
		if (trim($path, '/') != '') {
			$this->uri_string = $path;
			return;
		}

		$path =  (isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : @getenv('QUERY_STRING');
		if (trim($path, '/') != '') {
			$this->uri_string = $path;
			return;
		}

		$path = str_replace($_SERVER['SCRIPT_NAME'], '', (isset($_SERVER['ORIG_PATH_INFO'])) ? $_SERVER['ORIG_PATH_INFO'] : @getenv('ORIG_PATH_INFO'));
		if (trim($path, '/') != '') {
			$this->uri_string = $path;
			return;
		}

		$path =  (isset($_SERVER['SCRIPT_NAME'])) ? $_SERVER['SCRIPT_NAME'] : @getenv('SCRIPT_NAME');
		if (trim($path, '/') != '') {
			$this->uri_string = $path;
			return;
		}

		$this->uri_string = '';
	}

	private function explode_segments() {
		foreach(explode("/", preg_replace("|/*(.+?)/*$|", "\\1", $this->uri_string)) as $val) {
			$val = trim($val);

			if ($val != '')
			{
				$this->segments[] = $val;
			}
		}
	}

	private function reindex_segments() {
		array_unshift($this->segments, null);
		array_unshift($this->rsegments, null);
		unset($this->segments[0]);
		unset($this->rsegments[0]);
	}

	public static function segment($n, $no_result = false) {
		return ( ! isset(self::instance()->segments[$n])) ? $no_result : $this->segments[$n];
	}

	public static function rsegment($n, $no_result = false) {
		return ( ! isset(self::instance()->rsegments[$n])) ? $no_result : $this->rsegments[$n];
	}

	public static function uri_to_assoc($n = 3, $default = array()) {
	 	return self::instance()->_uri_to_assoc($n, $default, 'segment');
	}

	public static function ruri_to_assoc($n = 3, $default = array()) {
	 	return self::instance()->_uri_to_assoc($n, $default, 'rsegment');
	}

	private function _uri_to_assoc($n = 3, $default = array(), $which = 'segment') {
		if ($which == 'segment') {
			$total_segments = 'total_segments';
			$segment_array = 'segment_array';
		} else {
			$total_segments = 'total_rsegments';
			$segment_array = 'rsegment_array';
		}

		if ( ! is_numeric($n)) {
			return $default;
		}

		if (isset($this->keyval[$n])) {
			return $this->keyval[$n];
		}

		if ($this->$total_segments() < $n) {
			if (count($default) == 0) {
				return array();
			}

			$retval = array();
			foreach ($default as $val) {
				$retval[$val] = false;
			}
			return $retval;
		}

		$segments = array_slice($this->$segment_array(), ($n - 1));

		$i = 0;
		$lastval = '';
		$retval  = array();
		foreach ($segments as $seg) {
			if ($i % 2) {
				$retval[$lastval] = $seg;
			} else {
				$retval[$seg] = false;
				$lastval = $seg;
			}

			$i++;
		}

		if (count($default) > 0) {
			foreach ($default as $val) {
				if ( ! array_key_exists($val, $retval)) {
					$retval[$val] = false;
				}
			}
		}

		$this->keyval[$n] = $retval;
		return $retval;
	}

	public static function assoc_to_uri($array) {
		$temp = array();
		foreach ((array)$array as $key => $val) {
			$temp[] = $key;
			$temp[] = $val;
		}

		return implode('/', $temp);
	}

	public static function slash_segment($n, $where = 'trailing') {
		return self::instance()->_slash_segment($n, $where, 'segment');
	}

	public static function slash_rsegment($n, $where = 'trailing') {
		return self::instance()->_slash_segment($n, $where, 'rsegment');
	}

	private function _slash_segment($n, $where = 'trailing', $which = 'segment') {
		if ($where == 'trailing') {
			$trailing	= '/';
			$leading	= '';
		} elseif ($where == 'leading') {
			$leading	= '/';
			$trailing	= '';
		} else {
			$leading	= '/';
			$trailing	= '/';
		}
		return $leading.$this->$which($n).$trailing;
	}

	public static function segment_array() {
		return self::instance()->segments;
	}

	public static function rsegment_array() {
		return self::instance()->rsegments;
	}

	public static function total_segments() {
		return count(self::instance()->segments);
	}

	public static function total_rsegments() {
		return count(self::instance()->rsegments);
	}

	public static function uri_string() {
		return self::instance()->uri_string;
	}

	public static function ruri_string() {
		return '/'.implode('/', self::instance()->rsegment_array()).'/';
	}

}
