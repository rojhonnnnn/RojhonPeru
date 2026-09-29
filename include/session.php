<?php
// ROJHON SOLUTIONS - session, login guard, CSRF and flash messages

if (session_status() !== PHP_SESSION_ACTIVE) {
	$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
	session_name('ROJHONSESSID');
	session_set_cookie_params(array(
		'lifetime' => 0,
		'path'     => '/',
		'secure'   => $secure,
		'httponly' => true,
		'samesite' => 'Lax'
	));
	session_start();
}

function logged_in() {
	return isset($_SESSION['UID']);
}

/* True when the request came from jQuery/fetch rather than a normal page load. */
function is_ajax_request() {
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		return true;
	}
	$script = isset($_SERVER['SCRIPT_NAME']) ? basename($_SERVER['SCRIPT_NAME']) : '';
	return (bool)preg_match('/(ajax|api)\.php$/i', $script);
}

/* Blocks every script except the login page for visitors who are not
   signed in. AJAX calls get a JSON 401 instead of an HTML redirect so
   DataTables and the chat show a clean "session expired" state. */
function app_guard() {
	if (PHP_SAPI === 'cli') { return; }
	$script = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : '';
	$public = array(
		realpath(SITE_ROOT.DS.'login.php'),
		realpath(SITE_ROOT.DS.'logout.php')
	);
	if ($script && in_array($script, $public, true)) { return; }
	if (logged_in()) { return; }

	if (is_ajax_request()) {
		http_response_code(401);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('error' => 'session_expired', 'message' => 'Your session has ended. Please log in again.'));
		exit;
	}
	header('Location: '.WEB_ROOT.'login.php');
	exit;
}

function confirm_logged_in() {
	if (!logged_in()) { redirect(WEB_ROOT.'login.php'); }
}
function admin_confirm_logged_in() {
	confirm_logged_in();
}

/* ---------------- CSRF ---------------- */
function csrf_token() {
	if (empty($_SESSION['CSRF_TOKEN'])) {
		$_SESSION['CSRF_TOKEN'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['CSRF_TOKEN'];
}
function csrf_check($token) {
	return is_string($token) && $token !== '' && isset($_SESSION['CSRF_TOKEN'])
		&& hash_equals($_SESSION['CSRF_TOKEN'], $token);
}

/* ---------------- Flash messages ---------------- */
function message($msg = "", $msgtype = "") {
	if (!empty($msg)) {
		$_SESSION['message'] = $msg;
		$_SESSION['msgtype'] = $msgtype;
		return null;
	}
	return isset($_SESSION['message']) ? $_SESSION['message'] : '';
}

/* Renders the flash message once. json_encode keeps quotes and line
   breaks inside a message from breaking the JavaScript string. */
function check_message() {
	if (!isset($_SESSION['message'])) { return; }
	$type  = isset($_SESSION['msgtype']) ? $_SESSION['msgtype'] : 'info';
	$titles = array('info' => 'Information!', 'error' => 'Error!', 'success' => 'Success!', 'warning' => 'Warning!');
	if (!isset($titles[$type])) { $type = 'info'; }
	echo '<script type="text/javascript">swal('.json_encode($titles[$type]).', '
		.json_encode((string)$_SESSION['message']).', '.json_encode($type).');</script>';
	unset($_SESSION['message'], $_SESSION['msgtype']);
}
?>
