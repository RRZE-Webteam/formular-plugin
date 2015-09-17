<?php

if (!defined('COREPATH'))
    exit;

class File extends pattern\Singleton {

    const STATUS_WRITABLE = 1;
    const STATUS_READABLE = 2;
    const STATUS_OPEN_FAILED = 4;
    const STATUS_FILE_CLOSED = 8;

    private $file_status;
    private $file_path;
    private $file_handle;
    private static $default_permissions = 0777;
    
    private $max_size;
    private $max_filename;
    private $allowed_types;
    private $file_temp;
    private $file_name;
    private $orig_name;
    private $file_type;
    private $file_size;
    private $file_ext;
    private $upload_path;
    private $overwrite;
    private $encrypt_file_name;
    private $mimes;
    private $remove_spaces;
    private $file_name_override;
    private $error_msg;
    
    private static $messages = array(
        'file_exceeds_limit' => "Die Gr&ouml;sse der hochgeladenen Datei &uuml;berschreitet den in der System-Konfiguration angegebenen Maximalwert.",
        'file_exceeds_form_limit' => "Die Gr&ouml;sse der hochgeladenen Datei &uuml;berschreitet den im Formular festgelegten Maximalwert.",
        'file_partial' => "Die Datei wurde nicht vollst&auml;ndig hochgeladen.",
        'no_temp_directory' => "Der tempor&auml;re Ordner fehlt.",
        'unable_to_write_file' => "Die Datei konnte nicht auf die Festplatte geschrieben werden.",
        'stopped_by_extension' => "Die Datentyperweiterung verusachte den Abbruch des Ladens.",
        'no_file_selected' => "Sie haben keine Datei zum Hochladen ausgew&auml;hlt.",
        'invalid_filetype' => "Dateien dieses Dateityps k&ouml;nnen nicht hochgeladen werden.",
        'invalid_filesize' => "Die Gr&ouml;sse der hochgeladenen Datei &uuml;berschreitet den zul&auml;ssigen Maximalwert.",
        'destination_error' => "Die hochgeladene Datei konnte nicht an den definierten Zielort verschoben werden.",
        'bad_filename' => "Eine Datei gleichen Namens ist bereits vorhanden.",
        'no_filepath' => "Der Pfad zum Speichern hochgeladener Dateien ist ung&uuml;ltig.",
        'no_file_types' => "Es wurden keine g&uuml;ltigen Dateitypen zum Hochladen von Dateien festgelegt."
    );
    
    protected static $instance;

    protected static function instance() {
        return self::get_instance(get_class());
    }

    public static function set($file_dir, $filename, $create = false) {
        if (!file_exists($file_dir) && $create) {
            mkdir($file_dir, self::$default_permissions, true);
        }

        self::instance()->file_path = sprintf('%s%s', $file_dir, $filename);

        if (file_exists(self::instance()->file_path) &&
                !is_writable(self::instance()->file_path) && !is_readable(self::instance()->file_path)) {
            self::instance()->file_status = self::STATUS_OPEN_FAILED;
            return;
        }

        if ((self::instance()->file_handle = fopen(self::instance()->file_path, 'a'))) {
            self::instance()->file_status = self::STATUS_WRITABLE;
        } elseif ((self::instance()->file_handle = fopen(self::instance()->file_path, 'r'))) {
            self::instance()->file_status = self::STATUS_READABLE;
        } else {
            self::instance()->file_status = self::STATUS_OPEN_FAILED;
        }
    }

    public function __destruct() {
        if (!empty(self::instance()->file_handle)) {
            fclose(self::instance()->file_handle);
        }
    }

    public static function write($data) {
        if (self::instance()->file_status == self::STATUS_WRITABLE) {
            file_put_contents(self::instance()->file_handle, $data, FILE_APPEND | LOCK_EX);
        }
    }

    public static function write_to_csv($data, $delimiter = ';', $enclosure = '"') {
        if (self::instance()->file_status == self::STATUS_WRITABLE) {
            fputcsv(self::instance()->file_handle, $data, $delimiter, $enclosure);
        }
    }

    public static function read() {
        if (self::instance()->file_status == self::STATUS_WRITABLE || self::instance()->file_status == self::STATUS_READABLE) {
            $data = file_get_contents(self::instance()->file_handle);
            $encoding = mb_detect_encoding($data, 'auto', true);
            if ($encoding != Config::get('charset'))
                $data = mb_convert_encoding($sData, Config::get('charset'), $encoding);

            return $data;
        }
    }

    private static function set_error($msg) {
        if (isset(self::$messages[$msg])) {
            self::instance()->error_msg[] = self::$messages[$msg];
        }
    }

    public static function get_errors($open = '<p>', $close = '</p>') {
        $str = '';
        foreach (self::instance()->error_msg as $val) {
            $str .= $open . $val . $close;
        }

        return $str;
    }

    public static function upload($upload_path = null, $field = 'userfile', $test = false) {
        
        self::set_max_filename(255);
        self::instance()->encrypt_file_name = false;
        self::instance()->file_name_override = '';
        self::instance()->remove_spaces = true;
        self::instance()->overwrite = false;

        if (!isset($_FILES[$field])) {
            self::instance()->set_error('no_file_selected');
            return false;
        }

        if (empty($upload_path)) {
            self::instance()->set_error('no_filepath');
            return false;
        }

        self::instance()->upload_path = $upload_path;

        if (!file_exists(self::instance()->upload_path)) {
            if (!mkdir(self::instance()->upload_path, self::$default_permissions, true)) {
                self::instance()->set_error('no_filepath');
                return false;
            }
        }


        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            $error = (!isset($_FILES[$field]['error'])) ? 4 : $_FILES[$field]['error'];

            switch ($error) {
                case 1:
                    self::instance()->set_error('file_exceeds_limit');
                    break;
                case 2:
                    self::instance()->set_error('file_exceeds_form_limit');
                    break;
                case 3:
                    self::instance()->set_error('file_partial');
                    break;
                case 4:
                    self::instance()->set_error('no_file_selected');
                    break;
                case 6:
                    self::instance()->set_error('no_temp_directory');
                    break;
                case 7:
                    self::instance()->set_error('unable_to_write_file');
                    break;
                case 8:
                    self::instance()->set_error('stopped_by_extension');
                    break;
                default : self::instance()->set_error('no_file_selected');
                    break;
            }

            return;
        }

        self::instance()->file_temp = $_FILES[$field]['tmp_name'];
        self::instance()->file_size = $_FILES[$field]['size'];
        self::instance()->file_mime_type($_FILES[$field]);
        self::instance()->file_type = preg_replace("/^(.+?);.*$/", "\\1", self::instance()->file_type);
        self::instance()->file_type = strtolower(trim(stripslashes(self::instance()->file_type), '"'));
        self::instance()->file_name = self::instance()->prep_filename($_FILES[$field]['name']);
        self::instance()->file_ext = self::instance()->get_extension(self::instance()->file_name);

        if (!self::instance()->is_allowed_filetype()) {
            self::instance()->set_error('invalid_filetype');
            return false;
        }

        if (!empty(self::instance()->file_name_override)) {
            self::instance()->file_name = self::instance()->prep_filename(self::instance()->file_name_override);

            if (strpos(self::instance()->file_name_override, '.') === false) {
                self::instance()->file_name .= self::instance()->file_ext;
            } else {
                self::instance()->file_ext = self::instance()->get_extension(self::instance()->file_name_override);
            }

            if (!self::instance()->is_allowed_filetype(true)) {
                self::instance()->set_error('invalid_filetype');
                return false;
            }
        }

        // Convert the file size to kilobytes
        if (self::instance()->file_size > 0) {
            self::instance()->file_size = round(self::instance()->file_size / 1024, 2);
        }

        // Is the file size within the allowed maximum?
        if (!self::instance()->is_allowed_filesize()) {
            self::instance()->set_error('invalid_filesize');
            return false;
        }

        // Sanitize the file name
        self::instance()->file_name = self::instance()->clean_file_name(self::instance()->file_name);

        // Truncate the file name
        if (self::instance()->max_filename > 0) {
            self::instance()->file_name = self::instance()->limit_filename_length(self::instance()->file_name, self::instance()->max_filename);
        }

        // Remove white spaces in the name
        if (self::instance()->remove_spaces == true) {
            self::instance()->file_name = preg_replace("/\s+/", "_", self::instance()->file_name);
        }

        // Overwrite filename?
        self::instance()->orig_name = self::instance()->file_name;

        if (!self::instance()->overwrite) {
            self::instance()->file_name = self::instance()->set_filename(self::instance()->upload_path, self::instance()->file_name);

            if (self::instance()->file_name === false) {
                return false;
            }
        }

		if($test)
			return true;
		
        if (!@copy(self::instance()->file_temp, self::instance()->upload_path . self::instance()->file_name)) {
            if (!@move_uploaded_file(self::instance()->file_temp, self::instance()->upload_path . self::instance()->file_name)) {
                self::instance()->set_error('destination_error');
                return false;
            }
        }

        return true;
    }

    public static function set_max_filename($n) {
        self::instance()->max_filename = ((int) $n < 0) ? 0 : (int) $n;
    }

    public static function set_max_filesize($n) {
        self::instance()->max_size = ((int) $n < 0) ? 0 : (int) $n;
    }

    public static function set_allowed_types($types) {
        self::instance()->allowed_types = $types;
    }

    private static function set_filename($path, $filename) {
        if (self::instance()->encrypt_file_name) {
            mt_srand();
            $filename = md5(uniqid(mt_rand())) . self::instance()->file_ext;
        }

        if (!file_exists($path . $filename)) {
            return $filename;
        }

        $filename = str_replace(self::instance()->file_ext, '', $filename);

        $new_filename = '';
        for ($i = 1; $i < 100; $i++) {
            if (!file_exists(sprintf('%s%s-%s%s', $path, $filename, $i, self::instance()->file_ext))) {
                $new_filename = sprintf('%s-%s%s', $filename, $i, self::instance()->file_ext);
                break;
            }
        }

        if ($new_filename == '') {
            self::instance()->set_error('upload_bad_filename');
            return false;
        } else {
            return $new_filename;
        }
    }

    public static function get_file_name() {
        return self::instance()->file_name;
    }
    
    private static function get_extension($filename) {
        $x = explode('.', $filename);
        return '.' . end($x);
    }

    private static function is_allowed_filetype($ignore_mime = false) {
        if (self::instance()->allowed_types == '*') {
            return true;
        }



        if (count(self::instance()->allowed_types) == 0 OR !is_array(self::instance()->allowed_types)) {
            self::instance()->set_error('upload_no_file_types');
            return false;
        }

        $ext = strtolower(ltrim(self::instance()->file_ext, '.'));



        if (!in_array($ext, self::instance()->allowed_types)) {
            return false;
        }

        // Additional checks for images
        $image_types = array('gif', 'jpg', 'jpeg', 'png', 'jpe');



        if (in_array($ext, $image_types)) {
            if (getimagesize(self::instance()->file_temp) === false) {
                return false;
            }
        }



        if ($ignore_mime === true) {
            return true;
        }

        $mime = self::instance()->mimes_types($ext);


        if (is_array($mime)) {
            if (in_array(self::instance()->file_type, $mime, true)) {
                return true;
            }
        } elseif ($mime == self::instance()->file_type) {
            return true;
        }



        return false;
    }

    private static function is_allowed_filesize() {
        if (self::instance()->max_size != 0 AND self::instance()->file_size > self::instance()->max_size) {
            return false;
        } else {
            return true;
        }
    }

    public function clean_file_name($filename) {
        $bad = array(
            "<!--",
            "-->",
            "'",
            "<",
            ">",
            '"',
            '&',
            '$',
            '=',
            ';',
            '?',
            '/',
            "%20",
            "%22",
            "%3c", // <
            "%253c", // <
            "%3e", // >
            "%0e", // >
            "%28", // (
            "%29", // )
            "%2528", // (
            "%26", // &
            "%24", // $
            "%3f", // ?
            "%3b", // ;
            "%3d"  // =
        );

        $filename = str_replace($bad, '', $filename);

        return strtolower(stripslashes($filename));
    }

    private static function limit_filename_length($filename, $length) {
        if (strlen($filename) < $length) {
            return $filename;
        }

        $ext = '';
        if (strpos($filename, '.') !== false) {
            $parts = explode('.', $filename);
            $ext = '.' . array_pop($parts);
            $filename = implode('.', $parts);
        }

        return substr($filename, 0, ($length - strlen($ext))) . $ext;
    }

    private static function mimes_types($mime) {
        self::instance()->mimes = Config::get('mimes');

        return (!isset(self::instance()->mimes[$mime])) ? false : self::instance()->mimes[$mime];
    }

    private static function prep_filename($filename) {
        if (strpos($filename, '.') === false OR self::instance()->allowed_types == '*') {
            return $filename;
        }

        $parts = explode('.', $filename);
        $ext = array_pop($parts);
        $filename = array_shift($parts);

        foreach ($parts as $part) {
            if (!in_array(strtolower($part), self::instance()->allowed_types) OR self::instance()->mimes_types(strtolower($part)) === false) {
                $filename .= '.' . $part . '_';
            } else {
                $filename .= '.' . $part;
            }
        }

        $filename .= '.' . $ext;

        return $filename;
    }

    private static function file_mime_type($file) {
		$regexp = '/^([a-z\-]+\/[a-z0-9\-\.\+]+)(;\s.+)?$/';

		if (function_exists('finfo_file')) {
			$finfo = finfo_open(FILEINFO_MIME);
			if (is_resource($finfo)) {
				$mime = @finfo_file($finfo, $file['tmp_name']);
				finfo_close($finfo);

				if (is_string($mime) && preg_match($regexp, $mime, $matches)) {
					self::instance()->file_type = $matches[1];
					return;
				}
			}
		}

		if (DIRECTORY_SEPARATOR !== '\\') {
			$cmd = 'file --brief --mime ' . escapeshellarg($file['tmp_name']) . ' 2>&1';

			if (function_exists('exec')) {
				$mime = @exec($cmd, $mime, $return_status);
				if ($return_status === 0 && is_string($mime) && preg_match($regexp, $mime, $matches)) {
					self::instance()->file_type = $matches[1];
					return;
				}
			}

			if ( (bool) @ini_get('safe_mode') === FALSE && function_exists('shell_exec')) {
				$mime = @shell_exec($cmd);
				if (strlen($mime) > 0) {
					$mime = explode("\n", trim($mime));
					if (preg_match($regexp, $mime[(count($mime) - 1)], $matches)) {
						self::instance()->file_type = $matches[1];
						return;
					}
				}
			}

			if (function_exists('popen')) {
				$proc = @popen($cmd, 'r');
				if (is_resource($proc)) {
					$mime = @fread($proc, 512);
					@pclose($proc);
					if ($mime !== FALSE) {
						$mime = explode("\n", trim($mime));
						if (preg_match($regexp, $mime[(count($mime) - 1)], $matches)) {
							self::instance()->file_type = $matches[1];
							return;
						}
					}
				}
			}
		}

		if (function_exists('mime_content_type')) {
			self::instance()->file_type = @mime_content_type($file['tmp_name']);
			if (strlen(self::instance()->file_type) > 0) {
				return;
			}
		}

		self::instance()->file_type = $file['type'];
	}

    public static function format_size($size) {
        $sizes = array('kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        if ($size == 0)
            return $size; 
        $i = floor(log($size, 1024));
        return sprintf('%s %s', round($size / pow(1024, $i), 2), $sizes[$i]); 

    }

	public static function is_really_writable($file) {
		// If we're on a Unix server with safe_mode off we call is_writable
		if (DIRECTORY_SEPARATOR === '/' && (version_compare(PHP_VERSION, '5.4', '>=') OR ! ini_get('safe_mode'))) {
			return is_writable($file);
		}

		/* For Windows servers and safe_mode "on" installations we'll actually
		 * write a file then read it. Bah...
		 */
		if (is_dir($file)) {
			$file = rtrim($file, '/') . '/' . md5(mt_rand());
			if (($fp = @fopen($file, 'ab')) === FALSE) {
				return FALSE;
			}

			fclose($fp);
			@chmod($file, 0777);
			@unlink($file);
			return TRUE;
		} elseif (!is_file($file) OR ( $fp = @fopen($file, 'ab')) === FALSE) {
			return FALSE;
		}

		fclose($fp);
		return TRUE;
	}

}
