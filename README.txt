=======================================================================
ROJHON SOLUTIONS  -  School Records System  v3.1.0
PHP 8 + MySQL/MariaDB (XAMPP)  |  AdminLTE 3
=======================================================================

WHAT IS IN THIS BUILD
---------------------
* Student, Course, Subject, School Year, Sections, Enrollment (register
  then sectioning), Payments with Fee Schedule, Attendance (RFID Scan
  Kiosk + Report), Enrollment Details, Grades, Alumni Details, User
  Accounts and User Types.
* Messages: a Messenger-style chat between user accounts.
    - text, emojis (picker with 1,000+ emojis), photos and files
    - Sending / Sent / Delivered / Seen receipts, each with its time
    - Active now / Offline since <time> for every user
    - typing indicator, reactions, unsend for everyone, message details
    - unread badges on the navbar, sidebar and browser tab, a Chats
      dropdown in the navbar, pop-up toasts with a sound on every page
    - drag and drop or paste (Ctrl+V) screenshots, upload progress,
      retry when the connection drops, photo viewer, shared media panel
    - works on phones (list and conversation switch like the app)
* Dark mode (moon icon in the navbar), remembered per browser.


INSTALL (XAMPP ON WINDOWS)
--------------------------
1. Copy the "rojhon-class" folder into C:\xampp\htdocs\
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin
   - Import tab > Choose file > database\rojhon_db.sql > Go
   (The file creates the rojhon_db database itself. If an older
   rojhon_db exists, drop it first.)
4. Open http://localhost/rojhon-class/

   Optional vhost: follow setup\httpd-vhosts.conf and setup\hosts.txt,
   then open http://rojhon-class.local/  (both addresses work).

5. Chat uploads are saved in module\chat\uploads\. On Windows/XAMPP
   this works as-is. On Linux, make it writable:
       chmod -R 775 module/chat/uploads


LOGINS
------
    rojhon / rojhon      Administrator
    admin  / admin       Administrator  (display name Jason)
    dfd    / dfd         Staff          (old SHA1 account, upgraded on login)

Passwords are stored with bcrypt. Old SHA1 passwords still work and are
upgraded to bcrypt automatically the first time that user logs in.
Change these passwords after installing (Account Settings > Manage
User Accounts > key button).

To try the chat: log in as rojhon in one browser and as admin in
another (or a private/incognito window), then open Messages.


DATABASE
--------
rojhon_db, utf8mb4 (emojis are stored properly), 18 tables, 33 foreign
keys:

  alumni_details, tblusers, tblusertype, tblstudent, tblcourses,
  tblsubjects, tblschoolyear, tblsections, tblenrollment,
  tblenrollment_details, tblgrades, tblfeeschedule, tblpayments,
  tblattendance, tblchat_conversations, tblchat_messages,
  tblchat_reactions, tblchat_presence

Connection settings: include\config.php (root, no password, localhost).
Time zone: Asia/Manila for both PHP and MySQL.


HOW THE CHAT STAYS LIVE
-----------------------
No extra server or Node.js is needed. The open conversation checks for
changes every 1.5 seconds (every 5 seconds when the tab is in the
background); every other page checks every 4 seconds for the badge and
toasts. A user counts as "Active now" while any page of the system is
open and is shown as offline 30 seconds after the last page closes, or
immediately after Logout.

Upload rules: up to 10 MB per file (or less if php.ini is lower).
Photos: JPG, PNG, GIF, WEBP, BMP, checked by their real content.
Files: PDF, Office documents, text, CSV, ZIP/RAR/7Z, audio, video.
Scripts (.php, .html, .exe and similar) are refused. Files are saved
under random names and are only served to the two people in that
conversation; direct access to the uploads folder is blocked.


WHAT WAS FIXED FROM clyde-class
-------------------------------
Database
* The code used tables and columns the database did not have
  (tblpayments, tblfeeschedule, tblattendance, tblusers.PHOTO and
  RFID_NUMBER, tblstudent.PHOTO). All added, with sample data.
* Enrollment status was "Reserved" in the database but "Registered" in
  the code. Unified to "Registered".
* utf8mb4 on the connection, so emojis no longer turn into "????".
* PHP time zone set to Asia/Manila (XAMPP defaults to Europe/Berlin,
  which made every time wrong).

Tabs and pages
* Browser tab titles now show the page name plus the unread count.
* Sidebar tabs highlight the correct page, and Payments, Attendance and
  Account Settings open their submenu when you are inside them.
* The dark mode stylesheet was in the wrong folder and never loaded.
* A JavaScript error on every page (old jQuery .load() call and demo
  charts drawing on missing elements) is gone.
* The file picker no longer pops up an alert with the file name.
* Student Edit now saves the photo, LRN number, course and status.
* Links to pages that did not exist now show a proper 404 page.

Security
* Login could be bypassed by typing ' OR '1'='1 as the username.
  All queries that take user input now use prepared statements,
  including every table search, sort and page.
* Every AJAX endpoint used to answer without being logged in. All pages
  and endpoints now require a login (JSON 401 for AJAX, redirect for
  pages). Forms and the chat are protected with a CSRF token.
* Login slows down after 5 wrong tries. Sessions use HttpOnly cookies
  and a new session id after login.

Correctness
* Table counts (Showing 1 to 10 of N) were swapped, so paging was wrong.
* Row numbers now continue across pages instead of restarting at 1.
* Duplicate OR numbers, usernames, RFID cards, student ID numbers,
  course codes and user types give a clear message.
* You cannot delete or deactivate your own account, or delete the last
  active Administrator. A user type in use cannot be deleted.
* Renaming a user type updates the accounts that use it.
* Deleting a record that other records still use shows why it cannot
  be deleted instead of claiming success.
* PHP 8.2 deprecation warnings removed.


FOLDERS
-------
  database\          rojhon_db.sql
  include\           config, database, session, helpers, models,
                     chat.php (all chat logic)
  module\chat\       Messages page and api.php (chat JSON API)
  module\chat\uploads\  chat files (protected)
  module\...\        one folder per tab
  theme\             layout, sidebar, navbar, custom.css, chat.css,
                     chat.js, notify.js (badges and toasts), emoji.js
  setup\             vhost and hosts file instructions


TROUBLESHOOTING
---------------
* "Cannot connect to the database": start MySQL in XAMPP and import
  database\rojhon_db.sql.
* Chat photo or file will not send: check that module\chat\uploads\
  exists and is writable, and that upload_max_filesize and
  post_max_size in C:\xampp\php\php.ini are at least 10M.
* Times look wrong: the PC clock and time zone must be correct; the
  server side always uses Asia/Manila.
* After updating files, press Ctrl+F5 once to reload the styles.
