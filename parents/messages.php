<?php
declare(strict_types=1);
/* Messages — per-page sidebar (child list) */
$parentPageTitle  = 'Messages';
$parentActive     = 'messages';
$parentPageScript = 'parents/messages';
$parentSidebar    = true;
require __DIR__ . '/_head.php';
?>
<div class="container pp-container">
  <div class="row g-4">
    <div class="col-lg-3">
      <aside class="pp-sidebar">
        <div class="pp-sidebar-title">My children</div>
        <div class="pp-child-list" id="ppChildList"></div>
      </aside>
    </div>
    <div class="col-lg-9">
      <div class="pp-card">
        <div class="pp-card-header">
          <h1><i class="bi bi-chat-dots-fill me-2 text-success"></i><span id="ppStudentName">Messages</span></h1>
          <span class="badge bg-success-subtle text-success rounded-pill" id="ppMsgCount"></span>
        </div>
        <div class="pp-card-body" id="ppMessagesContent"></div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_foot.php'; ?>