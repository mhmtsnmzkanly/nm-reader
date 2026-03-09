<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">System Uploads</h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent File Uploads</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" id="refresh-uploads">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-valign-middle" id="uploads-table">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>File Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Uploader</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="uploads-list">
                            <tr>
                                <td colspan="7" class="text-center py-4">Loading uploads...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer clearfix">
                <ul class="pagination pagination-sm m-0 float-right" id="uploads-pagination">
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Modal for Image Preview -->
<div class="modal fade" id="preview-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body p-0 text-center">
                <img src="" id="full-preview" class="img-fluid" style="max-height: 85vh;">
            </div>
        </div>
    </div>
</div>
