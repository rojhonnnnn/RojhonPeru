<?php
// ROJHON SOLUTIONS - dashboard. Every box shows a live count and opens the matching tab.
global $mydb;

function home_count($sql, $params = array()) {
	global $mydb;
	return (int)$mydb->fetchValue($sql, $params);
}

$boxes = array(
	array('Alumni Count',    home_count("SELECT COUNT(*) FROM alumni_details"), 'bg-info',    'fa-id-card',       'module/generic/index.php?t=alumni_details'),
	array('Total Students',  home_count("SELECT COUNT(*) FROM tblstudent"),     'bg-success', 'fa-user-graduate', 'module/student/'),
	array('Total Courses',   home_count("SELECT COUNT(*) FROM tblcourses"),     'bg-warning', 'fa-book',          'module/course/'),
	array('Total Sections',  home_count("SELECT COUNT(*) FROM tblsections"),    'bg-danger',  'fa-chalkboard',    'module/generic/index.php?t=tblsections'),
);

$registered = home_count("SELECT COUNT(*) FROM tblenrollment WHERE STATUS = 'Registered'");
$enrolled   = home_count("SELECT COUNT(*) FROM tblenrollment WHERE STATUS = 'Enrolled'");
$paidToday  = (float)$mydb->fetchValue("SELECT COALESCE(SUM(AMOUNT), 0) FROM tblpayments WHERE DATE_PAID = ?", array(date('Y-m-d')));
$presentToday = home_count("SELECT COUNT(*) FROM tblattendance WHERE ATTENDANCE_DATE = ? AND AM_TIME_IN IS NOT NULL", array(date('Y-m-d')));
$activeStaff  = home_count("SELECT COUNT(*) FROM tblusers WHERE STATUSACTIVE = 1");
$unreadChats  = Chat::totalUnread((int)$_SESSION['UID']);
?>
<section class="content">
  <div class="container-fluid">
    <?php check_message(); ?>
    <div class="row">
      <?php foreach ($boxes as $b) { ?>
      <div class="col-lg-3 col-6">
        <div class="small-box <?php echo $b[2]; ?>">
          <div class="inner">
            <h3><?php echo number_format($b[1]); ?></h3>
            <p><?php echo h($b[0]); ?></p>
          </div>
          <div class="icon"><i class="fas <?php echo $b[3]; ?>"></i></div>
          <a href="<?php echo WEB_ROOT.$b[4]; ?>" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
        </div>
      </div>
      <?php } ?>
    </div>

    <div class="row">
      <div class="col-md-3 col-sm-6">
        <a class="info-box text-reset" href="<?php echo WEB_ROOT; ?>module/enrollment/index.php">
          <span class="info-box-icon bg-warning"><i class="fas fa-hourglass-half"></i></span>
          <div class="info-box-content"><span class="info-box-text">Waiting for sectioning</span><span class="info-box-number"><?php echo $registered; ?></span></div>
        </a>
      </div>
      <div class="col-md-3 col-sm-6">
        <a class="info-box text-reset" href="<?php echo WEB_ROOT; ?>module/enrollment/index.php">
          <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
          <div class="info-box-content"><span class="info-box-text">Enrolled</span><span class="info-box-number"><?php echo $enrolled; ?></span></div>
        </a>
      </div>
      <div class="col-md-3 col-sm-6">
        <a class="info-box text-reset" href="<?php echo WEB_ROOT; ?>module/payments/index.php">
          <span class="info-box-icon bg-info"><i class="fas fa-money-bill-wave"></i></span>
          <div class="info-box-content"><span class="info-box-text">Collected today</span><span class="info-box-number">&#8369;<?php echo number_format($paidToday, 2); ?></span></div>
        </a>
      </div>
      <div class="col-md-3 col-sm-6">
        <a class="info-box text-reset" href="<?php echo WEB_ROOT; ?>module/chat/">
          <span class="info-box-icon bg-primary"><i class="fas fa-comment-dots"></i></span>
          <div class="info-box-content"><span class="info-box-text">Unread chats</span><span class="info-box-number" id="rsHomeUnread"><?php echo $unreadChats; ?></span></div>
        </a>
      </div>
      <div class="col-md-3 col-sm-6">
        <a class="info-box text-reset" href="<?php echo WEB_ROOT; ?>module/attendance/index.php">
          <span class="info-box-icon bg-secondary"><i class="fas fa-user-clock"></i></span>
          <div class="info-box-content"><span class="info-box-text">Staff timed in today</span><span class="info-box-number"><?php echo $presentToday; ?> / <?php echo $activeStaff; ?></span></div>
        </a>
      </div>
    </div>
  </div>
</section>
