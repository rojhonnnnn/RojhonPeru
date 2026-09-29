<?php
// ROJHON SOLUTIONS - configuration
//
// The same files run on your PC (XAMPP) and on InfinityFree. The
// environment is picked from the address in the browser:
//   localhost, 127.0.0.1, *.local, *.test  -> LOCAL  (XAMPP)
//   anything else (rojhon-class.gt.tc)     -> LIVE   (InfinityFree)

$__host  = strtolower(preg_replace('/:\d+$/', '', isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost'));
$__local = (PHP_SAPI === 'cli')
   || in_array($__host, array('localhost', '127.0.0.1', '::1', '[::1]'), true)
   || (bool)preg_match('/\.(local|test|localhost)$/', $__host);
defined('APP_ENV') ? null : define('APP_ENV', $__local ? 'local' : 'live');

if (APP_ENV === 'live') {
   /* InfinityFree  (Control Panel > MySQL Databases) */
   defined('DB_SERVER') ? null : define('DB_SERVER', 'sql212.infinityfree.com');
   defined('DB_PORT')   ? null : define('DB_PORT', 3306);
   defined('DB_USER')   ? null : define('DB_USER', 'if0_42889151');
   defined('DB_PASS')   ? null : define('DB_PASS', 'HefYWtrsimw');
   defined('DB_NAME')   ? null : define('DB_NAME', 'if0_42889151_rojhon');
} else {
   /* XAMPP */
   defined('DB_SERVER') ? null : define('DB_SERVER', 'localhost');
   defined('DB_PORT')   ? null : define('DB_PORT', 3306);
   defined('DB_USER')   ? null : define('DB_USER', 'root');
   defined('DB_PASS')   ? null : define('DB_PASS', '');
   defined('DB_NAME')   ? null : define('DB_NAME', 'rojhon_db');
}

defined('APP_NAME')    ? null : define('APP_NAME', 'ROJHON SOLUTIONS');
defined('APP_SHORT')   ? null : define('APP_SHORT', 'Rojhon Solutions');
defined('APP_OWNER')   ? null : define('APP_OWNER', 'ROJHON');
defined('APP_VERSION') ? null : define('APP_VERSION', '3.2.0');

/* One timezone for PHP and MySQL. XAMPP ships PHP set to Europe/Berlin,
   which made every "time sent" / "last seen" six or seven hours off. */
defined('APP_TIMEZONE') ? null : define('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

/* Errors: shown on your PC while developing, hidden from visitors on the
   live site (still written to the server error log). */
error_reporting(E_ALL);
if (APP_ENV === 'live') {
   @ini_set('display_errors', '0');
   @ini_set('log_errors', '1');
   /* InfinityFree does not show its PHP error log on free accounts, so
      errors go to logs/php-errors.log (blocked from the web by .htaccess).
      Download it with FTP if something misbehaves. */
   $__logDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'logs';
   if (is_dir($__logDir) && is_writable($__logDir)) { @ini_set('error_log', $__logDir.DIRECTORY_SEPARATOR.'php-errors.log'); }
} else {
   @ini_set('display_errors', '1');
}

/* Chat live-update timing, in milliseconds. InfinityFree counts every
   request against a daily hit limit (about 50,000), so the live site
   checks less often and slows down further when nobody is chatting.
   ONLINE_WINDOW (seconds) must stay longer than the slowest check. */
if (APP_ENV === 'live') {
   defined('CHAT_POLL_ACTIVE') ? null : define('CHAT_POLL_ACTIVE', 2500);  // conversation open, recent activity
   defined('CHAT_POLL_IDLE')   ? null : define('CHAT_POLL_IDLE', 6000);    // conversation open, quiet for 45 s
   defined('CHAT_POLL_HIDDEN') ? null : define('CHAT_POLL_HIDDEN', 20000); // browser tab in the background
   defined('NOTIFY_POLL')      ? null : define('NOTIFY_POLL', 10000);      // every other page
   defined('CHAT_ONLINE_WINDOW') ? null : define('CHAT_ONLINE_WINDOW', 60);
} else {
   defined('CHAT_POLL_ACTIVE') ? null : define('CHAT_POLL_ACTIVE', 1500);
   defined('CHAT_POLL_IDLE')   ? null : define('CHAT_POLL_IDLE', 3000);
   defined('CHAT_POLL_HIDDEN') ? null : define('CHAT_POLL_HIDDEN', 5000);
   defined('NOTIFY_POLL')      ? null : define('NOTIFY_POLL', 4000);
   defined('CHAT_ONLINE_WINDOW') ? null : define('CHAT_ONLINE_WINDOW', 30);
}

defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);
defined('SITE_ROOT') ? null : define('SITE_ROOT', dirname(__DIR__));
defined('LIB_PATH')  ? null : define('LIB_PATH', SITE_ROOT.DS.'include');
defined('SRV_ROOT')  ? null : define('SRV_ROOT', SITE_ROOT.DS);

/* WEB_ROOT is the URL path of this folder:
     http://localhost/rojhon-class/  -> "/rojhon-class/"
     http://rojhon-class.local/      -> "/"
     https://rojhon-class.gt.tc/     -> "/"
   Worked out from the running script, so it is right even when the
   host's document root is a symlink (common on shared hosting). */
if (!defined('WEB_ROOT')) {
   $__webRoot = null;
   $__siteReal = str_replace('\\', '/', (string)realpath(SITE_ROOT));
   $__scriptFs = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', (string)realpath($_SERVER['SCRIPT_FILENAME'])) : '';
   $__scriptUrl = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
   if ($__siteReal !== '' && $__scriptFs !== '' && $__scriptUrl !== '' && stripos($__scriptFs, $__siteReal.'/') === 0) {
      $__rel = substr($__scriptFs, strlen($__siteReal));               // e.g. /module/chat/index.php
      if (strlen($__scriptUrl) >= strlen($__rel) && strcasecmp(substr($__scriptUrl, -strlen($__rel)), $__rel) === 0) {
         $__webRoot = rtrim(substr($__scriptUrl, 0, strlen($__scriptUrl) - strlen($__rel)), '/').'/';
      }
   }
   if ($__webRoot === null) {
      /* fallback: compare with the document root */
      $__doc = rtrim(str_replace('\\', '/', isset($_SERVER['DOCUMENT_ROOT']) ? (string)$_SERVER['DOCUMENT_ROOT'] : ''), '/');
      $__self = str_replace('\\', '/', SITE_ROOT);
      $__webRoot = ($__doc !== '' && stripos($__self, $__doc) === 0) ? substr($__self, strlen($__doc)).'/' : '/';
      if ($__webRoot[0] !== '/') { $__webRoot = '/'.$__webRoot; }
   }
   define('WEB_ROOT', $__webRoot);
}
?>