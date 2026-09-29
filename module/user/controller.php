<?php
// ROJHON SOLUTIONS - user account controller
require_once("../../include/initialize.php");

$action = isset($_GET['action']) ? $_GET['action'] : '';
switch ($action) {
	case 'add':      doInsert();     break;
	case 'edit':     doEdit();       break;
	case 'editpass': doChangePass(); break;
	case 'delete':   doDelete();     break;
	default:         redirect('index.php');
}

function back($msg, $type) {
	message($msg, $type);
	redirect('index.php');
	exit;
}

/* Resolves TYPEID to its name. TYPE (text) is still read by older pages,
   so both columns are always saved together. */
function user_type_row($typeId) {
	global $mydb;
	return $mydb->fetchOne("SELECT TYPEID, USERTYPE FROM tblusertype WHERE TYPEID = ? LIMIT 1", array((int)$typeId));
}

function clean_rfid($v) {
	$v = trim((string)$v);
	return ($v === '') ? null : substr(preg_replace('/\s+/', '', $v), 0, 50);
}

/* Photo: JPG/PNG/WEBP, up to 3 MB, checked by content. Saved as U_<uid>.<ext>. */
function user_photo_check($f) {
	if (!$f || !isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) { return ''; }
	if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024) { return false; }
	$info = @getimagesize($f['tmp_name']);
	$ok = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
	return ($info && isset($ok[$info['mime']])) ? $ok[$info['mime']] : false;
}
function user_photo_store($f, $ext, $uid) {
	$dir = __DIR__.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR;
	foreach (array('jpg', 'png', 'webp') as $old) { if (is_file($dir.'U_'.$uid.'.'.$old)) { @unlink($dir.'U_'.$uid.'.'.$old); } }
	$name = 'U_'.$uid.'.'.$ext;
	return move_uploaded_file($f['tmp_name'], $dir.$name) ? $name : false;
}
function user_photo_remove($photo) {
	$safe = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$photo);
	$path = __DIR__.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.$safe;
	if ($safe !== '' && strpos($safe, 'U_') === 0 && is_file($path)) { @unlink($path); }
}

function doInsert() {
	global $mydb;
	$name     = trim(isset($_POST['DISPLAYNAME']) ? $_POST['DISPLAYNAME'] : '');
	$username = trim(isset($_POST['USERNAME']) ? $_POST['USERNAME'] : '');
	$password = isset($_POST['PASSWORD']) ? (string)$_POST['PASSWORD'] : '';
	$type     = user_type_row(isset($_POST['TYPEID']) ? $_POST['TYPEID'] : 0);
	$rfid     = clean_rfid(isset($_POST['RFID_NUMBER']) ? $_POST['RFID_NUMBER'] : '');

	if ($name === '' || $username === '' || strlen($password) < 4 || !$type) { back("Fill in the name, username, a password of at least 4 characters, and the user type.", "error"); }
	if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) { back("Usernames use 3 to 50 letters, numbers, dots, dashes or underscores.", "error"); }
	if ($mydb->fetchValue("SELECT COUNT(*) FROM tblusers WHERE USERNAME = ?", array($username))) { back("Username [".$username."] already exists!", "error"); }
	if ($rfid !== null && $mydb->fetchValue("SELECT COUNT(*) FROM tblusers WHERE RFID_NUMBER = ?", array($rfid))) { back("That RFID card is already assigned to another account.", "error"); }

	$photo = isset($_FILES['PHOTO']) ? $_FILES['PHOTO'] : null;
	$ext = user_photo_check($photo);
	if ($ext === false) { back("Photo must be a JPG, PNG or WEBP image up to 3 MB.", "error"); }

	$today = date('Y-m-d');
	$me = (int)$_SESSION['UID'];
	$mydb->execute(
		"INSERT INTO tblusers (DISPLAYNAME, USERNAME, PASSWORD, TYPE, TYPEID, ADDEDBY, DATEADDED, MODIFIEDBY, DATEMODIFIED, STATUSACTIVE, RFID_NUMBER)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
		array($name, $username, User::hashPassword($password), $type->USERTYPE, (int)$type->TYPEID, $me, $today, $me, $today, $rfid));
	$uid = (int)$mydb->insert_id();
	if ($ext !== '') {
		$saved = user_photo_store($photo, $ext, $uid);
		if ($saved) { $mydb->execute("UPDATE tblusers SET PHOTO = ? WHERE UID = ?", array($saved, $uid)); }
	}
	back("New user [".$username."] has been created successfully!", "success");
}

function doEdit() {
	global $mydb;
	$uid      = isset($_POST['UID']) ? (int)$_POST['UID'] : 0;
	$cur      = $mydb->fetchOne("SELECT * FROM tblusers WHERE UID = ?", array($uid));
	if (!$cur) { back("That account no longer exists.", "error"); }
	$name     = trim(isset($_POST['DISPLAYNAME']) ? $_POST['DISPLAYNAME'] : '');
	$username = trim(isset($_POST['USERNAME']) ? $_POST['USERNAME'] : '');
	$type     = user_type_row(isset($_POST['TYPEID']) ? $_POST['TYPEID'] : 0);
	$rfid     = clean_rfid(isset($_POST['RFID_NUMBER']) ? $_POST['RFID_NUMBER'] : '');
	$isMe     = ($uid === (int)$_SESSION['UID']);
	/* You can never lock yourself out by switching off your own account. */
	$active   = $isMe ? 1 : (isset($_POST['status']) ? 1 : 0);

	if ($name === '' || $username === '' || !$type) { back("Name, username and user type are required.", "error"); }
	if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) { back("Usernames use 3 to 50 letters, numbers, dots, dashes or underscores.", "error"); }
	if ($mydb->fetchValue("SELECT COUNT(*) FROM tblusers WHERE USERNAME = ? AND UID <> ?", array($username, $uid))) { back("Username [".$username."] already exists!", "error"); }
	if ($rfid !== null && $mydb->fetchValue("SELECT COUNT(*) FROM tblusers WHERE RFID_NUMBER = ? AND UID <> ?", array($rfid, $uid))) { back("That RFID card is already assigned to another account.", "error"); }

	$photo = isset($_FILES['PHOTO']) ? $_FILES['PHOTO'] : null;
	$ext = user_photo_check($photo);
	if ($ext === false) { back("Photo must be a JPG, PNG or WEBP image up to 3 MB.", "error"); }

	$newPhoto = $cur->PHOTO;
	if (!empty($_POST['REMOVE_PHOTO'])) { user_photo_remove($cur->PHOTO); $newPhoto = null; }
	if ($ext !== '') {
		$saved = user_photo_store($photo, $ext, $uid);
		if ($saved) { $newPhoto = $saved; }
	}

	$mydb->execute(
		"UPDATE tblusers SET DISPLAYNAME = ?, USERNAME = ?, TYPE = ?, TYPEID = ?, RFID_NUMBER = ?, STATUSACTIVE = ?, PHOTO = ?,
		        MODIFIEDBY = ?, DATEMODIFIED = ? WHERE UID = ?",
		array($name, $username, $type->USERTYPE, (int)$type->TYPEID, $rfid, $active, $newPhoto, (int)$_SESSION['UID'], date('Y-m-d'), $uid));

	if (!$active) { Chat::setOffline($uid); }
	if ($isMe) { $_SESSION['DISPLAYNAME'] = $name; $_SESSION['USERNAME'] = $username; $_SESSION['TYPE'] = $type->USERTYPE; $_SESSION['PHOTO'] = $newPhoto; }
	back("User account has been updated successfully!", "success");
}

function doChangePass() {
	global $mydb;
	$uid = isset($_POST['UID']) ? (int)$_POST['UID'] : 0;
	$pw  = isset($_POST['PASSWORD']) ? (string)$_POST['PASSWORD'] : '';
	$pw2 = isset($_POST['CONFIRM']) ? (string)$_POST['CONFIRM'] : $pw;
	if (strlen($pw) < 4) { back("The password must be at least 4 characters.", "error"); }
	if ($pw !== $pw2) { back("The two passwords do not match.", "error"); }
	if (!$mydb->fetchValue("SELECT COUNT(*) FROM tblusers WHERE UID = ?", array($uid))) { back("That account no longer exists.", "error"); }
	$mydb->execute("UPDATE tblusers SET PASSWORD = ?, MODIFIEDBY = ?, DATEMODIFIED = ? WHERE UID = ?",
		array(User::hashPassword($pw), (int)$_SESSION['UID'], date('Y-m-d'), $uid));
	back("Password has been changed.", "success");
}

function doDelete() {
	global $mydb;
	$uid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	if ($uid === (int)$_SESSION['UID']) { back("You cannot delete the account you are logged in with.", "error"); }
	$cur = $mydb->fetchOne("SELECT UID, USERNAME, TYPE, PHOTO FROM tblusers WHERE UID = ?", array($uid));
	if (!$cur) { back("That account no longer exists.", "error"); }
	if ($cur->TYPE === 'Administrator' && (int)$mydb->fetchValue("SELECT COUNT(*) FROM tblusers WHERE TYPE = 'Administrator' AND STATUSACTIVE = 1 AND UID <> ?", array($uid)) === 0) {
		back("This is the last active Administrator. Create another administrator before deleting it.", "error");
	}
	/* Remove chat files this account sent before the rows cascade away. */
	foreach ($mydb->fetchAll("SELECT FILE_PATH FROM tblchat_messages WHERE SENDER_ID = ? AND FILE_PATH IS NOT NULL", array($uid)) as $f) {
		$p = Chat::uploadDir().str_replace('/', DS, $f->FILE_PATH);
		if (is_file($p)) { @unlink($p); }
	}
	if ($mydb->InsertThis("DELETE FROM tblusers WHERE UID = ".$uid." LIMIT 1")) {
		user_photo_remove($cur->PHOTO);
		back("User [".$cur->USERNAME."] has been deleted.", "success");
	}
	back("The account could not be deleted.", "error");
}
?>
