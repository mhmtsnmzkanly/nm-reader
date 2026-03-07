document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    const perPage = 20;

    function loadUploads(page = 1) {
        currentPage = page;
        const listBody = document.getElementById('uploads-list');
        const pagination = document.getElementById('uploads-pagination');

        fetch(`/api/v1/admin/uploads?page=${page}&per_page=${perPage}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            
            const data = res.data || [];
            const meta = res.meta || { page: 1, total_pages: 1 };

            listBody.innerHTML = '';
            
            if (data.length === 0) {
                listBody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No uploads found.</td></tr>';
                pagination.innerHTML = '';
                return;
            }

            data.forEach(item => {
                const tr = document.createElement('tr');
                
                // Construct URL from image_id and original name if possible
                // Based on UploadService, files are in /uploads/prefix.id.ext
                // But we'll try to guess or use a generic path if not stored.
                // Assuming standard naming convention: prefix.id.ext
                // In a real scenario, we might want to store the full path in the DB.
                
                const previewUrl = `/uploads/${item.image_id}`; // Fallback if direct ID is used
                
                tr.innerHTML = `
                    <td>
                        <img src="${item.file_path || previewUrl}" class="img-thumbnail" style="height: 50px; cursor: pointer;" 
                             onclick="window.previewImage('${item.file_path || previewUrl}')"
                             onerror="this.src='/assets/img/placeholder.png'">
                    </td>
                    <td><small class="text-muted">${item.original_name}</small></td>
                    <td><span class="badge badge-info">${item.mime_type.split('/')[1] || item.mime_type}</span></td>
                    <td>${(item.file_size / 1024).toFixed(1)} KB</td>
                    <td>${item.username || 'System'}</td>
                    <td>${new Date(item.created_at).toLocaleString()}</td>
                    <td>
                        <button class="btn btn-sm btn-danger delete-upload" data-id="${item.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                listBody.appendChild(tr);
            });

            // Handle Pagination
            renderPagination(meta, pagination, loadUploads);
        })
        .catch(err => {
            console.error('Upload load error:', err);
            listBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Error: ${err.message}</td></tr>`;
        });
    }

    function renderPagination(meta, container, loader) {
        container.innerHTML = '';
        if (meta.total_pages <= 1) return;

        for (let i = 1; i <= meta.total_pages; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === meta.page ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            li.addEventListener('click', (e) => {
                e.preventDefault();
                loader(i);
            });
            container.appendChild(li);
        }
    }

    window.previewImage = function(url) {
        document.getElementById('full-preview').src = url;
        $('#preview-modal').modal('show');
    };

    document.getElementById('refresh-uploads').addEventListener('click', () => loadUploads(currentPage));

    // Event delegation for delete buttons
    document.getElementById('uploads-list').addEventListener('click', function(e) {
        const btn = e.target.closest('.delete-upload');
        if (!btn) return;

        if (!confirm('Are you sure you want to delete this upload record? This will NOT delete the physical file from the server.')) {
            return;
        }

        const id = btn.dataset.id;
        fetch(`/api/v1/admin/uploads/${id}`, {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                loadUploads(currentPage);
            } else {
                alert('Error: ' + res.message);
            }
        });
    });

    loadUploads(1);
});
