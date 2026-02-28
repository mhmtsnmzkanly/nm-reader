<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">System Environment Configuration</h3></div>
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
    <div class="alert alert-warning border-start border-4 border-warning">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>Warning:</strong> You are editing the core <code>.env</code> file. Incorrect values can crash the entire system.
      A backup (<code>.env.bak</code>) is created automatically before each save.
    </div>

    <div class="card card-outline card-danger shadow-sm">
      <div class="card-header">
        <h3 class="card-title"><i class="bi bi-gear-fill me-2"></i>Environment Variables</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" id="btn-reload-env"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
      </div>
      <div class="card-body">
        <form id="form-env-config">
          <div id="env-inputs-container" class="row g-3">
            <div class="col-12 text-center py-4">
              <div class="spinner-border text-primary" role="status"></div>
              <p class="mt-2 text-muted">Reading .env file...</p>
            </div>
          </div>
          
          <div class="mt-4 pt-3 border-top d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" id="btn-add-var">
              <i class="bi bi-plus-circle me-1"></i> Add New Variable
            </button>
            <button type="submit" class="btn btn-danger px-4" id="btn-save-env">
              <i class="bi bi-save me-1"></i> Save Configuration
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
  .env-row:hover { background-color: rgba(0,0,0,0.02); }
  .env-row .btn-remove { opacity: 0.3; transition: opacity 0.2s; }
  .env-row:hover .btn-remove { opacity: 1; }
</style>
