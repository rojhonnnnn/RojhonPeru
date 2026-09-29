<?php
// ROJHON SOLUTIONS - user accounts data
require_once("../../include/initialize.php");
global $mydb;

if (isset($_POST['UID'])) {
	$r = $mydb->fetchOne("SELECT UID, DISPLAYNAME, USERNAME, TYPE, TYPEID, STATUSACTIVE, PHOTO, RFID_NUMBER FROM tblusers WHERE UID = ? LIMIT 1", array((int)$_POST['UID']));
	if (!$r) { json_out(array('error' => 'not_found', 'message' => 'User not found.'), 404); }
	$out = (array)$r;
	/* the type is looked up by name for old accounts that only had TYPE */
	if (!$out['TYPEID']) { $out['TYPEID'] = $mydb->fetchValue("SELECT TYPEID FROM tblusertype WHERE USERTYPE = ? LIMIT 1", array($r->TYPE)); }
	$out['PHOTO_URL'] = user_photo_url($r->PHOTO);
	$out['IS_ME'] = ((int)$r->UID === (int)$_SESSION['UID']);
	unset($out['PHOTO']);
	json_out($out);
}

$dt = dt_params(array(1 => 'u.DISPLAYNAME', 2 => 'u.USERNAME', 3 => 'u.TYPE', 4 => 'u.RFID_NUMBER', 5 => 'u.STATUSACTIVE', 6 => 'u.DATEADDED'), 'u.DISPLAYNAME ASC');
$where = ''; $params = array();
if ($dt['search'] !== '') {
	$where = " WHERE (u.DISPLAYNAME LIKE ? OR u.USERNAME LIKE ? OR u.TYPE LIKE ? OR u.RFID_NUMBER LIKE ?)";
	$like = '%'.$dt['search'].'%'; $params = array($like, $like, $like, $like);
}
$from = " FROM tblusers u LEFT JOIN tblchat_presence p ON p.UID = u.UID";
$total    = $mydb->fetchValue("SELECT COUNT(*) FROM tblusers");
$filtered = $mydb->fetchValue("SELECT COUNT(*)".$from.$where, $params);
$rows     = $mydb->fetchAll("SELECT u.*, p.IS_ONLINE, p.LAST_SEEN".$from.$where." ORDER BY ".$dt['order'].$dt['limit'], $params);

$data = array(); $i = $dt['start'] + 1;
foreach ($rows as $r) {
	$photo = user_photo_url($r->PHOTO);
	$online = Chat::isOnline($r->IS_ONLINE, $r->LAST_SEEN);
	$avatar = $photo
		? '<img src="'.h($photo).'" class="img-circle mr-2" style="width:28px;height:28px;object-fit:cover" alt="">'
		: '<img src="'.WEB_ROOT.'module/user/images/default.png" class="img-circle mr-2" style="width:28px;height:28px;object-fit:cover" alt="">';
	$status = (int)$r->STATUSACTIVE === 1
		? '<span class="badge badge-success">Active</span>'
		: '<span class="badge badge-secondary">Inactive</span>';
	if ($online) { $status .= ' <span class="badge badge-info" title="Online in the system right now">Online</span>'; }
	$isMe = ((int)$r->UID === (int)$_SESSION['UID']);
	$data[] = array(
		$i++,
		$avatar.h($r->DISPLAYNAME).($isMe ? ' <small class="text-muted">(you)</small>' : ''),
		h($r->USERNAME),
		h($r->TYPE),
		$r->RFID_NUMBER ? h($r->RFID_NUMBER) : '<span class="text-muted">-</span>',
		$status,
		h($r->DATEADDED),
		'<button type="button" UID="'.(int)$r->UID.'" class="btn btn-warning btn-xs editEntry" title="Edit"><span class="fa fa-edit"></span></button>
		 <button type="button" UID="'.(int)$r->UID.'" data-name="'.h($r->DISPLAYNAME).'" class="btn btn-info btn-xs changepass" title="Change password"><span class="fa fa-key"></span></button>
		 <a href="'.WEB_ROOT.'module/chat/?u='.(int)$r->UID.'" class="btn btn-primary btn-xs'.($isMe ? ' disabled' : '').'" title="Message"><span class="fa fa-comment-dots"></span></a>
		 <button type="button" UID="'.(int)$r->UID.'" data-name="'.h($r->DISPLAYNAME).'" class="btn btn-danger btn-xs deleteEntry"'.($isMe ? ' disabled title="You cannot delete your own account"' : ' title="Delete"').'><span class="fa fa-trash"></span></button>'
	);
}
dt_output($dt['draw'], $data, $total, $filtered);
?>
