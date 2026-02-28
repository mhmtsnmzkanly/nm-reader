<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">System Operations</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Operations</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-6">
        <div class="card card-outline card-dark">
          <div class="card-header border-0"><h3 class="card-title">Queue Operations</h3></div>
          <div class="card-body">
            <div id="queue-jobs-list" class="mb-3 small bg-light p-2 rounded border fs-8 scroll-y-auto h-140">Loading...</div>
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
      <div class="col-md-12 mt-3">
        <div class="card card-outline card-info">
          <div class="card-header border-0"><h3 class="card-title">Maintenance Tools</h3></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-3">
                <button class="btn btn-info btn-sm w-100" id="btn-trigger-backup">
                  <i class="bi bi-cloud-download me-1"></i> Full System Backup
                </button>
                <small class="text-muted d-block mt-1 fs-9">Generates a DB dump and archives media assets.</small>
              </div>
              <div class="col-md-3">
                <button class="btn btn-secondary btn-sm w-100" id="btn-trigger-analytics">
                  <i class="bi bi-graph-up me-1"></i> Aggregate Daily Analytics
                </button>
                <small class="text-muted d-block mt-1 fs-9">Processes raw events into snapshot charts.</small>
              </div>
              <div class="col-md-3">
                <button class="btn btn-primary btn-sm w-100" id="btn-trigger-sitemap">
                  <i class="bi bi-diagram-3 me-1"></i> Regenerate Sitemap.xml
                </button>
                <small class="text-muted d-block mt-1 fs-9">Converts dynamic sitemap to a static physical file.</small>
              </div>
              <div class="col-md-3">
                <button class="btn btn-warning btn-sm w-100" id="btn-trigger-warmup">
                  <i class="bi bi-fire me-1"></i> Warmup System Cache
                </button>
                <small class="text-muted d-block mt-1 fs-9">Pre-loads popular series and homepage data.</small>
              </div>
            </div>
            <div id="maintenance-output" class="mt-3 p-2 bg-dark text-light rounded fs-8 d-none" style="max-height: 200px; overflow-y: auto;"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
