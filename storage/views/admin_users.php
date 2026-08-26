<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">User Management</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Users</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-edit-user" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="form-edit-user">
        <input type="hidden" name="id" id="edit-user-id">
        <div class="modal-header text-bg-secondary"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label small">Username</label><input class="form-control" id="edit-user-username" readonly></div>
          <div class="mb-3"><label class="form-label small">Email</label><input class="form-control" type="email" name="email" id="edit-user-email" required></div>
          <div class="mb-3"><label class="form-label small">Bio</label><textarea class="form-control" name="bio" id="edit-user-bio" rows="4" maxlength="1000" placeholder="No bio"></textarea></div>
          <div class="mb-3">
            <label class="form-label small">Assign Role</label>
            <select class="form-select" name="role" id="edit-user-role">
              <!-- Dynamically populated -->
            </select>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_banned" id="edit-user-banned">
            <label class="form-check-label" for="edit-user-banned">Ban User</label>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="card card-outline card-secondary">
      <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <h3 class="card-title">User Base</h3>
        <button class="btn btn-tool" id="btn-refresh-users"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 fs-7">
          <thead><tr><th><?= $__t('admin.id') ?></th><th><?= $__t('admin.username') ?></th><th><?= $__t('admin.email') ?></th><th><?= $__t('admin.role') ?></th><th><?= $__t('admin.time') ?></th><th><?= $__t('admin.action') ?></th></tr></thead>
          <tbody id="users-list-body"><tr><td colspan="6" class="text-center">Loading users...</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="card card-outline card-primary mt-4">
      <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="bi bi-shield-lock-fill me-2"></i>Yetki ve Rol Matrisi (RBAC Matrix)</h3>
        <button class="btn btn-sm btn-tool" onclick="NMR_ADMIN_USERS.loadPermissionMatrix()"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <div class="card-body p-0 table-responsive" id="permission-matrix-container">
        <table class="table table-sm table-bordered table-hover align-middle mb-0 fs-8">
          <thead class="table-dark" id="matrix-head">
            <tr><th>Yetki Grubu / İzin Tanımı</th><th class="text-center">Super Admin</th><th class="text-center">Admin</th><th class="text-center">Editor</th><th class="text-center">Translator</th><th class="text-center">Moderator</th></tr>
          </thead>
          <tbody id="matrix-body">
            <tr><td colspan="6" class="text-center py-3 text-muted">Yetki matrisi yükleniyor...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
