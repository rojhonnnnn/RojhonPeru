<?php
// ROJHON SOLUTIONS
// Every table below shows up in the sidebar and gets Add/Edit/Delete +
// a DataTable list, exactly like the Student/Course/Subject modules -
// the columns and inputs are built automatically from whatever is
// really in the database (see include/generic.php).

$GENERIC_TABLES = array(
	'alumni_details' => array(
		'title' => 'Alumni Details',
		'icon'  => 'fa-id-card',
	),
	'tblschoolyear' => array(
		'title' => 'School Year',
		'icon'  => 'fa-calendar-alt',
	),
	'tblsections' => array(
		'title' => 'Sections',
		'icon'  => 'fa-chalkboard',
	),
	/* tblenrollment is NOT listed here on purpose. It now has its own
	   module (module/enrollment) because the two-stage Reserve ->
	   Sectioning flow needs custom screens the generic builder cannot
	   produce. Adding it back here would give you a second, conflicting
	   way to edit the same table. */
	'tblenrollment_details' => array(
		'title' => 'Enrollment Details',
		'icon'  => 'fa-list-alt',
	),
	'tblgrades' => array(
		'title' => 'Grades',
		'icon'  => 'fa-graduation-cap',
	),
	/* Tuition per course and year level. Payments reads this to show the
	   Total Tuition and Remaining Balance in the Add Payment form. */
	'tblfeeschedule' => array(
		'title' => 'Fee Schedule',
		'icon'  => 'fa-tags',
	),
);

/* column name => [table to pull options from, PK column, SQL expression for the label] */
$GENERIC_FK_MAP = array(
	'COURSE_ID'  => array('table' => 'tblcourses',   'pk' => 'COURSE_ID',  'label' => "CONCAT(COURSE_CODE, ' - ', COURSE_NAME)"),
	'SECTION_ID' => array('table' => 'tblsections',  'pk' => 'SECTION_ID', 'label' => "CONCAT(YEAR_LEVEL, ' - ', SECTION_NAME, ' (', COALESCE((SELECT c.COURSE_CODE FROM tblcourses c WHERE c.COURSE_ID = tblsections.COURSE_ID), ''), ')')"),
	'SY_ID'      => array('table' => 'tblschoolyear','pk' => 'SY_ID',      'label' => 'SCHOOL_YEAR'),
	'S_ID'       => array('table' => 'tblstudent',   'pk' => 'S_ID',       'label' => "CONCAT(LNAME, ', ', FNAME)"),
	'ENROLLMENT_ID' => array('table' => 'tblenrollment', 'pk' => 'ENROLLMENT_ID',
		'label' => "CONCAT('#', ENROLLMENT_ID, ' ', COALESCE((SELECT CONCAT(st.LNAME, ', ', st.FNAME) FROM tblstudent st WHERE st.S_ID = tblenrollment.S_ID), ''), ' - ', SEMESTER)"),
	'SUBJECT_ID' => array('table' => 'tblsubjects',  'pk' => 'SUBJECT_ID', 'label' => "CONCAT(SUBJECT_CODE, ' - ', SUBJECT_NAME)"),
	'UID'        => array('table' => 'tblusers',     'pk' => 'UID',        'label' => 'USERNAME'),
	'TYPEID'     => array('table' => 'tblusertype',  'pk' => 'TYPEID',     'label' => 'USERTYPE'),
	'AddedBy'    => array('table' => 'tblusers',     'pk' => 'UID',        'label' => 'DISPLAYNAME'),
);

/* STATUS means different things in different tables, so the generic form
   must not assume Active/Inactive everywhere. Keyed by table name. */
$GENERIC_STATUS_CHOICES = array(
	'tblenrollment' => array('Enrolled', 'Dropped', 'Completed'),
);

/* column name (exact, case-insensitive) => fixed dropdown choices, used
   instead of a plain text box in the Add/Edit forms. */
$GENERIC_FIELD_CHOICES = array(
	'YEAR_LEVEL' => array('1st Year', '2nd Year', '3rd Year', '4th Year'),
	'SEMESTER'   => array('1st Semester', '2nd Semester', 'Summer'),
	'REMARKS'    => array('Passed', 'Failed', 'Incomplete', 'Dropped'),
	'STATUS'     => array('Active', 'Inactive'),
);

/* substring (case-insensitive) => nicer label to show instead of the
   auto-generated one. Used e.g. so an ADVISER column in Sections is
   labeled "Program Head" everywhere in the UI. */
$GENERIC_LABEL_OVERRIDES = array(
	'ADVIS' => 'Program Head',
);

/* Returns the config for a table if (and only if) it is whitelisted -
   protects the generic module from being pointed at an arbitrary table
   name via the URL. */
function generic_table_config($tableKey) {
	global $GENERIC_TABLES;
	return isset($GENERIC_TABLES[$tableKey]) ? $GENERIC_TABLES[$tableKey] : null;
}
?>
