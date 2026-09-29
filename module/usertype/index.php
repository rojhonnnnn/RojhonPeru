<?php

//ROJHON SOLUTIONS

require_once("../../include/initialize.php");

$title   = "User Type";
$content = 'list.php';
require_once ("../../theme/template.php");

?>
  
 <script type="text/javascript">
  $(document).ready(function() {
    $('#tbluser').DataTable({
      "processing": true,
      "serverSide": true,
      "ajax": { url: "<?php echo WEB_ROOT; ?>module/usertype/usertype_ajax.php", type: "POST" },
      "columnDefs": [ { "orderable": false, "targets": [0, 3] } ],
      "order": [[ 1, 'asc' ]],
      "scrollY": "400px",
      "scrollCollapse": true
    });
  });
</script>
          
       
  <script type="text/javascript">
  $(document).on('click', '.editEntry', function(){
    var TYPEID = $(this).attr("TYPEID");
    $.ajax({
      url:"<?php echo WEB_ROOT; ?>module/usertype/usertype_ajax.php",
      method:"POST",
      data:{TYPEID:TYPEID},
      dataType:"json",
      success:function(data)
      {
       $('#editEntry').modal('show');
       $('#TYPEID').val(data.TYPEID);
       $('#USERTYPE').val(data.USERTYPE);
       $('#STATUS').val(data.STATUS);
        $('.modal-title').text("Modify User Type");
      
      }
    })
  });
</script>

<script type="text/javascript">
  $(document).on('click', '.deleteEntry', function(){
    var TYPEID = $(this).attr("TYPEID");
    Swal.fire({
      title: 'Delete User Type?',
      text: "This action cannot be undone. Are you sure you want to delete this user type?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.value) {
        window.location.href = "<?php echo WEB_ROOT; ?>module/usertype/controller.php?action=delete&id=" + TYPEID;
      }
    });
  });
</script>