<?php
// ROJHON SOLUTIONS - Manage User Accounts
require_once("../../include/initialize.php");

$title   = "User Module";
$content = 'list.php';
require_once("../../theme/template.php");
?>
<script type="text/javascript">
  $(document).ready(function () {
    $('#tbluser').DataTable({
      "processing": true,
      "serverSide": true,
      "ajax": { url: "<?php echo WEB_ROOT; ?>module/user/user_ajax.php", type: "POST" },
      "columnDefs": [ { "orderable": false, "targets": [0, 7] } ],
      "order": [[ 1, 'asc' ]],
      "scrollX": true
    });

    function preview(input, target) {
      if (input.files && input.files[0]) {
        var r = new FileReader();
        r.onload = function (e) { $(target).attr('src', e.target.result); };
        r.readAsDataURL(input.files[0]);
      }
    }
    $('#U_PHOTO').on('change', function () { preview(this, '#U_PHOTO_PREVIEW'); });
    $('#E_PHOTO').on('change', function () { preview(this, '#E_PHOTO_PREVIEW'); });
    $('#AddNewEntry').on('show.bs.modal', function () {
      this.querySelector('form').reset();
      $('#U_PHOTO_PREVIEW').attr('src', '<?php echo WEB_ROOT; ?>module/user/images/default.png');
    });
  });

  $(document).on('click', '.editEntry', function () {
    $.ajax({
      url: "<?php echo WEB_ROOT; ?>module/user/user_ajax.php",
      method: "POST", data: { UID: $(this).attr("UID") }, dataType: "json",
      success: function (d) {
        $('#E_UID').val(d.UID);
        $('#E_DISPLAYNAME').val(d.DISPLAYNAME);
        $('#E_USERNAME').val(d.USERNAME);
        $('#E_TYPEID').val(d.TYPEID || '');
        $('#E_RFID').val(d.RFID_NUMBER || '');
        $('#E_STATUS').prop('checked', parseInt(d.STATUSACTIVE, 10) === 1).prop('disabled', !!d.IS_ME);
        $('#E_STATUS_NOTE').toggle(!!d.IS_ME);
        $('#E_PHOTO').val('');
        $('#E_PHOTO_PREVIEW').attr('src', d.PHOTO_URL || '<?php echo WEB_ROOT; ?>module/user/images/default.png');
        $('#E_REMOVE_PHOTO').prop('checked', false).closest('.form-check').toggle(!!d.PHOTO_URL);
        $('#editEntry').modal('show');
      }
    });
  });

  $(document).on('click', '.changepass', function () {
    $('#P_UID').val($(this).attr('UID'));
    $('#P_NAME').text($(this).attr('data-name'));
    $('#P_PASSWORD, #P_CONFIRM').val('');
    $('#changepass').modal('show');
  });
  $('#changepassForm').on('submit', function (e) {
    if ($('#P_PASSWORD').val() !== $('#P_CONFIRM').val()) {
      e.preventDefault();
      Swal.fire('Passwords do not match', 'Type the same new password in both boxes.', 'error');
    }
  });

  $(document).on('click', '.deleteEntry', function () {
    var uid = $(this).attr("UID"), name = $(this).attr('data-name');
    Swal.fire({
      title: 'Delete ' + name + '?',
      text: "Their chat history and attendance records are deleted with the account. This cannot be undone.",
      icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, delete it', cancelButtonText: 'Cancel'
    }).then(function (result) {
      if (result.value) { window.location.href = "<?php echo WEB_ROOT; ?>module/user/controller.php?action=delete&id=" + uid; }
    });
  });
</script>
