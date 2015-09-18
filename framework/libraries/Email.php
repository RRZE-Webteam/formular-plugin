<?php if ( !defined('COREPATH') ) exit;

class Email extends pattern\Singleton {

	private $receiver;
	private $sender;
	private $reply_to;
	private $subject;
	private $body;
	private $cc;
	private $bcc;
	private $headers;

	protected static $instance;

	protected static function instance() {
		return self::get_instance( get_class() );
	}

	public static function send() {

		if (empty(self::instance()->sender)) {
			self::instance()->sender(Config::get('email_noreply'));
		}

		// Set headers
		self::instance()->set_headers();

		if (mail(self::instance()->receiver, self::instance()->subject, self::instance()->body, self::instance()->headers)) {
			return true;
		} else {
			return false;
		}

	}

	public static function receiver($send_to) {
		foreach ($send_to as $name => $email) {
		if ( ! is_numeric($name)) {
			self::instance()->receiver = ucfirst($name) . ' <' . $email . '>';

		} else {
			self::instance()->receiver = $email;

		}

		self::instance()->receiver .= ', ';

		}

		self::instance()->receiver = preg_replace('/, $/', '', self::instance()->receiver);

	}

	public static function sender($sender) {
		if ( ! is_numeric(key($sender))) {
		self::instance()->sender = ucfirst(key($sender)) . ' <' . $sender[key($sender)] . '>';

		} else {
		self::instance()->sender = $sender[0];

		}

	}

	public static function reply_to($reply_to) {
		if ( ! is_numeric(key($reply_to))) {
		self::instance()->reply_to = ucfirst(key($reply_to)) . ' <' . $reply_to[key($reply_to)] . '>';

		} else {
		self::instance()->reply_to = $reply_to[0];
		}

	}

	public static function cc($cc) {
		foreach ($cc as $name => $email) {

		if ( !is_numeric($name) ) {
			self::instance()->cc = ucfirst($name) . ' <' . $email . '>';

		} else {
			self::instance()->cc = $email;

		}

		self::instance()->cc .= ', ';
		}

		self::instance()->cc = preg_replace('/, $/', '', self::instance()->to);

	}

	public static function bcc($bcc) {
		foreach ($bcc as $name => $email) {

		if ( !is_numeric($name) ) {
			self::instance()->bcc = ucfirst($name) . ' <' . $email . '>';

		} else {
			self::instance()->bcc = $email;

		}

		self::instance()->bcc .= ', ';

		}

		self::instance()->bcc = preg_replace('/, $/', '', self::instance()->to);

	}

	public static function subject($subject) {
		// Strip any newlines
		self::instance()->subject = str_replace('\n', '', $subject);

	}

	public static function body($body) {
		self::instance()->body = $body;

	}

	private function set_headers() {
		$this->headers =
		"MIME-Version: 1.0\n"
		. "From: " . $this->sender . "\n";

		if ( ! empty($this->reply_to)) {
			$this->headers .= "Reply-To: " . $this->reply_to . "\n";
		}
		if ( ! empty($this->cc)) {
			$this->headers .= "Cc: " . $this->cc . "\n";
		}
		if ( ! empty($this->bcc)) {
			$this->headers .= "Bcc: " . $this->bcc . "\n";
		}

		$this->headers .=
			"Priority: normal\n"
			. "X-Mailer: PHP Mail (" . phpversion() . ")\n"
			. "Content-type: text/plain; charset=" . Config::get('charset');

	}

}
