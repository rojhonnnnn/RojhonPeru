<?php
// ROJHON SOLUTIONS - sidebar tabs
// One definition per tab. The active state is derived from $title, so a
// tab is highlighted on every page that belongs to it, and a parent menu
// opens automatically when one of its children is the current page.

$attendanceView = (isset($_GET['view']) && $_GET['view'] === 'scan') ? 'scan' : 'report';

$menu = array(
	array('label' => 'Dashboard',          'icon' => 'fa-tachometer-alt', 'url' => '',                                                'titles' => array('Home')),
	array('label' => 'Messages',           'icon' => 'fa-comment-dots',   'url' => 'module/chat/',                                    'titles' => array('Messages'), 'badge' => 'rsSideUnread'),
	array('label' => 'Alumni Details',     'icon' => 'fa-id-card',        'url' => 'module/generic/index.php?t=alumni_details',        'titles' => array('Alumni Details')),
	array('label' => 'Student',            'icon' => 'fa-user-graduate',  'url' => 'module/student/',                                 'titles' => array('Student Module')),
	array('label' => 'Course',             'icon' => 'fa-book',           'url' => 'module/course/',                                  'titles' => array('Course Module')),
	array('label' => 'Subject',            'icon' => 'fa-book-open',      'url' => 'module/subject/',                                 'titles' => array('Subject Module')),
	array('label' => 'School Year',        'icon' => 'fa-calendar-alt',   'url' => 'module/generic/index.php?t=tblschoolyear',         'titles' => array('School Year')),
	array('label' => 'Sections',           'icon' => 'fa-chalkboard',     'url' => 'module/generic/index.php?t=tblsections',           'titles' => array('Sections')),
	array('label' => 'Enrollment',         'icon' => 'fa-clipboard-list', 'url' => 'module/enrollment/index.php',                     'titles' => array('Enrollment')),
	array('label' => 'Payments',           'icon' => 'fa-money-bill-wave','titles' => array('Payments', 'Fee Schedule'), 'children' => array(
		array('label' => 'Collect Payments', 'icon' => 'fa-cash-register', 'url' => 'module/payments/index.php',                   'titles' => array('Payments')),
		array('label' => 'Fee Schedule',     'icon' => 'fa-tags',          'url' => 'module/generic/index.php?t=tblfeeschedule',     'titles' => array('Fee Schedule')),
	)),
	array('label' => 'Attendance',         'icon' => 'fa-user-clock',     'titles' => array('Attendance'), 'children' => array(
		array('label' => 'Scan Kiosk',        'icon' => 'fa-address-card', 'url' => 'module/attendance/index.php?view=scan', 'titles' => array('Attendance'), 'when' => 'scan'),
		array('label' => 'Attendance Report', 'icon' => 'fa-list-alt',     'url' => 'module/attendance/index.php',           'titles' => array('Attendance'), 'when' => 'report'),
	)),
	array('label' => 'Enrollment Details', 'icon' => 'fa-list-alt',       'url' => 'module/generic/index.php?t=tblenrollment_details', 'titles' => array('Enrollment Details')),
	array('label' => 'Grades',             'icon' => 'fa-graduation-cap', 'url' => 'module/generic/index.php?t=tblgrades',             'titles' => array('Grades')),
	array('label' => 'About',              'icon' => 'fa-info-circle',    'url' => 'module/about/index.php',                          'titles' => array('About')),
	array('label' => 'Account Settings',   'icon' => 'fa-cog',            'titles' => array('User Module', 'User Type'), 'children' => array(
		array('label' => 'Manage User Accounts', 'icon' => 'fa-users', 'url' => 'module/user/',     'titles' => array('User Module')),
		array('label' => 'Manage User Type',     'icon' => 'fa-key',   'url' => 'module/usertype/', 'titles' => array('User Type')),
	)),
);

function sidebar_is_active($item, $title, $attendanceView) {
	if (!in_array($title, $item['titles'], true)) { return false; }
	return !isset($item['when']) || $item['when'] === $attendanceView;
}
?>
<nav class="mt-2">
  <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
  <?php foreach ($menu as $item):
    $active = sidebar_is_active($item, $title, $attendanceView);
    if (!empty($item['children'])): ?>
    <li class="nav-item has-treeview<?php echo $active ? ' menu-open' : ''; ?>">
      <a href="#" class="nav-link<?php echo $active ? ' active' : ''; ?>">
        <i class="nav-icon fas <?php echo $item['icon']; ?>"></i>
        <p><?php echo h($item['label']); ?><i class="fas fa-angle-left right"></i></p>
      </a>
      <ul class="nav nav-treeview">
      <?php foreach ($item['children'] as $child): $ca = sidebar_is_active($child, $title, $attendanceView); ?>
        <li class="nav-item">
          <a href="<?php echo WEB_ROOT.$child['url']; ?>" class="nav-link<?php echo $ca ? ' active' : ''; ?>">
            <i class="nav-icon fas <?php echo $child['icon']; ?>"></i>
            <p><?php echo h($child['label']); ?></p>
          </a>
        </li>
      <?php endforeach; ?>
      </ul>
    </li>
    <?php else: ?>
    <li class="nav-item">
      <a href="<?php echo WEB_ROOT.$item['url']; ?>" class="nav-link<?php echo $active ? ' active' : ''; ?>">
        <i class="nav-icon fas <?php echo $item['icon']; ?>"></i>
        <p>
          <?php echo h($item['label']); ?>
          <?php if (!empty($item['badge'])) { ?><span class="badge badge-danger right rs-unread-badge" id="<?php echo $item['badge']; ?>" style="display:none">0</span><?php } ?>
        </p>
      </a>
    </li>
    <?php endif; ?>
  <?php endforeach; ?>
  </ul>
</nav>
