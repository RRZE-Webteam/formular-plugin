<?php if ( !defined('COREPATH') ) exit;

class Session extends pattern\Singleton {

	private static $session_id_ttl;
	private static $flash_key;
	private static $utc;

	protected static $instance;

	protected static function instance() {
		return self::get_instance( get_class() );
	}

    public static function set() {
        session_name(Config::get('session_name'));
        self::$session_id_ttl = Config::get('session_id_ttl');
        self::$flash_key = Config::get('flash_key');
        self::$utc = Config::get('utc');

        session_start();

        // check if session id needs regeneration
        if (self::instance()->session_id_expired()) {
          // regenerate session id (session data stays the
          // same, but old session storage is destroyed)
          self::instance()->regenerate_id();
        }

        // delete old flashdata (from last request)
        self::instance()->flashdata_sweep();

        // mark all new flashdata as old (data will be deleted before next request)
        self::instance()->flashdata_mark();
    }

	/**
	* Regenerates session id
	*/
	private function regenerate_id() {
		// copy old session data, including its id
		$old_session_id = session_id();
		$old_session_data = $_SESSION;

		// regenerate session id and store it
		session_regenerate_id();
		$new_session_id = session_id();

		// switch to the old session and destroy its storage
		session_id($old_session_id);
		$this->destroy();

		// switch back to the new session id and send the cookie
		session_id($new_session_id);
		session_start();

		// restore the old session data into the new session
		$_SESSION = $old_session_data;

		// update the session creation time
		$_SESSION['regenerated'] = self::$utc;

		// end the current session and store session data.
		session_write_close();
	}

	private function destroy() {
		unset($_SESSION);
		if (isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', self::$utc - 42000, '/');
		}
		session_destroy();
	}

	/**
	* Reads given session attribute value
	*/
	public static function userdata($item) {
		if($item == 'session_id') { //added for backward-compatibility
		return session_id();
		} else {
		return ( ! isset($_SESSION[$item])) ? false : $_SESSION[$item];
		}
	}

	/**
	* Sets session attributes to the given values
	*/
	public static function set_userdata($newdata = array(), $newval = '') {
		if (is_string($newdata)) {
		$newdata = array($newdata => $newval);
		}

		if (count($newdata) > 0) {
		foreach ($newdata as $key => $val) {
			$_SESSION[$key] = $val;
		}
		}
	}

	/**
	* Erases given session attributes
	*/
	public static function unset_userdata($newdata = array()) {
		if (is_string($newdata)) {
		$newdata = array($newdata => '');
		}

		if (count($newdata) > 0) {
		foreach ($newdata as $key => $val) {
			unset($_SESSION[$key]);
		}
		}
	}

	/**
	* Checks if session has expired
	*/
	private function session_id_expired() {
		if (!isset( $_SESSION['regenerated'])) {
		$_SESSION['regenerated'] = self::$utc;
		return false;
		}

		$expiry_time = self::$utc - self::$session_id_ttl;

		if ( $_SESSION['regenerated'] <=  $expiry_time ) {
		return true;
		}

		return false;
	}

	/**
	* Sets "flash" data which will be available only in next request (then it will
	* be deleted from session).
	*/
	public static function set_flashdata($key, $value) {
		$flash_key = self::$flash_key.':new:'.$key;
		self::instance()->set_userdata($flash_key, $value);
	}

	/**
	* Keeps existing "flash" data available to next request.
	*/
	public static function keep_flashdata($key) {
		$old_flash_key = self::$flash_key.':old:'.$key;
		$value = self::instance()->userdata($old_flash_key);

		$new_flash_key = self::$flash_key.':new:'.$key;
		self::instance()->set_userdata($new_flash_key, $value);
	}

	/**
	* Returns "flash" data for the given key.
	*/
	public static function flashdata($key) {
		$flash_key = self::$flash_key.':old:'.$key;
		return self::instance()->userdata($flash_key);
	}

	/**
	* Private method - marks "flash" session attributes as 'old'
	*/
	private function flashdata_mark() {
		foreach ($_SESSION as $name => $value) {
		$parts = explode(':new:', $name);
		if (is_array($parts) && count($parts) == 2) {
			$new_name = self::$flash_key.':old:'.$parts[1];
			self::set_userdata($new_name, $value);
			self::unset_userdata($name);
		}
		}
	}

	/**
	* Private method - removes "flash" session marked as 'old'
	*/
	private function flashdata_sweep() {
		foreach ($_SESSION as $name => $value) {
		$parts = explode(':old:', $name);
		if (is_array($parts) && count($parts) == 2 && $parts[0] == self::$flash_key) {
			self::unset_userdata($name);
		}
		}
	}

}
