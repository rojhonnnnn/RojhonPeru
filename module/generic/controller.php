<?php
// ROJHON SOLUTIONS

require_once("../../include/initialize.php");
require_once("config.php");

$table = isset($_GET['t']) ? $_GET['t'] : '';
$cfg   = generic_table_config($table);

if (!$cfg) {
	message("Unknown table.", "error");
	redirect(WEB_ROOT."module/error/index.php?view=list");
	exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$backUrl = 'index.php?t='.urlencode($table);

switch ($action) {
	case 'add':
		doInsert($table, $cfg, $backUrl);
		break;
	case 'edit':
		doEdit($table, $cfg, $backUrl);
		break;
	case 'delete':
		doDelete($table, $cfg, $backUrl);
		break;
}

function doInsert($table, $cfg, $backUrl) {
	$rec = new GenericRecord($table);
	$data = $_POST;
	unset($data['save']);

	global $mydb;
	$ok = $rec->create($data);
	if ($ok) {
		message($cfg['title']." record has been added successfully!", "success");
	} elseif ((int)$mydb->error_no === 23000) {
		message("That ".$cfg['title']." record already exists or points to a record that is missing.", "error");
	} else {
		message("No ".$cfg['title']." record has been created. Please check the values you entered.", "error");
	}
	redirect($backUrl);
}

function doEdit($table, $cfg, $backUrl) {
	$rec = new GenericRecord($table);
	$id  = isset($_POST['record_pk']) ? $_POST['record_pk'] : '';
	$data = $_POST;
	unset($data['edit'], $data['record_pk']);

	if ($id === '') {
		message("Could not determine which record to update.", "error");
		redirect($backUrl);
		return;
	}

	global $mydb;
	$ok = $rec->update($id, $data);
	if ($ok) {
		message($cfg['title']." record has been updated successfully!", "success");
	} elseif ((int)$mydb->error_no === 23000) {
		message("That change would duplicate another ".$cfg['title']." record.", "error");
	} else {
		message("No ".$cfg['title']." record has been updated.", "error");
	}
	redirect($backUrl);
}

function doDelete($table, $cfg, $backUrl) {
	$rec = new GenericRecord($table);
	$id  = isset($_GET['id']) ? $_GET['id'] : '';

	global $mydb;
	if ($id === '') {
		message("No record selected.", "error");
	} elseif ($rec->delete($id)) {
		message($cfg['title']." record has been deleted.", "success");
	} elseif ((int)$mydb->error_no === 23000) {
		message("This ".$cfg['title']." record is still used by other records, so it cannot be deleted yet.", "error");
	} else {
		message("The record could not be deleted.", "error");
	}
	redirect($backUrl);
}
?>
