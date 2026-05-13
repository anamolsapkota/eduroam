<?php
$admin_page_title = 'Administrators';
include dirname(__DIR__) . '/includes/admin-shell-header.php';
$currentAdminId = $_SESSION['user']['id'] ?? 0;
?>

<div class="admin-page-header">
    <div>
        <h1>Administrator Management</h1>
        <p>Manage admin accounts that can access this dashboard.</p>
    </div>
    <div class="admin-page-actions">
        <button class="btn btn-primary btn-sm" onclick="adm_openCreateModal()">
            <i class="fas fa-user-shield me-2"></i>Add New Admin
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
            <input type="text" id="adm_searchInput" class="form-control" placeholder="Search by username, name, or email..." style="max-width: 400px;">
            <button class="btn btn-outline-secondary btn-sm" onclick="adm_refreshTable()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="adm_tableBody">
                    <tr><td colspan="4" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination" id="adm_paginationContainer"></ul>
        </nav>
    </div>
</div>

<!-- Create/Edit Admin Modal -->
<div class="modal fade modal-professional" id="adm_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adm_modalLabel">
                    <i class="fas fa-user-shield"></i> Add New Admin
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="adm_form">
                    <input type="hidden" id="adm_editId" name="id">

                    <div class="mb-3">
                        <label for="adm_username" class="form-label form-label-professional">
                            Username <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="adm_username" name="username" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adm_fullname" class="form-label form-label-professional">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="adm_fullname" name="fullname" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adm_email" class="form-label form-label-professional">
                            Email <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="adm_email" name="email" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="adm_password" class="form-label form-label-professional">
                            Password <span class="text-danger" id="adm_passwordRequired">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="adm_password" name="password">
                        </div>
                        <small class="text-muted" id="adm_passwordHelp">Required for new admins</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-professional" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary btn-professional" onclick="adm_save()">
                    <i class="fas fa-save"></i> <span id="adm_saveText">Save Admin</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade modal-professional" id="adm_deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-professional alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><strong>Warning!</strong> This action cannot be undone.</div>
                </div>
                <p>Are you sure you want to delete admin <strong id="adm_deleteUsername"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-professional" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger btn-professional" onclick="adm_confirmDelete()">
                    <i class="fas fa-trash"></i> Delete Admin
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Admin Modal -->
<div class="modal fade modal-professional" id="adm_viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-circle"></i> Admin Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12 mb-3">
                        <strong><i class="fas fa-user"></i> Username:</strong>
                        <p class="ms-4" id="adm_viewUsername"></p>
                    </div>
                    <div class="col-12 mb-3">
                        <strong><i class="fas fa-id-card"></i> Full Name:</strong>
                        <p class="ms-4" id="adm_viewFullname"></p>
                    </div>
                    <div class="col-12 mb-3">
                        <strong><i class="fas fa-envelope"></i> Email:</strong>
                        <p class="ms-4" id="adm_viewEmail"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-professional" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var adm_currentPage = 1;
    var adm_isEdit = false;
    var adm_deleteId = null;
    var adm_currentAdminId = <?php echo (int) $currentAdminId; ?>;

    document.addEventListener('DOMContentLoaded', function () {
        adm_refreshTable();
        document.getElementById('adm_searchInput').addEventListener('input', function () {
            adm_currentPage = 1;
            adm_refreshTable();
        });
    });

    function adm_showError(msg) {
        alert(msg);
    }

    function adm_refreshTable() {
        var search = document.getElementById('adm_searchInput').value;
        var formData = new FormData();
        formData.append('action', 'list');
        formData.append('search', search);
        formData.append('page', adm_currentPage);
        formData.append('limit', 10);

        fetch('api.php', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status !== 'success') {
                    adm_showError(data.message);
                    return;
                }

                var tbody = document.getElementById('adm_tableBody');
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No administrators found</td></tr>';
                } else {
                    tbody.innerHTML = data.data.map(function (admin) {
                        var isSelf = parseInt(admin.id) === adm_currentAdminId;
                        var badge = isSelf ? ' <span class="badge bg-primary">You</span>' : '';
                        var deleteBtn = isSelf
                            ? '<button class="btn btn-outline-danger btn-sm" disabled title="Cannot delete yourself"><i class="fas fa-trash"></i></button>'
                            : '<button class="btn btn-outline-danger btn-sm" onclick="adm_openDelete(' + admin.id + ', \'' + admin.username.replace(/'/g, "\\'") + '\')"><i class="fas fa-trash"></i></button>';

                        return '<tr>' +
                            '<td>' + adm_esc(admin.username) + badge + '</td>' +
                            '<td>' + adm_esc(admin.fullname) + '</td>' +
                            '<td>' + adm_esc(admin.email) + '</td>' +
                            '<td>' +
                                '<div class="btn-group btn-group-sm">' +
                                    '<button class="btn btn-outline-primary btn-sm" onclick="adm_viewAdmin(' + admin.id + ')"><i class="fas fa-eye"></i></button>' +
                                    '<button class="btn btn-outline-secondary btn-sm" onclick="adm_openEdit(' + admin.id + ')"><i class="fas fa-edit"></i></button>' +
                                    deleteBtn +
                                '</div>' +
                            '</td>' +
                        '</tr>';
                    }).join('');
                }

                // Pagination
                var pagEl = document.getElementById('adm_paginationContainer');
                pagEl.innerHTML = '';
                for (var i = 1; i <= data.totalPages; i++) {
                    pagEl.innerHTML += '<li class="page-item ' + (i === data.page ? 'active' : '') + '"><a class="page-link" href="#" onclick="adm_goPage(' + i + '); return false;">' + i + '</a></li>';
                }
            })
            .catch(function (err) {
                console.error('Error:', err);
            });
    }

    function adm_goPage(page) {
        adm_currentPage = page;
        adm_refreshTable();
    }

    function adm_esc(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function adm_openCreateModal() {
        adm_isEdit = false;
        document.getElementById('adm_modalLabel').innerHTML = '<i class="fas fa-user-shield"></i> Add New Admin';
        document.getElementById('adm_saveText').textContent = 'Save Admin';
        document.getElementById('adm_form').reset();
        document.getElementById('adm_editId').value = '';
        document.getElementById('adm_username').disabled = false;
        document.getElementById('adm_password').required = true;
        document.getElementById('adm_passwordRequired').style.display = '';
        document.getElementById('adm_passwordHelp').textContent = 'Required for new admins';
        new bootstrap.Modal(document.getElementById('adm_modal')).show();
    }

    function adm_openEdit(id) {
        adm_isEdit = true;
        var formData = new FormData();
        formData.append('action', 'get');
        formData.append('id', id);

        fetch('api.php', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    var admin = data.data;
                    document.getElementById('adm_modalLabel').innerHTML = '<i class="fas fa-user-edit"></i> Edit Admin';
                    document.getElementById('adm_saveText').textContent = 'Update Admin';
                    document.getElementById('adm_editId').value = admin.id;
                    document.getElementById('adm_username').value = admin.username;
                    document.getElementById('adm_username').disabled = true;
                    document.getElementById('adm_fullname').value = admin.fullname;
                    document.getElementById('adm_email').value = admin.email;
                    document.getElementById('adm_password').value = '';
                    document.getElementById('adm_password').required = false;
                    document.getElementById('adm_passwordRequired').style.display = 'none';
                    document.getElementById('adm_passwordHelp').textContent = 'Leave blank to keep current password';
                    new bootstrap.Modal(document.getElementById('adm_modal')).show();
                } else {
                    adm_showError(data.message);
                }
            });
    }

    function adm_save() {
        var form = document.getElementById('adm_form');
        var formData = new FormData(form);

        if (adm_isEdit) {
            formData.append('action', 'update');
            formData.append('id', document.getElementById('adm_editId').value);
        } else {
            formData.append('action', 'create');
        }

        var saveBtn = document.querySelector('#adm_modal .btn-primary');
        var originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        saveBtn.disabled = true;

        fetch('api.php', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('adm_modal')).hide();
                    adm_refreshTable();
                } else {
                    adm_showError(data.message);
                }
            })
            .catch(function () {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                adm_showError('An error occurred');
            });
    }

    function adm_openDelete(id, username) {
        adm_deleteId = id;
        document.getElementById('adm_deleteUsername').textContent = username;
        new bootstrap.Modal(document.getElementById('adm_deleteModal')).show();
    }

    function adm_confirmDelete() {
        var formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', adm_deleteId);

        fetch('api.php', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                bootstrap.Modal.getInstance(document.getElementById('adm_deleteModal')).hide();
                if (data.status === 'success') {
                    adm_refreshTable();
                } else {
                    adm_showError(data.message);
                }
            });
    }

    function adm_viewAdmin(id) {
        var formData = new FormData();
        formData.append('action', 'get');
        formData.append('id', id);

        fetch('api.php', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    var admin = data.data;
                    document.getElementById('adm_viewUsername').textContent = admin.username;
                    document.getElementById('adm_viewFullname').textContent = admin.fullname;
                    document.getElementById('adm_viewEmail').textContent = admin.email;
                    new bootstrap.Modal(document.getElementById('adm_viewModal')).show();
                } else {
                    adm_showError(data.message);
                }
            });
    }
</script>

<?php include dirname(__DIR__) . '/includes/admin-shell-footer.php'; ?>
