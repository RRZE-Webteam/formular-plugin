<?php if ( !defined('COREPATH') ) exit;

define('CAPTCHA_NUMERIC_TEXT_RANGE_LOW', 0);
define('CAPTCHA_NUMERIC_TEXT_RANGE_HIGH', 20);

define('CAPTCHA_MAX_QUESTION_NUMBER_SIZE', 10);

define('CAPTCHA_NUM_ADDITION_PHRASES', 5);
define('CAPTCHA_NUM_SUBTRACTION_PHRASES', 5);

Class Captcha {

	private static $operation = array('addition', 'subtraction');
	
	public static function get_question() {
		$operation = self::$operation[array_rand(self::$operation)];

		if ($operation != 'division') {
			$number1 = rand(1, CAPTCHA_MAX_QUESTION_NUMBER_SIZE);
			$number2 = rand(1, CAPTCHA_MAX_QUESTION_NUMBER_SIZE);
		} else {
			$number1 = rand(1, CAPTCHA_MAX_QUESTION_NUMBER_SIZE);
			$dividers = array();

			for ($i = 1; $i <= CAPTCHA_MAX_QUESTION_NUMBER_SIZE; $i++) {
				if ($number1 % $i == 0) {
					$dividers[] = $i;
				}
			}

			$random_key = array_rand($dividers);
			$number2 = $dividers[$random_key];
		}

		switch ($operation) {
			case 'addition' :
				$answer = $number1 + $number2;
				$phrase = 'captcha_addition_2_' . rand(1, CAPTCHA_NUM_ADDITION_PHRASES);
				break;

			case 'subtraction' :
				$answer = ($number1 > $number2) ? $number1 - $number2 : $number2 - $number1;
				$phrase = 'captcha_subtraction_2_' . rand(1, CAPTCHA_NUM_SUBTRACTION_PHRASES);
				break;

			default :
				return false;
				break;
		}

		Session::set_flashdata('captcha_answer', $answer);

		if (($operation == 'subtraction') && ($number1 > $number2)) {
			return self::compile_question($phrase, array($number2, $number1));
		} else {
			return self::compile_question($phrase, array($number1, $number2));
		}
	}

	private static function compile_question($phrase, $numbers = array()) {
		if (rand(1, 2) == 1) {
			$numbers[0] = self::numeric_to_string($numbers[0]);
		}

		if (rand(1, 2) == 1) {
			$numbers[1] = self::numeric_to_string($numbers[1]);
		}

		$question_phrase = self::captcha_text($phrase);

		$question_phrase = str_replace('%1', $numbers[0], $question_phrase);
		$question_phrase = str_replace('%U1', ucfirst($numbers[0]), $question_phrase);
		$question_phrase = str_replace('%2', $numbers[1], $question_phrase);
		$question_phrase = str_replace('%U2', ucfirst($numbers[1]), $question_phrase);
		
		return $question_phrase;
	}

	public static function check_answer($answer) {
		$captcha_answer = Session::flashdata('captcha_answer');

		if ($captcha_answer !== false) {
			if ($answer === (string) $captcha_answer || strcasecmp($answer, self::numeric_to_string($captcha_answer)) == 0) {
				return true;
			} else {
				return false;
			}

		} else {
			return false;
		}
	}

	private static function numeric_to_string($number) {
		if (is_numeric($number) && $number >= CAPTCHA_NUMERIC_TEXT_RANGE_LOW && $number <= CAPTCHA_NUMERIC_TEXT_RANGE_HIGH) {
			return self::captcha_text('captcha_numeric_word_' . $number);
		} else {
			return false;
		}
	}

	private static function captcha_text($key) {
		$text['captcha_numeric_word_0'] = 'null';
		$text['captcha_numeric_word_1'] = 'eins';
		$text['captcha_numeric_word_2'] = 'zwei';
		$text['captcha_numeric_word_3'] = 'drei';
		$text['captcha_numeric_word_4'] = 'vier';
		$text['captcha_numeric_word_5'] = 'fünf';
		$text['captcha_numeric_word_6'] = 'sechs';
		$text['captcha_numeric_word_7'] = 'sieben';
		$text['captcha_numeric_word_8'] = 'acht';
		$text['captcha_numeric_word_9'] = 'neun';
		$text['captcha_numeric_word_10'] = 'zehn';
		$text['captcha_numeric_word_11'] = 'elf';
		$text['captcha_numeric_word_12'] = 'zwölf';
		$text['captcha_numeric_word_13'] = 'dreizehen';
		$text['captcha_numeric_word_14'] = 'vierzehn';
		$text['captcha_numeric_word_15'] = 'fünfzehen';
		$text['captcha_numeric_word_16'] = 'sechszehn';
		$text['captcha_numeric_word_17'] = 'siebzehn';
		$text['captcha_numeric_word_18'] = 'achtzehn';
		$text['captcha_numeric_word_19'] = 'neunzehn';
		$text['captcha_numeric_word_20'] = 'zwanzig';

		$text['captcha_addition_2_1'] = 'Was ergibt %1 plus %2?';
		$text['captcha_addition_2_2'] = 'Was ergibt die Summe aus %1 und %2?';
		$text['captcha_addition_2_3'] = 'Was erhält man, wenn man %1 und %2 zusammenzählt?';
		$text['captcha_addition_2_4'] = '%U1 und %2 macht?';
		$text['captcha_addition_2_5'] = 'Wenn man %1 und %2 zusammenzählt erhält man?';

		$text['captcha_subtraction_2_1'] = 'Was ergibt %2 minus %1?';
		$text['captcha_subtraction_2_2'] = 'Wenn man %1 von %2 wegnimmt erhält man?';
		$text['captcha_subtraction_2_3'] = 'Was erhält man, wenn man %1 von %2 abzieht?';
		$text['captcha_subtraction_2_4'] = '%U2 minus %1 macht?';
		$text['captcha_subtraction_2_5'] = 'Wenn man %1 von %2 abzieht erhält man?';
		
		return $text[$key];
	}
}
