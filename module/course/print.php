<?php
// DELFIN SOLUTIONS - Printable list of ALL courses
/* The on-screen list is paginated, so printing that page only ever gives
   the 10 rows currently visible. This page queries every record, renders
   a clean sheet with a letterhead, and triggers the print dialog. */
require_once("../../include/initialize.php");

global $mydb;
$mydb->setQuery("SELECT COURSE_ID, COURSE_CODE, COURSE_NAME, COURSE_DESC, STATUS
                 FROM `tblcourses`
                 ORDER BY COURSE_CODE ASC");
$rows  = $mydb->loadResultList();
$total = count($rows);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>List of Courses</title>
  <style>
    @page { size: A4 portrait; margin: 14mm 12mm; }
    body { font-family: 'Times New Roman', Times, serif; color: #000; font-size: 11pt; margin: 0; padding: 18px; }
    .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
    .head img { height: 66px; margin-bottom: 4px; }
    .head h1 { font-size: 15pt; margin: 2px 0 0; letter-spacing: .5px; }
    .head .addr { font-size: 9pt; margin-top: 2px; }
    .head .doc { margin-top: 9px; font-size: 12pt; font-weight: bold; letter-spacing: 1px; }
    .head .meta { font-size: 8.5pt; margin-top: 4px; }
    table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    th { background: #EEE; text-align: left; border: 1px solid #000; padding: 5px 7px; font-weight: bold; }
    td { border: 1px solid #999; padding: 5px 7px; }
    tr, img { page-break-inside: avoid; }
    thead { display: table-header-group; }
    .c { text-align: center; }
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
    <a class="b" href="index.php">Back to Courses</a>
  </div>

  <div class="head">
    <img src="<?php echo WEB_ROOT; ?>csr-scc.png" alt="Logo">
    <h1>ROJHON SOLUTIONS</h1>
    <div class="addr">School Records Management System</div>
    <div class="doc">LIST OF COURSES</div>
    <div class="meta">
      <?php echo $total; ?> record<?php echo ($total == 1 ? '' : 's'); ?>
      &nbsp;&middot;&nbsp; Printed <?php echo date('F j, Y \a\t g:i A'); ?>
      <?php if (!empty($_SESSION['DISPLAYNAME'])): ?>
        &nbsp;&middot;&nbsp; by <?php echo htmlspecialchars($_SESSION['DISPLAYNAME']); ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($total < 1): ?>
    <p style="text-align:center;">No course records found.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:6%" class="c">#</th>
        <th style="width:14%">Course Code</th>
        <th style="width:30%">Course Name</th>
        <th>Description</th>
        <th style="width:12%" class="c">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($rows as $r): ?>
      <tr>
        <td class="c"><?php echo $i++; ?></td>
        <td><?php echo htmlspecialchars($r->COURSE_CODE); ?></td>
        <td><?php echo htmlspecialchars($r->COURSE_NAME); ?></td>
        <td><?php echo htmlspecialchars($r->COURSE_DESC !== null ? $r->COURSE_DESC : ''); ?></td>
        <td class="c"><?php echo htmlspecialchars($r->STATUS); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <table class="sign">
    <tr>
      <td><div class="ln">PREPARED BY</div></td>
      <td><div class="ln">REGISTRAR</div></td>
    </tr>
  </table>

  <p class="foot">This report reflects the course records held in the system at the date and time printed above.</p>

</body>
</html>
