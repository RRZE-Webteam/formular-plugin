<?php

if (!defined('COREPATH'))
	exit;

class Input extends pattern\Singleton {

	public static $xss_hash = '';
	public static $ip_address = false;
	public static $user_agent = false;
	// never allowed, string replacement
	private static $never_allowed_str = array(
		'document.cookie' => '[removed]',
		'document.write' => '[removed]',
		'.parentNode' => '[removed]',
		'.innerHTML' => '[removed]',
		'window.location' => '[removed]',
		'-moz-binding' => '[removed]',
		'<!--' => '&lt;!--',
		'-->' => '--&gt;',
		'<![CDATA[' => '&lt;![CDATA[');
	// never allowed, regex replacement
	private static $never_allowed_regex = array(
		'javascript\s*:' => '[removed]',
		'expression\s*(\(|&\#40;)' => '[removed]', // CSS and IE
		'vbscript\s*:' => '[removed]', // IE
		'Redirect\s+302' => '[removed]');
	protected static $instance;

	protected static function instance() {
		return self::get_instance(get_class());
	}

	public static function set() {
		// register_globals = Off
		// magic_quotes_gpc = Off
		// Clean $_GET data
		$_GET = self::instance()->clean_input_data($_GET);

		// Clean $_POST Data
		$_POST = self::instance()->clean_input_data($_POST);

		// Clean $_COOKIE Data
		// Also get rid of specially treated cookies that might be set by a server
		// or silly application, that are of no use anyway
		// but that when present will trip our 'Disallowed Key Characters' alarm
		// http://www.ietf.org/rfc/rfc2109.txt
		// note that the key names below are single quoted strings, and are not PHP variables
		unset($_COOKIE['$Version']);
		unset($_COOKIE['$Path']);
		unset($_COOKIE['$Domain']);
		$_COOKIE = self::instance()->clean_input_data($_COOKIE);
	}

	// Escapes data and standardizes newline characters to \n
	private function clean_input_data($str) {
		if (is_array($str)) {
			$new_array = array();
			foreach ($str as $key => $val) {
				$new_array[self::instance()->clean_input_keys($key)] = self::instance()->clean_input_data($val);
			}
			return $new_array;
		}

		if (strpos($str, "\r") !== false) {
			$str = str_replace(array("\r\n", "\r"), "\n", $str);
		}

		return $str;
	}

	// To prevent malicious users
	// from trying to exploit keys we make sure that keys are
	// only named with alpha-numeric text and a few other items
	private function clean_input_keys($str) {
		if (!preg_match("/^[a-z0-9:_\/-]+$/i", $str)) {
			exit();
		}

		return $str;
	}

	// Retrieve values from global arrays
	private static function fetch_from_array(&$array, $index = '', $xss_clean = false) {
		if (!isset($array[$index])) {
			return false;
		}

		if ($xss_clean === true) {
			return self::xss_clean($array[$index]);
		}

		return $array[$index];
	}

	// Fetch an item from the GET array
	public static function get($index = '', $xss_clean = false) {
		return self::fetch_from_array($_GET, $index, $xss_clean);
	}

	// Fetch an item from the POST array
	public static function post($index = '', $xss_clean = false) {
		return self::fetch_from_array($_POST, $index, $xss_clean);
	}

	// Fetch an item from either the GET array or the POST
	public static function get_post($index = '', $xss_clean = false) {
		if (!isset($_POST[$index])) {
			return self::get($index, $xss_clean);
		} else {
			return self::post($index, $xss_clean);
		}
	}

	// Fetch an item from the COOKIE array
	public static function cookie($index = '', $xss_clean = false) {
		return self::fetch_from_array($_COOKIE, $index, $xss_clean);
	}

	// Fetch an item from the SERVER array
	public static function server($index = '', $xss_clean = false) {
		return self::fetch_from_array($_SERVER, $index, $xss_clean);
	}

	// Validate IP Address
	public static function valid_ip($ip) {
		$ip_segments = explode('.', $ip);

		// Always 4 segments needed
		if (count($ip_segments) != 4) {
			return false;
		}
		// IP can not start with 0
		if ($ip_segments[0][0] == '0') {
			return false;
		}
		// Check each segment
		foreach ($ip_segments as $segment) {
			// IP segments must be digits and can not be
			// longer than 3 digits or greater then 255
			if ($segment == '' || preg_match("/[^0-9]/", $segment) || $segment > 255 || strlen($segment) > 3) {
				return false;
			}
		}

		return true;
	}

	// User Agent
	public static function user_agent() {
		if (self::$user_agent !== false) {
			return self::$user_agent;
		}

		self::$user_agent = (!isset($_SERVER['HTTP_USER_AGENT'])) ? false : $_SERVER['HTTP_USER_AGENT'];

		return self::$user_agent;
	}

	// Check Cross Site Scripting Hacks
	public static function xss_clean($str, $is_image = false) {
		if (is_array($str)) {
			while (list($key) = each($str)) {
				$str[$key] = self::xss_clean($str[$key]);
			}

			return $str;
		}

		// Remove Invisible Characters
		$str = self::remove_invisible_characters($str);

		// Protect GET variables in URLs
		$str = preg_replace('|\&([a-z\_0-9]+)\=([a-z\_0-9]+)|i', self::xss_hash() . "\\1=\\2", $str);

		// Add a semicolon if missing.  We do this to enable
		// the conversion of entities to ASCII later.
		$str = preg_replace('#(&\#?[0-9a-z]{2,})([\x00-\x20])*;?#i', "\\1;\\2", $str);

		// Validate UTF16 two byte encoding (x00)
		// adds a semicolon if missing.
		$str = preg_replace('#(&\#x?)([0-9A-F]+);?#i', "\\1\\2;", $str);

		// Un-Protect GET variables in URLs
		$str = str_replace(self::xss_hash(), '&', $str);

		// URL Decode
		$str = rawurldecode($str);

		// Convert character entities to ASCII
		$str = preg_replace_callback("/[a-z]+=([\'\"]).*?\\1/si", array(__CLASS__, 'convert_attribute'), $str);
		$str = preg_replace_callback('/<\w+.*/si', array(__CLASS__, 'decode_entity'), $str);

		// Remove invisible characters again
		$str = self::remove_invisible_characters($str);

		// Convert all tabs to spaces
		// This prevents strings like this: ja	vascript
		if (strpos($str, "\t") !== false) {
			$str = str_replace("\t", ' ', $str);
		}

		// Capture converted string for later comparison
		$converted_string = $str;

		// Not allowed under any conditions
		foreach (self::$never_allowed_str as $key => $val) {
			$str = str_replace($key, $val, $str);
		}

		foreach (self::$never_allowed_regex as $key => $val) {
			$str = preg_replace("#" . $key . "#i", $val, $str);
		}

		// Makes PHP tags safe
		// XML tags are inadvertently replaced too: <?xml
		// But it doesn't seem to pose a problem.
		if ($is_image === true) {
			// Images have a tendency to have the PHP short opening
			// and closing tags every so often so we skip those
			// and only do the long opening tags.
			$str = preg_replace('/<\?(php)/i', "&lt;?\\1", $str);
		} else {
			$str = str_replace(array('<?', '?' . '>'), array('&lt;?', '?&gt;'), $str);
		}

		// Compact any exploded words
		// This corrects words like:  j a v a s c r i p t
		// These words are compacted back to their correct state.
		$words = array('javascript', 'expression', 'vbscript', 'script', 'applet', 'alert', 'document', 'write', 'cookie', 'window');
		foreach ($words as $word) {
			$temp = '';

			for ($i = 0, $wordlen = strlen($word); $i < $wordlen; $i++) {
				$temp .= substr($word, $i, 1) . "\s*";
			}

			// We only want to do this when it is followed by a non-word character
			// That way valid stuff like "dealer to" does not become "dealerto"
			$str = preg_replace_callback('#(' . substr($temp, 0, -3) . ')(\W)#is', array(__CLASS__, 'compact_exploded_words'), $str);
		}

		// Remove disallowed Javascript in links or img tags
		// We used to do some version comparisons and use of stripos for PHP5, but it is dog slow compared
		// to these simplified non-capturing preg_match(), especially if the pattern exists in the string
		do {
			$original = $str;

			if (preg_match("/<a/i", $str)) {
				$str = preg_replace_callback("#<a\s+([^>]*?)(>|$)#si", array(__CLASS__, 'js_link_removal'), $str);
			}

			if (preg_match("/<img/i", $str)) {
				$str = preg_replace_callback("#<img\s+([^>]*?)(\s?/?>|$)#si", array(__CLASS__, 'js_img_removal'), $str);
			}

			if (preg_match("/script/i", $str) || preg_match("/xss/i", $str)) {
				$str = preg_replace("#<(/*)(script|xss)(.*?)\>#si", '[removed]', $str);
			}
		} while ($original != $str);

		unset($original);

		// Remove JavaScript Event Handlers
		$event_handlers = array('[^a-z_\-]on\w*', 'xmlns');

		if ($is_image === true) {
			// Adobe Photoshop puts XML metadata into JFIF images, including namespacing,
			// so we have to allow this for images
			unset($event_handlers[array_search('xmlns', $event_handlers)]);
		}

		$str = preg_replace("#<([^><]+?)(" . implode('|', $event_handlers) . ")(\s*=\s*[^><]*)([><]*)#i", "<\\1\\4", $str);

		// Sanitize HTML elements
		$naughty = 'alert|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|isindex|layer|link|meta|object|plaintext|style|script|textarea|title|video|xml|xss';
		$str = preg_replace_callback('#<(/*\s*)(' . $naughty . ')([^><]*)([><]*)#is', array(__CLASS__, 'sanitize_naughty_html'), $str);

		// Sanitize scripting elements
		$str = preg_replace('#(alert|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si', "\\1\\2&#40;\\3&#41;", $str);

		// Final clean up
		foreach (self::$never_allowed_str as $key => $val) {
			$str = str_replace($key, $val, $str);
		}

		foreach (self::$never_allowed_regex as $key => $val) {
			$str = preg_replace("#" . $key . "#i", $val, $str);
		}

		if ($is_image === true) {
			if ($str == $converted_string) {
				return true;
			} else {
				return false;
			}
		}

		return $str;
	}

	// Random Hash for protecting URLs
	private static function xss_hash() {
		if (self::$xss_hash == '') {
			mt_srand();
			self::$xss_hash = md5(time() + mt_rand(0, 1999999999));
		}

		return self::$xss_hash;
	}

	// Prevents sandwiching null characters
	// between ascii characters, like Java\0script.
	private static function remove_invisible_characters($str) {
		// every control character except newline (dec 10), carriage return (dec 13), and horizontal tab (dec 09),
		$non_displayables = array(
			'/%0[0-8bcef]/', // url encoded 00-08, 11, 12, 14, 15
			'/%1[0-9a-f]/', // url encoded 16-31
			'/[\x00-\x08]/', // 00-08
			'/\x0b/', '/\x0c/', // 11, 12
			'/[\x0e-\x1f]/' // 14-31
		);

		do {
			$cleaned = $str;
			$str = preg_replace($non_displayables, '', $str);
		} while ($cleaned != $str);

		return $str;
	}

	// Compact Exploded Words
	// Callback function for xss_clean() to remove whitespace from
	// things like j a v a s c r i p t
	public static function compact_exploded_words($matches) {
		return preg_replace('/\s+/s', '', $matches[1]) . $matches[2];
	}

	// Sanitize Naughty HTML
	// Callback function for xss_clean() to remove naughty HTML elements
	public static function sanitize_naughty_html($matches) {
		// encode opening brace
		$str = '&lt;' . $matches[1] . $matches[2] . $matches[3];

		// encode captured opening or closing brace to prevent recursive vectors
		$str .= str_replace(array('>', '<'), array('&gt;', '&lt;'), $matches[4]);

		return $str;
	}

	// Callback function for xss_clean() to sanitize links
	// This limits the PCRE backtracks, making it more performance friendly
	// and prevents PREG_BACKTRACK_LIMIT_ERR|| from being triggered in
	// PHP 5.2+ on link-heavy strings
	public static function js_link_removal($match) {
		$attributes = self::instance()->filter_attributes(str_replace(array('<', '>'), '', $match[1]));
		return str_replace($match[1], preg_replace("#href=.*?(alert\(|alert&\#40;|javascript\:|charset\=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si", "", $attributes), $match[0]);
	}

	// Callback function for xss_clean() to sanitize image tags
	// This limits the PCRE backtracks, making it more performance friendly
	// and prevents PREG_BACKTRACK_LIMIT_ERR|| from being triggered in
	// PHP 5.2+ on image tag heavy strings
	public static function js_img_removal($match) {
		$attributes = self::instance()->filter_attributes(str_replace(array('<', '>'), '', $match[1]));
		return str_replace($match[1], preg_replace("#src=.*?(alert\(|alert&\#40;|javascript\:|charset\=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si", "", $attributes), $match[0]);
	}

	// Used as a callback for XSS Clean
	public static function convert_attribute($match) {
		return str_replace(array('>', '<', '\\'), array('&gt;', '&lt;', '\\\\'), $match[0]);
	}

	public static function decode_entity($match) {
		// Protect GET variables in URLs
		// 901119URL5918AMP18930PROTECT8198
		$match = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', self::xss_hash() . '\\1=\\2', $match[0]);

		// Decode, then un-protect URL GET vars
		return str_replace(self::xss_hash(), '&', self::entity_decode($match, Config::get('charset')));
	}

	private static function entity_decode($str, $charset = NULL) {
		if (strpos($str, '&') === FALSE) {
			return $str;
		}

		static $_entities;

		isset($charset) OR $charset = Config::get('charset');
		$flag = is_php('5.4') ? ENT_COMPAT | ENT_HTML5 : ENT_COMPAT;

		do {
			$str_compare = $str;

			// Decode standard entities, avoiding false positives
			if (preg_match_all('/&[a-z]{2,}(?![a-z;])/i', $str, $matches)) {
				if (!isset($_entities)) {
					$_entities = array_map(
							'strtolower', is_php('5.3.4') ? get_html_translation_table(HTML_ENTITIES, $flag, $charset) : get_html_translation_table(HTML_ENTITIES, $flag)
					);

					// If we're not on PHP 5.4+, add the possibly dangerous HTML 5
					// entities to the array manually
					if ($flag === ENT_COMPAT) {
						$_entities[':'] = '&colon;';
						$_entities['('] = '&lpar;';
						$_entities[')'] = '&rpar';
						$_entities["\n"] = '&newline;';
						$_entities["\t"] = '&tab;';
					}
				}

				$replace = array();
				$matches = array_unique(array_map('strtolower', $matches[0]));
				for ($i = 0, $c = count($matches); $i < $c; $i++) {
					if (($char = array_search($matches[$i] . ';', $_entities, TRUE)) !== FALSE) {
						$replace[$matches[$i]] = $char;
					}
				}

				$str = str_ireplace(array_keys($replace), array_values($replace), $str);
			}

			// Decode numeric & UTF16 two byte entities
			$str = html_entity_decode(
					preg_replace('/(&#(?:x0*[0-9a-f]{2,5}(?![0-9a-f;])|(?:0*\d{2,4}(?![0-9;]))))/iS', '$1;', $str), $flag, $charset
			);
		} while ($str_compare !== $str);
		return $str;
	}

	// Filters tag attributes for consistency and safety
	private function filter_attributes($str) {
		$out = '';

		if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches)) {
			foreach ($matches[0] as $match) {
				$out .= preg_replace("#/\*.*?\*/#s", '', $match);
			}
		}

		return $out;
	}

}
