<?php
// ROJHON SOLUTIONS - sign in
require_once("include/initialize.php");

if (isset($_SESSION['UID'])) {
	header("Location: ".WEB_ROOT."index.php");
	exit;
}

$error = '';
$notice = '';
$username = '';
if (isset($_GET['logout'])) { $notice = 'You have been logged out.'; }
if (isset($_GET['expired'])) { $notice = 'Your session ended. Please log in again.'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim(isset($_POST['username']) ? (string)$_POST['username'] : '');
	$password = isset($_POST['userpass']) ? (string)$_POST['userpass'] : '';

	/* Slow down password guessing: after 5 failures in a row this browser
	   waits a little longer before each new attempt. */
	$fails = isset($_SESSION['LOGIN_FAILS']) ? (int)$_SESSION['LOGIN_FAILS'] : 0;
	if ($fails >= 5) { sleep(min(5, $fails - 4)); }

	if (!csrf_check(isset($_POST['_csrf']) ? $_POST['_csrf'] : '')) {
		$error = 'The login form expired. Please try again.';
	} elseif ($username === '' || $password === '') {
		$error = 'Enter your username and password.';
	} elseif (User::AuthenticateUser($username, $password)) {
		unset($_SESSION['LOGIN_FAILS']);
		Chat::touchPresence((int)$_SESSION['UID']);
		header("Location: ".WEB_ROOT."index.php");
		exit;
	} else {
		$_SESSION['LOGIN_FAILS'] = $fails + 1;
		$error = 'Wrong username or password, or the account is deactivated.';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in | <?php echo h(APP_SHORT); ?></title>
  <link rel="icon" type="image/png" href="<?php echo WEB_ROOT; ?>csr-scc.png">
  <script>try { if (localStorage.getItem('rojhonTheme') === 'dark') { document.documentElement.classList.add('dark-mode-custom'); } } catch (e) {}</script>
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="<?php echo WEB_ROOT; ?>dist/css/adminlte.min.css">
  <link rel="stylesheet" href="<?php echo asset_v('theme/custom.css'); ?>">
  <style>
    body.login-page {
      background: #f0f2f7;
      background-image: radial-gradient(circle at 12% 18%, rgba(28,47,158,.10) 0, transparent 42%),
                        radial-gradient(circle at 88% 82%, rgba(14,177,122,.10) 0, transparent 40%);
    }
    .rs-login { width: 380px; max-width: 94vw; }
    .rs-login .card { border: 0; border-radius: 14px; box-shadow: 0 18px 40px rgba(28,47,158,.12), 0 2px 6px rgba(0,0,0,.06); overflow: hidden; }
    .rs-login .card-body { padding: 2rem 2rem 1.6rem; }
    .rs-login-brand { text-align: center; margin-bottom: 1.4rem; }
    .rs-login-brand img { width: 96px; height: 96px; object-fit: contain; }
    .rs-login-brand h1 { font-size: 1.3rem; font-weight: 700; letter-spacing: .06em; margin: .75rem 0 .15rem; color: #1c2f9e; }
    .rs-login-brand p { margin: 0; color: #6c757d; font-size: .92rem; }
    .rs-login .form-control { height: 44px; border-radius: 10px; }
    .rs-login .input-group-text { border-radius: 0 10px 10px 0; background: transparent; cursor: pointer; }
    .rs-login .input-group .form-control { border-right: 0; }
    .rs-login .btn-login { height: 44px; border-radius: 10px; font-weight: 600; }
    .rs-login .alert { border-radius: 10px; font-size: .9rem; padding: .6rem .9rem; }
    .rs-login-foot { text-align: center; color: #8a93a6; font-size: .8rem; margin-top: 1rem; }
    .rs-login-bar { height: 4px; background: linear-gradient(90deg, #1c2f9e, #0aa5c7, #0eb17a); }
    html.dark-mode-custom body.login-page { background: #10141c; }
    html.dark-mode-custom .rs-login .card { background: #1a2030; }
    html.dark-mode-custom .rs-login-brand h1 { color: #8fa2ff; }
    html.dark-mode-custom .rs-login .input-group-text { color: #dbe1ea; border-color: #343d55; }
  </style>
</head>
<body class="hold-transition login-page">
<div class="rs-login">
  <div class="card">
    <div class="rs-login-bar"></div>
    <div class="card-body">
      <div class="rs-login-brand">
        <img src="<?php echo WEB_ROOT; ?>csr-scc.png" alt="School seal">
        <h1><?php echo h(APP_NAME); ?></h1>
        <p>Student and Alumni Records</p>
      </div>

      <?php if ($error) { ?><div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-circle mr-1"></i> <?php echo h($error); ?></div><?php } ?>
      <?php if (!$error && $notice) { ?><div class="alert alert-info" role="status"><?php echo h($notice); ?></div><?php } ?>

      <form action="<?php echo WEB_ROOT; ?>login.php" method="post" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?php echo h(csrf_token()); ?>">
        <div class="form-group">
          <label for="username" class="sr-only">Username</label>
          <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?php echo h($username); ?>" autocomplete="username" required autofocus>
        </div>
        <div class="form-group">
          <label for="userpass" class="sr-only">Password</label>
          <div class="input-group">
            <input type="password" class="form-control" id="userpass" name="userpass" placeholder="Password" autocomplete="current-password" required>
            <div class="input-group-append">
              <span class="input-group-text" id="togglePass" role="button" tabindex="0" title="Show password" aria-label="Show password"><i class="fas fa-eye"></i></span>
            </div>
          </div>
        </div>
        <button type="submit" name="btnLogin" class="btn btn-primary btn-block btn-login"><i class="fas fa-sign-in-alt mr-1"></i> Log in</button>
      </form>
      <div class="rs-login-foot">&copy; <?php echo date('Y'); ?> <?php echo h(APP_OWNER); ?> &middot; v<?php echo h(APP_VERSION); ?></div>
    </div>
  </div>
</div>
<script>
  (function () {
    var t = document.getElementById('togglePass'), p = document.getElementById('userpass');
    function flip() {
      var show = p.type === 'password';
      p.type = show ? 'text' : 'password';
      t.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
      t.title = show ? 'Hide password' : 'Show password';
    }
    t.addEventListener('click', flip);
    t.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); flip(); } });
  })();
</script>
</body>
</html>
