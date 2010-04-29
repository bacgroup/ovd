<?php
/**
 * Copyright (C) 2008,2009,2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Julien LANGLOIS <julien@ulteo.com>
 * Author Laurent CLOUET <laurent@ulteo.com>
 * Author Jeremy DESVAGES <jeremy@ulteo.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/
function secure_html($data_) {
	if (is_array($data_))
		foreach ($data_ as $k => $v)
			$data_[$k] = secure_html($v);
	elseif (is_string($data_))
		$data_ = htmlspecialchars($data_, ENT_NOQUOTES);

	return $data_;
}

function in_admin() {
	$buf = basename(dirname('http://'.@$_SERVER['SERVER_NAME'].@$_SERVER['PHP_SELF']));

	if ($buf == 'admin')
		return true;

	return false;
}

function check_folder($folder_) {
	if (!is_dir($folder_))
		if (! @mkdir($folder_, 0750))
			return false;

	return true;
}

function str2num($str_) {
	// hoho
	$num = 0;
	$str = md5($str_);

	for ($i=0; $i < strlen($str); $i++)
		$num += ($i+1)*ord($str[$i]);

	return $num;
}

function var_dump2($content_) {
	var_dump($content_);
	echo '<hr />';
}

function print_array($data_, $n_=0) {
	if (is_array($data_)) {
		foreach ($data_ as $k => $v) {
			for ($i = 0; $i < $n_; $i++)
				echo '.';
			echo "<strong>$k</strong> => { ";
			if (is_array($v)) {
				echo "<br />\n";
				print_array($v,$n_+4);
				for ($i = 0; $i < $n_; $i++)
					echo '.';
				echo " }<br />\n";
			} else
				echo $v." }<br />\n";
		}
	} else {
		for ($i = 0; $i < $n_; $i++)
			echo '.';
		echo $data_."<br>\n";
	}
}

function array_merge2( $a1, $a2) {
	foreach ($a2 as $k2 => $v2) {
		if ( is_array($v2) && ($v2 != array())) {
			$a1[$k2] = array_merge2($a1[$k2], $a2[$k2]);
		}
		else {
			$a1[$k2] = $a2[$k2];
		}
	}
	return $a1;
}

function is_writable2($filename) {
	return ((is_file($filename) && is_writable($filename)) || (is_dir($filename) && is_writable($filename)) || is_writable(dirname($filename)));
}

function locale2unix($locale_) {
	if (preg_match('/[a-z]+_[A-Z]+\.[a-zA-Z-0-9]+/', $locale_))
		return $locale_;
	
	$locale = strtolower($locale_);
	$locales = array(
		'fr'	=>	'fr_FR',
		'en'	=>	'en_US',
		'de'	=>	'de_DE',
		'es'	=>	'es_ES',
		'pt'	=>	'pt_PT',
		'it'	=>	'it_IT'
	);

	if (!preg_match('/[a-zA-Z-_]/', $locale))
		$locale = $locales['en'];

	if (strlen($locale) == 2) {
		if (array_key_exists($locale, $locales))
			$locale = $locales[$locale];
		else
			$locale = $locale.'_'.strtoupper($locale);
	}
	elseif (strlen($locale) == 5)
		$locale = substr($locale, 0, 2).'_'.strtoupper(substr($locale, -2));

	$locale .= '.UTF-8';

	return $locale;
}

function plugin_error($errno_, $errstr_, $errfile_, $errline_, $errcontext_) {
	Logger::error('plugins', $errstr_.' in '.$errfile_.' line '.$errline_);
}

function conf_is_valid() {
	if (! defined('SESSIONMANAGER_SPOOL'))
		return _('SESSIONMANAGER_SPOOL is not defined');

	if (! defined('SESSIONMANAGER_LOGS'))
		return _('SESSIONMANAGER_LOGS is not defined');

	if (! defined('SESSIONMANAGER_CONFFILE_SERIALIZED'))
		return _('SESSIONMANAGER_CONFFILE_SERIALIZED is not defined');

	if (! defined('SESSIONMANAGER_ADMIN_LOGIN'))
		return _('SESSIONMANAGER_ADMIN_LOGIN is not defined');

	if (! defined('SESSIONMANAGER_ADMIN_PASSWORD'))
		return _('SESSIONMANAGER_ADMIN_PASSWORD is not defined');

	if (! is_writable2(SESSIONMANAGER_SPOOL))
		return _('SESSIONMANAGER_SPOOL is not writable').' : '.SESSIONMANAGER_SPOOL;

	if (! is_writable2(SESSIONMANAGER_LOGS))
		return _('SESSIONMANAGER_LOGS is not writable').' : '.SESSIONMANAGER_LOGS;

	if (! is_writable2(SESSIONMANAGER_CONFFILE_SERIALIZED))
		return _('SESSIONMANAGER_CONFFILE_SERIALIZED is not writable').' : '.SESSIONMANAGER_CONFFILE_SERIALIZED;

	return true;
}

function pathinfo_filename($path_) {
	if (version_compare(phpversion(), '5.2.0', '<')) {
		$temp = pathinfo($path_);
		if ($temp['extension'])
			$temp['filename'] = substr($temp['basename'], 0, (strlen($temp['basename'])-strlen($temp['extension'])-1));
		return $temp;
	}

	return pathinfo($path_);
}

function get_classes_startwith($start_name) {
	$classes_name = get_declared_classes();

	$ret = array();
	foreach ($classes_name as $name)
		if (substr($name, 0, strlen($start_name)) == $start_name)
			$ret[] = $name;

	return $ret;
}

function str_startswith($string_, $search_) {
	return (substr($string_, 0, strlen($search_)) == $search_);
}

function str_endswith($string_, $search_) {
	return (substr($string_, (strlen($search_)*-1)) == $search_);
}

function gen_string($nc, $st='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') {
	$len = strlen($st)-1;

	$ret = '';
	while ($nc-- > 0)
		$ret .= $st{mt_rand(0, $len)};

	return $ret;
}

function gen_unique_string() {
	return time().gen_string(5);
}

function get_needed_attributes_user_from_module_plugin() {
	$prefs = Preferences::getInstance();
	if (! $prefs)
		die_error('get Preferences failed',__FILE__,__LINE__);
	$plugs = new Plugins();
	$plugins = $plugs->getAvailablePlugins();
	if (array_key_exists('plugins', $plugins)) {
		$plugins['plugin_enable'] = $plugins['plugins'];
		unset($plugins['plugins']);
	}
	$attributes = array();
	foreach ($plugins as $k1 => $v1) {
		$plugins_enable = $prefs->get('plugins', $k1);
		foreach ($v1 as $k2 => $v2) {
			if (isset($v2['UserDB']) && ($k2 == $plugins_enable))
				$attributes = array_merge($attributes,$v2['UserDB']);
		}
	}
	return $attributes;
}

function user_cmp($o1, $o2) {
	return strcmp($o1->getAttribute('login'), $o2->getAttribute('login'));
}

function usergroup_cmp($o1, $o2) {
	return strcmp($o1->name, $o2->name);
}

function application_cmp($o1, $o2) {
	return strcmp($o1->getAttribute('name'), $o2->getAttribute('name'));
}

function appsgroup_cmp($o1, $o2) {
	return strcmp($o1->name, $o2->name);
}

/* caching helpers */
function get_from_cache($subdir_, $id_) {
	$file = CACHE_DIR.'/'.$subdir_.'/'.$id_;

	if (is_readable($file))
		$ret = unserialize(file_get_contents($file));
	else
		$ret = NULL;

	Logger::debug('main', 'get_from_cache '.$subdir_.'/'.$id_.': '.$ret);
	return $ret;
}

function set_cache($data_, $subdir_, $id_) {
	if (! is_dir(CACHE_DIR.'/'.$subdir_))
		mkdir(CACHE_DIR.'/'.$subdir_, 0775);

	$file = CACHE_DIR.'/'.$subdir_.'/'.$id_;
	$tmp = serialize($data_);
	return file_put_contents($file, $tmp);
}

function domain2suffix($domain_) {
		$domain_ = strtolower($domain_);
		$buf = explode('.', $domain_);
		if (! count($buf))
			return;

		$str='';
		foreach($buf as $d)
			$str.='dc='.$d.',';

		$str = substr($str, 0,-1);
		return $str;
}

function suffix2domain($suffix_) {
	$buf = explode(',', $suffix_);
	if (! count($buf))
		return;

	$build = array();
	foreach($buf as $s) {
		if (! str_startswith($s, 'dc='))
			return;

		$build[] = strtoupper(substr($s, 3));
	}

	$str = implode('.', $build);
	return $str;
}

function application_desktops_to_ids() {
	$applicationDB = ApplicationDB::getInstance();
	$all_applications = $applicationDB->getList();

	$ret = array();
	foreach ($all_applications as $app) {
		$ret[$app->getAttribute('desktopfile')] = (int)$app->getAttribute('id');
	}

	return $ret;
}

function base64url_encode($string_) {
	$base64 = base64_encode($string_);
	$base64url = strtr($base64, '+/=', '-_.');
	return ($base64url);
}

function base64url_decode($base64url_) {
	$base64 = strtr($base64url_, '-_.', '+/=');
	$string = base64_decode($base64);
	return ($string);
}

function is_base64url($string_) {
	return (substr($string_, -1) === '.');
}

/**
 * Delete a file, or a folder and its contents (recursive algorithm)
 *
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.0.3
 * @link        http://aidanlister.com/repos/v/function.rmdirr.php
 * @param       string   $dirname    Directory to delete
 * @return      bool     Returns TRUE on success, FALSE on failure
 */
function rmdirr($dirname) {
    // Sanity check
    if (!file_exists($dirname))
        return false;

    // Simple delete for a file
    if (is_file($dirname) || is_link($dirname))
        return unlink($dirname);

    // Loop through the folder
    $dir = dir($dirname);
    while (false !== $entry = $dir->read()) {
        // Skip pointers
        if ($entry == '.' || $entry == '..')
            continue;

        // Recurse
        rmdirr($dirname . DIRECTORY_SEPARATOR . $entry);
    }

    // Clean up
    $dir->close();
    return rmdir($dirname);
}

function explode_with_escape($pattern_, $string_, $limit_=NULL) {
	$buffer = explode($pattern_, $string_);
	$ret = array();
	
	$i = 0;
	$append_mode = false;
	foreach($buffer as $e) {
		if ($append_mode || $i>=$limit_) {
			$ret[$i-1] .= $pattern_.$e;
			$append_mode = false;
		} else {
			if (!isset($ret[$i]))
				$ret[$i] = '';
			$ret[$i++] .= $e;
		}
		
		if ( substr($e, -1) == '\\') {
			if (strlen($e)>1 && substr($e, -2) != '\\') {
				$append_mode = true;
				$ret[$i-1] = substr($ret[$i-1], 0, -1);
			}
		}
	}
	
	return $ret;
}

