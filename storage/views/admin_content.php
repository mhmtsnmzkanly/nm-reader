<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Content & Chapters</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Content</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
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
          <tbody id="contents-list-body"></tbody>
        </table>
      </div>
    </div>

    <div class="card card-outline card-dark mt-4">
      <div class="card-header border-0 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <h3 class="card-title mb-0">Chapters</h3>
          <select id="chapters-content-id" class="form-select form-select-sm"></select>
          <button class="btn btn-sm btn-outline-info" id="btn-series-team" title="Ekip & Çevirmen Yönetimi" onclick="NMR_ADMIN_CONTENT.openTeamModal()"><i class="bi bi-people-fill"></i> Ekip</button>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div id="bulk-action-bar" class="d-none align-items-center gap-1 bg-dark-subtle p-1 px-2 rounded border">
            <span class="small fw-semibold me-1" id="bulk-selected-count">0 seçildi:</span>
            <button class="btn btn-xs btn-success" onclick="NMR_ADMIN_CONTENT.bulkAction('publish')"><i class="bi bi-check2-circle"></i> Yayınla</button>
            <button class="btn btn-xs btn-warning" onclick="NMR_ADMIN_CONTENT.promptBulkSchedule()"><i class="bi bi-calendar-event"></i> Zamanla</button>
            <button class="btn btn-xs btn-info" onclick="NMR_ADMIN_CONTENT.promptBulkPrice()"><i class="bi bi-coin"></i> Fiyat</button>
            <button class="btn btn-xs btn-danger" onclick="NMR_ADMIN_CONTENT.bulkAction('delete')"><i class="bi bi-trash"></i> Sil</button>
          </div>
          <button class="btn btn-sm btn-primary" id="btn-add-chapter"><i class="bi bi-plus-lg"></i> New Chapter</button>
          <button class="btn btn-sm btn-tool" id="btn-refresh-chapters"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
      </div>
      <div class="card-body p-0 table-responsive scroll-y-auto max-h-320">
        <table class="table table-sm table-hover align-middle mb-0 fs-8">
          <thead><tr><th class="w-30"><input type="checkbox" id="check-all-chapters" onchange="NMR_ADMIN_CONTENT.toggleAllChapters(this)"></th><th>#</th><th>Title</th><th>Type</th><th>Price</th><th>Release</th><th>Uploader</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
          <tbody id="chapters-list-body"></tbody>
        </table>
      </div>
    </div>

    <div class="row mt-4" id="taxonomy-section">
      <div class="col-md-6">
        <div class="card card-outline card-secondary">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Genres</h3>
            <button class="btn btn-sm btn-primary" onclick="NMR_ADMIN_CONTENT.promptCreateTaxonomy('genre')">New Genre</button>
          </div>
          <div class="card-body p-0 scroll-y-auto max-h-200">
            <table class="table table-sm table-hover align-middle mb-0 fs-8">
              <thead><tr><th class="w-40">ID</th><th>Name</th></tr></thead>
              <tbody id="genres-list-body"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-outline card-secondary">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Tags</h3>
            <button class="btn btn-sm btn-primary" onclick="NMR_ADMIN_CONTENT.promptCreateTaxonomy('tag')">New Tag</button>
          </div>
          <div class="card-body p-0 scroll-y-auto max-h-200">
            <table class="table table-sm table-hover align-middle mb-0 fs-8">
              <thead><tr><th class="w-40">ID</th><th>Name</th></tr></thead>
              <tbody id="tags-list-body"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-create-content" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="form-create-content">
        <div class="modal-header text-bg-primary"><h5 class="modal-title">Create Content</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
          <div class="col-md-4">
            <label class="form-label small">Type</label>
            <select class="form-select" name="type" required>
              <option value="novel">Novel</option>
              <option value="manga">Manga</option>
              <option value="manhwa">Manhwa</option>
              <option value="webtoon">Webtoon</option>
              <option value="light-novel">Light Novel</option>
              <option value="web-novel">Web Novel</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Title</label>
            <input class="form-control" name="title" id="create-content-title" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Alternative Titles</label>
            <input class="form-control" name="alternative_titles" placeholder="Japanese, Romanized, etc.">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Slug</label>
            <input class="form-control" name="slug" id="create-content-slug" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Cover Image</label>
            <div class="input-group">
              <input class="form-control" name="cover_image" id="create-content-cover">
              <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('upload-cover-input').click()">Upload</button>
            </div>
            <input type="file" id="upload-cover-input" class="d-none" accept="image/*" onchange="NMR_ADMIN_CONTENT.uploadSpecificImage(this, 'create-content-cover', 'series_cover')">
          </div>
          <div class="col-12">
            <label class="form-label small">Description</label>
            <textarea class="form-control" name="description" rows="3"></textarea>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Author</label>
            <input class="form-control" name="author" id="create-content-author">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Artist</label>
            <input class="form-control" name="artist" id="create-content-artist">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Country</label>
            <input class="form-control" name="country" id="create-content-country">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Release Year</label>
            <input class="form-control" name="release_year" id="create-content-release-year" type="number" min="1800" max="<?= date('Y') + 1 ?>">
          </div>
          <div class="col-12">
            <label class="form-label small d-block mb-1">Genres</label>
            <div class="d-flex flex-wrap gap-1" id="create-content-genres-btns"></div>
          </div>
          <div class="col-12">
            <label class="form-label small d-block mb-1">Tags</label>
            <div class="d-flex flex-wrap gap-1" id="create-content-tags-btns"></div>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Create</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-edit-content" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="form-edit-content">
        <input type="hidden" name="id" id="edit-content-id">
        <div class="modal-header text-bg-info"><h5 class="modal-title">Edit Content</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
          <div class="col-md-4">
            <label class="form-label small">Title</label>
            <input class="form-control" name="title" id="edit-content-title" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Alternative Titles</label>
            <input class="form-control" name="alternative_titles" id="edit-content-alt-titles" placeholder="Japanese, Romanized, etc.">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Status</label>
            <select class="form-select" name="status" id="edit-content-status">
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Cover Image</label>
            <div class="input-group">
              <input class="form-control" name="cover_image" id="edit-content-cover">
              <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('upload-cover-edit-input').click()">Upload</button>
            </div>
            <input type="file" id="upload-cover-edit-input" class="d-none" accept="image/*" onchange="NMR_ADMIN_CONTENT.uploadSpecificImage(this, 'edit-content-cover', 'series_cover')">
          </div>
          <div class="col-12">
            <label class="form-label small">Description</label>
            <textarea class="form-control" name="description" id="edit-content-desc" rows="3"></textarea>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Author</label>
            <input class="form-control" name="author" id="edit-content-author">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Artist</label>
            <input class="form-control" name="artist" id="edit-content-artist">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Country</label>
            <input class="form-control" name="country" id="edit-content-country">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Release Year</label>
            <input class="form-control" name="release_year" id="edit-content-release-year" type="number" min="1800" max="<?= date('Y') + 1 ?>">
          </div>
          <div class="col-12">
            <label class="form-label small d-block mb-1">Genres</label>
            <div class="d-flex flex-wrap gap-1" id="edit-content-genres-btns"></div>
          </div>
          <div class="col-12">
            <label class="form-label small d-block mb-1">Tags</label>
            <div class="d-flex flex-wrap gap-1" id="edit-content-tags-btns"></div>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-info">Save</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-create-chapter" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="form-create-chapter">
        <input type="hidden" id="create-chapter-content-id">
        <input type="hidden" id="create-chapter-content-type">
        <input type="hidden" id="create-chapter-content-slug">
        <div class="modal-header text-bg-primary">
          <h5 class="modal-title">Create Chapter</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-2">
          <div class="col-12">
            <label class="form-label small">Content</label>
            <input class="form-control" id="create-chapter-content" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Chapter Number</label>
            <input class="form-control" name="chapter_number" id="create-chapter-number" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Type</label>
            <select class="form-select" name="type" id="create-chapter-type">
              <option value="text">Text</option>
              <option value="image">Image</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Title (optional)</label>
            <input class="form-control" name="title" id="create-chapter-title">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Price (coin)</label>
            <input class="form-control" type="number" min="0" step="1" name="price_coin" id="create-chapter-price" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Yayın Tarihi (Scheduled)</label>
            <input class="form-control" type="datetime-local" name="published_at" id="create-chapter-published-at">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Erken Erişim Bitişi (Free After)</label>
            <input class="form-control" type="datetime-local" name="is_free_after" id="create-chapter-free-after">
          </div>
          <div class="col-12" id="create-chapter-body-wrap">
            <label class="form-label small">Body (text chapter)</label>
            <textarea class="form-control" id="create-chapter-body" rows="8"></textarea>
          </div>
          <div class="col-12 d-none" id="create-chapter-pages-wrap">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small mb-0">Pages (image chapter)</label>
              <button type="button" class="btn btn-xs btn-success" onclick="document.getElementById('bulk-upload-chapter').click()">Bulk Upload Images</button>
            </div>
            <input type="file" id="bulk-upload-chapter" class="d-none" multiple accept="image/*" onchange="NMR_ADMIN_CONTENT.handleBulkUpload(this, 'chapters')">
            <textarea class="form-control mb-2" id="create-chapter-pages" rows="4" placeholder="URL listesi (her satıra bir görsel)..."></textarea>
            <div id="create-chapter-preview-grid" class="d-flex flex-wrap gap-2 p-2 bg-dark-subtle border rounded" style="min-height: 100px;">
              <small class="text-muted w-100">Görseller yüklendiğinde burada sıralanabilir kartlar olarak listelenecektir.</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-edit-chapter" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="form-edit-chapter">
        <input type="hidden" name="id" id="edit-chapter-id">
        <div class="modal-header text-bg-info">
          <h5 class="modal-title">Edit Chapter</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-2">
          <div class="col-md-4">
            <label class="form-label small">Chapter Number</label>
            <input class="form-control" name="chapter_number" id="edit-chapter-number" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Type</label>
            <select class="form-select" name="type" id="edit-chapter-type">
              <option value="text">Text</option>
              <option value="image">Image</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Title (optional)</label>
            <input class="form-control" name="title" id="edit-chapter-title">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Price (coin)</label>
            <input class="form-control" type="number" min="0" step="1" name="price_coin" id="edit-chapter-price" value="0">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Yayın Tarihi (Scheduled)</label>
            <input class="form-control" type="datetime-local" name="published_at" id="edit-chapter-published-at">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Erken Erişim Bitişi (Free After)</label>
            <input class="form-control" type="datetime-local" name="is_free_after" id="edit-chapter-free-after">
          </div>
          <div class="col-12" id="edit-chapter-body-wrap">
            <label class="form-label small">Body (text chapter)</label>
            <textarea class="form-control" id="edit-chapter-body" rows="8"></textarea>
          </div>
          <div class="col-12 d-none" id="edit-chapter-pages-wrap">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small mb-0">Pages (image chapter)</label>
              <button type="button" class="btn btn-xs btn-success" onclick="document.getElementById('bulk-upload-edit-chapter').click()">Görsel Ekle</button>
            </div>
            <input type="file" id="bulk-upload-edit-chapter" class="d-none" multiple accept="image/*" onchange="NMR_ADMIN_CONTENT.handleBulkUploadEdit(this)">
            <textarea class="form-control mb-2" id="edit-chapter-pages" rows="4"></textarea>
            <div id="edit-chapter-preview-grid" class="d-flex flex-wrap gap-2 p-2 bg-dark-subtle border rounded" style="min-height: 100px;"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-info">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modal-series-team" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header text-bg-dark">
        <h5 class="modal-title"><i class="bi bi-people-fill me-2"></i>Seri Ekip & Delegasyon Yönetimi</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="form-assign-team" class="row g-2 mb-3">
          <div class="col-7">
            <label class="form-label small">Kullanıcı (ID / Username)</label>
            <input class="form-control form-control-sm" id="team-user-id" placeholder="8 haneli User ID" required>
          </div>
          <div class="col-5">
            <label class="form-label small">Görev</label>
            <select class="form-select form-select-sm" id="team-user-role">
              <option value="translator">Çevirmen</option>
              <option value="proofreader">Kontrolör (Editor)</option>
              <option value="cleaner">Cleaner</option>
              <option value="typesetter">Dizgici (Typesetter)</option>
              <option value="uploader">Uploader</option>
              <option value="lead">Proje Lideri</option>
            </select>
          </div>
          <div class="col-12 text-end">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-person-plus-fill"></i> Ekle / Yetkilendir</button>
          </div>
        </form>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0 fs-8">
            <thead><tr><th>Kullanıcı</th><th>Görev</th><th>Tarih</th><th class="text-end">İşlem</th></tr></thead>
            <tbody id="series-team-list-body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
