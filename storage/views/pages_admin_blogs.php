<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Blog Moderation</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Blogs</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="card card-outline card-success">
      <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <h3 class="card-title">Pending Blogs</h3>
        <button class="btn btn-tool" id="btn-refresh-blogs"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-striped align-middle mb-0 fs-7">
          <thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Status</th><th>Action</th></tr></thead>
          <tbody id="pending-blogs-body"></tbody>
        </table>
      </div>
    </div>

    <div class="card card-outline card-secondary mt-4">
      <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <h3 class="card-title">All Blogs</h3>
        <button class="btn btn-tool" id="btn-refresh-blogs-all"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table table-sm align-middle mb-0 fs-8">
          <thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Approved</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody id="all-blogs-body"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

