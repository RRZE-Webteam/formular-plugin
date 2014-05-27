<?php if ( !defined('COREPATH') ) exit;

class Debug {

	public static function show($var, $exit = false) {
        echo '<pre>';
        print_r($var);
        echo '</pre>';
        if($exit) exit();
	}

	public static function dump() {
		$output = "<div style='clear:both;background-color:#fff;padding:10px;'>";
		$output .= self::post_data();
		$output .= self::memory_usage();
		$output .= self::http_headers();
		$output .= self::config_data();
		$output .= '</div>';
		echo $output;
	}

    public static function backtrace() {
        list($debug) = debug_backtrace();
        $arguments = func_get_args();
        $total_arguments = count($arguments);

        echo '<fieldset style="background: #FEFEFE !important; border:2px red solid; padding:5px">';
        echo '<legend style="background:lightgrey; padding:5px;">'.$debug['file'].' @ line: '.$debug['line'].'</legend><pre>';
        $i = 0;
        foreach ($arguments as $argument) {
            echo '<br/><strong>Debug #'.(++$i).' of '.$total_arguments.'</strong>: ';
            var_dump($argument);
        }

        echo "</pre>";
        echo "</fieldset>";
    }

	private static function post_data() {
		$output  = "\n\n";
		$output .= '<fieldset style="border:1px solid #009900;padding:6px 10px 10px 10px;margin:20px 0 20px 0;background-color:#eee">';
		$output .= "\n";
		$output .= '<legend style="color:#009900;">&nbsp;&nbsp;POST Data&nbsp;&nbsp;</legend>';
		$output .= "\n";

		if (count($_POST) == 0) {
			$output .= '<div style="color:#009900;font-weight:normal;padding:4px 0 4px 0">No POST Data</div>';
		} else {
			$output .= "\n\n<table style='width:100%'>\n";

			foreach ($_POST as $key => $val) {
				if ( ! is_numeric($key)) {
					$key = "'".$key."'";
				}

				$output .= "<tr><td style='width:50%;padding:5px;color:#000;background-color:#ddd;'>&#36;_POST[".$key."]&nbsp;&nbsp; </td><td style='width:50%;padding:5px;color:#009900;font-weight:normal;background-color:#ddd;'>";
				if (is_array($val)) {
					$output .= "<pre>" . htmlspecialchars(stripslashes(print_r($val, TRUE))) . "</pre>";
				} else {
					$output .= htmlspecialchars(stripslashes($val));
				}
				$output .= "</td></tr>\n";
			}

			$output .= "</table>\n";
		}
		$output .= "</fieldset>";

		return $output;
	}

	private static function memory_usage() {
		$output  = "\n\n";
		$output .= '<fieldset style="border:1px solid #5a0099;padding:6px 10px 10px 10px;margin:20px 0 20px 0;background-color:#eee">';
		$output .= "\n";
		$output .= '<legend style="color:#5a0099;">&nbsp;&nbsp;Memory usage&nbsp;&nbsp;</legend>';
		$output .= "\n";

		if (function_exists('memory_get_usage') && ($usage = memory_get_usage()) != '') {
			$output .= "<div style='color:#5a0099;font-weight:normal;padding:4px 0 4px 0'>".number_format($usage).' bytes</div>';
		} else {
			$output .= "<div style='color:#5a0099;font-weight:normal;padding:4px 0 4px 0'>Memory usage not available</div>";
		}

		$output .= "</fieldset>";

		return $output;
	}

	private static function http_headers() {
		$output  = "\n\n";
		$output .= '<fieldset style="border:1px solid #000;padding:6px 10px 10px 10px;margin:20px 0 20px 0;background-color:#eee">';
		$output .= "\n";
		$output .= '<legend style="color:#000;">&nbsp;&nbsp;Headers&nbsp;&nbsp;</legend>';
		$output .= "\n";

		$output .= "\n\n<table style='width:100%'>\n";

		foreach (array('HTTP_ACCEPT', 'HTTP_USER_AGENT', 'HTTP_CONNECTION', 'SERVER_PORT', 'SERVER_NAME', 'REMOTE_ADDR', 'SERVER_SOFTWARE', 'HTTP_ACCEPT_LANGUAGE', 'SCRIPT_NAME', 'REQUEST_METHOD',' HTTP_HOST', 'REMOTE_HOST', 'CONTENT_TYPE', 'SERVER_PROTOCOL', 'PATH_INFO', 'QUERY_STRING', 'ORIG_PATH_INFO', 'HTTP_ACCEPT_ENCODING', 'HTTP_X_FORWARDED_FOR') as $header) {
			$val = (isset($_SERVER[$header])) ? $_SERVER[$header] : '';
			$output .= "<tr><td style='vertical-align: top;width:50%;padding:5px;color:#900;background-color:#ddd;'>".$header."&nbsp;&nbsp;</td><td style='width:50%;padding:5px;color:#000;background-color:#ddd;'>".$val."</td></tr>\n";
		}

		$output .= "</table>\n";
		$output .= "</fieldset>";

		return $output;
	}

	private static function config_data() {
		$output  = "\n\n";
		$output .= '<fieldset style="border:1px solid #000;padding:6px 10px 10px 10px;margin:20px 0 20px 0;background-color:#eee">';
		$output .= "\n";
		$output .= '<legend style="color:#000;">&nbsp;&nbsp;Configuration&nbsp;&nbsp;</legend>';
		$output .= "\n";

		$output .= "\n\n<table style='width:100%'>\n";

		foreach (Config::load('config') as $config=>$val) {
			if (is_array($val))
			{
				$val = print_r($val, TRUE);
			}

			$output .= "<tr><td style='padding:5px; vertical-align: top;color:#900;background-color:#ddd;'>".$config."&nbsp;&nbsp;</td><td style='padding:5px; color:#000;background-color:#ddd;'>".htmlspecialchars($val)."</td></tr>\n";
		}

		$output .= "</table>\n";
		$output .= "</fieldset>";

		return $output;
	}

}
