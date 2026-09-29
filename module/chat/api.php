<?php
// ROJHON SOLUTIONS - Chat API (JSON)
//
//   GET  ?act=file&id=N[&dl=1]   stream an attachment (participants only)
//   POST act=bootstrap           me, people, inbox
//   POST act=open                newest page of one conversation + partner profile
//   POST act=sync                heartbeat + receipts + new/changed messages (Messages tab)
//   POST act=ping                heartbeat + unread badge + notifications (every other page)
//   POST act=history             older messages (scroll up)
//   POST act=send                text and/or one attachment (multipart)
//   POST act=react               add / change / remove a reaction
//   POST act=unsend              unsend your own message for everyone
//   POST act=media               shared photos and files for the info panel
//
// Every POST needs the CSRF token from the page (X-CSRF-Token header).

require_once("../../include/initialize.php");

$me  = (int)$_SESSION['UID'];
$act = isset($_REQUEST['act']) ? (string)$_REQUEST['act'] : '';

/* ---------------- attachments ---------------- */
if ($act === 'file') {
	session_write_close();
	$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	$msg = Chat::getMessage($id);
	if (!$msg || ((int)$msg->SENDER_ID !== $me && (int)$msg->RECEIVER_ID !== $me) || (int)$msg->IS_UNSENT === 1 || !$msg->FILE_PATH) {
		http_response_code(404);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'File not found or no longer available.';
		exit;
	}
	$rel  = str_replace(array('..', '\\'), '', (string)$msg->FILE_PATH);
	$path = Chat::uploadDir().str_replace('/', DS, $rel);
	if (!is_file($path)) {
		http_response_code(404);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'File not found on the server.';
		exit;
	}
	$download = !empty($_GET['dl']) || $msg->MSG_TYPE !== 'image';
	$mime = ($msg->MSG_TYPE === 'image' && isset(Chat::$imageMimes[$msg->FILE_MIME])) ? $msg->FILE_MIME : 'application/octet-stream';
	if (!$download && $msg->MSG_TYPE !== 'image') { $mime = 'application/octet-stream'; }
	$name = (string)$msg->FILE_NAME;
	$ascii = preg_replace('/[^\x20-\x7E]/', '_', str_replace(array('"', '\\'), '_', $name));

	/* Nothing but the file bytes may reach the browser: turn off PHP
	   output compression and on-screen notices for this response, and
	   drop anything already buffered. Otherwise some XAMPP setups send a
	   photo with the wrong length or with text in front of it. */
	@ini_set('zlib.output_compression', 'Off');
	@ini_set('display_errors', '0');
	while (ob_get_level() > 0) { ob_end_clean(); }
	header('Content-Type: '.$mime);
	header('Content-Length: '.filesize($path));
	header('X-Content-Type-Options: nosniff');
	header('Cache-Control: private, max-age=86400');
	header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
	header('Content-Disposition: '.($download ? 'attachment' : 'inline').'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($name));
	readfile($path);
	exit;
}

/* ---------------- everything else is POST + CSRF ---------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	json_out(array('error' => 'method_not_allowed'), 405);
}
$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_POST['_csrf']) ? $_POST['_csrf'] : '');
if (!csrf_check($token)) {
	json_out(array('error' => 'csrf', 'message' => 'Your session was refreshed. Reload the page and try again.'), 419);
}

/* The session is only needed for the checks above. Releasing it lets the
   2-second sync keep running while a big file is still uploading, instead
   of every request queueing behind PHP's session lock. */
session_write_close();

/* Upload bigger than post_max_size: PHP silently empties $_POST. */
if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
	json_out(array('error' => 'too_large', 'message' => 'That file is larger than the server allows ('.Chat::humanSize(Chat::maxUploadBytes()).').'), 413);
}

$with = isset($_POST['with']) ? (int)$_POST['with'] : 0;

switch ($act) {

	case 'bootstrap':
		Chat::touchPresence($me);
		Chat::markDelivered($me);
		$meRow = Chat::getUser($me);
		json_out(array(
			'ok'        => true,
			'now'       => dt_to_ms(now_ms_string()),
			'me'        => $meRow ? Chat::userCard($meRow) : null,
			'people'    => Chat::listUsers($me),
			'inbox'     => Chat::inbox($me),
			'unread'    => Chat::totalUnread($me),
			'reactions' => Chat::$reactionSet,
			'max_upload'=> Chat::maxUploadBytes(),
			'online_window' => Chat::ONLINE_WINDOW
		));
		break;

	case 'open':
		/* Opening a conversation: newest page + partner profile. */
		$partner = Chat::getUser($with);
		if (!$partner || $with === $me) { json_out(array('error' => 'not_found', 'message' => 'That person could not be found.'), 404); }
		$conv = Chat::findConversation($me, $with);
		$messages = $conv ? Chat::page($conv->CONV_ID) : array();
		if ($conv && !empty($_POST['seen'])) { Chat::markSeen($me, $conv->CONV_ID); }
		json_out(array(
			'ok'       => true,
			'cursor'   => now_ms_string(),
			'partner'  => Chat::userCard($partner),
			'conv'     => $conv ? (int)$conv->CONV_ID : 0,
			'messages' => $messages,
			'has_more' => count($messages) >= Chat::PAGE_SIZE,
			'typing'   => Chat::isTyping($with, $me)
		));
		break;

	case 'sync':
		$typing = isset($_POST['typing']) ? (int)$_POST['typing'] : -1;
		Chat::touchPresence($me, ($typing === -1) ? null : ($typing === 1 && $with > 0 ? $with : 0));
		Chat::markDelivered($me);

		$cursor = now_ms_string();
		$out = array('ok' => true, 'now' => dt_to_ms($cursor), 'cursor' => $cursor);

		if ($with > 0) {
			$conv = Chat::findConversation($me, $with);
			$convOut = array('with' => $with, 'conv' => 0, 'messages' => array(), 'updates' => array(), 'typing' => false);
			if ($conv) {
				if (!empty($_POST['seen'])) { Chat::markSeen($me, $conv->CONV_ID); }
				$after = isset($_POST['after']) ? (int)$_POST['after'] : 0;
				$convOut['conv'] = (int)$conv->CONV_ID;
				$convOut['messages'] = Chat::after($conv->CONV_ID, $after);
				if ($after > 0) {
					$convOut['updates'] = Chat::changedSince($conv->CONV_ID, isset($_POST['cursor']) ? (string)$_POST['cursor'] : '', $after);
				}
			}
			$convOut['typing'] = Chat::isTyping($with, $me);
			$out['thread'] = $convOut;
		}

		$inbox = Chat::inbox($me);
		$sig = md5(json_encode($inbox));
		$out['inbox_sig'] = $sig;
		if (!isset($_POST['inbox_sig']) || $_POST['inbox_sig'] !== $sig) { $out['inbox'] = $inbox; }
		$out['presence'] = Chat::presenceMap($me);
		$out['unread'] = Chat::totalUnread($me);
		json_out($out);
		break;

	case 'ping':
		Chat::touchPresence($me);
		Chat::markDelivered($me);
		$lastIn = isset($_POST['last_in']) ? (int)$_POST['last_in'] : -1;
		$out = array('ok' => true, 'unread' => Chat::totalUnread($me), 'last_in' => Chat::lastIncomingId($me));
		$out['incoming'] = ($lastIn >= 0) ? Chat::incomingSince($me, $lastIn) : array();
		$inbox = Chat::inbox($me, 8);
		$sig = md5(json_encode($inbox));
		$out['inbox_sig'] = $sig;
		if (!isset($_POST['inbox_sig']) || $_POST['inbox_sig'] !== $sig) { $out['inbox'] = $inbox; }
		json_out($out);
		break;

	case 'history':
		$conv = Chat::findConversation($me, $with);
		$before = isset($_POST['before']) ? (int)$_POST['before'] : 0;
		if (!$conv || $before <= 0) { json_out(array('ok' => true, 'messages' => array(), 'has_more' => false)); }
		$msgs = Chat::page($conv->CONV_ID, $before);
		json_out(array('ok' => true, 'messages' => $msgs, 'has_more' => count($msgs) >= Chat::PAGE_SIZE));
		break;

	case 'send':
		list($ok, $upload) = Chat::validateUpload(isset($_FILES['file']) ? $_FILES['file'] : null);
		if (!$ok) { json_out(array('error' => 'upload', 'message' => $upload), 422); }
		$res = Chat::send($me, $with, isset($_POST['body']) ? $_POST['body'] : '', $upload, isset($_POST['cid']) ? $_POST['cid'] : '');
		if (isset($res['error'])) { json_out(array('error' => 'send', 'message' => $res['error']), 422); }
		json_out(array('ok' => true, 'message' => $res['message']));
		break;

	case 'react':
		$res = Chat::react($me, isset($_POST['id']) ? (int)$_POST['id'] : 0, isset($_POST['emoji']) ? (string)$_POST['emoji'] : '');
		if (isset($res['error'])) { json_out(array('error' => 'react', 'message' => $res['error']), 422); }
		json_out(array('ok' => true, 'message' => $res['message']));
		break;

	case 'unsend':
		$res = Chat::unsend($me, isset($_POST['id']) ? (int)$_POST['id'] : 0);
		if (isset($res['error'])) { json_out(array('error' => 'unsend', 'message' => $res['error']), 422); }
		json_out(array('ok' => true, 'message' => $res['message']));
		break;

	case 'media':
		$conv = Chat::findConversation($me, $with);
		json_out(array('ok' => true, 'items' => $conv ? Chat::media($conv->CONV_ID) : array()));
		break;

	default:
		json_out(array('error' => 'unknown_action'), 400);
}
?>