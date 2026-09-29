<?php
// ROJHON SOLUTIONS - log out
require_once 'include/initialize.php';

if (isset($_SESSION['UID'])) {
	/* Show as offline right away instead of waiting for the heartbeat to expire. */
	Chat::setOffline((int)$_SESSION['UID']);
}

$_SESSION = array();
if (ini_get('session.use_cookies')) {
	$p = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: '.WEB_ROOT.'login.php?logout=1');
exit;
?>
