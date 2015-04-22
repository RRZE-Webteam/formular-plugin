<?php if ( !defined('COREPATH') ) exit;

$config['timezone']	= 'Europe/Berlin';

$config['charset'] = 'UTF-8';

$config['locale'] = array('de_De.utf8', 'de_DE@euro', 'de_DE');

$config['utc'] = time();

$config['site_url']	= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://').$_SERVER["HTTP_HOST"].'/';

$config['cookie_domain'] = '';

$config['cookie_path'] = '/';

$config['session_name']	= '_session';

$config['session_id_ttl'] = 3600; // session time to live in seconds (default 1 hour)

$config['flash_key'] = 'flash';

$config['mimes'] = array(
        'hqx'	=>	'application/mac-binhex40',
        'cpt'	=>	'application/mac-compactpro',
        'csv'	=>	array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel'),
        'bin'	=>	'application/macbinary',
        'dms'	=>	'application/octet-stream',
        'lha'	=>	'application/octet-stream',
        'lzh'	=>	'application/octet-stream',
        'exe'	=>	array('application/octet-stream', 'application/x-msdownload'),
        'class'	=>	'application/octet-stream',
        'psd'	=>	'application/x-photoshop',
        'so'	=>	'application/octet-stream',
        'sea'	=>	'application/octet-stream',
        'dll'	=>	'application/octet-stream',
        'oda'	=>	'application/oda',
        'pdf'	=>	array('application/pdf', 'application/x-download'),
        'ai'	=>	'application/postscript',
        'eps'	=>	'application/postscript',
        'ps'	=>	'application/postscript',
        'smi'	=>	'application/smil',
        'smil'	=>	'application/smil',
        'mif'	=>	'application/vnd.mif',
        'wbxml'	=>	'application/wbxml',
        'wmlc'	=>	'application/wmlc',
        'dcr'	=>	'application/x-director',
        'dir'	=>	'application/x-director',
        'dxr'	=>	'application/x-director',
        'dvi'	=>	'application/x-dvi',
        'gtar'	=>	'application/x-gtar',
        'gz'	=>	'application/x-gzip',
        'php'	=>	'application/x-httpd-php',
        'php4'	=>	'application/x-httpd-php',
        'php3'	=>	'application/x-httpd-php',
        'phtml'	=>	'application/x-httpd-php',
        'phps'	=>	'application/x-httpd-php-source',
        'js'	=>	'application/x-javascript',
        'swf'	=>	'application/x-shockwave-flash',
        'sit'	=>	'application/x-stuffit',
        'tar'	=>	'application/x-tar',
        'tgz'	=>	array('application/x-tar', 'application/x-gzip-compressed'),
        'xhtml'	=>	'application/xhtml+xml',
        'xht'	=>	'application/xhtml+xml',
        'zip'	=>  array('application/x-zip', 'application/zip', 'application/x-zip-compressed'),
        'mid'	=>	'audio/midi',
        'midi'	=>	'audio/midi',
        'mpga'	=>	'audio/mpeg',
        'mp2'	=>	'audio/mpeg',
        'mp3'	=>	array('audio/mpeg', 'audio/mpg', 'audio/mpeg3', 'audio/mp3'),
        'aif'	=>	'audio/x-aiff',
        'aiff'	=>	'audio/x-aiff',
        'aifc'	=>	'audio/x-aiff',
        'ram'	=>	'audio/x-pn-realaudio',
        'rm'	=>	'audio/x-pn-realaudio',
        'rpm'	=>	'audio/x-pn-realaudio-plugin',
        'ra'	=>	'audio/x-realaudio',
        'rv'	=>	'video/vnd.rn-realvideo',
        'wav'	=>	array('audio/x-wav', 'audio/wave', 'audio/wav'),
        'bmp'	=>	array('image/bmp', 'image/x-windows-bmp'),
        'gif'	=>	'image/gif',
        'jpeg'	=>	array('image/jpeg', 'image/pjpeg'),
        'jpg'	=>	array('image/jpeg', 'image/pjpeg'),
        'jpe'	=>	array('image/jpeg', 'image/pjpeg'),
        'png'	=>	array('image/png',  'image/x-png'),
        'tiff'	=>	'image/tiff',
        'tif'	=>	'image/tiff',
        'css'	=>	'text/css',
        'html'	=>	'text/html',
        'htm'	=>	'text/html',
        'shtml'	=>	'text/html',
        'txt'	=>	'text/plain',
        'text'	=>	'text/plain',
        'log'	=>	array('text/plain', 'text/x-log'),
        'rtx'	=>	'text/richtext',
        'rtf'	=>	'text/rtf',
        'xml'	=>	'text/xml',
        'xsl'	=>	'text/xml',
        'mpeg'	=>	'video/mpeg',
        'mpg'	=>	'video/mpeg',
        'mpe'	=>	'video/mpeg',
        'qt'	=>	'video/quicktime',
        'mov'	=>	'video/quicktime',
        'avi'	=>	'video/x-msvideo',
        'movie'	=>	'video/x-sgi-movie',
        'doc'	=>	'application/msword',
        'xls'	=>	array('application/excel', 'application/vnd.ms-excel', 'application/msexcel'),
        'ppt'	=>	array('application/powerpoint', 'application/vnd.ms-powerpoint'),	
        'docx'	=>	array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'),
        'xlsx'	=>	array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/powerpoint', 'application/vnd.ms-powerpoint'),
        'word'	=>	array('application/msword', 'application/octet-stream'),
        'xl'	=>	'application/excel',
        'eml'	=>	'message/rfc822',
        'json' => array('application/json', 'text/json')
    );
