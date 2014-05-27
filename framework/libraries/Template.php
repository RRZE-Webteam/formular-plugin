<?php if ( !defined('COREPATH') ) exit;

class Template extends pattern\Singleton {

	private $data = array();
	private $ignore = array();
	private $template;
    private $conditionals;

	public static $l_delim = '{';
    public static $r_delim = '}';
	public static $options = array('convert_delimiters' => array( true, '&#123;', '&#125'));

	protected static $instance;

	protected static function instance() {
		return self::get_instance( get_class() );
	}

    public static function parse($template = '', $data = array(), $file = false, $strip_vars = false) {

		self::instance()->template =& $template;
		self::instance()->data =& $data;
		self::instance()->ignore = array();

        if($file) {
			ob_start();
			include self::instance()->template;
			self::instance()->template = ob_get_contents();
			@ob_end_clean();
		}

		if (self::instance()->template == '') {
			return false;
		}

		self::instance()->store_ignored('ignore_pre');

		foreach (self::instance()->data as $key => $val) {
			if (is_array($val)) self::instance()->template = self::instance()->parse_pair($key, $val, self::instance()->template);
		}

		foreach (self::instance()->data as $key => $val) {
			if (is_array($val) == false) self::instance()->template = self::instance()->parse_single($key, $val, self::instance()->template);
		}

		foreach (self::instance()->data as $key => $val) {
			if (is_array($val)) self::instance()->template = self::instance()->parse_array_elems($key, $val, self::instance()->template);
		}

		self::instance()->conditionals = self::instance()->find_nested_conditionals(self::instance()->template);

		if(self::instance()->conditionals) {
			self::instance()->template = self::instance()->parse_conditionals(self::instance()->template);
		}

		self::instance()->store_ignored('ignore');

		if ($strip_vars) {
			if (preg_match_all("(".self::$l_delim."([^".self::$r_delim."/]*)".self::$r_delim.")", self::instance()->template, $m)) {
				foreach($m[1] as $value) {
					self::instance()->template = preg_replace('#'.self::$l_delim.$value.self::$r_delim.'(.+)'.self::$l_delim.'/'.$value.self::$r_delim.'#sU', "", self::instance()->template);
					self::instance()->template = str_replace ("{".$value."}", "", self::instance()->template);
				}
			}
		}

		if(!empty(self::instance()->ignore)) {
			self::instance()->restore_ignored();
		}

		return self::instance()->template;
	}

	private function restore_ignored() {
		foreach($this->ignore as $key => $item) {
			$this->template = str_replace($item['id'], $item['txt'], $this->template);
		}

		return true;
	}

	private function store_ignored($name) {
		if (false === ($matches = $this->match_pair($this->template, $name))) {
			return false;
		}

		foreach( $matches as $key => $tagpair) {

			$this->ignore[$name.$key] = array(
				'txt' => $tagpair[1],
				'id'  => '__'.$name.$key.'__'
			);

			$this->template = str_replace($tagpair[0], $this->ignore[$name.$key]['id'], $this->template);
		}

		return true;
	}

	private function parse_array_elems($name, $arr, $template) {
		foreach($arr as $arrkey => $arrval) {
			if(!is_array($arrval)) {
				$template = $this->parse_single("$name $arrkey", $arrval, $template);
			}
		}
		return $template;
	}

	private function find_nested_conditionals($template) {
		$f = strpos($template, '{if');
		if ($f === false) {
			return false;
		}

		$found_ifs = array();
		$found_open = strpos($template, '{if');
		while ( $found_open !== false) {
			$found_ifs[] = $found_open;
			$found_open = strpos($template, '{if', $found_open+3);
		}
		// Debug::show($conditionals);

		for($key = 0; $key < sizeof($found_ifs); ++$key) {
			$open_tag = $found_ifs[$key];
			$found_close = strpos($template, '{/if}', $open_tag);
			if($found_close === false){
				echo("\n Fehler. Kein passendes /if gefunden: $open_tag");
				exit();
			}
			$new_open  = $open_tag;
			$new_close = $found_close;

			$i = 0;
			$found_blocks = array();
			do {
				$chunk = substr($template, $new_open+3, $new_close - $new_open - 3);
				$found_open = strpos($chunk, '{if');

				if($found_open !== false) {
					$new_close = $new_close+5;
					$new_close = strpos($template, '{/if}', $new_close);
					if($new_close === false) {
						echo("\n Fehler. Kein passendes /if gefunden: $found_open");
						exit();
					}
					$new_open = $new_open + $found_open + 3;
					$found_blocks[] = $new_open;
				}
				$i++;
			}
			while( $found_open !== false && ($i < 100) );

			$length = $new_close - $open_tag + 5;
			$chunk = substr($template, $open_tag, $length);
			$conditionals[$open_tag]=array (
				'start'    => $open_tag,
				'stop'     => $open_tag + $length,
				'raw_code' => $chunk,
				'found_blocks' => $found_blocks
			);
		}

		$regexp = '#{if (.*)}(.*){/if}#sU';
		foreach($conditionals as $key => $conditional) {
			$found_blocks = $conditional['found_blocks'];
			$conditional['parse'] = $conditional['raw_code'];
			if(!empty($found_blocks)) {
				foreach($found_blocks as $num) {
					$unique = "__pparse{$num}__";
					$conditional['parse'] = str_replace($conditionals[$num]['raw_code'], $unique, $conditional['parse']);
				}
			}
			$conditionals[$key]['parse'] = $conditional['parse'];

			if(preg_match($regexp, $conditional['parse'], $preg_parts, PREG_OFFSET_CAPTURE)) {
				$raw_code = $preg_parts[0][0];
				$cond_str = $preg_parts[1][0] !=='' ? $preg_parts[1][0] : '';
				$insert   = $preg_parts[2][0] !=='' ? $preg_parts[2][0] : '';

				if($raw_code !== $conditional['parse']){ echo "\n Fehler. Code unterscheidet sich vom ersten Lauf!\n$raw_code\n{$conditional['raw_code']}";exit; }

				if(preg_match('/({|})/', $cond_str, $problematic_conditional)) {
					echo "\n Fehler. Trennzeichen in bedingte Anweisung gefunden\n: $cond_str";
					exit;
				}

				$conditionals[$key]['cond_str'] = $cond_str;
				$conditionals[$key]['insert']   = $insert;
			} else {
				echo "\n Fehler: Keine bzw. nicht korrekt geschlossen bedingte Anweisung gefunden";
				exit();
				// todo
				$conditionals[$key]['cond_str'] = '';
				$conditionals[$key]['insert']   = '';
			}
		}
		return $conditionals;
	}

	private function parse_conditionals($template) {
		if(empty ($this->conditionals)) {
			return $template;
		}

		$conditionals =& $this->conditionals;

		foreach($conditionals as $key => $conditional) {
			$raw_code = $conditional['raw_code'];
			$cond_str = $conditional['cond_str'];
			$insert   = $conditional['insert'];

			if($cond_str !== '' AND !empty($insert)) {
				$cond = preg_split("/(\!=|==|<=|>=|<>|<|>|AND|XOR|OR)/", $cond_str);

				if(count($cond) == 2) {

					preg_match("/(\!=|==|<=|>=|<>|<|>|AND|XOR|OR)/", $cond_str, $cond_m);
					array_push($cond, $cond_m[0]);

					$cond[0] = preg_replace("/[^a-zA-Z0-9_\s\.,-]/", '', trim($cond[0]));
					$cond[1] = preg_replace("/[^a-zA-Z0-9_\s\.,-]/", '', trim($cond[1]));

					if(is_int($cond[0]) && is_int($cond[1])) {
						$delim = "";
					} else {
						$delim ="'";
					}

					$to_eval = "\$result = ($delim$cond[0]$delim $cond[2] $delim$cond[1]$delim);";
					eval($to_eval);
				} else {
					$result = (isset($this->data[trim($cond_str)]) OR (intval($cond_str) AND (bool)$cond_str));
				}
			}
			else
			{
				$result = false;
			}

			$insert = explode('{else}', $insert, 2);

			if($result == TRUE) {
				$conditionals[$key]['insert'] = $insert[0];
			} else {
				$conditionals[$key]['insert'] = (isset($insert['1'])?$insert['1']:'');
			}

			foreach($conditional['found_blocks'] as $num) {
				$unique = "__pparse{$num}__";
				if(strpos($conditional['insert'], $unique))
				{
					$conditionals[$key]['insert'] = str_replace($unique, $conditionals[$num]['raw_code'], $conditionals[$key]['insert']);
				}
			}
		}

		foreach($conditionals as $conditional) $template = str_replace($conditional['raw_code'], $conditional['insert'], $template);

		return $template;
	}

	private function parse_single($key, $val, $string) {
		if(is_bool($val)) $val = intval($val); // boolean numbers
		$convert =& self::$options['convert_delimiters'];

		if($convert[0]) $val = str_replace(array(self::$l_delim, self::$r_delim), array($convert[1],$convert[2]), $val);
		return str_replace(self::$l_delim.$key.self::$r_delim, $val, $string);
	}

	private function parse_pair($variable, $data, $string) {
		if (false === ($matches = $this->match_pair($string, $variable))) {
			return $string;
		}

		$singles=array();

		foreach ($matches as $m) {
			$str = '';
			foreach ($data as $rowkey=>$row) {
				$temp = $m['1'];

				if(is_array($row))
				{
					foreach ($row as $key => $val)
					{
						if ( ! is_array($val))
						{
							$temp = $this->parse_single($key, $val, $temp);
						}
						else
						{
							$temp = $this->parse_pair($key, array($key=>$val), $temp);
						}
					}
					$str .= $temp;
				}
				else
				{
					$singles[$rowkey]=$row;
				}
			}
			if($singles) {
				foreach($singles as $key => $value) {
					$str = $this->parse_single($key, $value, $str);
				}
			}
			$string = str_replace($m['0'], $str, $string);
		}
		return $string;
	}

	private function match_pair($string, $variable) {
		if ( ! preg_match_all("|".self::$l_delim . $variable . self::$r_delim."(.+)".self::$l_delim . '/' . $variable . self::$r_delim."|sU", $string, $match, PREG_SET_ORDER))
		{
			return false;
		}

		return $match;
	}


}
