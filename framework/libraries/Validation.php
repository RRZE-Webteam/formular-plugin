<?php if ( !defined('COREPATH') ) exit;

class Validation extends pattern\Singleton {

    private $field_data	= array();
	private $error_array = array();
	private $error_messages	= array();
	private $error_prefix	= '<p>';
	private $error_suffix	= '</p>';
	private $safe_form_data = false;

    public static $field_messages =
        array(
          'required' => "Das Feld wird benötigt.",
		  'not_empty' => "Das Feld muss einen nicht-leeren und von 0 verschiedenen Wert enthalten.",
          'isset' => "Das Feld muss einen Wert haben.",
          'valid_email' => "Das Feld muss eine gültige E-Mail-Adresse enthalten.",
          'valid_emails' => "Das Feld muss alle gültige E-Mail-Adressen enthalten.",
          'allowed_emails' => "Die angegebene E-Mail-Adresse ist keine erlaubte E-Mail-Adresse.",
          'valid_ip' => "Das Feld muss eine valide IP enthalten.",
          'min_length' => "Das Feld muss mindestens %s Zeichen enthalten.",
          'max_length' => "Das Feld darf maximal %s Zeichen enthalten.",
          'min_options' => "Das Feld muss mindestens %s ausgewählte Optionen enthalten.",
          'max_options' => "Das Feld darf maximal %s ausgewählte Optionen enthalten.",
          'exact_length'  => "Das Feld muss genau %s Zeichen enthalten.",
          'alpha' => "Das Feld darf nur Buchstaben enthalten.",
          'alpha_numeric'	=> "Das Feld darf nur Buchstaben und/oder Zahlen enthalten.",
          'alpha_space' => "Das Feld darf nur Buchstaben, Zahlen und/oder Leerzeichen enthalten.",
          'alpha_dash' => "Das Feld darf nur Buchstagen, Zahlen, Unter- und Bindestriche enthalten.",
          'numeric' => "Das Feld darf nur Zahlen enthalten.",
          'is_numeric' => "Das Feld darf nur numerische Zeichen enthalten.",
          'integer' => "Das Feld darf nur eine Ganzzahl enthalten.",
          'matches' => "Das Feld muss mit dem Feld %s übereinstimmen.",
          'is_natural' => "Das Feld darf nur positive Zahlen enthalten.",
          'is_natural_no_zero' => "Das Feld muss eine Zahl größer als 0 enthalten.",
          'hexcolor' => "Das Feld muss mit # beginnen und durch einen hexadezimalen Farbwert gefolgt.",
		  'valid_captcha' => "Die Antwort ist falsch."
        );

	protected static $instance;

	protected static function instance() {
		return self::get_instance( get_class() );
	}

	public static function set_rules($field, $rules = '') {
		if (count($_POST) == 0)	{
			return;
		}

		if (is_array($field))	{
			foreach ($field as $row) {
				if ( ! isset($row['field']) || ! isset($row['rules'])) {
					continue;
				}

				self::instance()->set_rules($row['field'], $row['rules']);
			}
			return;
		}

		if ( ! is_string($field) ||  ! is_string($rules) || $field == '')	{
			return;
		}

		if (strpos($field, '[') !== false && preg_match_all('/\[(.*?)\]/', $field, $matches)) {
			$x = explode('[', $field);
			$indexes[] = current($x);

			for ($i = 0; $i < count($matches['0']); $i++) {
				if ($matches['1'][$i] != '') {
					$indexes[] = $matches['1'][$i];
				}
			}

			$is_array = true;
		}	else{
			$indexes 	= array();
			$is_array	= false;
		}

		self::instance()->field_data[$field] = array(
            'field'	=> $field,
            'rules'	=> $rules,
            'is_array' => $is_array,
            'keys' => $indexes,
            'postdata' => null,
            'error'	=> '');
	}

	public static function set_message($val = '') {
		self::instance()->error_messages = array_merge(self::instance()->error_messages, $val);
	}

	public static function set_error_delimiters($prefix = '<p>', $suffix = '</p>') {
		self::instance()->error_prefix = $prefix;
		self::instance()->error_suffix = $suffix;
	}

	public static function error($field = '', $prefix = '', $suffix = '') {
		if ( ! isset(self::instance()->field_data[$field]['error']) || self::instance()->field_data[$field]['error'] == '') {
			return '';
		}

		if ($prefix == '') {
			$prefix = self::instance()->error_prefix;
		}

		if ($suffix == '') {
			$suffix = self::instance()->error_suffix;
		}

		return $prefix.self::instance()->field_data[$field]['error'].$suffix;
	}

	public static function error_string($prefix = '', $suffix = '') {
		if (count(self::instance()->error_array) === 0) {
			return '';
		}

		if ($prefix == '') {
			$prefix = self::instance()->error_prefix;
		}

		if ($suffix == '') {
			$suffix = self::instance()->error_suffix;
		}

		$str = '';
		foreach (self::instance()->error_array as $val) {
			if ($val != '') {
				$str .= $prefix.$val.$suffix."\n";
			}
		}

		return $str;
	}

	public static function run() {
		if (count($_POST) == 0) {
			return false;
		}

		if (count(self::instance()->field_data) == 0) {
            return false;
		}

		foreach (self::instance()->field_data as $field => $row) {
			if ($row['is_array'] == true) {
				self::instance()->field_data[$field]['postdata'] = self::instance()->reduce_array($_POST, $row['keys']);
			} else {
				if (isset($_POST[$field]) && $_POST[$field] != "") {
					self::instance()->field_data[$field]['postdata'] = $_POST[$field];
				}
			}

			self::instance()->execute($row, explode('|', $row['rules']), self::instance()->field_data[$field]['postdata']);
		}

		$total_errors = count(self::instance()->error_array);

		if ($total_errors > 0) {
			self::instance()->safe_form_data = true;
		}

		self::instance()->reset_post_array();

		if ($total_errors == 0) {
			return true;
		}

		return false;
	}

    private function reduce_array($array, $keys, $i = 0) {
        if (is_array($array)) {
            if (isset($keys[$i])) {
                if (isset($array[$keys[$i]])) {
                    $array = $this->reduce_array($array[$keys[$i]], $keys, ($i+1));
                }	else {
                    return null;
                }
            } else {
                return $array;
            }
        }

        return $array;
    }

 	private function reset_post_array() {
		foreach ($this->field_data as $field => $row) {
			if ( ! is_null($row['postdata'])) {
				if ($row['is_array'] == false) {
					if (isset($_POST[$row['field']])) {
						$_POST[$row['field']] = self::prep_for_form($row['postdata']);
					}
				} else {
					$post_ref =& $_POST;

					if (count($row['keys']) == 1) {
						$post_ref =& $post_ref[current($row['keys'])];
					} else {
						foreach ($row['keys'] as $val) {
							$post_ref =& $post_ref[$val];
						}
					}

					if (is_array($row['postdata'])) {
						$array = array();
						foreach ($row['postdata'] as $k => $v) {
							$array[$k] = self::prep_for_form($v);
						}

						$post_ref = $array;
					} else {
						$post_ref = self::prep_for_form($row['postdata']);
					}

				}

			}

		}

	}

	private function execute($row, $rules, $postdata = null, $cycles = 0) {
		if (is_array($postdata)) {
			foreach ($postdata as $key => $val) {
				$this->execute($row, $rules, $val, $cycles);
				$cycles++;
			}
			return;
		}

		if ( ! in_array('required', $rules) && is_null($postdata)) {
            return;
		}

		if (is_null($postdata)) {
			if (in_array('isset', $rules, true) || in_array('required', $rules)) {
				$type = (in_array('required', $rules)) ? 'required' : 'isset';

				if ( ! isset($this->error_messages[$type])) {
                    $line = isset(self::$field_messages[$type]) ? self::$field_messages[$type] : false;
					if ($line === false) {
						$line = 'Das Feld wurde nicht definiert.';
					}
				} else {
					$line = $this->error_messages[$type];
				}

				$message = $line;

				$this->field_data[$row['field']]['error'] = $message;

				if ( ! isset($this->error_array[$row['field']])) {
					$this->error_array[$row['field']] = $message;
				}
			}

			return;
		}

		foreach ($rules as $rule) {
			$_in_array = false;

			if ($row['is_array'] == true && is_array($this->field_data[$row['field']]['postdata'])) {
				if ( ! isset($this->field_data[$row['field']]['postdata'][$cycles])) {
					continue;
				}

				$postdata = $this->field_data[$row['field']]['postdata'][$cycles];
				$_in_array = true;
			} else {
				$postdata = $this->field_data[$row['field']]['postdata'];
			}

			$param = false;
			if (preg_match("/(.*?)\[(.*?)\]/", $rule, $match)) {
				$rule	= $match[1];
				$param	= $match[2];
			}

            if ( ! method_exists(__CLASS__, $rule)) {
				if (function_exists($rule)) {
					$result = $rule($postdata);

					if ($_in_array == true) {
						$this->field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
					} else {
						$this->field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
					}
				}

				continue;
			}

			if( !in_array($rule, array('min_options', 'max_options')))
				$result = self::$rule($postdata, $param);
			else
				$result = self::$rule($this->field_data[$row['field']]['postdata'], $param);

			if ($_in_array == true)
				$this->field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
			else
				$this->field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;

			if ($result === false) {
				if ( ! isset($this->error_messages[$rule])) {
                $line = isset(self::$field_messages[$rule]) ? self::$field_messages[$rule] : false;
					if ($line === false) {
						$line = 'Es wurde kein Fehlermeldung für dieser Feldname eingerichtet.';
					}
				} else {
					$line = $this->error_messages[$rule];
				}

				$message = sprintf($line, $param);

				$this->field_data[$row['field']]['error'] = $message;

				if ( ! isset($this->error_array[$row['field']])) {
					$this->error_array[$row['field']] = $message;
				}

				return;
			}
		}
	}

	public static function set_value($field = '', $default = '') {
		if ( ! isset(self::instance()->field_data[$field])) {
			return $default;
		}

		return self::instance()->field_data[$field]['postdata'];
	}

	public static function set_select($field = '', $value = '', $default = false) {
		if ( ! isset(self::instance()->field_data[$field]) || ! isset(self::instance()->field_data[$field]['postdata'])) {
			if ($default === true && count(self::instance()->field_data) === 0) {
				return ' selected="selected"';
			}
			return '';
		}

		$field = self::instance()->field_data[$field]['postdata'];

		if (is_array($field)) {
			if ( ! in_array($value, $field)) {
				return '';
			}
		}	else {
			if (($field == '' || $value == '') || ($field != $value)) {
				return '';
			}
		}

		return ' selected="selected"';
	}

    public static function set_radio($field = '', $value = '', $default = false) {
		if ( ! isset(self::instance()->field_data[$field]) || ! isset(self::instance()->field_data[$field]['postdata'])) {
			if ($default === true && count(self::instance()->field_data) === 0) {
				return ' checked="checked"';
			}
			return '';
		}

		$field = self::instance()->field_data[$field]['postdata'];

		if (is_array($field)) {
			if ( ! in_array($value, $field)) {
				return '';
			}
		} else {
			if (($field == '' || $value == '') || ($field != $value)) {
				return '';
			}
		}

		return ' checked="checked"';
	}

    public static function set_checkbox($field = '', $value = '', $default = false) {
		if ( ! isset(self::instance()->field_data[$field]) || ! isset(self::instance()->field_data[$field]['postdata'])) {
			if ($default === true && count(self::instance()->field_data) === 0) {
				return ' checked="checked"';
			}
			return '';
		}

		$field = self::instance()->field_data[$field]['postdata'];

		if (is_array($field)) {
			if ( ! in_array($value, $field)) {
				return '';
			}
		} else {
			if (($field == '' || $value == '') || ($field != $value)) {
				return '';
			}
		}

		return ' checked="checked"';
	}

    public static function required($str) {
		if ( ! is_array($str)) {
			return (trim($str) == '') ? false : true;
		} else {
			return ( ! empty($str));
		}
	}

    public static function not_empty($str) {
		return ( ! empty($str));
	}

 	public static function matches($str, $field) {
		if ( ! isset($_POST[$field])) {
			return false;
		}

		$field = $_POST[$field];

		return ($str !== $field) ? false : true;
	}

    public static function min_length($str, $val) {
		if (preg_match("/[^0-9]/", $val)) {
			return false;
		}

		return (mb_strlen($str) < $val) ? false : true;
	}

	public static function max_length($str, $val) {
		if (preg_match("/[^0-9]/", $val)) {
			return false;
		}

		return (mb_strlen($str) > $val) ? false : true;
	}

	public static function min_options($data, $val) {
		if ( !is_array($data) || preg_match("/[^0-9]/", $val)) {
			return false;
		} else {
			return (count($data) < $val) ? false : true;
		}

	}

	public static function max_options($data, $val) {
		if ( !is_array($data) || preg_match("/[^0-9]/", $val)) {
			return false;
		} else {
			return (count($data) > $val) ? false : true;
		}

	}

	public static function exact_length($str, $val) {
		if (preg_match("/[^0-9]/", $val)) {
			return false;
		}

		return (mb_strlen($str) != $val) ? false : true;
	}

	public static function valid_email($str) {
		return ( ! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $str)) ? false : true;
	}

	public static function valid_emails($str) {
		if (strpos($str, ',') === false) {
			return self::valid_email(trim($str));
		}

        foreach(explode(',', $str) as $email) {
			if (trim($email) != '' && self::valid_email(trim($email)) === false) {
				return false;
			}
		}

		return true;
	}

    public static function allowed_emails($str, $val) {
        $val = str_replace(' ', '', $val);
        if(empty($val))
            return false;
        
        $allowed = explode(',', $val);
        if ( in_array($str, $allowed))
            return true;
        
        if( self::valid_email(trim($str))) {
            $domain = array_pop(explode('@', $str));
            if ( in_array($domain, $allowed)) 
                return true;
        }
        
        return false;
    }
    
	public static function valid_ip($ip) {
		return Input::valid_ip($ip);
	}

	public static function alpha($str) {
		return ( ! preg_match("/^([a-z])+$/i", $str)) ? false : true;
	}

	public static function alpha_numeric($str) {
		return ( ! preg_match("/^([a-z0-9])+$/i", $str)) ? false : true;
	}

	public static function alpha_space($str) {
		return ( ! preg_match("/^([a-z0-9\s])+$/i", $str)) ? false : true;
	}

	public static function alpha_dash($str) {
		return ( ! preg_match("/^([a-z0-9_-])+$/i", $str)) ? false : true;
	}

	public static function numeric($str) {
		return (bool)preg_match( '/^[\-+]?[0-9]*\.?[0-9]+$/', $str);

	}

    public static function is_numeric($str) {
        return ( ! is_numeric($str)) ? false : true;
    }

	public static function integer($str) {
		return (bool)preg_match( '/^[\-+]?[0-9]+$/', $str);
	}

    public static function is_natural($str) {
        return (bool)preg_match( '/^[0-9]+$/', $str);
    }

    public static function is_natural_no_zero($str) {
        if ( ! preg_match( '/^[0-9]+$/', $str)) {
            return false;
        }

        if ($str == 0) {
            return false;
        }

        return true;
    }

    public static function hexcolor($str) {
        return (bool)preg_match('/(#([0-9A-Fa-f]{3,6})\b)/u', $str) > 0;
    }

	public static function valid_base64($str) {
		return (bool) ! preg_match('/[^a-zA-Z0-9\/\+=]/', $str);
	}

    public static function trim_spaces($str) {
        return preg_replace('/\s\s+/u', ' ', $str);
    }

	public static function valid_captcha($str) {
		if(Captcha::check_answer($str)){
			return true;
		} else{
			return false;
		}
	}
	
    public static function prep_for_form($data = '') {
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = self::instance()->prep_for_form($val);
            }

            return $data;
        }

        if (self::instance()->safe_form_data == false || $data === '') {
            return $data;
        }

        return str_replace(array("'", '"', '<', '>'), array("&#39;", "&quot;", '&lt;', '&gt;'), stripslashes($data));
    }

	public static function prep_url($str = '') {
		if ($str == 'http://' || $str == '') {
			return '';
		}

		if (substr($str, 0, 7) != 'http://' && substr($str, 0, 8) != 'https://') {
			$str = 'http://'.$str;
		}

		return $str;
	}

	public static function text_clean($str) {
		return Text::text_clean($str);
	}

    public static function xss_clean($str) {
		return Input::xss_clean($str);
	}

	public static function encode_php_tags($str) {
		return str_replace(array('<?php', '<?PHP', '<?', '?>'),  array('&lt;?php', '&lt;?PHP', '&lt;?', '?&gt;'), $str);
	}

}
