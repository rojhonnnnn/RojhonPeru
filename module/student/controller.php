<?php
require_once ("../../include/initialize.php");
// ROJHON SOLUTIONS - Student controller (add, edit, register, delete)

$action = (isset($_GET['action']) && $_GET['action'] != '') ? $_GET['action'] : '';

switch ($action) {
	case 'add' :
	doInsert();
	break;
	
	case 'edit' :
	doEdit();
	break;
	
	case 'delete' :
	doDelete();
	break;

	case 'register' :
	doRegister();
	break;
	 
	}
    
	/* ---------------------------------------------------------------------
	   STUDENT PHOTO UPLOAD

	   Split into two steps on purpose:

	   validate_student_photo() only INSPECTS the upload - checks its real
	   content (not the filename extension the browser sent), its size,
	   and returns which extension to save it with. It never touches disk.

	   store_student_photo() does the actual move_uploaded_file(). PHP only
	   allows that to happen once per upload, and the final filename is
	   named after the student's own S_ID - which does not exist until
	   AFTER the INSERT - so validation has to happen first and the move
	   has to happen after, or the file would already be gone by the time
	   the real S_ID is known.
	   --------------------------------------------------------------------- */
	function validate_student_photo($file) {
		if (!isset($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
			return ''; // nothing chosen - photo is optional, not an error
		}
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return false;
		}
		if ($file['size'] > 3 * 1024 * 1024) { // 3MB cap
			return false;
		}
		// getimagesize() reads the actual file header, so a renamed .php
		// cannot pass itself off as a .jpg here.
		$info = @getimagesize($file['tmp_name']);
		if ($info === false) {
			return false;
		}
		$allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png');
		return isset($allowed[$info['mime']]) ? $allowed[$info['mime']] : false;
	}

	function store_student_photo($file, $ext, $sid) {
		$dir = __DIR__.DIRECTORY_SEPARATOR.'image'.DIRECTORY_SEPARATOR;

		// Clear out any older photo for this student under a different
		// extension, so replacing a .jpg with a .png does not leave both
		// files sitting in the folder.
		foreach (array('jpg', 'png') as $oldExt) {
			$old = $dir.'S_'.$sid.'.'.$oldExt;
			if (is_file($old)) { @unlink($old); }
		}

		$filename = 'S_'.$sid.'.'.$ext;
		if (!move_uploaded_file($file['tmp_name'], $dir.$filename)) {
			return false;
		}
		return $filename;
	}

	/* Reads the Add form (no suffix) or the Edit form (suffix "1").
	   Blank optional fields become NULL instead of empty strings, and a
	   blank date is never written as 0000-00-00. */
	function student_form($sfx) {
		$get = function ($k) use ($sfx) { return isset($_POST[$k.$sfx]) ? trim((string)$_POST[$k.$sfx]) : ''; };
		$nul = function ($v) { return ($v === '') ? null : $v; };
		$sex = $get('SEX');
		$bday = $get('BDAY');
		if ($bday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bday)) { $bday = ''; }
		$age = $get('AGE');
		if ($age === '' && $bday !== '') {
			$b = new DateTime($bday); $t = new DateTime('today');
			if ($b <= $t) { $age = (string)$b->diff($t)->y; }
		}
		$course = (int)$get('COURSE_ID');
		$status = $get('STATUS');
		return array(
			'IDNO'        => $get('IDNO'),
			'LRNNO'       => $nul($get('LRN_NO')),
			'FNAME'       => $get('FNAME'),
			'MNAME'       => $get('MNAME'),
			'LNAME'       => $get('LNAME'),
			'SEX'         => in_array($sex, array('Male', 'Female'), true) ? $sex : null,
			'BDAY'        => $nul($bday),
			'AGE'         => ($age === '') ? null : (int)$age,
			'BPLACE'      => $nul($get('BPLACE')),
			'NATIONALITY' => $nul($get('NATIONALITY')),
			'RELIGION'    => $nul($get('RELIGION')),
			'CONTACT_NO'  => $nul($get('CONTACT_NO')),
			'EMAIL'       => $nul($get('EMAIL')),
			'HOME_ADD'    => $nul($get('HOME_ADD')),
			'COURSE_ID'   => $course > 0 ? $course : null,
			'STATUS'      => in_array($status, array('Active', 'Inactive'), true) ? $status : 'Active'
		);
	}

	function doInsert(){
		global $mydb;
		$f = student_form('');

		if ($f['IDNO'] === '' || $f['FNAME'] === '' || $f['LNAME'] === '') {
			message("Student ID number, first name and last name are required.", "error");
			redirect('index.php'); return;
		}
		if ($mydb->fetchValue("SELECT COUNT(*) FROM tblstudent WHERE IDNO = ?", array($f['IDNO']))) {
			message("Student ID number [".$f['IDNO']."] already exists!", "error");
			redirect('index.php'); return;
		}

		/* Validate the photo BEFORE creating the record, so a bad upload
		   never leaves a half-finished student behind. */
		$photoFile = isset($_FILES['PHOTO']) ? $_FILES['PHOTO'] : null;
		$photoExt  = validate_student_photo($photoFile);
		if ($photoExt === false) {
			message("Photo must be a JPG or PNG file, up to 3MB.", "error");
			redirect('index.php'); return;
		}

		if ($f['SEX'] === null) { $f['SEX'] = 'Male'; }
		$f['AddedBy'] = (int)$_SESSION['UID'];
		$cols = array_keys($f);
		try {
			$mydb->execute("INSERT INTO tblstudent (`".implode("`, `", $cols)."`) VALUES (".implode(', ', array_fill(0, count($cols), '?')).")", array_values($f));
		} catch (PDOException $e) {
			message("The student could not be saved: ".$e->getMessage(), "error");
			redirect('index.php'); return;
		}
		$newId = (int)$mydb->insert_id();

		if ($photoExt !== '') {
			$savedName = store_student_photo($photoFile, $photoExt, $newId);
			if ($savedName !== false) {
				$mydb->execute("UPDATE tblstudent SET PHOTO = ? WHERE S_ID = ?", array($savedName, $newId));
			}
		}
		message("New Student [".$f['IDNO']."] has been created successfully!", "success");
		redirect('index.php');
	}

	function doEdit(){
		global $mydb;
		$sid = isset($_POST['UID']) ? (int)$_POST['UID'] : 0;
		if ($sid <= 0 || !$mydb->fetchValue("SELECT COUNT(*) FROM tblstudent WHERE S_ID = ?", array($sid))) {
			message("That student record no longer exists.", "error");
			redirect('index.php'); return;
		}
		$f = student_form('1');
		if ($f['IDNO'] === '' || $f['FNAME'] === '' || $f['LNAME'] === '') {
			message("Student ID number, first name and last name are required.", "error");
			redirect('index.php'); return;
		}
		if ($mydb->fetchValue("SELECT COUNT(*) FROM tblstudent WHERE IDNO = ? AND S_ID <> ?", array($f['IDNO'], $sid))) {
			message("Another student already uses ID number [".$f['IDNO']."].", "error");
			redirect('index.php'); return;
		}
		/* A blank gender in the form never wipes the stored one. */
		if ($f['SEX'] === null) { unset($f['SEX']); }

		$photoFile = isset($_FILES['PHOTO']) ? $_FILES['PHOTO'] : null;
		$photoExt  = validate_student_photo($photoFile);
		if ($photoExt === false) {
			message("Photo must be a JPG or PNG file, up to 3MB.", "error");
			redirect('index.php'); return;
		}

		$sets = array(); $vals = array();
		foreach ($f as $k => $v) { $sets[] = "`".$k."` = ?"; $vals[] = $v; }
		$vals[] = $sid;
		try {
			$mydb->execute("UPDATE tblstudent SET ".implode(', ', $sets)." WHERE S_ID = ?", $vals);
		} catch (PDOException $e) {
			message("The student could not be updated: ".$e->getMessage(), "error");
			redirect('index.php'); return;
		}
		if ($photoExt !== '') {
			$savedName = store_student_photo($photoFile, $photoExt, $sid);
			if ($savedName !== false) {
				$mydb->execute("UPDATE tblstudent SET PHOTO = ? WHERE S_ID = ?", array($savedName, $sid));
			}
		}
		message("Details have been updated successfully!", "success");
		redirect('index.php');
	}


	/* -------------------------------------------------------------------
	   STAGE 1 of the enrollment flow: REGISTER

	   Creates the tblenrollment row with STATUS = Registered. SECTION_ID
	   and DATE_ENROLLED are deliberately left NULL - they get filled in
	   during Stage 2 (Sectioning) on the Enrollment screen.

	   Ends by sending the user to Enrollment so the new registration is
	   right in front of them, ready to be sectioned.
	   ------------------------------------------------------------------- */
	function doRegister(){

		global $mydb;

		$S_ID       = isset($_POST['R_SID'])           ? intval($_POST['R_SID'])          : 0;
		$SY_ID      = isset($_POST['R_SY'])            ? intval($_POST['R_SY'])           : 0;
		$COURSE_ID  = isset($_POST['R_COURSE'])        ? intval($_POST['R_COURSE'])       : 0;
		$YEAR_LEVEL = isset($_POST['R_YEARLEVEL'])     ? trim($_POST['R_YEARLEVEL'])      : '';
		$SEMESTER   = isset($_POST['R_SEMESTER'])      ? trim($_POST['R_SEMESTER'])       : '';
		$CATEGORY   = isset($_POST['R_CATEGORY'])      ? trim($_POST['R_CATEGORY'])       : 'New';
		$CURRICULUM = isset($_POST['R_CURRICULUM'])    ? trim($_POST['R_CURRICULUM'])     : '';
		$RESERVED   = isset($_POST['R_DATE_RESERVED']) ? trim($_POST['R_DATE_RESERVED'])  : '';

		$enrollmentPage = WEB_ROOT.'module/enrollment/index.php';

		if ($S_ID <= 0 || $SY_ID <= 0 || $COURSE_ID <= 0 || $YEAR_LEVEL == '' || $SEMESTER == '') {
			message("Please complete all the reservation fields.", "error");
			redirect('index.php');
			return;
		}

		if ($RESERVED == '') { $RESERVED = date('Y-m-d'); }

		/* One registration per student per school year per semester. The
		   database also enforces this with a unique key, but catching it
		   here gives a readable message instead of a PDO error. */
		$mydb->setQuery("SELECT ENROLLMENT_ID FROM `tblenrollment` 
			WHERE S_ID     = '".$S_ID."' 
			  AND SY_ID    = '".$SY_ID."' 
			  AND SEMESTER = '".$mydb->escape_value($SEMESTER)."' 
			LIMIT 1");
		if ($mydb->num_rows() >= 1) {
			message("This student already has a record for that academic year and semester.", "error");
			redirect('index.php');
			return;
		}

		$encodedBy = isset($_SESSION['UID']) ? intval($_SESSION['UID']) : 0;
		$encodedSql = ($encodedBy > 0) ? "'".$encodedBy."'" : "NULL";

		$curriculumSql = ($CURRICULUM == '') ? "NULL" : "'".$mydb->escape_value($CURRICULUM)."'";

		$sql = "INSERT INTO `tblenrollment` 
			(`S_ID`, `COURSE_ID`, `SECTION_ID`, `SY_ID`, `YEAR_LEVEL`, `SEMESTER`, 
			 `CATEGORY`, `CURRICULUM_YR`, `DATE_RESERVED`, `DATE_ENROLLED`, `STATUS`, `ENCODED_BY`) 
			VALUES ('".$S_ID."', '".$COURSE_ID."', NULL, '".$SY_ID."', 
			'".$mydb->escape_value($YEAR_LEVEL)."', '".$mydb->escape_value($SEMESTER)."', 
			'".$mydb->escape_value($CATEGORY)."', ".$curriculumSql.", 
			'".$mydb->escape_value($RESERVED)."', NULL, 'Registered', ".$encodedSql.")";

		$istrue = $mydb->InsertThis($sql);

		if ($istrue) {

			/* Keep the student record in step with the course they registered
			   for, so the Student list shows the right program. */
			$mydb->InsertThis("UPDATE `tblstudent` SET `COURSE_ID` = '".$COURSE_ID."' WHERE `S_ID` = '".$S_ID."'");

			message("Slot registered. Use Enrollment to complete the sectioning.", "success");
			redirect($enrollmentPage);
		} else {
			message("The registration could not be saved.", "error");
			redirect('index.php');
		}
	}


	


	function doDelete(){
		global $mydb;
		$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		$st = $mydb->fetchOne("SELECT S_ID, IDNO, PHOTO FROM tblstudent WHERE S_ID = ?", array($id));
		if (!$st) { message("That student record no longer exists.", "error"); redirect('index.php'); return; }
		/* Enrollment, grades and payments cascade from the student in the
		   database, so they go with it. */
		if ($mydb->execute("DELETE FROM tblstudent WHERE S_ID = ?", array($id))) {
			if ($st->PHOTO) {
				$file = __DIR__.DIRECTORY_SEPARATOR.'image'.DIRECTORY_SEPARATOR.preg_replace('/[^A-Za-z0-9_.-]/', '', $st->PHOTO);
				if (is_file($file) && strpos(basename($file), 'S_') === 0) { @unlink($file); }
			}
			message("Student [".$st->IDNO."] has been deleted.", "info");
		} else {
			message("The student could not be deleted.", "error");
		}
		redirect('index.php');
	}

?>