<?php
// ROJHON SOLUTIONS - user account list and forms
global $mydb;
$types = $mydb->fetchAll("SELECT TYPEID, USERTYPE, STATUS FROM tblusertype ORDER BY USERTYPE ASC");
$defaultPhoto = WEB_ROOT.'module/user/images/default.png';
function user_type_options($types) {
	foreach ($types as $t) {
		echo '<option value="'.(int)$t->TYPEID.'"'.($t->STATUS !== 'Active' ? ' disabled' : '').'>'.h($t->USERTYPE).($t->STATUS !== 'Active' ? ' (inactive)' : '').'</option>';
	}
}
?>
<section class="content">
  <div class="container-fluid">
    <?php check_message(); ?>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">User Accounts</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#AddNewEntry"><i class="fas fa-user-plus"></i> Add New</button>
        </div>
      </div>
      <div class="card-body">
        <table id="tbluser" class="table table-bordered table-striped" style="width:100%">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Username</th>
              <th>User Type</th>
              <th>RFID Card</th>
              <th>Status</th>
              <th>Date Added</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- ADD -->
<div class="modal fade" id="AddNewEntry">
  <div class="modal-dialog modal-lg">
    <form action="controller.php?action=add" enctype="multipart/form-data" method="POST" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title">Add New User</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-3 text-center">
              <img id="U_PHOTO_PREVIEW" src="<?php echo $defaultPhoto; ?>" class="img-circle mb-2" style="width:100px;height:100px;object-fit:cover" alt="">
              <label class="btn btn-default btn-sm btn-block mb-0">Choose photo<input type="file" name="PHOTO" id="U_PHOTO" accept="image/jpeg,image/png,image/webp" hidden></label>
              <small class="text-muted">JPG, PNG or WEBP, up to 3 MB. Shown in Messages.</small>
            </div>
            <div class="col-md-9">
              <div class="row">
                <div class="col-sm-6 form-group">
                  <label for="U_DISPLAYNAME" class="col-form-label col-form-label-sm">Name</label>
                  <input type="text" class="form-control form-control-sm" name="DISPLAYNAME" id="U_DISPLAYNAME" maxlength="60" placeholder="Name shown in the system" required>
                </div>
                <div class="col-sm-6 form-group">
                  <label for="U_USERNAME" class="col-form-label col-form-label-sm">Username</label>
                  <input type="text" class="form-control form-control-sm" name="USERNAME" id="U_USERNAME" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" title="3 to 50 letters, numbers, dot, dash or underscore" required>
                </div>
                <div class="col-sm-6 form-group">
                  <label for="U_PASSWORD" class="col-form-label col-form-label-sm">Password</label>
                  <input type="password" class="form-control form-control-sm" name="PASSWORD" id="U_PASSWORD" minlength="4" autocomplete="new-password" required>
                </div>
                <div class="col-sm-6 form-group">
                  <label for="U_TYPEID" class="col-form-label col-form-label-sm">User Type</label>
                  <select class="form-control form-control-sm" name="TYPEID" id="U_TYPEID" required><?php user_type_options($types); ?></select>
                </div>
                <div class="col-sm-6 form-group">
                  <label for="U_RFID" class="col-form-label col-form-label-sm">RFID Card Number <small class="text-muted">(optional, for the Scan Kiosk)</small></label>
                  <input type="text" class="form-control form-control-sm" name="RFID_NUMBER" id="U_RFID" maxlength="50" placeholder="Tap the card here">
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" name="save">Create account</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- EDIT -->
<div class="modal fade" id="editEntry">
  <div class="modal-dialog modal-lg">
    <form action="controller.php?action=edit" enctype="multipart/form-data" method="POST" autocomplete="off">
      <input type="hidden" name="UID" id="E_UID">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title">Modify User Account</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-3 text-center">
              <img id="E_PHOTO_PREVIEW" src="<?php echo $defaultPhoto; ?>" class="img-circle mb-2" style="width:100px;height:100px;object-fit:cover" alt="">
              <label class="btn btn-default btn-sm btn-block mb-1">Change photo<input type="file" name="PHOTO" id="E_PHOTO" accept="image/jpeg,image/png,image/webp" hidden></label>
              <div class="form-check"><input type="checkbox" class="form-check-input" name="REMOVE_PHOTO" value="1" id="E_REMOVE_PHOTO"><label class="form-check-label small" for="E_REMOVE_PHOTO">Remove photo</label></div>
            </div>
            <div class="col-md-9">
              <div class="row">
                <div class="col-sm-6 form-group">
                  <label for="E_DISPLAYNAME" class="col-form-label col-form-label-sm">Name</label>
                  <input type="text" class="form-control form-control-sm" name="DISPLAYNAME" id="E_DISPLAYNAME" maxlength="60" required>
                </div>
                <div class="col-sm-6 form-group">
                  <label for="E_USERNAME" class="col-form-label col-form-label-sm">Username</label>
                  <input type="text" class="form-control form-control-sm" name="USERNAME" id="E_USERNAME" maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" title="3 to 50 letters, numbers, dot, dash or underscore" required>
                </div>
                <div class="col-sm-6 form-group">
                  <label for="E_TYPEID" class="col-form-label col-form-label-sm">User Type</label>
                  <select class="form-control form-control-sm" name="TYPEID" id="E_TYPEID" required><?php user_type_options($types); ?></select>
                </div>
                <div class="col-sm-6 form-group">
                  <label for="E_RFID" class="col-form-label col-form-label-sm">RFID Card Number</label>
                  <input type="text" class="form-control form-control-sm" name="RFID_NUMBER" id="E_RFID" maxlength="50">
                </div>
                <div class="col-sm-6 form-group">
                  <label class="col-form-label col-form-label-sm d-block">Status</label>
                  <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                    <input type="checkbox" name="status" class="custom-control-input" id="E_STATUS">
                    <label class="custom-control-label" for="E_STATUS">Active (can log in)</label>
                  </div>
                  <small class="text-muted" id="E_STATUS_NOTE" style="display:none">You cannot deactivate your own account.</small>
                </div>
              </div>
              <p class="text-muted small mb-0">Use the key button in the list to change the password.</p>
            </div>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" name="edit">Save changes</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- CHANGE PASSWORD -->
<div class="modal fade" id="changepass">
  <div class="modal-dialog">
    <form action="controller.php?action=editpass" method="POST" id="changepassForm" autocomplete="off">
      <input type="hidden" name="UID" id="P_UID">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title">Change password for <span id="P_NAME"></span></h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="P_PASSWORD" class="col-form-label col-form-label-sm">New password</label>
            <input type="password" name="PASSWORD" class="form-control form-control-sm" id="P_PASSWORD" minlength="4" autocomplete="new-password" required>
          </div>
          <div class="form-group">
            <label for="P_CONFIRM" class="col-form-label col-form-label-sm">Type it again</label>
            <input type="password" name="CONFIRM" class="form-control form-control-sm" id="P_CONFIRM" minlength="4" autocomplete="new-password" required>
          </div>
        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" name="editpass">Change password</button>
        </div>
      </div>
    </form>
  </div>
</div>
