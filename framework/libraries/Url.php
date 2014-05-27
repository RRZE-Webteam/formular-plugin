<?php if ( !defined('COREPATH') ) exit;

class Url {

	public static function site_url($uri = '') {
		if (is_array($uri))	{
			$uri = implode('/', $uri);
		}

		if ($uri == '')	{
			return Config::get('site_url');
		}	else {
			return Config::get('site_url').preg_replace("|^/*(.+?)/*$|", "\\1", $uri);
		}
	}

    public static function redirect($uri = '', $method = 'location', $http_response_code = 302) {
		if ( ! preg_match('#^https?://#i', $uri))	{
			$uri = self::site_url($uri);
		}

		switch($method) {
			case 'refresh': header("Refresh:0;url=".$uri);
				break;
			default: header("Location: ".$uri, TRUE, $http_response_code);
				break;
		}
		exit();
	}

	public static function anchor($uri = '', $title = '', $attributes = '') {
		$title = (string) $title;

		if ( ! is_array($uri)) {
			$site_url = ( ! preg_match('!^\w+://! i', $uri)) ? self::site_url($uri) : $uri;
		} else {
			$site_url = self::site_url($uri);
		}

		if ($title == '') {
			$title = $site_url;
		}

		if ($attributes != '') {
			$attributes = self::parse_attributes($attributes);
		}

		return '<a href="'.$site_url.'"'.$attributes.'>'.$title.'</a>';
	}

	private static function parse_attributes($attributes, $javascript = FALSE) {
		if (is_string($attributes)) {
			return ($attributes != '') ? ' '.$attributes : '';
		}

		$att = '';
		foreach ($attributes as $key => $val) {
			if ($javascript == TRUE) {
				$att .= $key . '=' . $val . ',';
			} else {
				$att .= ' ' . $key . '="' . $val . '"';
			}
		}

		if ($javascript == TRUE AND $att != '') {
			$att = substr($att, 0, -1);
		}

		return $att;
	}

}
