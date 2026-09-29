<?php
// Payments controller
require_once("../../include/initialize.php");
global $mydb;

$action = (isset($_GET['action']) && $_GET['action'] != '') ? $_GET['action'] : '';

switch ($action) {
	case 'add' :
		doAdd();
		break;

	case 'delete' :
		doDelete();
		break;
}


/* ---------------------------------------------------------------------
   Server-side re-check of the fixed amounts and the 1st-Year-only rule
   for Entrance Exam. The form already enforces these, but a form field
   is only as trustworthy as the browser sending it.
   --------------------------------------------------------------------- */
function doAdd() {

	global $mydb;

	$ENROLLMENT_ID = isset($_POST['P_ENROLLMENT']) ? intval($_POST['P_ENROLLMENT']) : 0;
	$FEE_TYPE      = isset($_POST['P_FEE_TYPE'])   ? trim($_POST['P_FEE_TYPE'])     : '';
	$AMOUNT        = isset($_POST['P_AMOUNT'])      ? (float)$_POST['P_AMOUNT']      : 0;
	$OR_NUMBER     = isset($_POST['P_OR_NUMBER'])   ? trim($_POST['P_OR_NUMBER'])    : '';
	$DATE_PAID     = isset($_POST['P_DATE_PAID'])   ? trim($_POST['P_DATE_PAID'])    : '';
	$REMARKS       = isset($_POST['P_REMARKS'])     ? trim($_POST['P_REMARKS'])      : '';

	$validFeeTypes = array('Enrollment Fee', 'Entrance Exam', 'Admission Fee');
	$fixedAmounts  = array('Entrance Exam' => 150.00, 'Admission Fee' => 30.00);

	if ($ENROLLMENT_ID <= 0 || !in_array($FEE_TYPE, $validFeeTypes) || $OR_NUMBER == '' || $DATE_PAID == '') {
		message("Please complete all the required payment fields.", "error");
		redirect('index.php');
		return;
	}

	/* Confirm the enrollment record actually exists, and get its year
	   level for the Entrance Exam eligibility check. */
	$mydb->setQuery("SELECT YEAR_LEVEL FROM `tblenrollment` WHERE ENROLLMENT_ID = '".$ENROLLMENT_ID."' LIMIT 1");
	$rows = $mydb->loadResultList();
	if (count($rows) < 1) {
		message("That enrollment record no longer exists.", "error");
		redirect('index.php');
		return;
	}
	$yearLevel = $rows[0]->YEAR_LEVEL;

	if ($FEE_TYPE == 'Entrance Exam' && $yearLevel != '1st Year') {
		message("Entrance Exam fee only applies to 1st Year students.", "error");
		redirect('index.php');
		return;
	}

	/* Entrance Exam and Admission Fee are fixed - the amount submitted is
	   overwritten here rather than trusted, in case the readonly field
	   was tampered with client-side. */
	if (isset($fixedAmounts[$FEE_TYPE])) {
		$AMOUNT = $fixedAmounts[$FEE_TYPE];
	}

	if ($AMOUNT <= 0) {
		message("Amount must be greater than zero.", "error");
		redirect('index.php');
		return;
	}

	/* OR numbers are unique in the database; say so plainly instead of a generic failure. */
	if ($mydb->fetchValue("SELECT COUNT(*) FROM tblpayments WHERE OR_NUMBER = ?", array($OR_NUMBER))) {
		message("OR number ".$OR_NUMBER." is already recorded. Check the receipt number.", "error");
		redirect('index.php');
		return;
	}
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $DATE_PAID)) { $DATE_PAID = date('Y-m-d'); }

	$receivedBy = isset($_SESSION['UID']) ? intval($_SESSION['UID']) : null;
	$receivedBySql = ($receivedBy) ? "'".$receivedBy."'" : "NULL";
	$remarksSql = ($REMARKS == '') ? "NULL" : "'".$mydb->escape_value($REMARKS)."'";

	$sql = "INSERT INTO `tblpayments` 
			(`ENROLLMENT_ID`, `FEE_TYPE`, `AMOUNT`, `OR_NUMBER`, `DATE_PAID`, `RECEIVED_BY`, `REMARKS`) 
		VALUES 
			('".$ENROLLMENT_ID."', '".$mydb->escape_value($FEE_TYPE)."', '".$AMOUNT."', 
			 '".$mydb->escape_value($OR_NUMBER)."', '".$mydb->escape_value($DATE_PAID)."', 
			 ".$receivedBySql.", ".$remarksSql.")";

	if ($mydb->InsertThis($sql)) {
		message($FEE_TYPE." of ₱".number_format($AMOUNT, 2)." recorded successfully.", "success");
	} else {
		message("The payment could not be saved.", "error");
	}
	redirect('index.php');
}


function doDelete() {

	global $mydb;

	$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

	if ($id <= 0) {
		message("No payment record selected.", "error");
		redirect('index.php');
		return;
	}

	if ($mydb->InsertThis("DELETE FROM `tblpayments` WHERE `PAYMENT_ID` = '".$id."'")) {
		message("Payment record deleted.", "info");
	} else {
		message("The payment record could not be deleted.", "error");
	}
	redirect('index.php');
}
?>