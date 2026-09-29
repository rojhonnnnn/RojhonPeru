<?php
// ROJHON SOLUTIONS - Messages (Messenger-style chat between user accounts)
require_once("../../include/initialize.php");

$title   = "Messages";
$content = 'view.php';
$hideContentHeader = true;
$bodyClass   = 'rs-chat-page';
$pageStyles  = array(asset_v('theme/chat.css'));
$pageScripts = array(asset_v('theme/emoji.js'), asset_v('theme/chat.js'));


require_once("../../theme/template.php");
?>
