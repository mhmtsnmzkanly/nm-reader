<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Management Console</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/">Site</a></li>
          <li class="breadcrumb-item active">Admin</li>
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
        <div class="modal-header text-bg-secondary"><h5 class="modal-title">Edit User Permissions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label small">Username</label><input class="form-control" id="edit-user-username" readonly></div>
          <div class="mb-3">
            <label class="form-label small">Assign Role</label>
            <select class="form-select" name="role" id="edit-user-role">
              <option value="user">User</option>
              <option value="moderator">Moderator</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_banned" id="edit-user-banned">
            <label class="form-check-label" for="edit-user-banned">Ban User (Restrict Access)</label>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Changes</button></div>
      </form>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <!-- KPI Snapshot -->
    <div class="row" id="dashboard-section">
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-primary">
          <div class="inner"><h3 id="kpi-users">0</h3><p>Total Users</p></div>
          <div class="small-box-icon"><i class="bi bi-people-fill"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-success">
          <div class="inner"><h3 id="kpi-contents">0</h3><p>Total Contents</p></div>
          <div class="small-box-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-warning">
          <div class="inner"><h3 id="kpi-chapters">0</h3><p>Total Chapters</p></div>
          <div class="small-box-icon"><i class="bi bi-collection-fill"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box text-bg-danger">
          <div class="inner"><h3 id="kpi-unread">0</h3><p>Unread Notifications</p></div>
          <div class="small-box-icon"><i class="bi bi-bell-fill"></i></div>
        </div>
      </div>
    </div>

    <!-- Site Visits -->
    <div class="row" id="site-visits-section">
      <div class="col-lg-4 col-12">
        <div class="small-box text-bg-info">
          <div class="inner"><h3 id="visits-daily">0</h3><p>Site Visits (24h)</p></div>
          <div class="small-box-icon"><i class="bi bi-graph-up"></i></div>
        </div>
      </div>
      <div class="col-lg-4 col-12">
        <div class="small-box text-bg-success">
          <div class="inner"><h3 id="visits-weekly">0</h3><p>Site Visits (7d)</p></div>
          <div class="small-box-icon"><i class="bi bi-calendar-week"></i></div>
        </div>
      </div>
      <div class="col-lg-4 col-12">
        <div class="small-box text-bg-secondary">
          <div class="inner"><h3 id="visits-monthly">0</h3><p>Site Visits (30d)</p></div>
          <div class="small-box-icon"><i class="bi bi-calendar3"></i></div>
        </div>
      </div>
    </div>

    <!-- Metrics & Analytics -->
    <div class="row mt-4" id="metrics-section">
      <!-- Top Contents -->
      <div class="col-lg-6 mb-4">
        <div class="card card-outline card-primary h-100">
          <div class="card-header border-0"><h3 class="card-title">Top Contents (7d)</h3></div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm align-middle mb-0 fs-8">
              <thead><tr><th>Content</th><th>Type</th><th class="text-end">Views</th><th class="text-end">Comments</th></tr></thead>
              <tbody id="metrics-top-contents"></tbody>
            </table>
          </div>
        </div>
      </div>
      
      <!-- Funnel & Health -->
      <div class="col-lg-3 mb-4">
        <div class="card card-outline card-info h-100">
          <div class="card-header border-0"><h3 class="card-title">Funnel & Health</h3></div>
          <div class="card-body pt-0" id="metrics-funnel-health"></div>
        </div>
      </div>

      <!-- Retention & Search -->
      <div class="col-lg-3 mb-4">
        <div class="card card-outline card-success h-100">
    <!-- Advanced Monetization & Search Insights Section -->
    <div class="row mt-4" id="advanced-analytics-section">
      <div class="col-lg-6 mb-4">
        <div class="card card-outline card-warning h-100">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-coin me-2"></i>Monetization & Coin Flow (30d)</h3>
            <span class="badge bg-warning text-dark" id="monetization-total-coins">0 Coins Unlocked</span>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm align-middle mb-0 fs-8">
              <thead class="table-dark"><tr><th>En Çok Gelir Getiren Seri</th><th>Tür</th><th class="text-end">Kilit Açma</th><th class="text-end">Toplam Coin</th></tr></thead>
              <tbody id="monetization-top-series">
                <tr><td colspan="4" class="text-center py-3 text-muted">Yükleniyor...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-6 mb-4">
        <div class="card card-outline card-danger h-100">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-search-heart me-2"></i>Sıfır Sonuç Dönen Aramalar (Fırsat Analizi)</h3>
            <span class="badge bg-danger">Zero-Results</span>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm align-middle mb-0 fs-8">
              <thead class="table-dark"><tr><th>Aranan Kelime / Terim</th><th class="text-center">Arama Sayısı</th><th class="text-end">Son Arama</th></tr></thead>
              <tbody id="search-insights-zero">
                <tr><td colspan="3" class="text-center py-3 text-muted">Yükleniyor...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-4" id="view-charts-section">
      <div class="col-lg-6">
        <div class="card card-outline card-info">
          <div class="card-header border-0"><h3 class="card-title">Most Viewed Tags</h3></div>
          <div class="card-body"><canvas id="chartTopTags" height="220"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card card-outline card-success">
          <div class="card-header border-0"><h3 class="card-title">Most Viewed Genres</h3></div>
          <div class="card-body"><canvas id="chartTopGenres" height="220"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card card-outline card-warning">
          <div class="card-header border-0"><h3 class="card-title">Most Viewed Types</h3></div>
          <div class="card-body"><canvas id="chartTopTypes" height="220"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card card-outline card-primary">
          <div class="card-header border-0"><h3 class="card-title">Most Viewed Contents</h3></div>
          <div class="card-body"><canvas id="chartTopContents" height="220"></canvas></div>
        </div>
      </div>
      <div class="col-12">
        <div class="card card-outline card-dark">
          <div class="card-header border-0"><h3 class="card-title">Most Viewed Chapters</h3></div>
          <div class="card-body"><canvas id="chartTopChapters" height="260"></canvas></div>
        </div>
      </div>
    </div>

    <div class="row mt-4" id="blog-stats-section">
      <div class="col-12">
        <div class="card card-outline card-success">
          <div class="card-header border-0"><h3 class="card-title">Blog Statistics</h3></div>
          <div class="card-body">
            <div class="row g-2 mb-3">
              <div class="col-6 col-md-2"><div class="small-box text-bg-light"><div class="inner"><h5 id="blog-stat-total">0</h5><p>Total</p></div></div></div>
              <div class="col-6 col-md-2"><div class="small-box text-bg-success"><div class="inner"><h5 id="blog-stat-visible">0</h5><p>Visible</p></div></div></div>
              <div class="col-6 col-md-2"><div class="small-box text-bg-secondary"><div class="inner"><h5 id="blog-stat-hidden">0</h5><p>Hidden</p></div></div></div>
              <div class="col-6 col-md-2"><div class="small-box text-bg-danger"><div class="inner"><h5 id="blog-stat-deleted">0</h5><p>Deleted</p></div></div></div>
              <div class="col-6 col-md-2"><div class="small-box text-bg-primary"><div class="inner"><h5 id="blog-stat-created-period">0</h5><p>Created (Period)</p></div></div></div>
              <div class="col-6 col-md-2"><div class="small-box text-bg-warning"><div class="inner"><h5 id="blog-stat-approved-period">0</h5><p>Approved (Period)</p></div></div></div>
            </div>
            <div class="row">
              <div class="col-lg-7">
                <div class="card card-outline card-primary mb-0">
                  <div class="card-header border-0"><h3 class="card-title">Daily Blog Activity</h3></div>
                  <div class="card-body"><canvas id="chartBlogDaily" height="220"></canvas></div>
                </div>
              </div>
              <div class="col-lg-5">
                <div class="card card-outline card-info mb-0">
                  <div class="card-header border-0"><h3 class="card-title">Top Blog Authors</h3></div>
                  <div class="card-body"><canvas id="chartBlogAuthors" height="220"></canvas></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Content Management -->
    <div class="row mt-4" id="content-section">
      <div class="col-12">
        <div class="card card-outline card-info">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Content Management</h3>
            <div>
              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-create-content">New Content</button>
              <button class="btn btn-sm btn-tool" id="btn-refresh-contents"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
              <thead><tr><th>ID</th><th>Type</th><th>Title</th><th>Slug</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody id="contents-list-body"><tr><td colspan="6" class="text-center">Loading contents...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Taxonomy Management -->
    <div class="row mt-4" id="taxonomy-section">
      <div class="col-md-6">
        <div class="card card-outline card-secondary">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Genres</h3>
            <button class="btn btn-sm btn-primary" onclick="NMR_ADMIN.promptCreateTaxonomy('genre')">New Genre</button>
          </div>
          <div class="card-body p-0 scroll-y-auto max-h-200">
            <table class="table table-sm mb-0 fs-8">
              <tbody id="genres-list-body"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-outline card-secondary">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Tags</h3>
            <button class="btn btn-sm btn-primary" onclick="NMR_ADMIN.promptCreateTaxonomy('tag')">New Tag</button>
          </div>
          <div class="card-body p-0 scroll-y-auto max-h-200">
            <table class="table table-sm mb-0 fs-8">
              <tbody id="tags-list-body"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Blogs & Users -->
    <div class="row mt-4">
      <div class="col-md-6" id="blogs-section">
        <div class="card card-outline card-success">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Blog Moderation</h3>
            <button class="btn btn-tool" id="btn-refresh-blogs"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body p-0">
            <ul class="nav nav-tabs small ps-3 pe-3 pt-2 mb-0" role="tablist">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-blogs-pending" type="button">Pending</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-blogs-all" type="button">All Blogs</button></li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="tab-blogs-pending">
                <div class="table-responsive">
                  <table class="table table-striped align-middle mb-0 fs-7">
                    <thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody id="pending-blogs-body"></tbody>
                  </table>
                </div>
              </div>
              <div class="tab-pane fade" id="tab-blogs-all">
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0 fs-8">
                    <thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Approved</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody id="all-blogs-body"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-md-6">
        <div class="card card-outline card-warning h-100">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <h4 class="fw-bold mb-2">User Management</h4>
              <p class="text-muted mb-3">Ban/role atamaları ve hesap denetimleri için özel sayfa.</p>
            </div>
            <a class="btn btn-warning" href="/admin/users">Open Users</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-outline card-danger h-100">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <h4 class="fw-bold mb-2">Comment Moderation</h4>
              <p class="text-muted mb-3">Blog ve içerik yorumlarını yönetin, silin.</p>
            </div>
            <a class="btn btn-danger" href="/admin/comments">Open Comments</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Queue & Operations -->
    <div class="row mt-4" id="ops-section">
      <div class="col-md-6">
        <div class="card card-outline card-dark">
          <div class="card-header border-0"><h3 class="card-title">Queue Operations</h3></div>
          <div class="card-body">
            <div id="queue-jobs-list" class="mb-3 small bg-light p-2 rounded border fs-8 scroll-y-auto h-120">Loading...</div>
            <div class="input-group input-group-sm">
              <span class="input-group-text">Run Count</span>
              <input type="number" id="jobs-limit" class="form-control" value="5">
              <button class="btn btn-primary" id="btn-run-jobs">Execute Jobs</button>
            </div>
            <button class="btn btn-outline-secondary btn-sm mt-2 w-100" id="btn-run-jobs-legacy">Execute Legacy Jobs</button>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-outline card-danger">
          <div class="card-header border-0"><h3 class="card-title">Retention & Cleanup</h3></div>
          <div class="card-body text-center">
            <p class="text-muted fs-8">Remove old audit logs, expired tokens, and inactive sessions.</p>
            <button class="btn btn-danger btn-sm w-100" id="btn-run-cleanup">Perform System Cleanup</button>
            <button class="btn btn-outline-danger btn-sm w-100 mt-2" id="btn-run-cleanup-legacy">Legacy Cleanup Endpoint</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Security & Audit Logs -->
    <div class="row mt-4" id="logs-section">
      <div class="col-12">
        <div class="card card-outline card-secondary">
          <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="log-tabs" role="tablist">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-audit" type="button">Audit Logs</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-logins" type="button">Login Events</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mod" type="button">Moderation History</button></li>
            </ul>
          </div>
          <div class="card-body p-0">
            <div class="tab-content">
              <div class="tab-pane fade show active" id="tab-audit">
                <div class="table-responsive"><table class="table table-sm table-striped fs-8 mb-0">
                  <thead><tr><th>User</th><th>Action</th><th>Target</th><th>Time</th></tr></thead>
                  <tbody id="audit-logs-body"></tbody>
                </table></div>
              </div>
              <div class="tab-pane fade" id="tab-logins">
                <div class="table-responsive"><table class="table table-sm table-striped fs-8 mb-0">
                  <thead><tr><th>User</th><th>IP</th><th>Result</th><th>Time</th></tr></thead>
                  <tbody id="login-logs-body"></tbody>
                </table></div>
              </div>
              <div class="tab-pane fade" id="tab-mod">
                <div class="table-responsive"><table class="table table-sm table-striped fs-8 mb-0">
                  <thead><tr><th>Mod</th><th>Action</th><th>Target</th><th>Reason</th></tr></thead>
                  <tbody id="mod-actions-body"></tbody>
                </table></div>
                <div class="p-3 border-top">
                  <form id="form-create-mod-action" class="row g-2 align-items-end">
                    <div class="col-md-3">
                      <label class="form-label small mb-1">Target Type</label>
                      <select class="form-select form-select-sm" name="target_type" id="mod-target-type">
                        <option value="user">user</option>
                        <option value="content">content</option>
                        <option value="chapter">chapter</option>
                        <option value="blog">blog</option>
                        <option value="comment">comment</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small mb-1">Target ID/Slug</label>
                      <input class="form-control form-control-sm" name="target_id" id="mod-target-id" required>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label small mb-1">Action</label>
                      <select class="form-select form-select-sm" name="action" id="mod-action">
                        <option value="hide">hide</option>
                        <option value="ban">ban</option>
                        <option value="delete">delete</option>
                        <option value="warn">warn</option>
                        <option value="unban">unban</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small mb-1">Reason</label>
                      <input class="form-control form-control-sm" name="reason" id="mod-reason">
                    </div>
                    <div class="col-md-1">
                      <button class="btn btn-sm btn-primary w-100" type="submit">Add</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RBAC Visibility -->
    <div class="row mt-4" id="rbac-section">
      <div class="col-12">
        <div class="card card-outline card-info">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">RBAC Assignments & Roles</h3>
            <button class="btn btn-tool" id="btn-refresh-rbac"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body p-0">
            <div class="row g-0">
              <div class="col-lg-7 border-end">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0 fs-8">
                    <thead><tr><th>Username</th><th>Assigned Roles</th><th>Derived Permissions</th></tr></thead>
                    <tbody id="rbac-assignments-body"></tbody>
                  </table>
                </div>
              </div>
              <div class="col-lg-5">
                <div class="table-responsive border-bottom">
                  <table class="table table-sm mb-0 fs-8">
                    <thead><tr><th>Role</th><th>Description</th></tr></thead>
                    <tbody id="rbac-roles-body"></tbody>
                  </table>
                </div>
                <div class="p-3">
                  <form id="form-assign-permission" class="row g-2 align-items-end">
                    <div class="col-6">
                      <label class="form-label small mb-1">Role Slug</label>
                      <input class="form-control form-control-sm" name="role_slug" id="role-slug-input" required>
                    </div>
                    <div class="col-6">
                      <label class="form-label small mb-1">Permission Code</label>
                      <input class="form-control form-control-sm" name="permission_code" id="perm-code-input" required placeholder="e.g. content.edit">
                    </div>
                    <div class="col-12">
                      <button class="btn btn-sm btn-info w-100" type="submit">Assign Permission</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- User Reputation -->
    <div class="row mt-4" id="reputation-section">
      <div class="col-12">
        <div class="card card-outline card-primary">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">User Reputation (Activity Based)</h3>
            <button class="btn btn-tool" id="btn-refresh-reputation"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 fs-8">
              <thead><tr><th>User</th><th class="text-end">Score</th><th class="text-end">Comments</th><th class="text-end">Votes Given</th><th class="text-end">Votes Up</th><th class="text-end">Votes Down</th><th class="text-end">Time</th></tr></thead>
              <tbody id="reputation-body"></tbody>
            </table>
          </div>
          <div class="card-footer text-muted fs-8">İpucu: Skor; yorumlar (x2), alınan upvote'lar, verilen oylar (x0.5) ve sitede geçirilen süreden (Saat başı +10) oluşur.</div>
        </div>
      </div>
    </div>

    <!-- Legacy Metrics -->
    <div class="row mt-4" id="legacy-metrics-section">
      <div class="col-12">
        <div class="card card-outline card-secondary">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Legacy Metrics APIs</h3>
            <button class="btn btn-tool" id="btn-refresh-legacy-metrics"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body">
            <div class="row g-2 mb-2">
              <div class="col-md-3 d-grid"><button class="btn btn-outline-primary btn-sm" id="btn-metrics-dashboard">GET /admin/dashboard</button></div>
              <div class="col-md-3 d-grid"><button class="btn btn-outline-primary btn-sm" id="btn-metrics-snapshot">GET /admin/metrics</button></div>
              <div class="col-md-3 d-grid"><button class="btn btn-outline-primary btn-sm" id="btn-metrics-insights">GET /admin/metrics/insights</button></div>
              <div class="col-md-3">
                <div class="input-group input-group-sm">
                  <span class="input-group-text">Genre Slug</span>
                  <input class="form-control" id="genre-insight-slug" placeholder="e.g. action">
                  <button class="btn btn-outline-primary" id="btn-metrics-genre">Go</button>
                </div>
              </div>
            </div>
            <div class="row g-3 mb-2">
              <div class="col-md-4">
                <div class="small bg-light border rounded p-2" id="legacy-kpis">No data yet.</div>
              </div>
              <div class="col-md-4">
                <canvas id="chartLegacyTopContents" height="180"></canvas>
              </div>
              <div class="col-md-4">
                <canvas id="chartLegacyTopGenres" height="180"></canvas>
              </div>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <canvas id="chartLegacyGenreInterest" height="220"></canvas>
              </div>
              <div class="col-md-6">
                <pre class="bg-light border rounded p-2 small scroll-y-auto h-220" id="legacy-metrics-output">No data yet.</pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
