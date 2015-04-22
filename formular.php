<?php

/**
  Plugin Name: Formular
  Plugin URI: http://www.vorlagen.uni-erlangen.de/vorlagen/hilfreiche-plugins/formular.shtml
  Description: Das Formular-Plugin vereinfacht die Erstellung von Formularen, dessen Absenden, Validierung und Weiterverarbeitung.
  Version: 1.15.0422
  Author: Rolf v.d. Forst, RRZE WebTeam
  Author Email: rolf.v.d.forst@fau.de
  Author URI: http://blogs.fau.de/webworking/
  Support URI: http://www.portal.uni-erlangen.de/forums/viewforum/93
 *
 */
// Load framework/setup
require_once('framework/setup.php');

// Define paths
define('APPPATH', realpath(pathinfo(__FILE__, PATHINFO_DIRNAME)) . '/');
define('UPLOADPATH', APPPATH . 'anlagen/');
define('ENTRIESPATH', APPPATH . 'eintraege/');

$plugin_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://';
$plugin_url .= sprintf('%s%s?download-file=', $_SERVER['HTTP_HOST'], $_SERVER['PHP_SELF']);

define('DOWNLOADURL', $plugin_url);

$str = <<<EOF
<div class="hinweis">
    <p>
        Erfolgreich abgesendet.<br />
        Vielen Dank, für Ihre Anfrage.
    </p>
</div>
EOF;
define('SUCCESS_VIEW', $str);

$str = <<<EOF
<div class="hinweis_wichtig">
    <p>
        Absendung fehlgeschlagen.<br />
        Bitte versuchen Sie es erneut.
    </p>
</div>
EOF;
define('ERROR_VIEW', $str);

$str = <<<EOF
<div class="hinweis_wichtig">
    <p>
        Sie haben dieses Formular bereits abgesendet.<br />
        Vielen Dank.
    </p>
</div>
EOF;
define('LOCK_VIEW', $str);

if (isset($_GET['download-file']) && !empty($_GET['download-file'])) {
    Formular::download_file(Input::get('download-file', true));
    exit();
}

if (isset($_POST['submit']) && !empty($_POST['submit']))
    Formular::init(Input::get('conf', true));
else
    echo Formular::init(Input::get('conf', true));

class Formular {

    private static $referer = '';
    
    private static $redirect = '';
    
    private static $conferror = '';

    public static function init($appconf = '') {
        self::$referer = self::clean_referer(Input::get('referer', true));
        if (!empty(self::$referer)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';
            self::$redirect = sprintf('%s%s%s', $protocol, $_SERVER['HTTP_HOST'], self::$referer);
    
        } else
            return sprintf('<div class="hinweis_wichtig"><p>%s</p></div>', 'Einbindungsfehler: Der Name der Referrer fehlt.');

        if (empty($appconf) || !preg_match("/^([a-z0-9_-])+$/i", $appconf))
            return sprintf('<div class="hinweis_wichtig"><p>%s</p></div>', 'Einbindungsfehler: Der Name der Konfigurationsdatei fehlt oder ist ungültig.');
        
        //default conf
        $default_config =
                array(
                    'form_views' => array(
                        'success' => SUCCESS_VIEW,
                        'error' => ERROR_VIEW,
                        'lock' => LOCK_VIEW
                    ),
                    'form_error' => array(
                        'prefix' => '',
                        'suffix' => ''
                    )
        );

        // load custom conf file and merge with default conf
        $config = self::loadconf($appconf);

        if (!$config)
            return sprintf('<div class="hinweis_wichtig"><p>%s</p></div>', self::$conferror);

        $config = array_merge($default_config, $config);

        $email_notification = (isset($config['email_notification']['active']) && $config['email_notification']['active'] == 0) ? false : true;
        $csv_entries = (isset($config['csv_entries']['active']) && $config['csv_entries']['active'] == 1) ? true : false;
        $cookie_lock = (isset($config['cookie_lock']['active']) && $config['cookie_lock']['active'] == 1) ? true : false;

        $views = $config['form_views'];
        $error = $config['form_error'];
        $form_submit = $config['form_submit'];
        $email_views = $config['email_views'];

        if ($cookie_lock && isset($_COOKIE[md5(sprintf('%s-lock', $appconf))])) {
            if (file_exists($tpl = sprintf('%s%s.html', APPPATH, $views['lock'])))
                return Template::parse($tpl, array(), true);
            else
                return Template::parse($default_config['form_views']['lock']);
        }

        $data = array();
        $upload = array();
        $fields = $config['form_field'];

        if (Session::flashdata(md5(sprintf('%s-postdata', $appconf)))) {
            $postdata = unserialize(Session::flashdata(md5(sprintf('%s-postdata', $appconf))));
            $_POST = array_merge($_POST, $postdata);
        }
        
        if (isset($config['rules_messages'])) {
            $fields_messages = Validation::$field_messages;
            Validation::$field_messages = array_replace($fields_messages, $config['rules_messages']);
        }

        foreach ($config['form_field'] as $field) {
            if (in_array($field['type'], array('radio', 'checkbox'))) {
                $fieldname = sprintf('%s[]', $field['name']);
            } else {
                $fieldname = $field['name'];
            }
            if ($field['type'] != 'upload')
                Validation::set_rules($fieldname, $field['rules']);
        }

        $validation = Validation::run();
        
        if (!empty($_POST['submit'])) {
                        
            foreach ($fields as $key => $field) {
                if (in_array($field['type'], array('radio', 'checkbox'))) {
                    $fieldname = sprintf('%s[]', $field['name']);
                    $postdata[sprintf('%s_error', $fieldname)] = Form::form_error($fieldname, $error['prefix'], $error['suffix']);
                    
                } elseif (in_array($field['type'], array('dropdown'))) {
                    $postdata[sprintf('%s_error', $field['name'])] = Form::form_error($field['name'], $error['prefix'], $error['suffix']);
    
                
                } elseif ($field['type'] == 'upload') {

                    if (mb_strstr($field['rules'], 'required') && empty($_FILES[$field['name']]['size'])) {
                        $postdata[$field['name'] . '_error'] = sprintf('%sSie haben keine Datei zum Hochladen ausgew&auml;hlt.%s', $error['prefix'], $error['suffix']);
                        $upload[] = false;
                        continue;
                    }
                    
                    if (!empty($_FILES[$field['name']]['size'])) {
                        File::set_allowed_types($config['file_allowed_types']);
                        File::set_max_filesize($config['file_max_size']);

                        if (!File::upload(UPLOADPATH, $field['name'], true)) {
                            $postdata[$field['name'] . '_error'] = File::get_errors($error['prefix'], $error['suffix']);
                            $upload[] = false;
                            continue;                           
                        }
                        
                        if ($validation) {
                            if(!File::upload(UPLOADPATH, $field['name'])) {
                                $postdata[$field['name'] . '_error'] = File::get_errors($error['prefix'], $error['suffix']);
                                $upload[] = false;                          
                            } else {
                                $postdata[$field['name'] . '_file_name'] = File::get_file_name();
                            }
                        }
                    }

                } else {
                    $postdata[sprintf('%s_error', $field['name'])] = Form::form_error($field['name'], $error['prefix'], $error['suffix']);
                }
                
            }           
            
            $postdata['upload'] = $upload;
            
            unset($_POST['submit']);
            $postdata = array_merge($postdata, $_POST);
            
            Session::set_flashdata(md5(sprintf('%s-postdata', $appconf)), serialize($postdata));
            Url::redirect(self::$redirect);
        }
        
        if ($validation && empty($postdata['upload'])) {
            $csv = array();
            $result = '';
            
            $csv['date'] = date('Y-m-d H:s:i');

            $email_receiver = $config['email_receiver'];
            $email_subject = $config['email_subject'];
            $email_sender = $config['email_sender'];

            $receiver = array($email_receiver['name'] => $email_receiver['email']);
            $subject = $email_subject['subject'];

            if (mb_strstr($email_sender['name'], '|')) {
                $name = array();
                $names = explode('|', $email_sender['name']);
                foreach ($names as $value) {
                    $name[] = Validation::set_value($value);
                }
                $name = implode(' ', $name);
                
            } else {
                $name = Validation::set_value($email_sender['name']);
            }

            $sender = array($name => Validation::set_value($email_sender['email']));

            $email_infos = array();

            $data['upload_path'] = UPLOADPATH;
            $data['download_url'] = DOWNLOADURL;

            foreach ($fields as $field) {

                if (in_array($field['type'], array('radio', 'checkbox'))) {
                    $field_values = explode(';', $field['value']);
                    $post_values = Validation::set_value(sprintf('%s[]', $field['name']));
                    $keys = array();
                    $values = array();
                    foreach ($post_values as $key) {
                        if (!isset($field_values[$key]))
                            continue;
                        $keys[] = $key;
                        $values[] = $field_values[$key];
                    }

                    $data[$field['name'] . '_keys'] = implode(',', $keys);
                    $data[$field['name'] . '_values'] = implode(', ', $values);
                    $data[$field['name']] = implode(', ', $values);
                    
                } elseif (in_array($field['type'], array('dropdown'))) {
                    $field_values = explode(';', $field['value']);
                    $post_value = Validation::set_value($field['name']);
                    $data[$field['name'] . '_keys'] = isset($field_values[$post_value]) ? $post_value : '';
                    $data[$field['name']] = isset($field_values[$post_value]) ? $field_values[$post_value] : '';
                    
                } elseif ($field['type'] == 'upload') {
                    $data[$field['name']] = isset($postdata[$field['name'] . '_file_name']) ? $postdata[$field['name'] . '_file_name'] : '';
                    
                } else {
                    if ($field['type'] == 'input' && !empty($field['emailinfo'])) {
                        $email_infos[Validation::set_value($field['name'], '')] = $field['emailinfo'];
                    }
                    $data[$field['name'] . '_keys'] = 0;
                    $data[$field['name'] . '_values'] = Validation::set_value($field['name'], '');
                    $data[$field['name']] = Validation::set_value($field['name'], '');
                }

                $csv[$field['name']] = Text::remove_line_break($data[$field['name']]);
            }

            if (!$email_notification) {
                if ($csv_entries) {
                    File::set(ENTRIESPATH, sprintf('%s.csv', $appconf), true);
                    File::write_to_csv($csv);
                }
                
                $result = 'success';
                
                if ($cookie_lock)
                    setcookie(md5(sprintf('%s-lock', $appconf)), true, Config::get('utc') + 31536000, '/');
            
            } else {
            
                if (isset($email_views['receiver']) && file_exists($tpl = sprintf('%s%s.html', APPPATH, $email_views['receiver']))) {
                    $body = Template::parse($tpl, $data, true);
                } else {
                    $body = '';
                    foreach ($csv as $key => $value) {
                        $body .= sprintf("%s: %s \n", $key, $value);
                    }
                }

                Email::sender($sender);
                Email::receiver($receiver);
                Email::subject($subject);
                Email::body($body);

                if (Email::send()) {

                    if ($csv_entries) {
                        File::set(ENTRIESPATH, sprintf('%s.csv', $appconf), true);
                        File::write_to_csv($csv);
                    }

                    if (isset($email_views['sender']) && file_exists($tpl = sprintf('%s%s.html', APPPATH, $email_views['sender']))) {
                        $body = Template::parse($tpl, $data, true);
                        Email::sender($receiver);
                        Email::receiver($sender);
                        Email::subject($subject);
                        Email::body($body);
                        Email::send();
                    }

                    foreach ($email_infos as $email => $view) {
                        if (!empty($email) && file_exists($tpl = sprintf('%s%s.html', APPPATH, $view))) {
                            $receiver = array($email => $email);
                            $body = Template::parse($tpl, $data, true);
                            Email::sender($sender);
                            Email::receiver($receiver);
                            Email::subject($subject);
                            Email::body($body);
                            Email::send();
                        }
                    }

                    $result = 'success';
                    
                    if ($cookie_lock)
                        setcookie(md5(sprintf('%s-lock', $appconf)), true, Config::get('utc') + 31536000, '/');

                } else {
                    $result = 'error';
                }
            
            }
            
            if (isset($views[$result]) && file_exists($tpl = sprintf('%s%s.html', APPPATH, $views[$result])))
                return Template::parse($tpl, array(), true);

            elseif (isset($default_config['form_views'][$result]))
                return Template::parse($default_config['form_views'][$result]);

            else
                return Template::parse($default_config['form_views']['error']);

        }

        $data['form_open'] = call_user_func(array('Form', 'form_open'), sprintf('%s?referer=%s&conf=%s', $_SERVER['SCRIPT_NAME'], self::$referer, $appconf));
        $data['form_open_multipart'] = call_user_func(array('Form', 'form_open_multipart'), sprintf('%s?referer=%s&conf=%s', $_SERVER['SCRIPT_NAME'], self::$referer, $appconf));
        $data['form_close'] = call_user_func(array('Form', 'form_close'));
        $data['form_submit'] = call_user_func(array('Form', 'form_submit'), $form_submit['name'], $form_submit['value'], $form_submit['attributes']);

        $data['file_allowed_types'] = isset($config['file_allowed_types']) ? ucwords(strtolower(implode('- ', $config['file_allowed_types']))) : '';
        $data['file_max_size'] = isset($config['file_max_size']) ? File::format_size($config['file_max_size']) : '';

        foreach ($fields as $field) {

            $arrkeys = array();
            $arrvalues = array();

            if (in_array($field['type'], array('radio', 'checkbox'))) {
                $fieldname = sprintf('%s[]', $field['name']);
                $data[sprintf('%s_error', $field['name'])] = isset($postdata[sprintf('%s_error', $fieldname)]) ? $postdata[sprintf('%s_error', $fieldname)] : '';

                $values = array();
                $values['name'] = $fieldname;

                $form_fields_arr = array();
                $param = explode(';', $field['value']);
                $param = self::trim_value($param);
                foreach ($param as $key => $val) {
                    $values['value'] = $key;
                    $values['checked'] = Validation::set_radio($fieldname, $key, Validation::set_value($fieldname) === $key ? true : false);
                    if ($values['checked']) {
                        $arrkeys[] = $key;
                        $arrvalues[] = $val;
                    }
                    $extra = sprintf('id="%s"', self::filter_value($val));
                    $form_field = call_user_func(array('Form', 'form_' . $field['type']), $values, '', false, $extra);
                    $form_fields_arr[] = sprintf('%s <label for="%s">%s</label>', $form_field, self::filter_value($val), $val);
                }
                $data[$field['name']] = implode('<br />', $form_fields_arr);
                
            } elseif (in_array($field['type'], array('dropdown'))) {
                $data[sprintf('%s_error', $field['name'])] = isset($postdata[sprintf('%s_error', $field['name'])]) ? $postdata[sprintf('%s_error', $field['name'])] : '';

                $arr_options = array();
                $values = array();
                $values['name'] = $field['name'];

                $options = explode(';', $field['value']);
                $options = self::trim_value($options);
                foreach ($options as $key => $option) {
                    $arr_options[$key] = $option;
                }
                $options = $arr_options;
                $values['options'] = $options;
                $values['value'] = Validation::set_value($field['name'], 0);
                $arrkeys[] = $values['value'];
                $arrvalues[] = $options[$values['value']];

                $data[$field['name']] = call_user_func(array('Form', 'form_' . $field['type']), $field['name'], $options, $values['value']);
                
            } elseif ($field['type'] == 'hidden') {
                $data[$field['name']] = call_user_func(array('Form', 'form_' . $field['type']), $field['name'], $field['value']);
                
            } elseif ($field['type'] == 'upload') {
                $data[sprintf('%s_error', $field['name'])] = isset($postdata[sprintf('%s_error', $field['name'])]) ? $postdata[sprintf('%s_error', $field['name'])] : '';
                $values = array();
                $values['name'] = $field['name'];

                $data[$field['name']] = call_user_func(array('Form', 'form_' . $field['type']), $values);
                
            } else {
                $data[sprintf('%s_error', $field['name'])] = isset($postdata[sprintf('%s_error', $field['name'])]) ? $postdata[sprintf('%s_error', $field['name'])] : '';
                
                $values = array();
                $values['name'] = $field['name'];

                $values['value'] = Validation::set_value($values['name'], $field['value']);
                $arrkeys[] = 0;
                $arrvalues[] = $values['value'];

                $data[$field['name']] = call_user_func(array('Form', 'form_' . $field['type']), $values);
            }

            if (!in_array($field['type'], array('hidden'))) {
                $data[sprintf('%s_keys', $field['name'])] = implode(',', $arrkeys);
                $data[sprintf('%s_values', $field['name'])] = implode(', ', $arrvalues);
            }
        }

        if (isset($views['form']) && file_exists($tpl = sprintf('%s%s.html', APPPATH, $views['form'])))
            return Template::parse($tpl, $data, true);
        else
            return Template::parse(ERROR_VIEW, $data);
    }

    private static function loadconf($appconf) {
		$doc_root  = preg_replace("!{$_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']);
		$vkdaten_file = sprintf('%1$s/vkdaten/%2$s.conf', $doc_root, $appconf);
		
		if(file_exists($vkdaten_file)) {
			$conf_file = $vkdaten_file;
		} else {
			$conf_file = sprintf('%1$s%2$s.conf', APPPATH, $appconf);
		}
		
        $config = Config::load_options($conf_file);
        if (!$config) {
            self::$conferror = sprintf('Die Konfigurationsdatei "%s.conf" konnte nicht geladen werden.', $appconf);
            return false;
        }

        $email_notification = true;
        
        $allowed_options = array('email_notification', 'csv_entries', 'cookie_lock', 'email_receiver', 'email_subject', 'email_sender', 'email_views', 'form_views', 'form_submit', 'form_error', 'rules_messages', 'form_field', 'file_allowed_types', 'file_max_size');
        
        $required_email_options = array('email_receiver', 'email_subject', 'email_sender', 'email_views');
        
        $required_form_options = array('form_views', 'form_submit');
        
        $required_file_options = array('file_allowed_types', 'file_max_size');

        $file_options = false;

        foreach ($config as $value) {
            if($value[0] == 'email_notification' && !trim($value[1]))
                $email_notification = false;
        }
        
        foreach ($config as $value) {
            $key = array_search($value[0], $allowed_options);
            if ($key === false)
                continue;

            if (is_null($value[1])) {
                self::$conferror = sprintf('%s.conf: Die Option "%s" muss einen gültigen Wert enthalten.', $appconf, $value[0]);
                return false;
            }
            $value[1] = Text::convert_to_utf8($value[1]);

            $key = array_search($value[0], $required_email_options);
            if ($key !== false)
                unset($required_email_options[$key]);

            $key = array_search($value[0], $required_file_options);
            if ($key !== false) {
                $file_options = true;
                unset($required_file_options[$key]);
            }

            if ($value[0] == 'email_notification') {
                $conf_arr[$value[0]] = array('active' => trim($value[1]));
                
            } elseif ($value[0] == 'csv_entries') {
                $conf_arr[$value[0]] = array('active' => trim($value[1]));
                
            } elseif ($value[0] == 'cookie_lock') {
                $conf_arr[$value[0]] = array('active' => trim($value[1]));
                
            } elseif ($value[0] == 'file_allowed_types') {
                $params = explode('|', $value[1]);
                if (count($params) < 1) {
                    self::$conferror = sprintf('%s.conf: Die Option "%s => %s" enthält nicht genügend Parameter.', $appconf, $value[0], $value[1]);
                    return false;
                }
                $params = self::trim_value($params);
                $conf_arr[$value[0]] = $params;
                
            } elseif ($value[0] == 'file_max_size') {
                $conf_arr[$value[0]] = (int) $value[1];
                
            } elseif ($value[0] == 'email_receiver' && $email_notification) {
                $params = explode('|', $value[1]);
                if (count($params) != 2) {
                    self::$conferror = sprintf('%s.conf: Die Option "%s => %s" enthält nicht genügend Parameter.', $appconf, $value[0], $value[1]);
                    return false;
                }
                $params = self::trim_value($params);
                if (mb_strstr($params[0], ';')) {
                    $param = explode(';', $params[0]);
                    $param = self::trim_value($param);
                    $params[0] = implode(' ', $param);
                }
                $conf_arr[$value[0]] = array('name' => $params[0], 'email' => $params[1]);
                
            } elseif ($value[0] == 'email_subject' && $email_notification) {
                $conf_arr[$value[0]] = array('subject' => trim($value[1]));
                
            } elseif ($value[0] == 'email_sender' && $email_notification) {
                $params = explode('|', $value[1]);
                if (count($params) != 2) {
                    self::$conferror = sprintf('%s.conf: Die Option "%s => %s" enthält nicht genügend Parameter.', $appconf, $value[0], $value[1]);
                    return false;
                }
                $params = self::trim_value($params);
                if (mb_strstr($params[0], ';')) {
                    $param = explode(';', $params[0]);
                    $param = self::trim_value($param);
                    $params[0] = implode('|', $param);
                }
                $conf_arr[$value[0]] = array('name' => $params[0], 'email' => $params[1]);
                
            } elseif ($value[0] == 'email_views' && $email_notification) {
                $params = explode('|', $value[1]);
                if (count($params) < 1) {
                    self::$conferror = sprintf('Die Option "%s => %s" enthält nicht genügend Parameter.', $appconf, $value[0], $value[1]);
                    return false;
                }
                $params[1] = isset($params[1]) ? $params[1] : '';

                $params = self::trim_value($params);
                if( !file_exists(sprintf('%s%s.html', APPPATH, $params[0]))) {
                    self::$conferror = sprintf('<b>%s</b>: Die Eintragdatei "%s.html" konnte nicht geladen werden.', $value[0], $params[0]);
                    return false;                    
                } elseif( !empty($params[1]) && !file_exists(sprintf('%s%s.html', APPPATH, $params[1]))) {
                    self::$conferror = sprintf('<b>%s</b>: Die Antwortdatei "%s.html" konnte nicht geladen werden.', $value[0], $params[1]);
                    return false;                
                }        
                $conf_arr[$value[0]] = array('receiver' => $params[0], 'sender' => $params[1]);
                
            } elseif ($value[0] == 'form_views') {
                $params = explode('|', $value[1]);
                if (count($params) < 1) {
                    self::$conferror = sprintf('%s.conf: Die Option "%s => %s" enthält nicht genügend Parameter.', $appconf, $value[0], $value[1]);
                    return false;
                }
                $params[1] = isset($params[1]) ? $params[1] : '';
                $params[2] = isset($params[2]) ? $params[2] : '';
                $params[3] = isset($params[3]) ? $params[3] : '';

                $params = self::trim_value($params);
                if( !file_exists(sprintf('%s%s.html', APPPATH, $params[0]))) {
                    self::$conferror = sprintf('<b>%s</b>: Die Formulardatei "%s.html" konnte nicht geladen werden.', $value[0], $params[0]);
                    return false;                    
                } elseif( !empty($params[1]) && !file_exists(sprintf('%s%s.html', APPPATH, $params[1]))) {
                    self::$conferror = sprintf('<b>%s</b>: Die Success-Datei "%s.html" konnte nicht geladen werden.', $value[0], $params[1]);
                    return false;                
                } elseif( !empty($params[2]) && !file_exists(sprintf('%s%s.html', APPPATH, $params[2]))) {
                    self::$conferror = sprintf('<b>%s</b>: Die Error-Datei "%s.html" konnte nicht geladen werden.', $value[0], $params[2]);
                    return false;                
                } elseif( !empty($params[3]) && !file_exists(sprintf('%s%s.html', APPPATH, $params[3]))) {
                    self::$conferror = sprintf('<b>%s</b>: Die Lock-Datei "%s.html" konnte nicht geladen werden.', $value[0], $params[3]);
                    return false;                
                }                
                $conf_arr[$value[0]] = array('form' => $params[0], 'success' => $params[1], 'error' => $params[2], 'lock' => $params[3]);
                
            } elseif ($value[0] == 'form_submit') {
                $params = explode('|', $value[1]);
                if (count($params) < 1) {
                    self::$conferror = sprintf('%s.conf: Die Option "%s => %s" enthält nicht genügend Parameter.', $appconf, $value[0], $value[1]);
                    return false;
                }
                $params[1] = isset($params[1]) ? $params[1] : '';

                $params = self::trim_value($params);
                $conf_arr[$value[0]] = array('name' => 'submit', 'value' => $params[0], 'attributes' => $params[1]);
                
            } elseif ($value[0] == 'form_error') {
                $params = explode('|', $value[1]);
                if (count($params) != 2) {
                    self::$conferror = sprintf('%s.conf: Die Option "%s => %s" enthält nicht genügend Parameter.', $appconf, $value[0], $value[1]);
                    return false;
                }
                $params = self::trim_value($params);
                $conf_arr[$value[0]] = array('prefix' => $params[0], 'suffix' => $params[1]);
                
            } elseif ($value[0] == 'rules_messages') {
                $params = explode('|', $value[1]);
                if (!empty($params)) {
                    $params = self::trim_value($params);
                    $rules_messages = array();
                    foreach ($params as $param) {
                        $message = explode(';', $param);
                        $message = self::trim_value($message);
                        $rules_messages[$message[0]] = $message[1];
                    }
                    $conf_arr[$value[0]] = $rules_messages;
                }
                
            } elseif ($value[0] == 'form_field') {
                $params = explode('|', $value[1]);

                if (count($params) < 2) {
                    self::$conferror = sprintf('%s.conf: Die Option "%s => %s" enthält nicht genügend Parameter.', $appconf, $value[0], $value[1]);
                    return false;
                }

                $params = self::trim_value($params);
                $params[2] = isset($params[2]) ? $params[2] : '';
                $params[3] = isset($params[3]) ? $params[3] : '';
                $params[4] = isset($params[4]) ? $params[4] : '';

                if (mb_strstr($params[3], ';')) {
                    $param = explode(';', $params[3]);
                    $param = self::trim_value($param);
                    $params[3] = implode('|', $param);
                }

                if ($params[0] == 'upload') {
                    $file_options = true;
                }

                $conf_arr[$value[0]][] = array('type' => $params[0], 'name' => $params[1], 'value' => $params[2], 'rules' => $params[3], 'emailinfo' => $params[4]);
            }
        }

        if (!empty($required_email_options) && $email_notification) {
            self::$conferror = sprintf('%s.conf: Die folgende Optionen fehlen: %s', $appconf, implode(', ', $required_email_options));
            return false;
        }

        if ($file_options && !empty($required_file_options)) {
            self::$conferror = sprintf('%s.conf: Die folgende Optionen fehlen: %s', $appconf, implode(', ', $required_file_options));
            return false;
        }


        $config = $conf_arr;

        return $config;
    }

    private static function trim_value($value) {
        if (!is_array($value))
            return trim($value);

        array_walk($value, create_function('&$val', '$val = trim($val);'));
        return $value;
    }

    public static function download_file($filename = '') {
        if (empty($filename))
            Url::redirect('/error404.shtml');

        $filepath = sprintf('%s%s', UPLOADPATH, $filename);
        $data = file_get_contents($filepath);
        if (empty($data))
            Url::redirect('/error404.shtml');

        self::force_download($filename, $data);
    }

    private static function force_download($filename = '', $data = '') {
        if ($filename == '' || $data == '')
            return false;

        if (false === strpos($filename, '.'))
            return false;

        $x = explode('.', $filename);
        $extension = end($x);

        $mimes = Config::get('mimes');

        if (!isset($mimes[$extension]))
            $mime = 'application/octet-stream';
        else
            $mime = (is_array($mimes[$extension])) ? $mimes[$extension][0] : $mimes[$extension];

        if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== FALSE) {
            header('Content-Type: "' . $mime . '"');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header("Content-Transfer-Encoding: binary");
            header('Pragma: public');
            header("Content-Length: " . strlen($data));
        } else {
            header('Content-Type: "' . $mime . '"');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header("Content-Transfer-Encoding: binary");
            header('Expires: 0');
            header('Pragma: no-cache');
            header("Content-Length: " . strlen($data));
        }

        exit($data);
    }
    
    private static function filter_value($value) {
        if(!is_array($value))
            return preg_replace( '/[^a-z0-9]/i', '', strtolower ($value) ); 
        
        array_walk($value, create_function('&$val', '$val = preg_replace( "/[^a-z0-9]/i", "", strtolower($val) );'));
        return $value;
    }

    private static function clean_referer($referer) {
        $path = parse_url($referer, PHP_URL_PATH);
        
        $path = strip_tags($path);
        $path = strstr($path, '.shtml', true);
        
        $referer = !empty($path) ? htmlentities($path) . '.shtml' : '';
        
        return $referer;
    }

}
