<div class="card chat-container">
  <div class="chat-header">
    <div class="flex items-center gap-2">
      <div class="status-indicator online"></div>
      <h3 class="m-0"><?= $__t('global_chat') ?></h3>
    </div>
    <div class="text-muted text-sm" id="onlineCount"><?= $__t('loading') ?></div>
  </div>
  <div class="chat-messages" id="chatMessages"></div>
  <div class="chat-input-area">
    <form id="chatFormPage" class="flex gap-2">
      <input type="text" id="chatInput" class="form-item flex-grow" placeholder="<?= $__t('type_your_message') ?>" autocomplete="off">
      <button type="submit" class="btn btn-primary"><?= $__t('send') ?></button>
    </form>
  </div>
</div>
