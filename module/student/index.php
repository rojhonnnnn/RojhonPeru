<?php
// ROJHON SOLUTIONS - Student module
require_once("../../include/initialize.php");

$view    = (isset($_GET['view']) && $_GET['view'] != '') ? $_GET['view'] : '';
$title   = "Student Module";
$content = ($view === 'view') ? 'view.php' : 'list.php';
require_once ("../../theme/template.php");

?>
  
 <script type="text/javascript">
  $(document).ready(function() {
    $('#tblstudent').DataTable({
      "processing": true,
      "serverSide": true,
      "ajax": { url: "<?php echo WEB_ROOT; ?>module/student/ajax.php", type: "POST" },
      "columnDefs": [ { "orderable": false, "targets": [0, 8] } ],
      "order": [[ 2, 'asc' ]],
      "scrollX": true,
      "scrollY": "400px",
      "scrollCollapse": true
    });
  });
</script>
        <script type="text/javascript">
          $(document).ready( function() {
      $(document).on('change', '.btn-file :file', function() {
    var input = $(this),
      label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
    input.trigger('fileselect', [label]);
    });

    $('.btn-file :file').on('fileselect', function(event, label) {
        
        var input = $(this).parents('.input-group').find(':text'),
            log = label;
        
        if( input.length ) {
            input.val(log);
        } else {
            /* photo preview shows the choice, no popup needed */
        }
      
    });
    function readURL(input, target) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            
            reader.onload = function (e) {
                $(target).attr('src', e.target.result);
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    $("#imgInp").change(function(){
        readURL(this, '#img-upload');
    });
    $("#imgInp1").change(function(){
        readURL(this, '#img-upload1');
    });   
  });
        </script>
<script type="text/javascript">
  $(document).on('click', '.editEntry', function(){
    var uid = $(this).attr("UID");
    $.ajax({
      url:"<?php echo WEB_ROOT; ?>module/student/ajax.php",
      method:"POST",
      data:{UID:uid},
      dataType:"json",
      success:function(data)
      {
       $('#UID').val(data.UID);
       $('#IDNO1').val(data.IDNO);
       $('#FNAME1').val(data.FNAME);
       $('#MNAME1').val(data.MNAME);
       $('#LNAME1').val(data.LNAME);

       /* Gender: the stored value has to match one of the <option value>
          entries exactly, otherwise the select stays on the placeholder.
          Old rows may hold junk like "Select Gen", so fall back to blank. */
       var sex = data.SEX ? $.trim(data.SEX) : '';
       if (sex !== 'Male' && sex !== 'Female') { sex = ''; }
       $('#SEX1').val(sex);

       /* Date input needs YYYY-MM-DD; ajax.php already normalizes it. */
       $('#BDAY1').val(data.BDAY ? data.BDAY : '');

       /* Additional profile/contact fields. */
       $('#BPLACE1').val(data.BPLACE ? data.BPLACE : '');
       $('#NATIONALITY1').val(data.NATIONALITY ? data.NATIONALITY : '');
       $('#RELIGION1').val(data.RELIGION ? data.RELIGION : '');
       $('#CONTACT_NO1').val(data.CONTACT_NO ? data.CONTACT_NO : '');
       $('#EMAIL1').val(data.EMAIL ? data.EMAIL : '');
       $('#HOME_ADD1').val(data.HOME_ADD ? data.HOME_ADD : '');
       $('#STATUS1').val(data.STATUS ? data.STATUS : 'Active');

       /* Clear any previously chosen file (reopening the modal for a
          different student must not carry over the last student's
          picked photo), then show this student's actual current photo -
          falling back to the default silhouette if they have none. */
       $('#imgInp1').val('');
       var photoSrc = data.PHOTO_URL ? data.PHOTO_URL : '<?php echo WEB_ROOT; ?>module/student/image/default.png';
       $('#img-upload1').attr('src', photoSrc).attr('data-current-src', photoSrc);
       $('#LRN_NO1').val(data.LRNNO || '');
       $('#COURSE_ID1').val(data.COURSE_ID || '');
       $('#AGE1').val(data.AGE === '' || data.AGE === null ? '' : data.AGE);
       $('#editEntry a[href="#editProfile"]').tab('show');

       $('#editEntry').modal('show');
      }
    })
  });
</script>

<script type="text/javascript">
/* ---------------------------------------------------------------
   STAGE 1: RESERVE
   The green Reg button opens the reservation form for that student.
   No section is picked here - that is Stage 2 on the Enrollment
   screen, so there is no dependent dropdown to load.
   --------------------------------------------------------------- */
$(document).on('click', '.registerEntry', function(){
  var uid = $(this).attr("UID");

  $.ajax({
    url:"<?php echo WEB_ROOT; ?>module/student/ajax.php",
    method:"POST",
    data:{act:'register_info', UID:uid},
    dataType:"json",
    success:function(data)
    {
      $('#R_SID').val(data.S_ID);
      $('#R_IDNO_TEXT').text(data.IDNO ? data.IDNO : '-');
      $('#R_NAME_TEXT').text(data.FULLNAME ? data.FULLNAME : '-');

      /* Reset first so a previous student's picks never carry over. */
      $('#R_SEMESTER').val('');
      $('#R_YEARLEVEL').val('');

      /* Sensible defaults: active school year, the course already on the
         student record, and the AY label as the curriculum year. */
      $('#R_SY').val(data.ACTIVE_SY ? data.ACTIVE_SY : '');
      $('#R_COURSE').val(data.COURSE_ID ? data.COURSE_ID : '');
      $('#R_CURRICULUM').val(data.ACTIVE_AY ? data.ACTIVE_AY : '');
      $('#R_CATEGORY').val(data.SUGGEST_CATEGORY ? data.SUGGEST_CATEGORY : 'New');

      $('#registerEntry').modal('show');
    },
    error:function()
    {
      alert('Could not load the student record.');
    }
  });
});
</script>

<script type="text/javascript">
  $(document).on('click', '.deleteStudent', function(){
    var sid = $(this).attr('UID'), name = $(this).attr('data-name');
    Swal.fire({
      title: 'Delete ' + name + '?',
      text: 'Their enrollment records, grades and payments will be deleted too. This cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel'
    }).then(function (r) {
      if (r.value) { window.location.href = "<?php echo WEB_ROOT; ?>module/student/controller.php?action=delete&id=" + sid; }
    });
  });
  $('#AddNewEntry').on('show.bs.modal', function () { $(this).find('a[href="#addProfile"]').tab('show'); });
</script>
