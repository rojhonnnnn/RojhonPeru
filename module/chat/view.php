<?php
// ROJHON SOLUTIONS - Messages layout. Filled and kept live by theme/chat.js.
$openWith = isset($_GET['u']) ? (int)$_GET['u'] : 0;
?>
<div class="rs-chat" id="rsChat" data-open="<?php echo $openWith; ?>" data-max-upload="<?php echo (int)Chat::maxUploadBytes(); ?>">

  <aside class="rs-side" aria-label="Chats">
    <div class="rs-side-head">
      <h2>Chats</h2>
      <button type="button" class="rs-iconbtn grey" id="rsNew" title="New message" aria-label="New message"><i class="fas fa-edit"></i></button>
    </div>
    <div class="rs-search">
      <i class="fas fa-search" aria-hidden="true"></i>
      <input type="search" id="rsSearch" placeholder="Search people" autocomplete="off" aria-label="Search people">
    </div>
    <div class="rs-strip" id="rsStrip" aria-label="Active now"></div>
    <div class="rs-list" id="rsList" role="list">
      <div class="rs-list-empty">Loading chats...</div>
    </div>
  </aside>

  <section class="rs-main" aria-label="Conversation">
    <div class="rs-empty" id="rsEmpty">
      <div class="rs-empty-mark"><i class="fas fa-comment-dots"></i></div>
      <h3>Your messages</h3>
      <p>Pick a chat on the left, or start a new one with anyone who has an account.</p>
      <button type="button" class="btn btn-primary btn-sm" id="rsEmptyNew">Start a new chat</button>
    </div>

    <div class="rs-thread" id="rsThread" hidden>
      <header class="rs-head">
        <button type="button" class="rs-iconbtn rs-back" id="rsBack" title="Back to chats" aria-label="Back to chats"><i class="fas fa-arrow-left"></i></button>
        <div class="rs-head-who">
          <span id="rsHeadAv"></span>
          <div style="min-width:0">
            <div class="rs-head-name" id="rsHeadName"></div>
            <div class="rs-head-status" id="rsHeadStatus"></div>
          </div>
        </div>
        <button type="button" class="rs-iconbtn" id="rsInfoBtn" title="Conversation info" aria-label="Conversation info"><i class="fas fa-info-circle"></i></button>
      </header>
      <div class="rs-offline" id="rsOffline" hidden>Reconnecting... messages will send when the connection is back.</div>

      <div class="rs-scroll" id="rsScroll" aria-live="polite">
        <div class="rs-intro" id="rsIntro"></div>
        <div class="rs-older" id="rsOlder" hidden></div>
        <div id="rsMsgs"></div>
        <div class="rs-typing" id="rsTyping" hidden>
          <span class="rs-av sm" id="rsTypingAv"></span>
          <div class="rs-bubble" aria-label="typing"><i></i><i></i><i></i></div>
        </div>
      </div>
      <button type="button" class="rs-jump" id="rsJump" hidden>New messages <i class="fas fa-arrow-down"></i></button>
      <div class="rs-drop" id="rsDrop" hidden>Drop files to send</div>

      <footer class="rs-compose">
        <div class="rs-tray" id="rsTray"></div>
        <div class="rs-bar">
          <button type="button" class="rs-iconbtn" id="rsPickImage" title="Send a photo" aria-label="Send a photo"><i class="fas fa-image"></i></button>
          <button type="button" class="rs-iconbtn" id="rsPickFile" title="Attach a file" aria-label="Attach a file"><i class="fas fa-paperclip"></i></button>
          <div class="rs-input">
            <textarea id="rsInput" rows="1" placeholder="Aa" aria-label="Message" maxlength="5000"></textarea>
            <button type="button" class="rs-iconbtn" id="rsEmojiBtn" title="Choose an emoji" aria-label="Choose an emoji"><i class="far fa-smile"></i></button>
          </div>
          <button type="button" class="rs-iconbtn rs-send like" id="rsSend" title="Send a like" aria-label="Send">👍</button>
        </div>
        <div class="rs-emoji" id="rsEmoji" hidden>
          <div class="rs-emoji-tabs" id="rsEmojiTabs"></div>
          <div class="rs-emoji-title" id="rsEmojiTitle"></div>
          <div class="rs-emoji-grid" id="rsEmojiGrid"></div>
        </div>
        <input type="file" id="rsFileImage" accept="image/*" multiple hidden>
        <input type="file" id="rsFileAny" multiple hidden>
      </footer>
    </div>
  </section>

  <aside class="rs-info" id="rsInfo" hidden aria-label="Conversation info">
    <div class="rs-info-top">
      <span id="rsInfoAv"></span>
      <h4 id="rsInfoName"></h4>
      <p id="rsInfoType"></p>
      <p id="rsInfoStatus"></p>
    </div>
    <h5>Shared photos</h5>
    <div class="rs-media" id="rsInfoMedia"></div>
    <h5>Shared files</h5>
    <div class="rs-files" id="rsInfoFiles"></div>
  </aside>
</div>

<div class="rs-lightbox" id="rsLightbox" hidden role="dialog" aria-label="Photo viewer">
  <div class="rs-lightbox-bar">
    <a href="#" id="rsLightboxDl" title="Download" aria-label="Download"><i class="fas fa-download"></i></a>
    <button type="button" id="rsLightboxClose" title="Close" aria-label="Close"><i class="fas fa-times"></i></button>
  </div>
  <img id="rsLightboxImg" src="" alt="">
  <div class="rs-lightbox-cap" id="rsLightboxCap"></div>
</div>
