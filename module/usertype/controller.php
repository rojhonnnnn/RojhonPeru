<?php
//ROJHON SOLUTIONS
require_once ("../../include/initialize.php");
if (!isset($_SESSION['ACCOUNT_ID'])){
    // redirect(web_root."admin/index.php");
}

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
}

function doInsert(){
	$Usertype = new Usertype();
	$USERTYPE = isset($_POST['usertype']) ? trim($_POST['usertype']) : (isset($_POST['USERTYPE']) ? trim($_POST['USERTYPE']) : '');
	if ($USERTYPE === '') { message("Enter a user type name.", "error"); redirect('index.php'); return; }
	$STATUS   = isset($_POST['STATUS']) ? $_POST['STATUS'] : 'Active';

	$res = $Usertype->find_all_usertype($USERTYPE);

	if ($res >= 1) {
		message("User type [".$USERTYPE."] already exists!", "error");
		redirect('index.php');
	} else {
		$Usertype->USERTYPE = $USERTYPE;
		$Usertype->STATUS   = $STATUS;

		$istrue = $Usertype->create();
		if ($istrue == true){
			message("New user type [". $USERTYPE ."] has been created successfully!", "success");
			redirect('index.php');
		} else {
			message("No user type has been created successfully!", "error");
			redirect('index.php');
		}
	}
}

function doEdit(){
	if (isset($_POST['edit'])) {
		$Usertype = new Usertype();
		$TYPEID   = $_POST['TYPEID'];
		$USERTYPE = $_POST['USERTYPE'];
		$STATUS   = isset($_POST['STATUS']) ? $_POST['STATUS'] : 'Active';

		$Usertype->USERTYPE = $USERTYPE;
		$Usertype->STATUS   = $STATUS;

		global $mydb;
		if ($mydb->fetchValue("SELECT COUNT(*) FROM tblusertype WHERE USERTYPE = ? AND TYPEID <> ?", array($USERTYPE, (int)$TYPEID))) {
			message("User type [".$USERTYPE."] already exists!", "error");
			redirect('index.php'); return;
		}
		$istrue = $Usertype->update($TYPEID);
		if ($istrue == true){
			/* keep the TYPE label on user accounts in step with the rename */
			$mydb->execute("UPDATE tblusers SET TYPE = ? WHERE TYPEID = ?", array($USERTYPE, (int)$TYPEID));
			message("User Type [". $USERTYPE ."] has been Updated successfully!", "success");
			redirect('index.php');
		} else {
			message("No user type has been updated successfully!", "error");
			redirect('index.php');
		}
	}
}

function doDelete(){
	global $mydb;
	$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	$inUse = (int)$mydb->fetchValue("SELECT COUNT(*) FROM tblusers WHERE TYPEID = ?", array($id));
	if ($inUse > 0) {
		message("This user type is still assigned to ".$inUse." account(s). Move them to another type first.", "error");
		redirect('index.php'); return;
	}
	$Usertype = new Usertype();
	if ($Usertype->delete($id)) { message("User type deleted.", "success"); }
	else { message("The user type could not be deleted.", "error"); }
	redirect('index.php');
}
?>
