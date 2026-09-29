<?php
/* Payments module - Treasurer / Registrar fee collection.
   Dropdown data for the Add Payment modal. $mydb is set up by
   include/initialize.php, which template.php has already loaded. */
global $mydb;

$regEnrollments = array();
$mydb->setQuery("SELECT e.ENROLLMENT_ID, e.YEAR_LEVEL, e.SEMESTER, e.COURSE_ID,
    s.IDNO, s.LNAME, s.FNAME, s.MNAME,
    c.COURSE_CODE, sy.SCHOOL_YEAR
  FROM `tblenrollment` e
  JOIN `tblstudent` s ON s.S_ID = e.S_ID
  JOIN `tblcourses` c ON c.COURSE_ID = e.COURSE_ID
  JOIN `tblschoolyear` sy ON sy.SY_ID = e.SY_ID
  ORDER BY e.ENROLLMENT_ID DESC");
foreach ($mydb->loadResultList() as $r) { $regEnrollments[] = $r; }

$regFeeTypes = array('Enrollment Fee', 'Entrance Exam', 'Admission Fee');
?>
<style>
  @media print {
    .main-header, .main-sidebar, .content-header, .main-footer,
    #feeTypeFilters, #printPayments, .card-tools .btn-primary, .brand-link { display: none !important; }
    .content-wrapper { margin-left: 0 !important; }
  }
</style>
<section class="content">
  <div class="container-fluid">
    <?php check_message(); ?>
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Payments</h3>
            <div class="card-tools">
              <div class="btn-group" id="feeTypeFilters">
                <button type="button" class="btn btn-sm btn-outline-secondary active" data-filter="">All</button>
                <button type="button" class="btn btn-sm btn-outline-warning" data-filter="Enrollment Fee">Enrollment Fee</button>
                <button type="button" class="btn btn-sm btn-outline-info" data-filter="Entrance Exam">Entrance Exam</button>
                <button type="button" class="btn btn-sm btn-outline-success" data-filter="Admission Fee">Admission Fee</button>
              </div>
              <button type="button" class="btn btn-sm btn-primary ml-2" data-toggle="modal" data-target="#addPaymentModal">
                <i class="fa fa-plus"></i> Add Payment
              </button>
              <button type="button" id="printPayments" class="btn btn-sm btn-default ml-2">
                <i class="fa fa-print"></i> Print
              </button>
            </div>
          </div>
          <div class="card-body">
            <table id="tblpaymentslist" class="table table-bordered table-striped" style="width:100%">
              <thead>
                <tr>
                  <th>#</th>
                  <th>OR No.</th>
                  <th>Student</th>
                  <th>Course</th>
                  <th>Fee Type</th>
                  <th>Amount</th>
                  <th>Date Paid</th>
                  <th>Received By</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===================== ADD PAYMENT ===================== -->
<div class="modal fade" id="addPaymentModal">
  <div class="modal-dialog modal-lg">
    <form action="controller.php?action=add" method="POST">
      <div class="modal-content">
        <div class="modal-header bg-primary">
          <h4 class="modal-title"><i class="fa fa-money-bill-wave"></i>&nbsp; Add Payment</h4>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">

          <div class="form-group">
            <label for="P_ENROLLMENT" class="col-form-label col-form-label-sm">Student / Enrollment Record</label>
            <select class="form-control form-control-sm select2" name="P_ENROLLMENT" id="P_ENROLLMENT" style="width:100%" required>
              <option value="">Select a student's enrollment record</option>
              <?php foreach ($regEnrollments as $e) { ?>
              <option value="<?php echo $e->ENROLLMENT_ID; ?>">
                <?php echo htmlspecialchars($e->IDNO.' - '.$e->LNAME.', '.$e->FNAME.' '.$e->MNAME.' ('.$e->COURSE_CODE.' - '.$e->YEAR_LEVEL.' - '.$e->SCHOOL_YEAR.' '.$e->SEMESTER.')'); ?>
              </option>
              <?php } ?>
            </select>
          </div>

          <div class="callout callout-info py-2 mb-3" id="P_INFO_PANEL" style="display:none;">
            <div class="row">
              <div class="col-sm-3">
                <small class="text-muted d-block">Course / Year Level</small>
                <strong id="P_INFO_COURSE">-</strong>
              </div>
              <div class="col-sm-3">
                <small class="text-muted d-block">Total Tuition</small>
                <strong id="P_INFO_TUITION">-</strong>
              </div>
              <div class="col-sm-3">
                <small class="text-muted d-block">Total Paid (Enrollment Fee)</small>
                <strong id="P_INFO_PAID">-</strong>
              </div>
              <div class="col-sm-3">
                <small class="text-muted d-block">Remaining Balance</small>
                <strong id="P_INFO_BALANCE">-</strong>
              </div>
            </div>
          </div>

          <div class="row">

            <div class="col-sm-6">
              <div class="form-group">
                <label for="P_FEE_TYPE" class="col-form-label col-form-label-sm">Fee Type</label>
                <select class="form-control form-control-sm" name="P_FEE_TYPE" id="P_FEE_TYPE" required>
                  <?php foreach ($regFeeTypes as $ft) { ?>
                  <option value="<?php echo $ft; ?>"><?php echo $ft; ?></option>
                  <?php } ?>
                </select>
                <small class="form-text text-muted" id="P_ENTRANCE_NOTE" style="display:none;">
                  Entrance Exam only applies to 1st Year students.
                </small>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="form-group">
                <label for="P_AMOUNT" class="col-form-label col-form-label-sm">Amount</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="P_AMOUNT" id="P_AMOUNT" required>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="form-group">
                <label for="P_OR_NUMBER" class="col-form-label col-form-label-sm">OR Number</label>
                <input type="text" class="form-control form-control-sm" name="P_OR_NUMBER" id="P_OR_NUMBER" placeholder="Official Receipt No." required>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="form-group">
                <label for="P_DATE_PAID" class="col-form-label col-form-label-sm">Date Paid</label>
                <input type="date" class="form-control form-control-sm" name="P_DATE_PAID" id="P_DATE_PAID" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
            </div>

            <div class="col-sm-12">
              <div class="form-group">
                <label for="P_REMARKS" class="col-form-label col-form-label-sm">Remarks</label>
                <input type="text" class="form-control form-control-sm" name="P_REMARKS" id="P_REMARKS" placeholder="Optional">
              </div>
            </div>

          </div>

        </div>
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Payment</button>
        </div>
      </div>
    </form>
  </div>
</div>
<!-----END of Add Payment Form---->