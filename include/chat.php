<?php
// ROJHON SOLUTIONS - Chat (Messages tab)
//
// Everything the chat needs from the database lives here, so the API
// endpoint (module/chat/api.php) stays a thin router. Every query uses
// bound parameters.
//
// Status model, same as Messenger:
//   Sending    the browser has not heard back from the server yet
//   Sent       saved in tblchat_messages (SENT_AT)
//   Delivered  the receiver's browser has fetched it (DELIVERED_AT). Any
//              open page of the app counts, not only the Messages tab.
//   Seen       the receiver had this conversation open and visible (SEEN_AT)
//
// Presence: a user is "Active now" while any page of the app is open and
// polling. IS_ONLINE is cleared on logout; LAST_SEEN gives "Active 5m ago".

require_once(LIB_PATH.DS.'database.php');

class Chat {

	const ONLINE_WINDOW = 30;       // seconds without a heartbeat before a user counts as offline
	const TYPING_WINDOW = 6;        // seconds a typing ping stays valid
	const MAX_FILE_BYTES = 10485760; // 10 MB
	const PAGE_SIZE = 40;

	public static $imageMimes = array(
		'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
		'image/webp' => 'webp', 'image/bmp' => 'bmp'
	);

	public static $fileExtensions = array(
		'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf',
		'odt', 'ods', 'odp', 'zip', 'rar', '7z', 'mp3', 'wav', 'm4a', 'ogg',
		'mp4', 'webm', 'mov', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'
	);

	public static $reactionSet = array('👍', '❤️', '😆', '😮', '😢', '😡');

	/* ------------------------------------------------------------ */
	/* helpers                                                      */
	/* ------------------------------------------------------------ */

	static function db() {
		global $mydb;
		return $mydb;
	}

	static function pair($a, $b) {
		$a = (int)$a; $b = (int)$b;
		return ($a < $b) ? array($a, $b) : array($b, $a);
	}

	static function secondsAgo($seconds) {
		$d = new DateTime('now');
		$d->modify('-'.(int)$seconds.' seconds');
		return $d->format('Y-m-d H:i:s.v');
	}

	static function uploadDir() {
		return SITE_ROOT.DS.'module'.DS.'chat'.DS.'uploads'.DS;
	}

	static function fileUrl($msgId, $download = false) {
		return WEB_ROOT.'module/chat/api.php?act=file&id='.(int)$msgId.($download ? '&dl=1' : '');
	}

	/* Largest upload the server will actually accept, so the UI can warn
	   before sending instead of failing after a long upload. */
	static function maxUploadBytes() {
		$limits = array(self::MAX_FILE_BYTES);
		foreach (array('upload_max_filesize', 'post_max_size') as $key) {
			$v = trim((string)ini_get($key));
			if ($v === '') { continue; }
			$unit = strtolower(substr($v, -1));
			$n = (float)$v;
			if ($unit === 'g') { $n *= 1073741824; }
			elseif ($unit === 'm') { $n *= 1048576; }
			elseif ($unit === 'k') { $n *= 1024; }
			if ($n > 0) { $limits[] = (int)$n; }
		}
		return min($limits);
	}

	/* ------------------------------------------------------------ */
	/* users and presence                                           */
	/* ------------------------------------------------------------ */

	static function isOnline($isOnlineFlag, $lastSeen) {
		if ((int)$isOnlineFlag !== 1 || !$lastSeen) { return false; }
		return $lastSeen >= self::secondsAgo(self::ONLINE_WINDOW);
	}

	static function userCard($row) {
		$lastSeen = isset($row->LAST_SEEN) ? $row->LAST_SEEN : null;
		return array(
			'uid'       => (int)$row->UID,
			'name'      => (string)$row->DISPLAYNAME,
			'username'  => (string)$row->USERNAME,
			'type'      => (string)$row->TYPE,
			'photo'     => user_photo_url(isset($row->PHOTO) ? $row->PHOTO : ''),
			'active'    => (int)$row->STATUSACTIVE === 1,
			'online'    => self::isOnline(isset($row->IS_ONLINE) ? $row->IS_ONLINE : 0, $lastSeen),
			'last_seen' => dt_to_ms($lastSeen)
		);
	}

	static function getUser($uid) {
		return self::db()->fetchOne(
			"SELECT u.UID, u.DISPLAYNAME, u.USERNAME, u.TYPE, u.PHOTO, u.STATUSACTIVE, p.IS_ONLINE, p.LAST_SEEN
			   FROM tblusers u LEFT JOIN tblchat_presence p ON p.UID = u.UID
			  WHERE u.UID = ? LIMIT 1", array((int)$uid));
	}

	/* Everyone the current user can start a chat with. */
	static function listUsers($me) {
		$rows = self::db()->fetchAll(
			"SELECT u.UID, u.DISPLAYNAME, u.USERNAME, u.TYPE, u.PHOTO, u.STATUSACTIVE, p.IS_ONLINE, p.LAST_SEEN
			   FROM tblusers u LEFT JOIN tblchat_presence p ON p.UID = u.UID
			  WHERE u.UID <> ? AND u.STATUSACTIVE = 1
			  ORDER BY u.DISPLAYNAME ASC", array((int)$me));
		$out = array();
		foreach ($rows as $r) { $out[] = self::userCard($r); }
		return $out;
	}

	/* Compact map uid => [online, last_seen] used to refresh green dots. */
	static function presenceMap($me) {
		$rows = self::db()->fetchAll(
			"SELECT u.UID, p.IS_ONLINE, p.LAST_SEEN FROM tblusers u
			   LEFT JOIN tblchat_presence p ON p.UID = u.UID
			  WHERE u.UID <> ?", array((int)$me));
		$out = array();
		foreach ($rows as $r) {
			$out[(int)$r->UID] = array(
				'online'    => self::isOnline($r->IS_ONLINE, $r->LAST_SEEN),
				'last_seen' => dt_to_ms($r->LAST_SEEN)
			);
		}
		return $out;
	}

	/* Heartbeat. $typingTo: null = leave unchanged, 0 = stopped typing,
	   >0 = typing to that user right now. */
	static function touchPresence($uid, $typingTo = null) {
		$now = now_ms_string();
		if ($typingTo === null) {
			self::db()->execute(
				"INSERT INTO tblchat_presence (UID, IS_ONLINE, LAST_SEEN) VALUES (?, 1, ?)
				 ON DUPLICATE KEY UPDATE IS_ONLINE = 1, LAST_SEEN = VALUES(LAST_SEEN)",
				array((int)$uid, $now));
			return;
		}
		$typingTo = (int)$typingTo;
		self::db()->execute(
			"INSERT INTO tblchat_presence (UID, IS_ONLINE, LAST_SEEN, TYPING_TO, TYPING_AT) VALUES (?, 1, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE IS_ONLINE = 1, LAST_SEEN = VALUES(LAST_SEEN),
			                         TYPING_TO = VALUES(TYPING_TO), TYPING_AT = VALUES(TYPING_AT)",
			array((int)$uid, $now, $typingTo > 0 ? $typingTo : null, $typingTo > 0 ? $now : null));
	}

	static function setOffline($uid) {
		self::db()->execute(
			"INSERT INTO tblchat_presence (UID, IS_ONLINE, LAST_SEEN) VALUES (?, 0, ?)
			 ON DUPLICATE KEY UPDATE IS_ONLINE = 0, LAST_SEEN = VALUES(LAST_SEEN), TYPING_TO = NULL, TYPING_AT = NULL",
			array((int)$uid, now_ms_string()));
	}

	static function isTyping($partner, $me) {
		$row = self::db()->fetchOne("SELECT TYPING_TO, TYPING_AT FROM tblchat_presence WHERE UID = ?", array((int)$partner));
		if (!$row || (int)$row->TYPING_TO !== (int)$me || !$row->TYPING_AT) { return false; }
		return $row->TYPING_AT >= self::secondsAgo(self::TYPING_WINDOW);
	}

	/* ------------------------------------------------------------ */
	/* delivery and seen receipts                                   */
	/* ------------------------------------------------------------ */

	static function markDelivered($me) {
		$now = now_ms_string();
		return self::db()->execute(
			"UPDATE tblchat_messages SET DELIVERED_AT = ?, UPDATED_AT = ?
			  WHERE RECEIVER_ID = ? AND DELIVERED_AT IS NULL",
			array($now, $now, (int)$me));
	}

	static function markSeen($me, $convId) {
		$now = now_ms_string();
		return self::db()->execute(
			"UPDATE tblchat_messages
			    SET SEEN_AT = ?, DELIVERED_AT = COALESCE(DELIVERED_AT, ?), UPDATED_AT = ?
			  WHERE CONV_ID = ? AND RECEIVER_ID = ? AND SEEN_AT IS NULL",
			array($now, $now, $now, (int)$convId, (int)$me));
	}

	static function totalUnread($me) {
		return (int)self::db()->fetchValue(
			"SELECT COUNT(DISTINCT CONV_ID) FROM tblchat_messages
			  WHERE RECEIVER_ID = ? AND SEEN_AT IS NULL AND IS_UNSENT = 0", array((int)$me));
	}

	/* ------------------------------------------------------------ */
	/* conversations                                                */
	/* ------------------------------------------------------------ */

	static function findConversation($me, $partner) {
		list($one, $two) = self::pair($me, $partner);
		return self::db()->fetchOne("SELECT * FROM tblchat_conversations WHERE USER_ONE = ? AND USER_TWO = ? LIMIT 1", array($one, $two));
	}

	static function getOrCreateConversation($me, $partner) {
		$conv = self::findConversation($me, $partner);
		if ($conv) { return $conv; }
		list($one, $two) = self::pair($me, $partner);
		$now = now_ms_string();
		self::db()->execute(
			"INSERT IGNORE INTO tblchat_conversations (USER_ONE, USER_TWO, CREATED_AT, UPDATED_AT) VALUES (?, ?, ?, ?)",
			array($one, $two, $now, $now));
		return self::findConversation($me, $partner);
	}

	static function isParticipant($conv, $uid) {
		return $conv && ((int)$conv->USER_ONE === (int)$uid || (int)$conv->USER_TWO === (int)$uid);
	}

	/* Conversation list for the left column and the navbar dropdown. */
	static function inbox($me, $limit = 60) {
		$me = (int)$me;
		$rows = self::db()->fetchAll(
			"SELECT c.CONV_ID, c.UPDATED_AT AS CONV_UPDATED,
			        u.UID, u.DISPLAYNAME, u.USERNAME, u.TYPE, u.PHOTO, u.STATUSACTIVE,
			        p.IS_ONLINE, p.LAST_SEEN,
			        m.MSG_ID, m.SENDER_ID, m.MSG_TYPE, m.BODY, m.FILE_NAME, m.IS_UNSENT,
			        m.SENT_AT, m.DELIVERED_AT, m.SEEN_AT,
			        (SELECT COUNT(*) FROM tblchat_messages x
			          WHERE x.CONV_ID = c.CONV_ID AND x.RECEIVER_ID = ? AND x.SEEN_AT IS NULL AND x.IS_UNSENT = 0) AS UNREAD
			   FROM tblchat_conversations c
			   JOIN tblusers u ON u.UID = IF(c.USER_ONE = ?, c.USER_TWO, c.USER_ONE)
			   LEFT JOIN tblchat_presence p ON p.UID = u.UID
			   LEFT JOIN tblchat_messages m ON m.MSG_ID = c.LAST_MSG_ID
			  WHERE (c.USER_ONE = ? OR c.USER_TWO = ?) AND c.LAST_MSG_ID IS NOT NULL
			  ORDER BY c.UPDATED_AT DESC
			  LIMIT ".(int)$limit,
			array($me, $me, $me, $me));

		$out = array();
		foreach ($rows as $r) {
			$card = self::userCard($r);
			$body = ($r->IS_UNSENT ? null : $r->BODY);
			if ($body !== null && mb_strlen($body) > 140) { $body = mb_substr($body, 0, 140).'…'; }
			$out[] = array(
				'conv'    => (int)$r->CONV_ID,
				'partner' => $card,
				'unread'  => (int)$r->UNREAD,
				'last'    => array(
					'id'        => (int)$r->MSG_ID,
					'from'      => (int)$r->SENDER_ID,
					'type'      => $r->MSG_TYPE,
					'body'      => $body,
					'file_name' => $r->IS_UNSENT ? null : $r->FILE_NAME,
					'unsent'    => (bool)$r->IS_UNSENT,
					'sent'      => dt_to_ms($r->SENT_AT),
					'delivered' => dt_to_ms($r->DELIVERED_AT),
					'seen'      => dt_to_ms($r->SEEN_AT)
				)
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------ */
	/* messages                                                     */
	/* ------------------------------------------------------------ */

	static function reactionsFor($ids) {
		$ids = array_values(array_filter(array_map('intval', (array)$ids)));
		if (empty($ids)) { return array(); }
		$rows = self::db()->fetchAll(
			"SELECT MSG_ID, UID, EMOJI FROM tblchat_reactions
			  WHERE MSG_ID IN (".implode(',', array_fill(0, count($ids), '?')).")
			  ORDER BY CREATED_AT ASC", $ids);
		$map = array();
		foreach ($rows as $r) {
			$map[(int)$r->MSG_ID][] = array('uid' => (int)$r->UID, 'emoji' => $r->EMOJI);
		}
		return $map;
	}

	static function formatMessages($rows) {
		$ids = array();
		foreach ($rows as $r) { $ids[] = (int)$r->MSG_ID; }
		$reactions = self::reactionsFor($ids);
		$out = array();
		foreach ($rows as $r) {
			$id = (int)$r->MSG_ID;
			$unsent = (bool)$r->IS_UNSENT;
			$file = null;
			if (!$unsent && ($r->MSG_TYPE === 'image' || $r->MSG_TYPE === 'file') && $r->FILE_PATH) {
				$file = array(
					'name'  => $r->FILE_NAME,
					'size'  => (int)$r->FILE_SIZE,
					'mime'  => $r->FILE_MIME,
					'w'     => $r->FILE_W ? (int)$r->FILE_W : null,
					'h'     => $r->FILE_H ? (int)$r->FILE_H : null,
					'url'   => self::fileUrl($id),
					'dl'    => self::fileUrl($id, true)
				);
			}
			$out[] = array(
				'id'        => $id,
				'conv'      => (int)$r->CONV_ID,
				'from'      => (int)$r->SENDER_ID,
				'to'        => (int)$r->RECEIVER_ID,
				'type'      => $r->MSG_TYPE,
				'body'      => $unsent ? null : $r->BODY,
				'file'      => $file,
				'cid'       => $r->CLIENT_ID,
				'sent'      => dt_to_ms($r->SENT_AT),
				'delivered' => dt_to_ms($r->DELIVERED_AT),
				'seen'      => dt_to_ms($r->SEEN_AT),
				'unsent'    => $unsent,
				'reactions' => isset($reactions[$id]) ? $reactions[$id] : array()
			);
		}
		return $out;
	}

	/* Newest page of a conversation, or the page older than $beforeId. */
	static function page($convId, $beforeId = 0, $limit = self::PAGE_SIZE) {
		$params = array((int)$convId);
		$where = "CONV_ID = ?";
		if ($beforeId > 0) { $where .= " AND MSG_ID < ?"; $params[] = (int)$beforeId; }
		$rows = self::db()->fetchAll(
			"SELECT * FROM tblchat_messages WHERE ".$where." ORDER BY MSG_ID DESC LIMIT ".(int)$limit, $params);
		return self::formatMessages(array_reverse($rows));
	}

	static function after($convId, $afterId) {
		$rows = self::db()->fetchAll(
			"SELECT * FROM tblchat_messages WHERE CONV_ID = ? AND MSG_ID > ? ORDER BY MSG_ID ASC LIMIT 200",
			array((int)$convId, (int)$afterId));
		return self::formatMessages($rows);
	}

	/* Messages whose status, reactions or unsend flag changed since the
	   client's cursor. A 3 second overlap covers requests that were in
	   flight at the same time; the browser de-duplicates by id. */
	static function changedSince($convId, $cursor, $upToId) {
		if (!$cursor || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/', $cursor)) { return array(); }
		$d = new DateTime($cursor);
		$d->modify('-3 seconds');
		$rows = self::db()->fetchAll(
			"SELECT * FROM tblchat_messages WHERE CONV_ID = ? AND UPDATED_AT >= ? AND MSG_ID <= ?
			  ORDER BY MSG_ID ASC LIMIT 300",
			array((int)$convId, $d->format('Y-m-d H:i:s.v'), (int)$upToId));
		return self::formatMessages($rows);
	}

	static function getMessage($id) {
		return self::db()->fetchOne("SELECT * FROM tblchat_messages WHERE MSG_ID = ? LIMIT 1", array((int)$id));
	}

	/* $upload is the validated file array from validateUpload(), or null. */
	static function send($me, $to, $body, $upload, $clientId) {
		$db = self::db();
		$me = (int)$me; $to = (int)$to;
		$body = ($body === null) ? '' : trim(str_replace("\r\n", "\n", (string)$body));
		if (mb_strlen($body) > 5000) { $body = mb_substr($body, 0, 5000); }
		$clientId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$clientId);
		$clientId = ($clientId === '') ? null : substr($clientId, 0, 40);

		if ($to <= 0 || $to === $me) { return array('error' => 'Choose someone to message.'); }
		$receiver = $db->fetchOne("SELECT UID, STATUSACTIVE FROM tblusers WHERE UID = ?", array($to));
		if (!$receiver) { return array('error' => 'That account no longer exists.'); }
		if ((int)$receiver->STATUSACTIVE !== 1) { return array('error' => 'This account is deactivated and cannot receive messages.'); }
		if ($body === '' && !$upload) { return array('error' => 'Type a message first.'); }

		/* Retried send (flaky connection): hand back the original, never a duplicate. */
		if ($clientId !== null) {
			$dupe = $db->fetchOne("SELECT * FROM tblchat_messages WHERE SENDER_ID = ? AND CLIENT_ID = ? LIMIT 1", array($me, $clientId));
			if ($dupe) {
				if ($upload && is_file($upload['tmp'])) { @unlink($upload['tmp']); }
				$msgs = self::formatMessages(array($dupe));
				return array('message' => $msgs[0]);
			}
		}

		$conv = self::getOrCreateConversation($me, $to);
		if (!$conv) { return array('error' => 'Could not open the conversation.'); }

		$stored = null;
		if ($upload) {
			$stored = self::storeUpload($upload);
			if (!$stored) { return array('error' => 'The file could not be saved on the server. Check that module/chat/uploads is writable.'); }
		}

		$now = now_ms_string();
		try {
			$db->conn->beginTransaction();
			$db->execute(
				"INSERT INTO tblchat_messages
				   (CONV_ID, SENDER_ID, RECEIVER_ID, MSG_TYPE, BODY, FILE_PATH, FILE_NAME, FILE_MIME, FILE_SIZE, FILE_W, FILE_H,
				    CLIENT_ID, SENT_AT, UPDATED_AT)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
				array(
					(int)$conv->CONV_ID, $me, $to,
					$upload ? ($upload['is_image'] ? 'image' : 'file') : 'text',
					$body === '' ? null : $body,
					$stored ? $stored : null,
					$upload ? $upload['name'] : null,
					$upload ? $upload['mime'] : null,
					$upload ? (int)$upload['size'] : null,
					($upload && $upload['w']) ? (int)$upload['w'] : null,
					($upload && $upload['h']) ? (int)$upload['h'] : null,
					$clientId, $now, $now
				));
			$id = (int)$db->insert_id();
			$db->execute("UPDATE tblchat_conversations SET LAST_MSG_ID = ?, UPDATED_AT = ? WHERE CONV_ID = ?",
				array($id, $now, (int)$conv->CONV_ID));
			/* Sending a message also means you stopped typing. */
			$db->execute("UPDATE tblchat_presence SET TYPING_TO = NULL, TYPING_AT = NULL WHERE UID = ?", array($me));
			$db->conn->commit();
		} catch (PDOException $e) {
			if ($db->conn->inTransaction()) { $db->conn->rollBack(); }
			if ($stored) { @unlink(self::uploadDir().str_replace('/', DS, $stored)); }
			error_log('[ROJHON chat] '.$e->getMessage());
			return array('error' => 'The message could not be saved.');
		}

		$msgs = self::formatMessages(array(self::getMessage($id)));
		return array('message' => $msgs[0]);
	}

	static function react($me, $msgId, $emoji) {
		$msg = self::getMessage($msgId);
		if (!$msg || ((int)$msg->SENDER_ID !== (int)$me && (int)$msg->RECEIVER_ID !== (int)$me)) {
			return array('error' => 'Message not found.');
		}
		if ((int)$msg->IS_UNSENT === 1) { return array('error' => 'This message was unsent.'); }

		$db = self::db();
		$existing = $db->fetchValue("SELECT EMOJI FROM tblchat_reactions WHERE MSG_ID = ? AND UID = ?", array((int)$msgId, (int)$me));
		if ($emoji === '' || $existing === $emoji) {
			$db->execute("DELETE FROM tblchat_reactions WHERE MSG_ID = ? AND UID = ?", array((int)$msgId, (int)$me));
		} else {
			if (!in_array($emoji, self::$reactionSet, true)) { return array('error' => 'Unknown reaction.'); }
			$db->execute(
				"INSERT INTO tblchat_reactions (MSG_ID, UID, EMOJI, CREATED_AT) VALUES (?, ?, ?, ?)
				 ON DUPLICATE KEY UPDATE EMOJI = VALUES(EMOJI), CREATED_AT = VALUES(CREATED_AT)",
				array((int)$msgId, (int)$me, $emoji, now_ms_string()));
		}
		$db->execute("UPDATE tblchat_messages SET UPDATED_AT = ? WHERE MSG_ID = ?", array(now_ms_string(), (int)$msgId));
		$msgs = self::formatMessages(array(self::getMessage($msgId)));
		return array('message' => $msgs[0]);
	}

	/* Unsend for everyone. The text is wiped and any attachment is
	   deleted from disk, not just hidden. */
	static function unsend($me, $msgId) {
		$msg = self::getMessage($msgId);
		if (!$msg || (int)$msg->SENDER_ID !== (int)$me) { return array('error' => 'You can only unsend your own messages.'); }
		if ((int)$msg->IS_UNSENT === 1) {
			$msgs = self::formatMessages(array($msg));
			return array('message' => $msgs[0]);
		}
		if ($msg->FILE_PATH) {
			$path = self::uploadDir().str_replace('/', DS, $msg->FILE_PATH);
			if (is_file($path)) { @unlink($path); }
		}
		$db = self::db();
		$db->execute(
			"UPDATE tblchat_messages SET IS_UNSENT = 1, BODY = NULL, FILE_PATH = NULL, UPDATED_AT = ? WHERE MSG_ID = ?",
			array(now_ms_string(), (int)$msgId));
		$db->execute("DELETE FROM tblchat_reactions WHERE MSG_ID = ?", array((int)$msgId));
		$msgs = self::formatMessages(array(self::getMessage($msgId)));
		return array('message' => $msgs[0]);
	}

	/* Shared photos and files for the info panel. */
	static function media($convId) {
		$rows = self::db()->fetchAll(
			"SELECT * FROM tblchat_messages
			  WHERE CONV_ID = ? AND IS_UNSENT = 0 AND MSG_TYPE IN ('image','file') AND FILE_PATH IS NOT NULL
			  ORDER BY MSG_ID DESC LIMIT 60", array((int)$convId));
		return self::formatMessages($rows);
	}

	/* Latest messages to me that I have not been notified about yet. */
	static function incomingSince($me, $afterId) {
		$rows = self::db()->fetchAll(
			"SELECT m.MSG_ID, m.SENDER_ID, m.MSG_TYPE, m.BODY, m.FILE_NAME, m.SENT_AT, u.DISPLAYNAME, u.PHOTO
			   FROM tblchat_messages m JOIN tblusers u ON u.UID = m.SENDER_ID
			  WHERE m.RECEIVER_ID = ? AND m.MSG_ID > ? AND m.IS_UNSENT = 0 AND m.SEEN_AT IS NULL
			  ORDER BY m.MSG_ID DESC LIMIT 5", array((int)$me, (int)$afterId));
		$out = array();
		foreach (array_reverse($rows) as $r) {
			$body = $r->BODY;
			if ($body !== null && mb_strlen($body) > 90) { $body = mb_substr($body, 0, 90).'…'; }
			$out[] = array(
				'id'    => (int)$r->MSG_ID,
				'from'  => (int)$r->SENDER_ID,
				'name'  => $r->DISPLAYNAME,
				'photo' => user_photo_url($r->PHOTO),
				'type'  => $r->MSG_TYPE,
				'body'  => $body,
				'file_name' => $r->FILE_NAME,
				'sent'  => dt_to_ms($r->SENT_AT)
			);
		}
		return $out;
	}

	static function lastIncomingId($me) {
		return (int)self::db()->fetchValue("SELECT COALESCE(MAX(MSG_ID), 0) FROM tblchat_messages WHERE RECEIVER_ID = ?", array((int)$me));
	}

	/* ------------------------------------------------------------ */
	/* uploads                                                      */
	/* ------------------------------------------------------------ */

	static function uploadErrorText($code) {
		switch ((int)$code) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE: return 'That file is larger than the server allows ('.self::humanSize(self::maxUploadBytes()).').';
			case UPLOAD_ERR_PARTIAL:   return 'The upload was interrupted. Please try again.';
			case UPLOAD_ERR_NO_TMP_DIR:return 'The server has no temporary folder for uploads (check php.ini upload_tmp_dir).';
			case UPLOAD_ERR_CANT_WRITE:return 'The server could not write the uploaded file to disk.';
			case UPLOAD_ERR_EXTENSION: return 'A PHP extension blocked this upload.';
		}
		return 'The file could not be uploaded.';
	}

	static function humanSize($bytes) {
		$bytes = (float)$bytes;
		if ($bytes >= 1048576) { return round($bytes / 1048576, 1).' MB'; }
		if ($bytes >= 1024) { return round($bytes / 1024).' KB'; }
		return (int)$bytes.' B';
	}

	/* Inspects an entry of $_FILES. Returns array(ok, data|error). The
	   file's real content decides whether it is an image, never the
	   extension the browser claims. SVG and anything executable is refused. */
	static function validateUpload($f) {
		if (!$f || !isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) { return array(true, null); }
		if ($f['error'] !== UPLOAD_ERR_OK) { return array(false, self::uploadErrorText($f['error'])); }
		if (!is_uploaded_file($f['tmp_name'])) { return array(false, 'Invalid upload.'); }
		if ($f['size'] <= 0) { return array(false, 'That file is empty.'); }
		if ($f['size'] > self::maxUploadBytes()) { return array(false, 'Files can be up to '.self::humanSize(self::maxUploadBytes()).'.'); }

		$name = basename(str_replace('\\', '/', (string)$f['name']));
		$name = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]+/u', '_', $name);
		if ($name === '' || $name === '.') { $name = 'file'; }
		if (mb_strlen($name) > 150) {
			$ext0 = pathinfo($name, PATHINFO_EXTENSION);
			$name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 140).($ext0 !== '' ? '.'.$ext0 : '');
		}
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

		$info = @getimagesize($f['tmp_name']);
		if ($info && isset(self::$imageMimes[$info['mime']])) {
			$realExt = self::$imageMimes[$info['mime']];
			return array(true, array(
				'tmp' => $f['tmp_name'], 'name' => $name, 'size' => (int)$f['size'],
				'mime' => $info['mime'], 'ext' => $realExt, 'is_image' => true,
				'w' => min(65535, (int)$info[0]), 'h' => min(65535, (int)$info[1])
			));
		}

		if ($ext === '' || !in_array($ext, self::$fileExtensions, true)) {
			return array(false, 'That file type is not allowed. Send images, PDF, Office documents, text, ZIP/RAR, audio or video.');
		}
		if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'), true)) {
			return array(false, 'That image file is damaged or not really an image.');
		}

		$mime = 'application/octet-stream';
		if (function_exists('finfo_open')) {
			$fi = finfo_open(FILEINFO_MIME_TYPE);
			if ($fi) {
				$m = finfo_file($fi, $f['tmp_name']);
				if ($m) { $mime = $m; }
				finfo_close($fi);
			}
		}
		/* Never keep a script-ish mime type, whatever the extension says. */
		if (preg_match('/(php|x-httpd|javascript|html|x-sh|x-msdownload|x-executable)/i', $mime)) {
			return array(false, 'That file type is not allowed.');
		}
		return array(true, array(
			'tmp' => $f['tmp_name'], 'name' => $name, 'size' => (int)$f['size'],
			'mime' => $mime, 'ext' => $ext, 'is_image' => false, 'w' => null, 'h' => null
		));
	}

	/* Moves the upload to uploads/YYYY/MM/<random>.<ext>. The random name
	   means files can never be guessed or overwritten; the original name
	   is only kept in the database for display and download. */
	static function storeUpload($u) {
		$sub = date('Y').'/'.date('m');
		$dir = self::uploadDir().str_replace('/', DS, $sub).DS;
		if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { return false; }
		$file = bin2hex(random_bytes(16)).'.'.$u['ext'];
		$ok = is_uploaded_file($u['tmp']) ? @move_uploaded_file($u['tmp'], $dir.$file) : @rename($u['tmp'], $dir.$file);
		return $ok ? $sub.'/'.$file : false;
	}
}
?>
