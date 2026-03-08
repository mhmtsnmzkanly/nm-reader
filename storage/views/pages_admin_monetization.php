<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0">Monetization Console</h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
          <li class="breadcrumb-item active">Monetization</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card card-outline card-primary">
          <div class="card-header border-0"><h3 class="card-title">User Wallet</h3></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label small">User Select</label>
              <select id="money-user-id" class="form-select form-select-sm">
                <option value="">Loading users...</option>
              </select>
            </div>
            <div id="wallet-summary-box" class="small bg-light border rounded p-3">Select a user to inspect wallet data.</div>
          </div>
        </div>

        <div class="card card-outline card-success mt-4">
          <div class="card-header border-0"><h3 class="card-title">Manual Coin Action</h3></div>
          <div class="card-body">
            <form id="form-wallet-adjust">
              <div class="mb-2">
                <label class="form-label small">Action</label>
                <select class="form-select form-select-sm" name="action">
                  <option value="credit">Credit</option>
                  <option value="debit">Debit</option>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label small">Amount</label>
                <input type="number" min="1" class="form-control form-control-sm" name="amount" value="10">
              </div>
              <div class="mb-2">
                <label class="form-label small">Reason</label>
                <input type="text" class="form-control form-control-sm" name="reason" placeholder="manual adjustment">
              </div>
              <button type="submit" class="btn btn-success btn-sm w-100">Apply</button>
            </form>
          </div>
        </div>

        <div class="card card-outline card-info mt-4">
          <div class="card-header border-0"><h3 class="card-title">Grant Package</h3></div>
          <div class="card-body">
            <form id="form-grant-package">
              <div class="mb-2">
                <label class="form-label small">Package</label>
                <select class="form-select form-select-sm" id="grant-package-id" name="package_id"></select>
              </div>
              <div class="mb-2">
                <label class="form-label small">Cash Amount</label>
                <input type="text" class="form-control form-control-sm" name="cash_amount" placeholder="99.90">
              </div>
              <div class="mb-2">
                <label class="form-label small">Reason</label>
                <input type="text" class="form-control form-control-sm" name="reason" placeholder="manual payment confirmation">
              </div>
              <button type="submit" class="btn btn-info btn-sm w-100">Grant Package</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card card-outline card-warning">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Shop Packages</h3>
            <button class="btn btn-sm btn-outline-primary" id="btn-refresh-packages"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body">
            <form id="form-package" class="row g-2 mb-3">
              <input type="hidden" name="id" id="package-id">
              <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="name" placeholder="Package name"></div>
              <div class="col-md-2"><input type="number" min="1" class="form-control form-control-sm" name="coin_amount" placeholder="Coin"></div>
              <div class="col-md-2"><input type="number" min="0" class="form-control form-control-sm" name="bonus_coin" placeholder="Bonus"></div>
              <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="display_price" placeholder="Price"></div>
              <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="currency" value="TRY"></div>
              <div class="col-md-2"><input type="number" class="form-control form-control-sm" name="sort_order" value="0" placeholder="Sort"></div>
              <div class="col-md-2">
                <select class="form-select form-select-sm" name="is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
              <div class="col-md-8 d-grid"><button type="submit" class="btn btn-warning btn-sm">Save Package</button></div>
            </form>
            <div class="table-responsive">
              <table class="table table-sm table-striped mb-0">
                <thead><tr><th>ID</th><th>Name</th><th>Coin</th><th>Bonus</th><th>Total</th><th>Price</th><th>Status</th><th></th></tr></thead>
                <tbody id="packages-table-body"><tr><td colspan="8" class="text-center">Loading...</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card card-outline card-danger mt-4">
          <div class="card-header border-0"><h3 class="card-title">Ad-Free Product</h3></div>
          <div class="card-body">
            <form id="form-adfree" class="row g-2">
              <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="name" placeholder="Ad Free 30 Days"></div>
              <div class="col-md-2"><input type="number" min="0" class="form-control form-control-sm" name="coin_price" placeholder="Coin"></div>
              <div class="col-md-2"><input type="number" min="1" class="form-control form-control-sm" name="duration_days" placeholder="Days"></div>
              <div class="col-md-2">
                <select class="form-select form-select-sm" name="is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
              <div class="col-md-1 d-grid"><button type="submit" class="btn btn-danger btn-sm">Save</button></div>
            </form>
            <div id="adfree-summary" class="small text-muted mt-3">Loading current ad-free configuration...</div>
          </div>
        </div>

        <div class="card card-outline card-secondary mt-4">
          <div class="card-header border-0"><h3 class="card-title">Pricing</h3></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <form id="form-series-price" class="border rounded p-3 bg-light">
                  <h6 class="mb-3">Series Pricing</h6>
                  <div class="mb-2"><input type="text" class="form-control form-control-sm" name="content_id" placeholder="Series ID"></div>
                  <div class="mb-2"><input type="number" min="0" class="form-control form-control-sm" name="price_coin" placeholder="Coin price"></div>
                  <div class="mb-2">
                    <select class="form-select form-select-sm" name="is_active">
                      <option value="1">Active</option>
                      <option value="0">Inactive</option>
                    </select>
                  </div>
                  <button type="submit" class="btn btn-secondary btn-sm w-100">Save Series Pricing</button>
                </form>
              </div>
              <div class="col-md-6">
                <form id="form-chapter-price" class="border rounded p-3 bg-light">
                  <h6 class="mb-3">Chapter Pricing</h6>
                  <div class="mb-2"><input type="text" class="form-control form-control-sm" name="chapter_id" placeholder="Chapter ID"></div>
                  <div class="mb-2"><input type="number" min="0" class="form-control form-control-sm" name="price_coin" placeholder="Coin price"></div>
                  <div class="mb-2">
                    <select class="form-select form-select-sm" name="is_active">
                      <option value="1">Active</option>
                      <option value="0">Inactive</option>
                    </select>
                  </div>
                  <button type="submit" class="btn btn-secondary btn-sm w-100">Save Chapter Pricing</button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <div class="card card-outline card-dark mt-4">
          <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title">Wallet Transactions</h3>
            <button class="btn btn-sm btn-outline-dark" id="btn-refresh-wallet-transactions"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-striped mb-0">
                <thead><tr><th>ID</th><th>Type</th><th>Delta</th><th>After</th><th>Reference</th><th>Created</th></tr></thead>
                <tbody id="wallet-transactions-body"><tr><td colspan="6" class="text-center">Load a user wallet first.</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
