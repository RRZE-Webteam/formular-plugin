<?php if ( !defined('COREPATH') ) exit;

class Config {
	// Get default config key => option
    public static function get($key) {
        global $config;
        return $config[$key];
    }

	// Set config key => option
    public static function set($key, $value) {
        global $config;
		$config[$key] = $value;
    }

	// Load config file
	public static function load($filename = '') {
		$config = array();
		include(sprintf('%sconfig/%s.php', COREPATH, $filename));
		return $config;
	}

	// Load options from a tab separated config file
    public static function load_options($file = '') {
        $error = false;
		$options = array();
        $fh = fopen($file, 'r');
        
		if(empty($fh)) {
			return false;
		}
		
        while(!feof($fh)) {
            $line = fgets($fh);
            $line = trim($line);
            if((strlen($line) == 0) || (substr($line, 0, 1) == '#')) {
                continue; // ignore comments and empty rows
            }
            $arr_opts = preg_split('/\t/', $line); // tab separated
			if(count($arr_opts) == 2) {
				$options[] = $arr_opts;
			} else {
				$error = true;
			}

        }

        fclose($fh);
		
		if(empty($options) || $error) {
			return false;
		}
		
        return $options;
    }

}