<?php
// ROJHON SOLUTIONS - shared helpers

function strip_zeros_from_date($marked_string = "") {
	$no_zeros = str_replace('*0', '', $marked_string);
	return str_replace('*0', '', $no_zeros);
}

function redirect_to($location = NULL) {
	if ($location != NULL) {
		header("Location: {$location}");
		exit;
	}
}

/* JavaScript redirect, used after controllers set a flash message. */
function redirect($location = null) {
	if ($location != null) {
		echo "<script>window.location=".json_encode($location).";</script>";
	} else {
		echo 'error location';
	}
}

function output_message($message = "") {
	return !empty($message) ? "<p class=\"message\">".htmlspecialchars($message)."</p>" : "";
}

function date_toText($datetime = "") {
	$nicetime = strtotime($datetime);
	return date("F d, Y \\a\\t h:i A", $nicetime);
}

function currentpage() {
	return basename($_SERVER['SCRIPT_NAME']);
}

/* URL of a static file with its modified time appended, so browsers never
   keep an old cached copy after an update. */
function asset_v($rel) {
	$f = SITE_ROOT.DS.str_replace('/', DS, $rel);
	return WEB_ROOT.$rel.(is_file($f) ? '?v='.filemtime($f) : '');
}

/* HTML-escape shortcut */
function h($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* Current time with milliseconds, in the app timezone, as stored in DATETIME(3). */
function now_ms_string() {
	return (new DateTime('now'))->format('Y-m-d H:i:s.v');
}

/* DATETIME string -> Unix epoch in milliseconds (what the browser needs). */
function dt_to_ms($dt) {
	if ($dt === null || $dt === '' || strpos((string)$dt, '0000-00-00') === 0) { return null; }
	$d = DateTime::createFromFormat('Y-m-d H:i:s.u', (string)$dt);
	if (!$d) { $d = DateTime::createFromFormat('Y-m-d H:i:s', substr((string)$dt, 0, 19)); }
	if (!$d) { return null; }
	return (int)round(((float)$d->format('U.u')) * 1000);
}

/* Web URL of a staff account photo, falling back to the default avatar. */
function user_photo_url($photo) {
	$dir = SITE_ROOT.DS.'module'.DS.'user'.DS.'images'.DS;
	$web = WEB_ROOT.'module/user/images/';
	if (!empty($photo)) {
		$safe = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$photo);
		if ($safe !== '' && is_file($dir.$safe)) {
			return $web.$safe.'?v='.filemtime($dir.$safe);
		}
	}
	return '';
}

/* ------------------------------------------------------------------
   DataTables server-side request, read safely.
   Every legacy *_ajax.php used to paste $_POST['search'], ['order']
   and ['start'] straight into SQL, which was both an SQL injection
   hole and the reason paging counts were wrong.
   ------------------------------------------------------------------ */
function dt_params($orderMap, $defaultOrder) {
	$start  = isset($_POST['start'])  ? max(0, intval($_POST['start'])) : 0;
	$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
	if ($length === 0 || $length < -1) { $length = 10; }
	if ($length > 1000) { $length = 1000; }
	$search = (isset($_POST['search']['value']) && is_string($_POST['search']['value'])) ? trim($_POST['search']['value']) : '';

	$order = $defaultOrder;
	if (isset($_POST['order'][0]['column'])) {
		$ci  = intval($_POST['order'][0]['column']);
		$dir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';
		if (isset($orderMap[$ci])) { $order = $orderMap[$ci].' '.$dir; }
	}
	return array(
		'start'  => $start,
		'length' => $length,
		'search' => $search,
		'order'  => $order,
		'limit'  => ($length === -1) ? '' : ' LIMIT '.$start.', '.$length,
		'draw'   => isset($_POST['draw']) ? intval($_POST['draw']) : 0
	);
}

function dt_output($draw, $data, $total, $filtered) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array(
		'draw'            => $draw,
		'data'            => $data,
		'recordsTotal'    => (int)$total,
		'recordsFiltered' => (int)$filtered
	));
	exit;
}

function json_out($data, $code = 200) {
	if ($code !== 200) { http_response_code($code); }
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode($data);
	exit;
}
?>
