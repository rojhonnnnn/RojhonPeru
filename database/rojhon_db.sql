-- =====================================================================
-- ROJHON SOLUTIONS
-- Database: rojhon_db
--
-- Import this ONCE in phpMyAdmin (Import tab, choose this file, Go).
-- It creates the database, all 18 tables, the sample data, and every
-- foreign key. You do NOT need to create the database by hand first.
-- Re-importing it later resets everything back to the sample data.
--
-- Every table is related to at least one other table, so the Designer
-- view in phpMyAdmin shows one connected diagram with no orphan boxes.
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = "+08:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `rojhon_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `rojhon_db`;

DROP TABLE IF EXISTS `tblchat_reactions`;
DROP TABLE IF EXISTS `tblchat_presence`;
DROP TABLE IF EXISTS `tblchat_messages`;
DROP TABLE IF EXISTS `tblchat_conversations`;
DROP TABLE IF EXISTS `tblattendance`;
DROP TABLE IF EXISTS `tblpayments`;
DROP TABLE IF EXISTS `tblfeeschedule`;
DROP TABLE IF EXISTS `tblgrades`;
DROP TABLE IF EXISTS `tblenrollment_details`;
DROP TABLE IF EXISTS `tblenrollment`;
DROP TABLE IF EXISTS `tblsubjects`;
DROP TABLE IF EXISTS `tblsections`;
DROP TABLE IF EXISTS `tblschoolyear`;
DROP TABLE IF EXISTS `tblcourses`;
DROP TABLE IF EXISTS `alumni_details`;
DROP TABLE IF EXISTS `tblstudent`;
DROP TABLE IF EXISTS `tblusers`;
DROP TABLE IF EXISTS `tblusertype`;

-- ---------------------------------------------------------------------
-- tblusertype  (top of the account chain)
-- ---------------------------------------------------------------------
CREATE TABLE `tblusertype` (
  `TYPEID` int(11) NOT NULL AUTO_INCREMENT,
  `USERTYPE` varchar(30) NOT NULL,
  `STATUS` varchar(20) NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`TYPEID`),
  UNIQUE KEY `uq_usertype` (`USERTYPE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blank STATUS values from the old database are normalized to 'Active'.
INSERT INTO `tblusertype` (`TYPEID`, `USERTYPE`, `STATUS`) VALUES
(1, 'Administrator', 'Active'),
(2, 'Doctor', 'Active'),
(3, 'Staff', 'Active'),
(7, 'Nurse', 'Active'),
(12, 'hello', 'Inactive'),
(13, 'cashier', 'Active'),
(14, 'sdsds', 'Inactive');

ALTER TABLE `tblusertype` AUTO_INCREMENT = 16;

-- ---------------------------------------------------------------------
-- tblusers  -> tblusertype
-- TYPE (the varchar) is kept because login.php and the session still
-- read it. TYPEID is the new real relationship.
-- ---------------------------------------------------------------------
CREATE TABLE `tblusers` (
  `UID` int(11) NOT NULL AUTO_INCREMENT,
  `DISPLAYNAME` varchar(60) NOT NULL,
  `USERNAME` varchar(50) NOT NULL,
  `PASSWORD` varchar(255) NOT NULL,
  `TYPE` varchar(30) NOT NULL,
  `TYPEID` int(11) DEFAULT NULL,
  `ADDEDBY` int(3) NOT NULL,
  `DATEADDED` date NOT NULL,
  `MODIFIEDBY` int(3) NOT NULL,
  `DATEMODIFIED` date NOT NULL,
  `STATUSACTIVE` int(2) NOT NULL DEFAULT 1,
  `PHOTO` varchar(255) DEFAULT NULL,
  `RFID_NUMBER` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`UID`),
  UNIQUE KEY `uq_username` (`USERNAME`),
  UNIQUE KEY `uq_rfid` (`RFID_NUMBER`),
  KEY `fk_users_usertype` (`TYPEID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Passwords: rojhon / rojhon and admin / admin (bcrypt).
-- dfd and dfdf keep their original SHA1 hash (password: dfd). Any SHA1
-- account is upgraded to bcrypt automatically on its first login.
INSERT INTO `tblusers` (`UID`, `DISPLAYNAME`, `USERNAME`, `PASSWORD`, `TYPE`, `TYPEID`, `ADDEDBY`, `DATEADDED`, `MODIFIEDBY`, `DATEMODIFIED`, `STATUSACTIVE`, `PHOTO`, `RFID_NUMBER`) VALUES
(1, 'Jason', 'admin', '$2y$10$zFuZH8k/JbqHIBO6fCmbkOnQDlQMgMJIJYRqbkJYFqxccr6bhkYHW', 'Administrator', 1, 1, '2020-08-27', 1, '2021-07-05', 1, NULL, '1000000002'),
(77, 'dfd', 'dfd', '6bb65257fcab4e2975cd96b0f7fc4b53d97c10b6', 'Staff', 3, 1, '2025-01-16', 1, '2026-08-02', 1, NULL, NULL),
(78, 'dfdf', 'dfdf', '6bb65257fcab4e2975cd96b0f7fc4b53d97c10b6', 'Staff', 3, 1, '2025-01-16', 1, '2025-01-16', 1, NULL, NULL),
(79, 'Erick', 'jason', '5c2dd944dde9e08881bef0894fe7b22a5c9c4b06', 'Administrator', 1, 1, '2025-01-24', 1, '2025-01-24', 1, NULL, NULL),
(81, 'Rojhon', 'rojhon', '$2y$10$fF2r.DeHkG00LGE3DIWHaO/kBNdtfcqRNtLt.RaY5IcZvc6yh0HOK', 'Administrator', 1, 1, '2026-09-11', 1, '2026-09-11', 1, NULL, '1000000001');

ALTER TABLE `tblusers` AUTO_INCREMENT = 82;

-- ---------------------------------------------------------------------
-- tblcourses
-- ---------------------------------------------------------------------
CREATE TABLE `tblcourses` (
  `COURSE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `COURSE_CODE` varchar(20) NOT NULL,
  `COURSE_NAME` varchar(150) NOT NULL,
  `COURSE_DESC` text DEFAULT NULL,
  `STATUS` varchar(20) NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`COURSE_ID`),
  UNIQUE KEY `COURSE_CODE` (`COURSE_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblcourses` (`COURSE_ID`, `COURSE_CODE`, `COURSE_NAME`, `COURSE_DESC`, `STATUS`) VALUES
(1, 'BSIT', 'Bachelor of Science in Information Technology', 'Information Technology program', 'Active'),
(2, 'BSTM', 'Bachelor of Science in Tourism Management', 'Tourism Management program', 'Active'),
(3, 'BSED', 'Bachelor of Secondary Education', 'Secondary Education program', 'Active');

ALTER TABLE `tblcourses` AUTO_INCREMENT = 4;

-- ---------------------------------------------------------------------
-- tblstudent  -> tblcourses, tblusers
-- COURSE_ID gives every student a program. AddedBy records who encoded
-- the record. Both nullable so old rows stay valid.
-- IDNO is varchar now: an int cannot hold a 12-digit LRN.
-- Charset changed from latin1 to utf8mb4 to match every other table.
-- ---------------------------------------------------------------------
CREATE TABLE `tblstudent` (
  `S_ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDNO` varchar(20) NOT NULL,
  `FNAME` varchar(40) NOT NULL,
  `LNAME` varchar(40) NOT NULL,
  `MNAME` varchar(40) NOT NULL,
  `SEX` varchar(10) NOT NULL DEFAULT 'Male',
  `BDAY` date DEFAULT NULL,
  `BPLACE` text DEFAULT NULL,
  `STATUS` varchar(30) NOT NULL DEFAULT 'Active',
  `AGE` int(11) DEFAULT NULL,
  `NATIONALITY` varchar(40) DEFAULT NULL,
  `RELIGION` varchar(255) DEFAULT NULL,
  `CONTACT_NO` varchar(40) DEFAULT NULL,
  `HOME_ADD` text DEFAULT NULL,
  `EMAIL` varchar(150) DEFAULT NULL,
  `ACC_PASSWORD` text DEFAULT NULL,
  `LRNNO` varchar(15) DEFAULT NULL,
  `CONTACTPERSON` varchar(150) DEFAULT NULL,
  `COMPANYIDNO` int(11) DEFAULT NULL,
  `COURSE_ID` int(11) DEFAULT NULL,
  `AddedBy` int(11) DEFAULT NULL,
  `PHOTO` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`S_ID`),
  UNIQUE KEY `IDNO` (`IDNO`),
  KEY `fk_student_course` (`COURSE_ID`),
  KEY `fk_student_addedby` (`AddedBy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- SEX 'Select Gen' from the old data is corrected to 'Male' so the
-- gender dropdown in the Student module can match it.
INSERT INTO `tblstudent` (`S_ID`, `IDNO`, `FNAME`, `LNAME`, `MNAME`, `SEX`, `BDAY`, `BPLACE`, `STATUS`, `AGE`, `NATIONALITY`, `RELIGION`, `CONTACT_NO`, `HOME_ADD`, `EMAIL`, `ACC_PASSWORD`, `LRNNO`, `CONTACTPERSON`, `COMPANYIDNO`, `COURSE_ID`, `AddedBy`, `PHOTO`) VALUES
(1, '2011072501', 'ZHA KEISHA', 'BATUTO', 'JIMENEZ', 'Female', '2011-07-25', NULL, 'Active', 13, NULL, NULL, '09176374293', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2011072501.jpg'),
(5, '3333', 'Jha Syl', 'hjh', 'hjh', 'Male', '2025-01-14', 'Cebu Cebu', 'Active', 11, 'Filipino', NULL, NULL, NULL, 'jason@co.ph', NULL, NULL, NULL, NULL, 1, 1, '3333.jpg'),
(6, '22222', 'ssd', 'sds', 'sds', 'Male', '2025-02-04', 'sds', 'Active', 22, 'Filipino', NULL, NULL, NULL, 'jason@co.ph', NULL, NULL, NULL, NULL, 2, 1, '22222.jpg');

ALTER TABLE `tblstudent` AUTO_INCREMENT = 7;

-- ---------------------------------------------------------------------
-- alumni_details  -> tblstudent, tblusers
-- S_ID links an alumni record back to the student it belongs to, which
-- is what turns this from an orphan table into part of the diagram.
-- ---------------------------------------------------------------------
CREATE TABLE `alumni_details` (
  `AlumniID` int(11) NOT NULL AUTO_INCREMENT,
  `InstitutionName` varchar(255) NOT NULL,
  `Degree` varchar(100) NOT NULL,
  `FieldOfStudy` varchar(100) DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `logo` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `S_ID` int(11) DEFAULT NULL,
  `AddedBy` int(11) DEFAULT NULL,
  PRIMARY KEY (`AlumniID`),
  KEY `fk_alumni_student` (`S_ID`),
  KEY `fk_alumni_addedby` (`AddedBy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `alumni_details` (`AlumniID`, `InstitutionName`, `Degree`, `FieldOfStudy`, `StartDate`, `EndDate`, `logo`, `description`, `S_ID`, `AddedBy`) VALUES
(6, 'PIES DIGITAL', 'MIT', 'SYSTEM DEVELOPMENT', '2024-06-26', '2024-06-25', 'image/csr-scc.png', 'Avtech Solution', 1, 1),
(7, 'ROJHON SOLUTIONS', 'BSED-MATH', 'Research', '1988-09-23', '2024-06-25', 'image/ejb.png', 'Research is defined as the creation of new knowledge and/or the use of existing knowledge in a new and creative way so as to generate new concepts, methodologies and understandings. This could include synthesis and analysis of previous research to the extent that it leads to new and creative outcomes.', 5, 1),
(8, 'Diocese of San Carlos', 'DOSC', 'Church System', '2022-01-01', '2024-06-25', 'image/1.png', 'Smart Diocese App', 6, 1),
(10, 'Tanon State', 'MIT', 'Church System', '2025-01-30', '2025-01-16', 'image/Teacher-male512_44209.png', 'pataka', NULL, 1);

ALTER TABLE `alumni_details` AUTO_INCREMENT = 11;

-- ---------------------------------------------------------------------
-- tblschoolyear
-- ---------------------------------------------------------------------
CREATE TABLE `tblschoolyear` (
  `SY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SCHOOL_YEAR` varchar(20) NOT NULL,
  `STATUS` varchar(20) NOT NULL DEFAULT 'Inactive',
  PRIMARY KEY (`SY_ID`),
  UNIQUE KEY `SCHOOL_YEAR` (`SCHOOL_YEAR`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only one school year should be Active at a time.
INSERT INTO `tblschoolyear` (`SY_ID`, `SCHOOL_YEAR`, `STATUS`) VALUES
(1, '2025-2026', 'Active'),
(4, '2024-2025', 'Inactive');

ALTER TABLE `tblschoolyear` AUTO_INCREMENT = 5;

-- ---------------------------------------------------------------------
-- tblsections  -> tblcourses, tblschoolyear
-- ---------------------------------------------------------------------
CREATE TABLE `tblsections` (
  `SECTION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SECTION_NAME` varchar(50) NOT NULL,
  `COURSE_ID` int(11) NOT NULL,
  `SY_ID` int(11) NOT NULL,
  `YEAR_LEVEL` varchar(20) NOT NULL,
  `PROGRAM_HEAD` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`SECTION_ID`),
  KEY `fk_sections_course` (`COURSE_ID`),
  KEY `fk_sections_sy` (`SY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblsections` (`SECTION_ID`, `SECTION_NAME`, `COURSE_ID`, `SY_ID`, `YEAR_LEVEL`, `PROGRAM_HEAD`) VALUES
(1, 'A', 1, 1, '1st Year', NULL),
(2, 'B', 1, 1, '1st Year', NULL),
(3, 'C', 1, 1, '1st Year', NULL),
(4, 'D', 1, 1, '1st Year', NULL),
(5, 'A', 2, 1, '1st Year', NULL),
(6, 'B', 2, 1, '1st Year', NULL),
(7, 'C', 2, 1, '1st Year', NULL),
(8, 'D', 2, 1, '1st Year', NULL),
(9, 'A', 3, 1, '1st Year', NULL),
(10, 'B', 3, 1, '1st Year', NULL),
(11, 'C', 3, 1, '1st Year', NULL),
(12, 'D', 3, 1, '1st Year', NULL);

ALTER TABLE `tblsections` AUTO_INCREMENT = 13;

-- ---------------------------------------------------------------------
-- tblsubjects  -> tblcourses
-- The old dump had this table empty, which left the Subject module,
-- Enrollment Details and Grades with nothing to point at. Seeded with
-- 1st Year 1st Semester subjects for each of the three courses.
-- ---------------------------------------------------------------------
CREATE TABLE `tblsubjects` (
  `SUBJECT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SUBJECT_CODE` varchar(20) NOT NULL,
  `SUBJECT_NAME` varchar(150) NOT NULL,
  `UNITS` int(11) NOT NULL DEFAULT 3,
  `COURSE_ID` int(11) NOT NULL,
  `YEAR_LEVEL` varchar(20) DEFAULT NULL,
  `SEMESTER` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`SUBJECT_ID`),
  UNIQUE KEY `uq_subject_code` (`SUBJECT_CODE`),
  KEY `fk_subjects_course` (`COURSE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblsubjects` (`SUBJECT_ID`, `SUBJECT_CODE`, `SUBJECT_NAME`, `UNITS`, `COURSE_ID`, `YEAR_LEVEL`, `SEMESTER`) VALUES
(1, 'IT101', 'Introduction to Computing', 3, 1, '1st Year', '1st Semester'),
(2, 'IT102', 'Computer Programming 1', 3, 1, '1st Year', '1st Semester'),
(3, 'IT103', 'Discrete Mathematics', 3, 1, '1st Year', '1st Semester'),
(4, 'IT104', 'Web Systems and Technologies', 3, 1, '1st Year', '2nd Semester'),
(5, 'TM101', 'Introduction to Tourism', 3, 2, '1st Year', '1st Semester'),
(6, 'TM102', 'Philippine Culture and Tourism Geography', 3, 2, '1st Year', '1st Semester'),
(7, 'TM103', 'Micro Perspective of Tourism', 3, 2, '1st Year', '2nd Semester'),
(8, 'ED101', 'The Teaching Profession', 3, 3, '1st Year', '1st Semester'),
(9, 'ED102', 'Child and Adolescent Development', 3, 3, '1st Year', '1st Semester'),
(10, 'ED103', 'Facilitating Learner-Centered Teaching', 3, 3, '1st Year', '2nd Semester');

ALTER TABLE `tblsubjects` AUTO_INCREMENT = 11;

-- ---------------------------------------------------------------------
-- tblenrollment  -> tblstudent, tblcourses, tblsections, tblschoolyear
-- ---------------------------------------------------------------------
CREATE TABLE `tblenrollment` (
  `ENROLLMENT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `S_ID` int(11) NOT NULL,
  `COURSE_ID` int(11) NOT NULL,
  `SECTION_ID` int(11) DEFAULT NULL,
  `SY_ID` int(11) NOT NULL,
  `YEAR_LEVEL` varchar(20) NOT NULL,
  `SEMESTER` varchar(20) NOT NULL,
  `CATEGORY` varchar(30) NOT NULL DEFAULT 'New',
  `CURRICULUM_YR` varchar(20) DEFAULT NULL,
  `DATE_RESERVED` date DEFAULT NULL,
  `DATE_ENROLLED` date DEFAULT NULL,
  `STATUS` varchar(30) NOT NULL DEFAULT 'Registered',
  `ENCODED_BY` int(11) DEFAULT NULL,
  PRIMARY KEY (`ENROLLMENT_ID`),
  UNIQUE KEY `uq_enrollment_term` (`S_ID`,`SY_ID`,`SEMESTER`),
  KEY `fk_enrollment_student` (`S_ID`),
  KEY `fk_enrollment_course` (`COURSE_ID`),
  KEY `fk_enrollment_section` (`SECTION_ID`),
  KEY `fk_enrollment_sy` (`SY_ID`),
  KEY `fk_enrollment_encodedby` (`ENCODED_BY`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Two-stage flow. SECTION_ID and DATE_ENROLLED stay NULL until the
-- Sectioning step, which is why they are nullable. Row 3 is left as a
-- Registered record on purpose so the flow is visible on first run.
INSERT INTO `tblenrollment` (`ENROLLMENT_ID`, `S_ID`, `COURSE_ID`, `SECTION_ID`, `SY_ID`, `YEAR_LEVEL`, `SEMESTER`, `CATEGORY`, `CURRICULUM_YR`, `DATE_RESERVED`, `DATE_ENROLLED`, `STATUS`, `ENCODED_BY`) VALUES
(1, 1, 1, 1, 1, '1st Year', '1st Semester', 'New', '2025-2026', '2025-06-05', '2025-06-10', 'Enrolled', 1),
(2, 5, 1, 2, 1, '1st Year', '1st Semester', 'Old', '2025-2026', '2025-06-06', '2025-06-11', 'Enrolled', 1),
(3, 6, 2, NULL, 1, '1st Year', '1st Semester', 'Transferee', '2025-2026', '2025-06-12', NULL, 'Registered', 1);

ALTER TABLE `tblenrollment` AUTO_INCREMENT = 4;

-- ---------------------------------------------------------------------
-- tblenrollment_details  -> tblenrollment, tblsubjects
-- ---------------------------------------------------------------------
CREATE TABLE `tblenrollment_details` (
  `DETAIL_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ENROLLMENT_ID` int(11) NOT NULL,
  `SUBJECT_ID` int(11) NOT NULL,
  PRIMARY KEY (`DETAIL_ID`),
  UNIQUE KEY `uq_enrollment_subject` (`ENROLLMENT_ID`,`SUBJECT_ID`),
  KEY `fk_endetails_enrollment` (`ENROLLMENT_ID`),
  KEY `fk_endetails_subject` (`SUBJECT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblenrollment_details` (`DETAIL_ID`, `ENROLLMENT_ID`, `SUBJECT_ID`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 2, 1),
(5, 2, 2),
(6, 2, 3);

ALTER TABLE `tblenrollment_details` AUTO_INCREMENT = 7;

-- ---------------------------------------------------------------------
-- tblgrades  -> tblstudent, tblsubjects, tblenrollment, tblschoolyear
-- ---------------------------------------------------------------------
CREATE TABLE `tblgrades` (
  `GRADE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `S_ID` int(11) NOT NULL,
  `SUBJECT_ID` int(11) NOT NULL,
  `ENROLLMENT_ID` int(11) NOT NULL,
  `SY_ID` int(11) NOT NULL,
  `SEMESTER` varchar(20) DEFAULT NULL,
  `GRADE` decimal(5,2) DEFAULT NULL,
  `REMARKS` varchar(20) DEFAULT NULL,
  `DATE_ENCODED` date NOT NULL DEFAULT curdate(),
  PRIMARY KEY (`GRADE_ID`),
  KEY `fk_grades_student` (`S_ID`),
  KEY `fk_grades_subject` (`SUBJECT_ID`),
  KEY `fk_grades_enrollment` (`ENROLLMENT_ID`),
  KEY `fk_grades_sy` (`SY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblgrades` (`GRADE_ID`, `S_ID`, `SUBJECT_ID`, `ENROLLMENT_ID`, `SY_ID`, `SEMESTER`, `GRADE`, `REMARKS`, `DATE_ENCODED`) VALUES
(1, 1, 1, 1, 1, '1st Semester', 1.75, 'Passed', '2025-10-20'),
(2, 1, 2, 1, 1, '1st Semester', 2.00, 'Passed', '2025-10-20'),
(3, 1, 3, 1, 1, '1st Semester', 1.50, 'Passed', '2025-10-20'),
(4, 5, 1, 2, 1, '1st Semester', 2.25, 'Passed', '2025-10-20'),
(5, 5, 2, 2, 1, '1st Semester', 3.00, 'Passed', '2025-10-20'),
(6, 5, 3, 2, 1, '1st Semester', 1.25, 'Passed', '2025-10-20');

ALTER TABLE `tblgrades` AUTO_INCREMENT = 7;

-- ---------------------------------------------------------------------
-- tblfeeschedule  -> tblcourses
-- Tuition per course and year level. The Payments screen reads this to
-- show Total Tuition and Remaining Balance. Edit it under
-- Payments > Fee Schedule.
-- ---------------------------------------------------------------------
CREATE TABLE `tblfeeschedule` (
  `FEE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `COURSE_ID` int(11) NOT NULL,
  `YEAR_LEVEL` varchar(20) NOT NULL,
  `TUITION_FEE` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`FEE_ID`),
  UNIQUE KEY `uq_fee_course_year` (`COURSE_ID`,`YEAR_LEVEL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblfeeschedule` (`FEE_ID`, `COURSE_ID`, `YEAR_LEVEL`, `TUITION_FEE`) VALUES
(1, 1, '1st Year', 18500.00), (2, 1, '2nd Year', 19000.00), (3, 1, '3rd Year', 19500.00), (4, 1, '4th Year', 20000.00),
(5, 2, '1st Year', 17500.00), (6, 2, '2nd Year', 18000.00), (7, 2, '3rd Year', 18500.00), (8, 2, '4th Year', 19000.00),
(9, 3, '1st Year', 16500.00), (10, 3, '2nd Year', 17000.00), (11, 3, '3rd Year', 17500.00), (12, 3, '4th Year', 18000.00);

ALTER TABLE `tblfeeschedule` AUTO_INCREMENT = 13;

-- ---------------------------------------------------------------------
-- tblpayments  -> tblenrollment, tblusers
-- Admission Fee must exist before Sectioning is allowed.
-- ---------------------------------------------------------------------
CREATE TABLE `tblpayments` (
  `PAYMENT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ENROLLMENT_ID` int(11) NOT NULL,
  `FEE_TYPE` varchar(30) NOT NULL,
  `AMOUNT` decimal(10,2) NOT NULL,
  `OR_NUMBER` varchar(30) NOT NULL,
  `DATE_PAID` date NOT NULL,
  `RECEIVED_BY` int(11) DEFAULT NULL,
  `REMARKS` varchar(255) DEFAULT NULL,
  `CREATED_AT` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`PAYMENT_ID`),
  UNIQUE KEY `uq_or_number` (`OR_NUMBER`),
  KEY `fk_payments_enrollment` (`ENROLLMENT_ID`),
  KEY `fk_payments_receivedby` (`RECEIVED_BY`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblpayments` (`PAYMENT_ID`, `ENROLLMENT_ID`, `FEE_TYPE`, `AMOUNT`, `OR_NUMBER`, `DATE_PAID`, `RECEIVED_BY`, `REMARKS`) VALUES
(1, 1, 'Entrance Exam', 150.00, 'OR-0001', '2025-06-04', 1, NULL),
(2, 1, 'Admission Fee', 30.00, 'OR-0002', '2025-06-05', 1, NULL),
(3, 1, 'Enrollment Fee', 5000.00, 'OR-0003', '2025-06-10', 1, 'Down payment'),
(4, 2, 'Admission Fee', 30.00, 'OR-0004', '2025-06-06', 1, NULL),
(5, 2, 'Enrollment Fee', 3500.00, 'OR-0005', '2025-06-11', 1, NULL);

ALTER TABLE `tblpayments` AUTO_INCREMENT = 6;

-- ---------------------------------------------------------------------
-- tblattendance  -> tblusers
-- One row per staff account per day, filled by the RFID Scan Kiosk.
-- ---------------------------------------------------------------------
CREATE TABLE `tblattendance` (
  `ATTENDANCE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `UID` int(11) NOT NULL,
  `ATTENDANCE_DATE` date NOT NULL,
  `AM_TIME_IN` time DEFAULT NULL,
  `AM_TIME_OUT` time DEFAULT NULL,
  `PM_TIME_IN` time DEFAULT NULL,
  `PM_TIME_OUT` time DEFAULT NULL,
  PRIMARY KEY (`ATTENDANCE_ID`),
  UNIQUE KEY `uq_attendance_day` (`UID`,`ATTENDANCE_DATE`),
  KEY `idx_attendance_date` (`ATTENDANCE_DATE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tblattendance` (`UID`, `ATTENDANCE_DATE`, `AM_TIME_IN`, `AM_TIME_OUT`, `PM_TIME_IN`, `PM_TIME_OUT`) VALUES
(81, CURDATE() - INTERVAL 1 DAY, '07:52:10', '12:01:44', '12:57:03', '17:02:31'),
(1,  CURDATE() - INTERVAL 1 DAY, '08:21:37', '12:00:12', '13:04:55', NULL);

-- ---------------------------------------------------------------------
-- CHAT (Messages tab)
--   tblchat_conversations  one row per pair of users (USER_ONE < USER_TWO)
--   tblchat_messages       text / image / file, with Sent, Delivered and
--                          Seen times, unsend flag, and UPDATED_AT so the
--                          other side picks up status changes
--   tblchat_reactions      one reaction per user per message
--   tblchat_presence       online flag, last seen, typing indicator
-- utf8mb4 everywhere so emojis are stored correctly.
-- ---------------------------------------------------------------------
CREATE TABLE `tblchat_conversations` (
  `CONV_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ONE` int(11) NOT NULL,
  `USER_TWO` int(11) NOT NULL,
  `LAST_MSG_ID` int(11) DEFAULT NULL,
  `CREATED_AT` datetime(3) NOT NULL,
  `UPDATED_AT` datetime(3) NOT NULL,
  PRIMARY KEY (`CONV_ID`),
  UNIQUE KEY `uq_chat_pair` (`USER_ONE`,`USER_TWO`),
  KEY `fk_chatconv_user_two` (`USER_TWO`),
  KEY `fk_chatconv_lastmsg` (`LAST_MSG_ID`),
  KEY `idx_chatconv_updated` (`UPDATED_AT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tblchat_messages` (
  `MSG_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CONV_ID` int(11) NOT NULL,
  `SENDER_ID` int(11) NOT NULL,
  `RECEIVER_ID` int(11) NOT NULL,
  `MSG_TYPE` varchar(10) NOT NULL DEFAULT 'text',
  `BODY` text DEFAULT NULL,
  `FILE_PATH` varchar(255) DEFAULT NULL,
  `FILE_NAME` varchar(255) DEFAULT NULL,
  `FILE_MIME` varchar(100) DEFAULT NULL,
  `FILE_SIZE` int(11) DEFAULT NULL,
  `FILE_W` smallint(5) UNSIGNED DEFAULT NULL,
  `FILE_H` smallint(5) UNSIGNED DEFAULT NULL,
  `CLIENT_ID` varchar(40) DEFAULT NULL,
  `SENT_AT` datetime(3) NOT NULL,
  `DELIVERED_AT` datetime(3) DEFAULT NULL,
  `SEEN_AT` datetime(3) DEFAULT NULL,
  `IS_UNSENT` tinyint(1) NOT NULL DEFAULT 0,
  `UPDATED_AT` datetime(3) NOT NULL,
  PRIMARY KEY (`MSG_ID`),
  UNIQUE KEY `uq_chatmsg_client` (`SENDER_ID`,`CLIENT_ID`),
  KEY `idx_chatmsg_conv` (`CONV_ID`,`MSG_ID`),
  KEY `idx_chatmsg_conv_updated` (`CONV_ID`,`UPDATED_AT`),
  KEY `idx_chatmsg_delivery` (`RECEIVER_ID`,`DELIVERED_AT`),
  KEY `idx_chatmsg_seen` (`RECEIVER_ID`,`SEEN_AT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tblchat_reactions` (
  `MSG_ID` int(11) NOT NULL,
  `UID` int(11) NOT NULL,
  `EMOJI` varchar(16) NOT NULL,
  `CREATED_AT` datetime(3) NOT NULL,
  PRIMARY KEY (`MSG_ID`,`UID`),
  KEY `fk_chatreact_user` (`UID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tblchat_presence` (
  `UID` int(11) NOT NULL,
  `IS_ONLINE` tinyint(1) NOT NULL DEFAULT 0,
  `LAST_SEEN` datetime(3) DEFAULT NULL,
  `TYPING_TO` int(11) DEFAULT NULL,
  `TYPING_AT` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`UID`),
  KEY `fk_chatpresence_typingto` (`TYPING_TO`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A short sample conversation between Jason (admin) and Rojhon so the
-- Messages tab is not empty on first run.
INSERT INTO `tblchat_conversations` (`CONV_ID`, `USER_ONE`, `USER_TWO`, `LAST_MSG_ID`, `CREATED_AT`, `UPDATED_AT`) VALUES
(1, 1, 81, 4, NOW(3) - INTERVAL 2 HOUR, NOW(3) - INTERVAL 95 MINUTE);

INSERT INTO `tblchat_messages` (`MSG_ID`, `CONV_ID`, `SENDER_ID`, `RECEIVER_ID`, `MSG_TYPE`, `BODY`, `SENT_AT`, `DELIVERED_AT`, `SEEN_AT`, `IS_UNSENT`, `UPDATED_AT`) VALUES
(1, 1, 1, 81, 'text', 'Good morning Rojhon! 👋 The new Messages tab is live.', NOW(3) - INTERVAL 2 HOUR, NOW(3) - INTERVAL 2 HOUR, NOW(3) - INTERVAL 118 MINUTE, 0, NOW(3) - INTERVAL 118 MINUTE),
(2, 1, 81, 1, 'text', 'Nice! Can I send files and photos here too?', NOW(3) - INTERVAL 117 MINUTE, NOW(3) - INTERVAL 117 MINUTE, NOW(3) - INTERVAL 116 MINUTE, 0, NOW(3) - INTERVAL 116 MINUTE),
(3, 1, 1, 81, 'text', 'Yes 😄 images, PDFs, Word and Excel files. Drag them in or paste a screenshot.', NOW(3) - INTERVAL 115 MINUTE, NOW(3) - INTERVAL 115 MINUTE, NOW(3) - INTERVAL 100 MINUTE, 0, NOW(3) - INTERVAL 100 MINUTE),
(4, 1, 1, 81, 'text', 'Try reacting to this message ❤️', NOW(3) - INTERVAL 95 MINUTE, NULL, NULL, 0, NOW(3) - INTERVAL 95 MINUTE);

ALTER TABLE `tblchat_conversations` AUTO_INCREMENT = 2;
ALTER TABLE `tblchat_messages` AUTO_INCREMENT = 5;

INSERT INTO `tblchat_reactions` (`MSG_ID`, `UID`, `EMOJI`, `CREATED_AT`) VALUES
(3, 81, '👍', NOW(3) - INTERVAL 99 MINUTE);

INSERT INTO `tblchat_presence` (`UID`, `IS_ONLINE`, `LAST_SEEN`, `TYPING_TO`, `TYPING_AT`) VALUES
(1, 0, NOW(3) - INTERVAL 90 MINUTE, NULL, NULL),
(81, 0, NOW(3) - INTERVAL 99 MINUTE, NULL, NULL);

-- =====================================================================
-- FOREIGN KEYS
-- Links added after the original alumni_db are marked NEW.
-- =====================================================================

-- NEW: user account -> user type
ALTER TABLE `tblusers`
  ADD CONSTRAINT `fk_users_usertype` FOREIGN KEY (`TYPEID`)
      REFERENCES `tblusertype` (`TYPEID`) ON DELETE SET NULL ON UPDATE CASCADE;

-- NEW: student -> course, student -> user who encoded it
ALTER TABLE `tblstudent`
  ADD CONSTRAINT `fk_student_course` FOREIGN KEY (`COURSE_ID`)
      REFERENCES `tblcourses` (`COURSE_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_addedby` FOREIGN KEY (`AddedBy`)
      REFERENCES `tblusers` (`UID`) ON DELETE SET NULL ON UPDATE CASCADE;

-- NEW: alumni record -> student, alumni record -> user who encoded it
ALTER TABLE `alumni_details`
  ADD CONSTRAINT `fk_alumni_student` FOREIGN KEY (`S_ID`)
      REFERENCES `tblstudent` (`S_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alumni_addedby` FOREIGN KEY (`AddedBy`)
      REFERENCES `tblusers` (`UID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `tblsections`
  ADD CONSTRAINT `fk_sections_course` FOREIGN KEY (`COURSE_ID`)
      REFERENCES `tblcourses` (`COURSE_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sections_sy` FOREIGN KEY (`SY_ID`)
      REFERENCES `tblschoolyear` (`SY_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tblsubjects`
  ADD CONSTRAINT `fk_subjects_course` FOREIGN KEY (`COURSE_ID`)
      REFERENCES `tblcourses` (`COURSE_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tblenrollment`
  ADD CONSTRAINT `fk_enrollment_student` FOREIGN KEY (`S_ID`)
      REFERENCES `tblstudent` (`S_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrollment_course` FOREIGN KEY (`COURSE_ID`)
      REFERENCES `tblcourses` (`COURSE_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrollment_section` FOREIGN KEY (`SECTION_ID`)
      REFERENCES `tblsections` (`SECTION_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrollment_encodedby` FOREIGN KEY (`ENCODED_BY`)
      REFERENCES `tblusers` (`UID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_enrollment_sy` FOREIGN KEY (`SY_ID`)
      REFERENCES `tblschoolyear` (`SY_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tblenrollment_details`
  ADD CONSTRAINT `fk_endetails_enrollment` FOREIGN KEY (`ENROLLMENT_ID`)
      REFERENCES `tblenrollment` (`ENROLLMENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_endetails_subject` FOREIGN KEY (`SUBJECT_ID`)
      REFERENCES `tblsubjects` (`SUBJECT_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tblgrades`
  ADD CONSTRAINT `fk_grades_student` FOREIGN KEY (`S_ID`)
      REFERENCES `tblstudent` (`S_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grades_subject` FOREIGN KEY (`SUBJECT_ID`)
      REFERENCES `tblsubjects` (`SUBJECT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grades_enrollment` FOREIGN KEY (`ENROLLMENT_ID`)
      REFERENCES `tblenrollment` (`ENROLLMENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_grades_sy` FOREIGN KEY (`SY_ID`)
      REFERENCES `tblschoolyear` (`SY_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- NEW: payments, fee schedule, attendance
ALTER TABLE `tblfeeschedule`
  ADD CONSTRAINT `fk_fee_course` FOREIGN KEY (`COURSE_ID`)
      REFERENCES `tblcourses` (`COURSE_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tblpayments`
  ADD CONSTRAINT `fk_payments_enrollment` FOREIGN KEY (`ENROLLMENT_ID`)
      REFERENCES `tblenrollment` (`ENROLLMENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_receivedby` FOREIGN KEY (`RECEIVED_BY`)
      REFERENCES `tblusers` (`UID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `tblattendance`
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`UID`)
      REFERENCES `tblusers` (`UID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- NEW: chat
ALTER TABLE `tblchat_conversations`
  ADD CONSTRAINT `fk_chatconv_user_one` FOREIGN KEY (`USER_ONE`)
      REFERENCES `tblusers` (`UID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chatconv_user_two` FOREIGN KEY (`USER_TWO`)
      REFERENCES `tblusers` (`UID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chatconv_lastmsg` FOREIGN KEY (`LAST_MSG_ID`)
      REFERENCES `tblchat_messages` (`MSG_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `tblchat_messages`
  ADD CONSTRAINT `fk_chatmsg_conv` FOREIGN KEY (`CONV_ID`)
      REFERENCES `tblchat_conversations` (`CONV_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chatmsg_sender` FOREIGN KEY (`SENDER_ID`)
      REFERENCES `tblusers` (`UID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chatmsg_receiver` FOREIGN KEY (`RECEIVER_ID`)
      REFERENCES `tblusers` (`UID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tblchat_reactions`
  ADD CONSTRAINT `fk_chatreact_msg` FOREIGN KEY (`MSG_ID`)
      REFERENCES `tblchat_messages` (`MSG_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chatreact_user` FOREIGN KEY (`UID`)
      REFERENCES `tblusers` (`UID`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tblchat_presence`
  ADD CONSTRAINT `fk_chatpresence_user` FOREIGN KEY (`UID`)
      REFERENCES `tblusers` (`UID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chatpresence_typingto` FOREIGN KEY (`TYPING_TO`)
      REFERENCES `tblusers` (`UID`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
