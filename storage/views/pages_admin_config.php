<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">System Environment</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Environment</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    
    <div class="row">
      <!-- Left: Navigation -->
      <div class="col-md-3">
        <div class="card card-outline card-primary shadow-sm mb-4">
          <div class="card-header"><h3 class="card-title">Categories</h3></div>
          <div class="card-body p-0">
            <ul class="nav nav-pills flex-column" id="env-category-nav">
              <li class="nav-item"><a href="#cat-APP" class="nav-link active"><i class="bi bi-cpu me-2"></i> Application</a></li>
              <li class="nav-item"><a href="#cat-DB" class="nav-link"><i class="bi bi-database me-2"></i> Database</a></li>
              <li class="nav-item"><a href="#cat-SITE" class="nav-link"><i class="bi bi-globe me-2"></i> Site Identity</a></li>
              <li class="nav-item"><a href="#cat-DEFAULT" class="nav-link"><i class="bi bi-palette me-2"></i> Defaults</a></li>
              <li class="nav-item"><a href="#cat-SECURITY" class="nav-link"><i class="bi bi-shield-lock me-2"></i> Security</a></li>
              <li class="nav-item"><a href="#cat-OTHER" class="nav-link"><i class="bi bi-three-dots me-2"></i> Other Vars</a></li>
            </ul>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-body text-center p-3">
            <button type="button" class="btn btn-primary btn-sm w-100 mb-2" id="btn-add-var">
              <i class="bi bi-plus-lg me-1"></i> Add Custom Var
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="btn-reload-env">
              <i class="bi bi-arrow-clockwise me-1"></i> Reload File
            </button>
          </div>
        </div>
      </div>

      <!-- Right: Inputs -->
      <div class="col-md-9">
        <form id="form-env-config">
          <div id="env-sections-wrapper">
            <div class="text-center py-5">
              <div class="spinner-border text-primary" role="status"></div>
              <p class="mt-2 text-muted">Parsing configuration...</p>
            </div>
          </div>

          <div class="card mt-4 shadow sticky-bottom bg-white" style="bottom: 1rem; z-index: 1020;">
            <div class="card-body d-flex justify-content-between align-items-center p-3">
              <div>
                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> Safety backup active</span>
              </div>
              <button type="submit" class="btn btn-danger btn-lg px-5 shadow" id="btn-save-env">
                <i class="bi bi-save2-fill me-2"></i> Save Changes
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<style>
  .env-section { margin-bottom: 2rem; scroll-margin-top: 5rem; }
  .env-row { transition: all 0.2s; border-left: 3px solid transparent; }
  .env-row:hover { background: #f8f9fa; border-left-color: var(--bs-primary); }
  .env-key-label { font-size: 0.75rem; color: #6c757d; font-weight: 700; margin-bottom: 0.25rem; }
  .env-row .btn-remove { opacity: 0; }
  .env-row:hover .btn-remove { opacity: 1; }
  .sticky-bottom { border-top: 3px solid #dc3545; }
</style>
