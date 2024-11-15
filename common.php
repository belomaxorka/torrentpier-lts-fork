<?php

if (isset($_REQUEST['GLOBALS'])) die();

ignore_user_abort(true);
define('TIMESTART', utime());
define('TIMENOW',   time());

// Check system requirements
// PHP
if (PHP_VERSION_ID < 50400) die('TorrentPier II requires PHP version 5.4.0+. Your PHP version ' . PHP_VERSION);
if (PHP_VERSION_ID >= 70000) die('TorrentPier II requires PHP version lower than PHP 7. Your PHP version ' . PHP_VERSION);
// Magic quotes
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) die('Set: magic_quotes_gpc = Off');
// JSON
if (!function_exists('json_encode')) die('json_encode() function not exists');
// getmypid
if (!function_exists('getmypid') || !getmypid()) die('getmypid() function is disabled');
// Mbstring
if (!extension_loaded('mbstring')) die('Mbstring extension not installed');
// BCMath
if (!extension_loaded('bcmath')) die('BCMath extension not installed');
// Intl
if (!extension_loaded('intl')) die('Intl extension not installed');

if (empty($_SERVER['REMOTE_ADDR']))     $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if (empty($_SERVER['HTTP_USER_AGENT'])) $_SERVER['HTTP_USER_AGENT'] = '';
if (empty($_SERVER['HTTP_REFERER']))    $_SERVER['HTTP_REFERER'] = '';
if (empty($_SERVER['SERVER_NAME']))     $_SERVER['SERVER_NAME'] = getenv('SERVER_NAME');
if (empty($_SERVER['SERVER_ADDR']))     $_SERVER['SERVER_ADDR'] = getenv('SERVER_ADDR');

if (!defined('BB_ROOT')) define('BB_ROOT', './');
if (!defined('BB_SCRIPT')) define('BB_SCRIPT', null);
if (!defined('IN_FORUM') && !defined('IN_TRACKER')) define('IN_FORUM', true);

header('X-Frame-Options: SAMEORIGIN');
header('X-Powered-By: TorrentPier LTS Forever!');
date_default_timezone_set('UTC');

// Cloudflare
if (isset($_SERVER['HTTP_CF_CONNECTING_IP']))
{
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

// Get initial config
if (file_exists(BB_ROOT . 'library/config.local.php'))
{
	require(BB_ROOT . 'library/config.local.php');
}
else
{
	require(BB_ROOT . 'library/config.php');
}

if (!defined('BB_CFG_LOADED')) trigger_error('Configuration file could not be loaded', E_USER_ERROR);

// Get mods config
if (file_exists(BB_ROOT . 'library/config.mods.php'))
{
	require(BB_ROOT . 'library/config.mods.php');
}

// Load Zend Framework
use Zend\Loader\StandardAutoloader;
require(BB_ROOT . 'library/Zend/Loader/StandardAutoloader.php');
$loader = new StandardAutoloader(array('autoregister_zf' => true));
$loader->register();

// ZF use
use Zend\Json;

$server_protocol = ($bb_cfg['cookie_secure']) ? 'https://' : 'http://';
$server_port = (in_array((int)$bb_cfg['server_port'], array(80, 443))) ? '' : ':' . $bb_cfg['server_port'];
define('FORUM_PATH', $bb_cfg['script_path']);
define('FULL_URL', $server_protocol . $bb_cfg['server_name'] . $server_port . $bb_cfg['script_path']);
define('FULL_URL_MD5', md5(FULL_URL));
unset($server_protocol, $server_port);

// Debug options
define('COOKIE_DBG', 'bb_dbg_' . FULL_URL_MD5);
define('DBG_USER', (isset($_COOKIE[COOKIE_DBG])));

// Board/Tracker shared constants and functions
define('BB_BT_TORRENTS',     'bb_bt_torrents');
define('BB_BT_TRACKER',      'bb_bt_tracker');
define('BB_BT_TRACKER_SNAP', 'bb_bt_tracker_snap');
define('BB_BT_USERS',        'bb_bt_users');

define('BT_AUTH_KEY_LENGTH', 10); // (НЕ РЕКОМЕНДУЕТСЯ МЕНЯТЬ ЗНАЧЕНИЕ) длина passkey

define('PEER_HASH_PREFIX',   'peer_');
define('PEERS_LIST_PREFIX',  'peers_list_');
define('PEER_HASH_EXPIRE',   round($bb_cfg['announce_interval'] * (0.85 * $tr_cfg['expire_factor']))); // sec
define('PEERS_LIST_EXPIRE',  round($bb_cfg['announce_interval'] * 0.7)); // sec

define('DL_STATUS_RELEASER', -1);
define('DL_STATUS_DOWN',      0);
define('DL_STATUS_COMPLETE',  1);
define('DL_STATUS_CANCEL',    3);
define('DL_STATUS_WILL',      4);

define('TOR_TYPE_GOLD',       1);
define('TOR_TYPE_SILVER',     2);

define('GUEST_UID', -1);
define('ANONYMOUS', GUEST_UID); // Alias for GUEST_UID
define('BOT_UID',   -746);

// Alias for SQLite3::escapeString
function sqlite3_escape_string ($str)
{
	return SQLite3::escapeString($str);
}

/**
 * Database
 */
// Core DB class
require(CORE_DIR . 'dbs.php');
$DBS = new DBS($bb_cfg);

function DB ($db_alias = 'db1')
{
	global $DBS;
	return $DBS->get_db_obj($db_alias);
}

/**
 * Cache
 */
// Main cache class
require(INC_DIR . 'cache/common.php');
// Main datastore class
require(INC_DIR . 'datastore/common.php');

// Core CACHE class
require(CORE_DIR . 'caches.php');
$CACHES = new CACHES($bb_cfg);

function CACHE ($cache_name)
{
	global $CACHES;
	return $CACHES->get_cache_obj($cache_name);
}

// Common cache classes
require(INC_DIR . 'cache/memcache.php');
require(INC_DIR . 'cache/sqlite.php');
require(INC_DIR . 'cache/redis.php');
require(INC_DIR . 'cache/apc.php');
require(INC_DIR . 'cache/xcache.php');
require(INC_DIR . 'cache/file.php');

/**
* Datastore
*/
// Common datastore classes
require(INC_DIR . 'datastore/memcache.php');
require(INC_DIR . 'datastore/sqlite.php');
require(INC_DIR . 'datastore/redis.php');
require(INC_DIR . 'datastore/apc.php');
require(INC_DIR . 'datastore/xcache.php');
require(INC_DIR . 'datastore/file.php');

// Initialize datastore
switch ($bb_cfg['datastore_type'])
{
	case 'memcache':
		$datastore = new datastore_memcache($bb_cfg['cache']['memcache'], $bb_cfg['cache']['prefix']);
		break;

	case 'sqlite':
		$default_cfg = array(
			'db_file_path' => $bb_cfg['cache']['db_dir'] .'datastore.sqlite.db',
			'pconnect'     => true,
			'con_required' => true,
		);
		$datastore = new datastore_sqlite($default_cfg, $bb_cfg['cache']['prefix']);
		break;

	case 'redis':
		$datastore = new datastore_redis($bb_cfg['cache']['redis'], $bb_cfg['cache']['prefix']);
		break;

	case 'apc':
		$datastore = new datastore_apc($bb_cfg['cache']['prefix']);
		break;

	case 'xcache':
		$datastore = new datastore_xcache($bb_cfg['cache']['prefix']);
		break;

	case 'filecache':
		default: $datastore = new datastore_file($bb_cfg['cache']['db_dir'] . 'datastore/', $bb_cfg['cache']['prefix']);
}

function sql_dbg_enabled ()
{
	return (SQL_DEBUG && DBG_USER && !empty($_COOKIE['sql_log']));
}

function short_query ($sql, $esc_html = false)
{
	$max_len = 100;
	$sql = str_compact($sql);

	if (!empty($_COOKIE['sql_log_full']))
	{
		if (mb_strlen($sql, 'UTF-8') > $max_len)
		{
			$sql = mb_substr($sql, 0, 50) .' [...cut...] '. mb_substr($sql, -50);
		}
	}

	return ($esc_html) ? htmlCHR($sql, true) : $sql;
}

// Functions
function is_secure()
{
	if (
		(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
		|| (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
		|| (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
		|| (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443)
		|| (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https')
	) {
		return true;
	}
	return false;
}

function utime ()
{
	return array_sum(explode(' ', microtime()));
}

function bb_log ($msg, $file_name = 'logs')
{
	if (is_array($msg))
	{
		$msg = join(LOG_LF, $msg);
	}
	$file_name .= (LOG_EXT) ? '.'. LOG_EXT : '';
	return file_write($msg, LOG_DIR . $file_name);
}

function file_write ($str, $file, $max_size = LOG_MAX_SIZE, $lock = true, $replace_content = false)
{
	$bytes_written = false;
	clearstatcache();

	if (is_file($file) && ($max_size && (filesize($file) >= $max_size)))
	{
		$file_parts = pathinfo($file);
		$new_name = ($file_parts['dirname'] . '/' . $file_parts['filename'] . '_[old]_' . date('Y-m-d_H-i-s_') . getmypid() . '.' . $file_parts['extension']);
		clearstatcache();
		if (!is_file($new_name))
		{
			rename($file, $new_name);
		}
	}
	clearstatcache();
	if (bb_mkdir(dirname($file)))
	{
		if ($fp = fopen($file, 'ab+'))
		{
			if ($lock)
			{
				flock($fp, LOCK_EX);
			}
			if ($replace_content)
			{
				ftruncate($fp, 0);
				fseek($fp, 0, SEEK_SET);
			}
			$bytes_written = fwrite($fp, $str);
			fclose($fp);
		}
	}

	return $bytes_written;
}

function bb_mkdir ($path, $mode = 0777)
{
	$old_um = umask(0);
	$dir = mkdir_rec($path, $mode);
	umask($old_um);
	return $dir;
}

function mkdir_rec ($path, $mode)
{
	if (is_dir($path))
	{
		return ($path !== '.' && $path !== '..') ? is_writable($path) : false;
	}
	else
	{
		return (mkdir_rec(dirname($path), $mode)) ? @mkdir($path, $mode) : false;
	}
}

function verify_id ($id, $length)
{
	return (is_string($id) && preg_match('#^[a-zA-Z0-9]{'. $length .'}$#', $id));
}

function clean_filename ($fname)
{
	static $s = array('\\', '/', ':', '*', '?', '"', '<', '>', '|', ' ');
	return str_replace($s, '_', str_compact($fname));
}

function encode_ip ($ip)
{
	$d = explode('.', $ip);
	return sprintf('%02x%02x%02x%02x', $d[0], $d[1], $d[2], $d[3]);
}

function decode_ip ($ip)
{
	return long2ip("0x{$ip}");
}

function ip2int ($ip)
{
	return (float) sprintf('%u', ip2long($ip));  // для совместимости с 32 битными системами
}

// long2ip( mask_ip_int(ip2int('1.2.3.4'), 24) ) = '1.2.3.255'
function mask_ip_int ($ip, $mask)
{
	$ip_int = is_numeric($ip) ? $ip : ip2int($ip);
	$ip_masked = $ip_int | ((1 << (32 - $mask)) - 1);
	return (float) sprintf('%u', $ip_masked);
}

function bb_crc32 ($str)
{
	return (float) sprintf('%u', crc32($str));
}

function hexhex ($value)
{
	return dechex(hexdec($value));
}

function verify_ip ($ip)
{
	return preg_match('#^(\d{1,3}\.){3}\d{1,3}$#', $ip);
}

function str_compact ($str)
{
	return preg_replace('#\s+#u', ' ', trim($str));
}

function make_rand_str ($len = 10)
{
	return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', (int)$len)), 0, $len);
}

function get_bt_ratio ($btu)
{
	return
		(!empty($btu['u_down_total']) && $btu['u_down_total'] > MIN_DL_FOR_RATIO)
		? round((($btu['u_up_total'] + $btu['u_up_release'] + $btu['u_up_bonus']) / $btu['u_down_total']), 2)
		: null
	;
}

// bencode: based on OpenTracker
function bencode ($var)
{
	if (is_string($var))
	{
		return strlen($var) .':'. $var;
	}
	else if (is_int($var))
	{
		return 'i'. $var .'e';
	}
	else if (is_float($var))
	{
		return 'i'. sprintf('%.0f', $var) .'e';
	}
	else if (is_array($var))
	{
		if (count($var) == 0)
		{
			return 'de';
		}
		else
		{
			$assoc = false;

			foreach ($var as $key => $val)
			{
				if (!is_int($key))
				{
					$assoc = true;
					break;
				}
			}

			if ($assoc)
			{
				ksort($var, SORT_REGULAR);
				$ret = 'd';

				foreach ($var as $key => $val)
				{
					$ret .= bencode($key) . bencode($val);
				}
				return $ret .'e';
			}
			else
			{
				$ret = 'l';

				foreach ($var as $val)
				{
					$ret .= bencode($val);
				}
				return $ret .'e';
			}
		}
	}
	else
	{
		trigger_error('bencode error: wrong data type', E_USER_ERROR);
	}
}

function array_deep (&$var, $fn, $one_dimensional = false, $array_only = false)
{
	if (is_array($var))
	{
		foreach ($var as $k => $v)
		{
			if (is_array($v))
			{
				if ($one_dimensional)
				{
					unset($var[$k]);
				}
				else if ($array_only)
				{
					$var[$k] = $fn($v);
				}
				else
				{
					array_deep($var[$k], $fn);
				}
			}
			else if (!$array_only)
			{
				$var[$k] = $fn($v);
			}
		}
	}
	else if (!$array_only)
	{
		$var = $fn($var);
	}
}

function hide_bb_path ($path)
{
	return ltrim(str_replace(BB_PATH, '', $path), '/\\');
}

function sys ($param)
{
	switch ($param)
	{
		case 'la':
			return function_exists('sys_getloadavg') ? join(' ', sys_getloadavg()) : 0;
			break;
		case 'mem':
			return function_exists('memory_get_usage') ? memory_get_usage() : 0;
			break;
		case 'mem_peak':
			return function_exists('memory_get_peak_usage') ? memory_get_peak_usage() : 0;
			break;
		default:
			trigger_error("invalid param: $param", E_USER_ERROR);
	}
}

function ver_compare ($version1, $operator, $version2)
{
	return version_compare($version1, $version2, $operator);
}

function dbg_log ($str, $file)
{
	if (!DBG_LOG_TRACKER && !DBG_LOG) return false;
	$dir = (defined('IN_TRACKER') ? 'dbg_tr/' : 'dbg_bb/') . date('m-d_H') .'/';
	return bb_log($str, $dir . clean_filename($file) . '_' . TIMENOW);
}

function log_get ($file = '', $prepend_str = false)
{
	log_request($file, $prepend_str, false);
}

function log_post ($file = '', $prepend_str = false)
{
	log_request($file, $prepend_str, true);
}

function log_request ($file = '', $prepend_str = false, $add_post = true)
{
	global $user;

	$file = ($file) ? $file : 'req/'. date('m-d');
	$str = array();
	$str[] = date('m-d H:i:s');
	if ($prepend_str !== false) $str[] = $prepend_str;
	if (!empty($user->data)) $str[] = $user->id ."\t". html_entity_decode($user->name);
	$str[] = sprintf('%-15s', $_SERVER['REMOTE_ADDR']);

	if (isset($_SERVER['REQUEST_URI'])) {
		$str[] = $_SERVER['REQUEST_URI'];
	}
	if (isset($_SERVER['HTTP_USER_AGENT'])) {
		$str[] = $_SERVER['HTTP_USER_AGENT'];
	}
	if (isset($_SERVER['HTTP_REFERER'])) {
		$str[] = $_SERVER['HTTP_REFERER'];
	}

	if (!empty($_POST) && $add_post) $str[] = "post: ". str_compact(urldecode(http_build_query($_POST)));
	$str = join("\t", $str) . "\n";
	bb_log($str, $file);
}

// Board init
if (defined('IN_FORUM'))
{
	require(INC_DIR .'init_bb.php');
}
// Tracker init
else if (defined('IN_TRACKER'))
{
	define('DUMMY_PEER', pack('Nn', ip2long($_SERVER['REMOTE_ADDR']), !empty($_GET['port']) ? intval($_GET['port']) : mt_rand(1000, 65000)));

	function dummy_exit ($interval = 1800)
	{
		$output = bencode(array(
			'interval'     => (int)    $interval,
			'min interval' => (int)    $interval,
			'peers'        => (string) DUMMY_PEER,
		));

		die($output);
	}

	header('Content-Type: text/plain');
	header('Pragma: no-cache');

	if (!defined('IN_ADMIN'))
	{
		// Exit if tracker is disabled via ON/OFF trigger
		if (file_exists(BB_DISABLED))
		{
			dummy_exit(mt_rand(60, 2400));
		}
	}
}
