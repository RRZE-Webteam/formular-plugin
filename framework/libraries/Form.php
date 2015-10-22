<?php if ( !defined('COREPATH') ) exit;

class Form {

	public static function form_error($field = '', $prefix = '', $suffix = '') {
		return Validation::error($field, $prefix, $suffix);
	}

	public static function form_open($action = '', $attributes = '', $hidden = array()) {
		if ($attributes == '') {
			$attributes = 'method="post"';
		}

		$action = (strpos($action, '://') === false) ? Url::site_url($action) : $action;

		$form = '<form action="'.$action.'"';

		$form .= self::attributes_to_string($attributes, true);

		$form .= '>';

		if (is_array($hidden) && count($hidden) > 0)
		{
			$form .= self::form_hidden($hidden);
		}

		return $form;
	}

	public static function form_open_multipart($action, $attributes = array(), $hidden = array()) {
		$attributes['enctype'] = 'multipart/form-data';
		return self::form_open($action, $attributes, $hidden);
	}

	public static function form_hidden($name, $value = '', $recursing = false) {
		static $form;

		if ($recursing === false) {
			$form = "\n";
		}

		if (is_array($name)) {
			foreach ($name as $key => $val) {
				self::form_hidden($key, $val, true);
			}
			return $form;
		}

		if ( ! is_array($value)) {
			$form .= '<input type="hidden" name="'.$name.'" value="'.self::form_prep($value, $name).'" >'."\n";
		} else {
			foreach ($value as $k => $v) {
				$k = (is_int($k)) ? '' : $k;
				self::form_hidden($name.'['.$k.']', $v, true);
			}
		}

		return $form;
	}

	public static function form_input($data = '', $value = '', $extra = '') {
		$defaults = array('type' => 'text', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);

		return "<input ".self::parse_form_attributes($data, $defaults).$extra." >";
	}

	public static function form_password($data = '', $value = '', $extra = '') {
		if ( ! is_array($data)) {
			$data = array('name' => $data);
		}

		$data['type'] = 'password';
		return self::form_input($data, $value, $extra);
	}

	public static function form_upload($data = '', $value = '', $extra = '') {
		if ( ! is_array($data)) {
			$data = array('name' => $data);
		}

		$data['type'] = 'file';
		return self::form_input($data, $value, $extra);
	}

	public static function form_textarea($data = '', $value = '', $extra = '') {
		$defaults = array('name' => (( ! is_array($data)) ? $data : ''));

		if ( ! is_array($data) || ! isset($data['value'])) {
			$val = $value;
		} else {
			$val = $data['value'];
			unset($data['value']);
		}

		$name = (is_array($data)) ? $data['name'] : $data;
		return "<textarea ".self::parse_form_attributes($data, $defaults).$extra.">".self::form_prep($val, $name)."</textarea>";
	}

	public static function form_multiselect($name = '', $options = array(), $selected = array(), $extra = '') {
		if ( ! strpos($extra, 'multiple'))
		{
			$extra .= ' multiple="multiple"';
		}

		return self::form_dropdown($name, $options, $selected, $extra);
	}

	public static function form_dropdown($name = '', $options = array(), $selected = array(), $extra = '') {
		if ( ! is_array($selected)) {
			$selected = array($selected);
		}

		if (count($selected) === 0) {
			if (isset($_POST[$name])) {
				$selected = array($_POST[$name]);
			}
		}

		if ($extra != '') $extra = ' '.$extra;

		$multiple = (count($selected) > 1 && strpos($extra, 'multiple') === false) ? ' multiple="multiple"' : '';

		$form = '<select name="'.$name.'"'.$extra.$multiple.">\n";

		foreach ($options as $key => $val) {
			$key = (string) $key;

			if (is_array($val)) {
				$form .= '<optgroup label="'.$key.'">'."\n";

				foreach ($val as $optgroup_key => $optgroup_val) {
					$sel = (in_array($optgroup_key, $selected)) ? ' selected="selected"' : '';

					$form .= '<option value="'.$optgroup_key.'"'.$sel.'>'.(string) $optgroup_val."</option>\n";
				}

				$form .= '</optgroup>'."\n";
			} else {
				$sel = (in_array($key, $selected)) ? ' selected="selected"' : '';

				$form .= '<option value="'.$key.'"'.$sel.'>'.(string) $val."</option>\n";
			}
		}

		$form .= '</select>';

		return $form;
	}

	public static function form_checkbox($data = '', $value = '', $checked = false, $extra = '') {
		$defaults = array('type' => 'checkbox', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);

		if (is_array($data) && array_key_exists('checked', $data)) {
			$checked = $data['checked'];

			if ($checked == false) {
				unset($data['checked']);
			} else {
				$data['checked'] = 'checked';
			}
		}

		if ($checked == true) {
			$defaults['checked'] = 'checked';
		} else {
			unset($defaults['checked']);
		}

		return "<input ".self::parse_form_attributes($data, $defaults).$extra." >";
	}

	public static function form_radio($data = '', $value = '', $checked = false, $extra = '') {
		if ( ! is_array($data)) {
			$data = array('name' => $data);
		}

		$data['type'] = 'radio';
		return self::form_checkbox($data, $value, $checked, $extra);
	}

	public static function form_captcha($data = '', $value = '', $captcha = '', $extra = '') {
		$defaults = array('type' => 'captcha', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);		

		return "<span>".$captcha."</span><br><input ".self::parse_form_attributes($data, $defaults).$extra." >";
	}
	
	public static function form_submit($data = '', $value = '', $extra = '') {
		$defaults = array('type' => 'submit', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);

		return "<input ".self::parse_form_attributes($data, $defaults).$extra." >";
	}

	public static function form_reset($data = '', $value = '', $extra = '') {
		$defaults = array('type' => 'reset', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);

		return "<input ".self::parse_form_attributes($data, $defaults).$extra." >";
	}

	public static function form_button($data = '', $content = '', $extra = '') {
		$defaults = array('name' => (( ! is_array($data)) ? $data : ''), 'type' => 'button');

		if ( is_array($data) && isset($data['content'])) {
			$content = $data['content'];
			unset($data['content']);
		}

		return "<button ".self::parse_form_attributes($data, $defaults).$extra.">".$content."</button>";
	}

	public static function form_label($label_text = '', $id = '', $attributes = array()) {

		$label = '<label';

		if ($id != '') {
			 $label .= " for=\"$id\"";
		}

		if (is_array($attributes) && count($attributes) > 0) {
			foreach ($attributes as $key => $val) {
				$label .= ' '.$key.'="'.$val.'"';
			}
		}

		$label .= ">$label_text</label>";

		return $label;
	}

	public static function form_fieldset($legend_text = '', $attributes = array()) {
		$fieldset = "<fieldset";

		$fieldset .= self::attributes_to_string($attributes, false);

		$fieldset .= ">\n";

		if ($legend_text != '') {
			$fieldset .= "<legend>$legend_text</legend>\n";
		}

		return $fieldset;
	}

	public static function form_fieldset_close($extra = '') {
		return "</fieldset>".$extra;
	}

	public static function form_close($extra = '') {
		return "</form>".$extra;
	}

	public static function form_prep($str = '', $field_name = '') {
		static $prepped_fields = array();

		if (is_array($str)) {
			foreach ($str as $key => $val) {
				$str[$key] = self::form_prep($val);
			}

			return $str;
		}

		if ($str === '') {
			return '';
		}

		if (isset($prepped_fields[$field_name])) {
			return $str;
		}

		$str = htmlspecialchars($str);

		$str = str_replace(array("'", '"'), array("&#39;", "&quot;"), $str);

		if ($field_name != '') {
			$prepped_fields[$field_name] = $str;
		}

		return $str;
	}

	private static function parse_form_attributes($attributes, $default) {
		if (is_array($attributes)) {
			foreach ($default as $key => $val) {
				if (isset($attributes[$key])) {
					$default[$key] = $attributes[$key];
					unset($attributes[$key]);
				}
			}

			if (count($attributes) > 0) {
				$default = array_merge($default, $attributes);
			}
		}

		$att = '';

		foreach ($default as $key => $val) {
			if ($key == 'value') {
				$val = self::form_prep($val, $default['name']);
			}

			$att .= $key . '="' . $val . '" ';
		}

		return $att;
	}

  private static function attributes_to_string($attributes, $formtag = false) {
		if (is_string($attributes) && strlen($attributes) > 0) {
			if ($formtag == true && strpos($attributes, 'method=') === false) {
				$attributes .= ' method="post"';
			}

			if ($formtag == true && strpos($attributes, 'accept-charset=') === false) {
				$attributes .= ' accept-charset="'.strtolower(Config::get('charset')).'"';
			}

		return ' '.$attributes;
		}

		if (is_object($attributes) && count($attributes) > 0) {
			$attributes = (array)$attributes;
		}

		if (is_array($attributes) && count($attributes) > 0) {
			$atts = '';

			if ( ! isset($attributes['method']) && $formtag === true) {
				$atts .= ' method="post"';
			}

			if ( ! isset($attributes['accept-charset']) && $formtag === true) {
				$atts .= ' accept-charset="'.strtolower(Config::get('charset')).'"';
			}

			foreach ($attributes as $key => $val) {
				$atts .= ' '.$key.'="'.$val.'"';
			}

			return $atts;
		}
	}


}
