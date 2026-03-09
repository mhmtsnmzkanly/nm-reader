<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Logs & Security</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Logs</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-6">
        <div class="card card-outline card-secondary">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Audit Logs</h3>
            <button class="btn btn-tool" id="btn-refresh-logs"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body p-0 table-responsive scroll-y-auto max-h-300">
            <table class="table table-sm table-hover mb-0 fs-8">
              <thead><tr><th>ZAMAN</th><th>METOT</th><th>PATH</th><th>IP HASH</th><th>USER</th></tr></thead>
              <tbody id="logs-body"><tr><td colspan="5" class="text-center">Loading...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card card-outline card-info">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Login Events</h3>
            <button class="btn btn-tool" id="btn-refresh-logins"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body p-0 table-responsive scroll-y-auto max-h-300">
            <table class="table table-sm table-hover mb-0 fs-8">
              <thead><tr><th>Email</th><th>IP</th><th>UA</th><th>Success</th><th>At</th></tr></thead>
              <tbody id="logins-body"><tr><td colspan="5" class="text-center">Loading...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- System Logs Section -->
    <div class="row mt-4">
      <div class="col-lg-6">
        <div class="card card-outline card-primary">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Access Logs (Last 50)</h3>
            <button class="btn btn-tool" id="btn-refresh-access"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body p-3 scroll-y-auto max-h-600 bg-light" id="access-logs-container">
            <div class="text-center py-4 text-muted">Loading access logs...</div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card card-outline card-danger">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Error Logs (Last 50)</h3>
            <button class="btn btn-tool" id="btn-refresh-error"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body p-3 scroll-y-auto max-h-600 bg-light" id="error-logs-container">
            <div class="text-center py-4 text-muted">Loading error logs...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
