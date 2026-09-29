<?php 
require_once("../../include/initialize.php");
global $mydb;

$act = isset($_POST['act']) ? $_POST['act'] : '';

/* -----------------------------------------------------------------
   STAGE 1 (Reserve): details for the Reserve Slot modal.

   The section lookup that used to live here has MOVED to
   module/enrollment/ajax.php, because sectioning is now the second
   stage and belongs to the Enrollment screen.
   ----------------------------------------------------------------- */
if ($act === 'register_info') {

	$sid = intval($_POST['UID']);
	$output = array();

	$mydb->setQuery("SELECT * FROM `tblstudent` WHERE S_ID = '".$sid."' LIMIT 1");
	foreach ($mydb->loadResultList() as $row) {
		$output['S_ID']      = $row->S_ID;
		$output['IDNO']      = $row->IDNO;
		$output['FULLNAME']  = trim($row->LNAME.', '.$row->FNAME.' '.$row->MNAME);
		$output['COURSE_ID'] = $row->COURSE_ID;
	}

	/* Default to the school year flagged Active, and offer its label as
	   the curriculum year, so the clerk is not retyping it every time. */
	$output['ACTIVE_SY']  = '';
	$output['ACTIVE_AY']  = '';
	$mydb->setQuery("SELECT SY_ID, SCHOOL_YEAR FROM `tblschoolyear` WHERE STATUS = 'Active' ORDER BY SY_ID DESC LIMIT 1");
	foreach ($mydb->loadResultList() as $row) {
		$output['ACTIVE_SY'] = $row->SY_ID;
		$output['ACTIVE_AY'] = $row->SCHOOL_YEAR;
	}

	/* A student who has never been enrolled is New, otherwise Old. Just a
	   starting suggestion, the clerk can still change it. */
	$output['SUGGEST_CATEGORY'] = 'New';
	$mydb->setQuery("SELECT ENROLLMENT_ID FROM `tblenrollment` WHERE S_ID = '".$sid."' LIMIT 1");
	if ($mydb->num_rows() > 0) {
		$output['SUGGEST_CATEGORY'] = 'Old';
	}

	json_out($output);
	exit;
}

if (isset($_POST['UID'])) {
	$output = array();
	$sid = intval($_POST["UID"]);
	$query =	"SELECT * FROM `tblstudent` 
		WHERE S_ID = '".$sid."' 
		LIMIT 1";
	$mydb->setQuery($query);
	$result = $mydb->loadResultList();

	foreach($result as $row)
	{ 
		$output["UID"]   = $row->S_ID;
		$output["IDNO"]  = $row->IDNO;
		$output["FNAME"] = $row->FNAME;
		$output["MNAME"] = $row->MNAME;
		$output["LNAME"] = $row->LNAME;
		$output["SEX"]   = $row->SEX;

		/* BDAY is a DATE column. An <input type="date"> only accepts
		   YYYY-MM-DD, so normalize anything odd (0000-00-00, NULL) to
		   an empty string instead of feeding the input a bad value. */
		$bday = $row->BDAY;
		if ($bday === null || $bday == '0000-00-00' || $bday == '') {
			$output["BDAY"] = '';
		} else {
			$output["BDAY"] = substr($bday, 0, 10);
		}

		/* Additional profile/contact fields, so the Edit modal can be
		   pre-filled instead of always opening blank. NULL columns come
		   back from the DB as PHP null, which json_encode renders as
		   JSON null - coerce to '' so the JS side can just drop them
		   straight into text inputs without a null check on every field. */
		$output["BPLACE"]      = $row->BPLACE      !== null ? $row->BPLACE      : '';
		$output["NATIONALITY"] = $row->NATIONALITY !== null ? $row->NATIONALITY : '';
		$output["RELIGION"]    = $row->RELIGION    !== null ? $row->RELIGION    : '';
		$output["CONTACT_NO"]  = $row->CONTACT_NO  !== null ? $row->CONTACT_NO  : '';
		$output["EMAIL"]       = $row->EMAIL       !== null ? $row->EMAIL       : '';
		$output["HOME_ADD"]    = $row->HOME_ADD    !== null ? $row->HOME_ADD    : '';
		$output["STATUS"]      = $row->STATUS      !== null ? $row->STATUS      : 'Active';
		$output["LRNNO"]       = $row->LRNNO       !== null ? $row->LRNNO       : '';
		$output["COURSE_ID"]   = $row->COURSE_ID   !== null ? $row->COURSE_ID   : '';
		$output["AGE"]         = $row->AGE         !== null ? $row->AGE         : '';

		/* Resolve the student's current photo the same way view.php does,
		   so the Edit modal preview shows the real photo instead of the
		   default silhouette, and so "cancel the file picker" in the Edit
		   modal reverts to this instead of losing the photo visually. */
		$output["PHOTO_URL"] = '';
		if (!empty($row->PHOTO)) {
			$safe = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$row->PHOTO);
			$dir  = __DIR__.DIRECTORY_SEPARATOR.'image'.DIRECTORY_SEPARATOR;
			if ($safe !== '' && is_file($dir.$safe)) {
				$output["PHOTO_URL"] = WEB_ROOT.'module/student/image/'.$safe.'?v='.filemtime($dir.$safe);
			}
		}
	}
	json_out($output);
} else {
	/* DataTables list. Columns: #, ID No., Last, First, Middle, Sex, Birthday, Course, Action */
	$dt = dt_params(array(1 => 's.IDNO', 2 => 's.LNAME', 3 => 's.FNAME', 4 => 's.MNAME', 5 => 's.SEX', 6 => 's.BDAY', 7 => 'c.COURSE_CODE'), 's.LNAME ASC');
	$where = '';
	$params = array();
	if ($dt['search'] !== '') {
		$where = " WHERE (s.LNAME LIKE ? OR s.FNAME LIKE ? OR s.MNAME LIKE ? OR s.IDNO LIKE ? OR c.COURSE_CODE LIKE ?)";
		$like = '%'.$dt['search'].'%';
		$params = array($like, $like, $like, $like, $like);
	}
	$from = " FROM tblstudent s LEFT JOIN tblcourses c ON c.COURSE_ID = s.COURSE_ID";
	$total    = $mydb->fetchValue("SELECT COUNT(*) FROM tblstudent");
	$filtered = $mydb->fetchValue("SELECT COUNT(*)".$from.$where, $params);
	$rows     = $mydb->fetchAll("SELECT s.S_ID, s.IDNO, s.LNAME, s.FNAME, s.MNAME, s.SEX, s.BDAY, c.COURSE_CODE".$from.$where." ORDER BY ".$dt['order'].$dt['limit'], $params);

	$data = array();
	$i = $dt['start'] + 1;
	foreach ($rows as $r) {
		$data[] = array(
			$i++,
			h($r->IDNO),
			h($r->LNAME),
			h($r->FNAME),
			h($r->MNAME),
			h($r->SEX),
			$r->BDAY ? h(date('M j, Y', strtotime($r->BDAY))) : '<span class="text-muted">-</span>',
			$r->COURSE_CODE ? h($r->COURSE_CODE) : '<span class="text-muted">-</span>',
			'<button type="button" UID="'.(int)$r->S_ID.'" class="btn btn-warning btn-xs editEntry" title="Edit"><span class="fa fa-edit fw-fa"></span></button>
			<a href="index.php?view=view&id='.(int)$r->S_ID.'" class="btn btn-info btn-xs" title="View"><span class="fa fa-eye"></span></a>
			<button type="button" UID="'.(int)$r->S_ID.'" class="btn btn-success btn-xs registerEntry" title="Register for enrollment"><span class="fa fa-clipboard-check fw-fa"></span></button>
			<button type="button" UID="'.(int)$r->S_ID.'" data-name="'.h($r->LNAME.', '.$r->FNAME).'" class="btn btn-danger btn-xs deleteStudent" title="Delete"><span class="fa fa-trash fw-fa"></span></button>'
		);
	}
	dt_output($dt['draw'], $data, $total, $filtered);
}
?>