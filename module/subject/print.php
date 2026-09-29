<?php
// DELFIN SOLUTIONS - Printable list of ALL subjects
/* Same reasoning as the course sheet: the screen list is paginated, so
   this page pulls every subject, joined to its course, and prints them
   grouped by course so the sheet is readable. */
require_once("../../include/initialize.php");

global $mydb;
$mydb->setQuery("SELECT s.SUBJECT_ID, s.SUBJECT_CODE, s.SUBJECT_NAME, s.UNITS,
                        s.YEAR_LEVEL, s.SEMESTER,
                        c.COURSE_CODE, c.COURSE_NAME
                 FROM `tblsubjects` s
                 LEFT JOIN `tblcourses` c ON c.COURSE_ID = s.COURSE_ID
                 ORDER BY c.COURSE_CODE ASC, s.YEAR_LEVEL ASC, s.SEMESTER ASC, s.SUBJECT_CODE ASC");
$rows  = $mydb->loadResultList();
$total = count($rows);

/* Group by course so each course gets its own block and subtotal. */
$grouped = array();
foreach ($rows as $r) {
    $key = $r->COURSE_CODE ? $r->COURSE_CODE.' - '.$r->COURSE_NAME : 'Unassigned';
    if (!isset($grouped[$key])) { $grouped[$key] = array(); }
    $grouped[$key][] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>List of Subjects</title>
  <style>
    @page { size: A4 portrait; margin: 14mm 12mm; }
    body { font-family: 'Times New Roman', Times, serif; color: #000; font-size: 11pt; margin: 0; padding: 18px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
    .head img { height: 66px; margin-bottom: 4px; }
    .head h1 { font-size: 15pt; margin: 2px 0 0; letter-spacing: .5px; }
    .head .addr { font-size: 9pt; margin-top: 2px; }
    .head .doc { margin-top: 9px; font-size: 12pt; font-weight: bold; letter-spacing: 1px; }
    .head .meta { font-size: 8.5pt; margin-top: 4px; }
    h2.grp { font-size: 10.5pt; margin: 16px 0 5px; padding-bottom: 3px; border-bottom: 1px solid #000; }
    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    th { background: #EEE; text-align: left; border: 1px solid #000; padding: 5px 7px; font-weight: bold; }
    td { border: 1px solid #999; padding: 5px 7px; }
    tr, img { page-break-inside: avoid; }
    thead { display: table-header-group; }
    .c { text-align: center; }
    .sub { font-size: 8.5pt; font-style: italic; margin: 3px 0 0; }
    .sign { margin-top: 46px; width: 100%; }
    .sign td { border: none; padding-top: 26px; text-align: center; width: 50%; font-size: 9pt; }
    .sign .ln { border-top: 1px solid #000; padding-top: 3px; margin: 0 24px; }
    .foot { margin-top: 20px; font-size: 8pt; font-style: italic; text-align: center; }
    .bar { text-align: center; margin-bottom: 16px; }
    .bar button, .bar a { font-family: Arial, sans-serif; font-size: 13px; padding: 9px 20px; margin: 0 4px;
      border-radius: 8px; border: none; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 700; }
    .bar .p { background: #752BDF; color: #fff; }
    .bar .b { background: #EDEFF6; color: #2C3146; }
    @media print { .bar { display: none !important; } body { padding: 0; } }
  </style>
</head>
<body>

  <div class="bar">
    <button class="p" onclick="window.print();">Print this list</button>
    <a class="b" href="index.php">Back to Subjects</a>
  </div>

  <div class="head">
    <img src="<?php echo WEB_ROOT; ?>csr-scc.png" alt="Logo">
    <h1>ROJHON SOLUTIONS</h1>
    <div class="addr">School Records Management System</div>
    <div class="doc">LIST OF SUBJECTS</div>
    <div class="meta">
      <?php echo $total; ?> record<?php echo ($total == 1 ? '' : 's'); ?>
      across <?php echo count($grouped); ?> course<?php echo (count($grouped) == 1 ? '' : 's'); ?>
      &nbsp;&middot;&nbsp; Printed <?php echo date('F j, Y \a\t g:i A'); ?>
      <?php if (!empty($_SESSION['DISPLAYNAME'])): ?>
        &nbsp;&middot;&nbsp; by <?php echo htmlspecialchars($_SESSION['DISPLAYNAME']); ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($total < 1): ?>
    <p style="text-align:center;">No subject records found.</p>
  <?php else: ?>
    <?php foreach ($grouped as $courseLabel => $list):
            $units = 0; foreach ($list as $x) { $units += (int)$x->UNITS; } ?>
      <h2 class="grp"><?php echo htmlspecialchars($courseLabel); ?></h2>
      <table>
        <thead>
          <tr>
            <th style="width:6%" class="c">#</th>
            <th style="width:15%">Subject Code</th>
            <th>Subject Name</th>
            <th style="width:9%" class="c">Units</th>
            <th style="width:14%">Year Level</th>
            <th style="width:14%">Semester</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($list as $r): ?>
          <tr>
            <td class="c"><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($r->SUBJECT_CODE); ?></td>
            <td><?php echo htmlspecialchars($r->SUBJECT_NAME); ?></td>
            <td class="c"><?php echo (int)$r->UNITS; ?></td>
            <td><?php echo htmlspecialchars($r->YEAR_LEVEL !== null ? $r->YEAR_LEVEL : ''); ?></td>
            <td><?php echo htmlspecialchars($r->SEMESTER !== null ? $r->SEMESTER : ''); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="sub"><?php echo count($list); ?> subject<?php echo (count($list) == 1 ? '' : 's'); ?>,
         <?php echo $units; ?> total unit<?php echo ($units == 1 ? '' : 's'); ?>.</p>
    <?php endforeach; ?>
  <?php endif; ?>

  <table class="sign">
    <tr>
      <td><div class="ln">PREPARED BY</div></td>
      <td><div class="ln">REGISTRAR</div></td>
    </tr>
  </table>

  <p class="foot">This report reflects the subject records held in the system at the date and time printed above.</p>

</body>
</html>
