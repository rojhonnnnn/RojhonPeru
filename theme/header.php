<nav class="main-header navbar navbar-expand navbar-dark">
  <!-- ROJHON SOLUTIONS -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="<?php echo WEB_ROOT; ?>" class="nav-link">Home</a>
    </li>
  </ul>

  <ul class="navbar-nav ml-auto">

    <!-- Messages (Messenger-style dropdown, filled by theme/notify.js) -->
    <li class="nav-item dropdown rs-nav-msg">
      <a class="nav-link" data-toggle="dropdown" href="#" id="rsNavMsgToggle" title="Messages" aria-label="Messages">
        <i class="fas fa-comment-dots"></i>
        <span class="badge badge-danger navbar-badge rs-unread-badge" id="rsNavUnread" style="display:none">0</span>
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right rs-nav-dd p-0">
        <div class="rs-nav-dd-head">
          <strong>Chats</strong>
          <a href="<?php echo WEB_ROOT; ?>module/chat/" class="rs-nav-dd-open" title="Open Messages"><i class="fas fa-expand-alt"></i></a>
        </div>
        <div class="rs-nav-dd-list" id="rsNavList">
          <div class="rs-nav-dd-empty">Loading chats...</div>
        </div>
        <a href="<?php echo WEB_ROOT; ?>module/chat/" class="rs-nav-dd-foot">See all in Messages</a>
      </div>
    </li>

    <!-- Dark mode toggle -->
    <li class="nav-item">
      <a class="nav-link" href="#" id="darkModeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
        <i class="fas fa-moon" id="darkModeIcon"></i>
      </a>
    </li>

    <!-- Account -->
    <li class="nav-item dropdown">
      <a class="nav-link rs-nav-user" data-toggle="dropdown" href="#">
        <?php if ($__myPhoto) { ?>
          <img src="<?php echo h($__myPhoto); ?>" class="rs-nav-avatar" alt="">
        <?php } else { ?>
          <span class="rs-nav-avatar rs-initials" data-name="<?php echo h($_SESSION['DISPLAYNAME']); ?>"></span>
        <?php } ?>
        <span class="d-none d-md-inline ml-1"><?php echo h($_SESSION['DISPLAYNAME']); ?></span>
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right p-0">
        <div class="card card-widget widget-user mb-0">
          <div class="widget-user-header bg-info">
            <h4 class="widget-user-username"><?php echo h(isset($_SESSION['DISPLAYNAME']) ? $_SESSION['DISPLAYNAME'] : 'User'); ?></h4>
            <h5 class="widget-user-desc"><?php echo h(isset($_SESSION['TYPE']) ? $_SESSION['TYPE'] : 'Guest'); ?></h5>
          </div>
          <div class="widget-user-image">
            <img class="img-circle elevation-2" style="width:90px;height:90px;object-fit:cover;background:#fff" src="<?php echo h($__myPhoto ? $__myPhoto : WEB_ROOT.'module/user/images/default.png'); ?>" alt="User Avatar">
          </div>
          <div class="card-footer d-flex justify-content-between">
            <a href="<?php echo WEB_ROOT; ?>module/chat/" class="small-box-footer"><i class="fas fa-comment-dots"></i> Messages</a>
            <a href="<?php echo WEB_ROOT; ?>logout.php" class="small-box-footer">Logout <i class="fas fa-sign-out-alt"></i></a>
          </div>
        </div>
      </div>
    </li>
  </ul>
</nav>

<script>
  /* Plain JS on purpose: jQuery is loaded at the bottom of the page. */
  document.addEventListener('DOMContentLoaded', function () {
    var htmlEl = document.documentElement;
    var toggle = document.getElementById('darkModeToggle');
    var icon   = document.getElementById('darkModeIcon');
    function setIcon(dark) {
      if (!icon) { return; }
      icon.classList.toggle('fa-moon', !dark);
      icon.classList.toggle('fa-sun', dark);
    }
    var isDark = false;
    try { isDark = (localStorage.getItem('rojhonTheme') === 'dark'); } catch (e) {}
    htmlEl.classList.toggle('dark-mode-custom', isDark);
    setIcon(isDark);
    if (toggle) {
      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        var nowDark = htmlEl.classList.toggle('dark-mode-custom');
        try { localStorage.setItem('rojhonTheme', nowDark ? 'dark' : 'light'); } catch (err) {}
        setIcon(nowDark);
      });
    }
  });
</script>
