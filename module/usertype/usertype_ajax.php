<?php 

//ROJHON SOLUTIONS

require_once("../../include/initialize.php");
global $mydb;

if (isset($_POST['TYPEID'])) {
	$output = array();
	$query =	"SELECT * FROM tblusertype 
		WHERE TYPEID = '".(int)$_POST["TYPEID"]."' 
		LIMIT 1";
	$mydb->setQuery($query);
	$result = $mydb->loadResultList();

	foreach($result as $row)
	{ 
		$output["TYPEID"] = $row->TYPEID;
		$output["USERTYPE"] = $row->USERTYPE;
		$output["STATUS"] = $row->STATUS;
	}
	echo json_encode($output);
} else {
	$dt = dt_params(array(1 => 't.USERTYPE', 2 => 't.STATUS'), 't.USERTYPE ASC');
	$where = ''; $params = array();
	if ($dt['search'] !== '') { $where = " WHERE t.USERTYPE LIKE ?"; $params = array('%'.$dt['search'].'%'); }
	$total    = $mydb->fetchValue("SELECT COUNT(*) FROM tblusertype");
	$filtered = $mydb->fetchValue("SELECT COUNT(*) FROM tblusertype t".$where, $params);
	$rows     = $mydb->fetchAll("SELECT t.*, (SELECT COUNT(*) FROM tblusers u WHERE u.TYPEID = t.TYPEID) AS USERS FROM tblusertype t".$where." ORDER BY ".$dt['order'].$dt['limit'], $params);
	$data = array(); $i = $dt['start'] + 1;
	foreach ($rows as $r) {
		$data[] = array($i++, h($r->USERTYPE).' <small class="text-muted">('.(int)$r->USERS.' account'.((int)$r->USERS === 1 ? '' : 's').')</small>',
			'<span class="badge badge-'.($r->STATUS === 'Active' ? 'success' : 'secondary').'">'.h($r->STATUS).'</span>',
			'<button type="button" name="update" TYPEID="'.(int)$r->TYPEID.'" class="btn btn-warning btn-xs editEntry" title="Edit"><span class="fa fa-edit fw-fa"></span></button>
			<button type="button" name="delete" TYPEID="'.(int)$r->TYPEID.'" class="btn btn-danger btn-xs deleteEntry" title="Delete"><span class="fa fa-trash"></span></button>');
	}
	dt_output($dt['draw'], $data, $total, $filtered);
}
?>
