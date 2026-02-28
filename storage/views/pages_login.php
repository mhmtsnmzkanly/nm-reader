<div class="card max-w-520 mx-auto">
  <h1 class="mb-3"><?= $__t('login') ?></h1>
  <p class="text-muted mb-4"><?= $__t('use_account_to_continue') ?></p>
  <div class="flex gap-2">
    <button class="btn btn-primary" onclick="openModal('loginModal')"><?= $__t('open_login') ?></button>
    <button class="btn btn-outline" onclick="openModal('registerModal')"><?= $__t('create_account') ?></button>
  </div>
</div>

<script>
  window.addEventListener('load', function () {
    openModal('loginModal');
  });
</script>
