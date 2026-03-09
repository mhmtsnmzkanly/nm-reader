<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Comment Moderation</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Comments</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="card card-outline card-danger">
      <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <h3 class="card-title">Latest Comments</h3>
        <button class="btn btn-tool" id="btn-refresh-comments"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <div class="card-body p-0 table-responsive scroll-y-auto max-h-520">
        <table class="table table-sm table-hover align-middle mb-0 fs-7">
          <thead><tr><th>User</th><th>Comment</th><th>Content/Blog</th><th>Date</th><th>Action</th></tr></thead>
          <tbody id="comments-list-body"><tr><td colspan="5" class="text-center">Loading comments...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

