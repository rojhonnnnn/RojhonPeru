<?php
// ROJHON SOLUTIONS - main layout (sidebar + navbar + content)
//
// Pages set:
//   $title        page heading and active sidebar tab
//   $content      file to render inside the content area
//   $pageStyles   optional array of extra CSS urls
//   $pageScripts  optional array of extra JS urls (loaded after jQuery)
//   $bodyClass    optional extra class on <body>
//   $hideContentHeader  optional, true to drop the title bar (Messages uses it)

if (!isset($_SESSION['UID'])) {
	redirect(WEB_ROOT."login.php");
	exit;
}
if (!isset($title))       { $title = 'Home'; }
if (!isset($pageStyles))  { $pageStyles = array(); }
if (!isset($pageScripts)) { $pageScripts = array(); }
if (!isset($bodyClass))   { $bodyClass = ''; }
$isChatPage = ($title === 'Messages');

/* Fresh name/photo on every page so an edit in Manage User Accounts
   shows up in the navbar without logging out and back in. */
$__me = $mydb->fetchOne("SELECT DISPLAYNAME, TYPE, PHOTO FROM tblusers WHERE UID = ? LIMIT 1", array((int)$_SESSION['UID']));
if ($__me) {
	$_SESSION['DISPLAYNAME'] = $__me->DISPLAYNAME;
	$_SESSION['TYPE']        = $__me->TYPE;
	$_SESSION['PHOTO']       = $__me->PHOTO;
}
$__myPhoto = user_photo_url(isset($_SESSION['PHOTO']) ? $_SESSION['PHOTO'] : '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?php echo h(csrf_token()); ?>">
  <title><?php echo h($title); ?> | <?php echo h(APP_SHORT); ?></title>
  <link rel="icon" type="image/png" href="<?php echo WEB_ROOT; ?>csr-scc.png">
  <!-- Apply saved dark-mode preference before paint, so there is no flash of the wrong theme -->
  <script>
    try { if (localStorage.getItem('rojhonTheme') === 'dark') { document.documentElement.classList.add('dark-mode-custom'); } } catch (e) {}
    window.RS = {
      root: <?php echo json_encode(WEB_ROOT); ?>,
      uid: <?php echo (int)$_SESSION['UID']; ?>,
      name: <?php echo json_encode((string)$_SESSION['DISPLAYNAME']); ?>,
      photo: <?php echo json_encode($__myPhoto); ?>,
      csrf: <?php echo json_encode(csrf_token()); ?>,
      chatPage: <?php echo $isChatPage ? 'true' : 'false'; ?>
    };
  </script>
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>dist/css/adminlte.min.css">
  <!-- Custom theme (Hark palette + dark mode) -->
  <link rel="stylesheet" href="<?php echo asset_v('theme/custom.css'); ?>">
  <link rel="stylesheet" href="<?php echo asset_v('theme/notify.css'); ?>">
  <?php foreach ($pageStyles as $css) { echo '<link rel="stylesheet" href="'.h($css).'">'."\n  "; } ?>
  <!-- SweetAlert2 is loaded here (not at the bottom) because check_message()
       prints swal(...) calls inside the page body. Local copy, so alerts
       work with no internet. -->
  <script src="<?php echo WEB_ROOT; ?>plugins/sweetalert2/sweetalert2.min.js"></script>
  <script>
    /* The old pages call swal(title, text, icon) - SweetAlert 1 style.
       Route those to SweetAlert2 so both styles work. */
    window.swal = function (a, b, c) {
      if (a && typeof a === 'object') { return Swal.fire(a); }
      return Swal.fire({ title: a || '', text: b || '', icon: c || 'info' });
    };
  </script>
</head>
<body class="hold-transition sidebar-mini layout-fixed <?php echo h($bodyClass); ?>">
<div class="wrapper">

  <?php require_once("header.php"); ?>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?php echo WEB_ROOT; ?>" class="brand-link">
      <img src="<?php echo WEB_ROOT; ?>csr-scc.png" class="brand-image-fixed" alt="Logo">
      <span class="brand-text font-weight-light"><?php echo h(APP_NAME); ?></span>
    </a>
    <div class="sidebar">
      <?php require_once("sidebar.php"); ?>
    </div>
  </aside>

  <div class="content-wrapper">
    <?php if (empty($hideContentHeader)) { ?>
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0 text-dark"><?php echo h($title); ?></h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="<?php echo WEB_ROOT; ?>">Home</a></li>
              <li class="breadcrumb-item active"><?php echo h($title); ?></li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    <?php } ?>

    <?php require_once $content; ?>
  </div>

  <footer class="main-footer no-print">
    <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="<?php echo WEB_ROOT; ?>module/about/index.php"><?php echo h(APP_OWNER); ?></a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> <?php echo h(APP_VERSION); ?>
    </div>
  </footer>
</div>

<script src="<?php echo WEB_ROOT; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
  /* jQuery UI and Bootstrap both define .button and .tooltip */
  $.widget.bridge('uibutton', $.ui.button);
  /* Every AJAX call carries the CSRF token and is marked as AJAX, so an
     expired session comes back as JSON instead of the login page HTML. */
  $.ajaxSetup({ headers: { 'X-CSRF-Token': window.RS.csrf, 'X-Requested-With': 'XMLHttpRequest' } });
  $(document).ajaxError(function (e, xhr) {
    if (xhr && xhr.status === 401) { window.location = window.RS.root + 'login.php?expired=1'; }
  });
</script>
<script src="<?php echo WEB_ROOT; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/select2/js/select2.full.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/moment/moment.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
<script src="<?php echo WEB_ROOT; ?>dist/js/adminlte.min.js"></script>
<script>
  $(function () {
    $('.select2').not('.modal .select2').select2({ width: '100%' });
    $('.select2bs4').select2({ theme: 'bootstrap4', width: '100%' });
    if (window.bsCustomFileInput) { bsCustomFileInput.init(); }
    /* A select2 inside a Bootstrap modal needs the modal as its parent,
       otherwise its search box cannot be typed into. */
    $('.modal .select2').each(function () {
      $(this).select2({ width: '100%', dropdownParent: $(this).closest('.modal') });
    });
    /* DataTables: show server errors as a toast instead of the default alert() */
    if ($.fn.dataTable) {
      $.fn.dataTable.ext.errMode = function (settings, techNote, message) {
        if (window.console) { console.warn(message); }
      };
    }
  });
</script>
<script src="<?php echo asset_v('theme/notify.js'); ?>"></script>
<?php foreach ($pageScripts as $js) { echo '<script src="'.h($js).'"></script>'."\n"; } ?>
</body>
</html>
