<?php
$admin_page_title = 'Users';
include dirname(__DIR__) . '/includes/admin-shell-header.php';
?>

<div class="admin-page-header">
    <div>
        <h1>User Management</h1>
        <p>Create, edit, and manage eduroam user accounts.</p>
    </div>
    <div class="admin-page-actions">
        <button class="btn btn-primary btn-sm" onclick="um_openCreateModal()">
            <i class="fas fa-user-plus me-2"></i>Add New User
        </button>
    </div>
</div>

<!-- Advanced User Management -->
<div class="card">
    <div class="card-body">
        <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
            <input type="text" id="um_searchInput" class="form-control" placeholder="Search by username, name, or email..." style="max-width: 400px;">
            <select id="um_limitSelect" class="form-select" style="max-width: 150px;">
                <option value="10">10 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" onclick="um_refreshTable()">
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
                        <th>Updated Date</th>
                        <th>Updated By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="um_userTableBody">
                    <tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>
                </tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination" id="um_paginationContainer"></ul>
        </nav>
    </div>
</div>

<!-- Create/Edit User Modal -->
<div class="modal fade modal-professional" id="um_userModal" tabindex="-1" aria-labelledby="um_userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="um_userModalLabel">
                    <i class="fas fa-user-plus"></i> Add New User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="um_userForm">
                    <input type="hidden" id="um_originalUsername" name="original_username">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="um_username" class="form-label form-label-professional">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control form-control-professional" id="um_username" name="username" required>
                                </div>
                                <small class="text-muted" id="um_usernameHelp">Cannot be changed after creation</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="um_fullname" class="form-label form-label-professional">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control form-control-professional" id="um_fullname" name="fullname" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="um_email" class="form-label form-label-professional">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control form-control-professional" id="um_email" name="email" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="um_password" class="form-label form-label-professional">
                            Password
                        </label>
                        <div class="input-group-professional">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control form-control-professional" id="um_password" name="password" disabled>
                            </div>
                            <i class="fas fa-eye toggle-password" style="visibility: hidden;"></i>
                        </div>
                        <small class="text-muted" id="um_passwordHelp">Leave blank to auto-generate a secure password</small>
                        <div class="password-strength" id="um_passwordStrength" style="display: none;"></div>
                    </div>

                    <div class="mb-3" id="um_sendEmailContainer">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="um_send_email" name="send_email" checked>
                            <label class="form-check-label" for="um_send_email">
                                <i class="fas fa-paper-plane"></i> Send welcome email with credentials
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-professional alert-info" id="um_passwordDisplay" style="display: none;">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Generated Password:</strong>
                            <code id="um_generatedPassword" style="font-size: 1.1rem;"></code>
                            <button type="button" class="btn btn-sm btn-outline-info ms-2" onclick="um_copyPassword()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-professional" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary btn-professional" onclick="um_saveUser()">
                    <i class="fas fa-save"></i> <span id="um_saveButtonText">Save User</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade modal-professional" id="um_deleteModal" tabindex="-1" aria-labelledby="um_deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="um_deleteModalLabel">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-professional alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Warning!</strong> This action cannot be undone.
                    </div>
                </div>
                <p>Are you sure you want to delete user <strong id="um_deleteUsername"></strong>?</p>
                <p class="text-muted">This will permanently remove all user data and access credentials.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-professional" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger btn-professional" onclick="um_confirmDelete()">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View User Details Modal -->
<div class="modal fade modal-professional" id="um_viewModal" tabindex="-1" aria-labelledby="um_viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="um_viewModalLabel">
                    <i class="fas fa-user-circle"></i> User Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-user"></i> Username:</strong>
                        <p class="ms-4" id="um_viewUsername"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-id-card"></i> Full Name:</strong>
                        <p class="ms-4" id="um_viewFullname"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-envelope"></i> Email:</strong>
                        <p class="ms-4" id="um_viewEmail"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-lock"></i> Password:</strong>
                        <p class="ms-4"><code>••••••••</code></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-calendar"></i> Updated Date:</strong>
                        <p class="ms-4" id="um_viewUpdatedate"></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong><i class="fas fa-user-edit"></i> Updated By:</strong>
                        <p class="ms-4" id="um_viewUpdateby"></p>
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
    // ========== Advanced User Management JS (uses api.php) ==========

    let um_isEdit = false;
    let um_currentPage = 1;
    let um_currentLimit = 10;
    let um_currentSearch = '';
    let um_deleteTargetUsername = '';

    document.addEventListener('DOMContentLoaded', function () {
        um_loadUsers();

        // Search with debounce
        let searchTimeout;
        document.getElementById('um_searchInput').addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                um_currentSearch = document.getElementById('um_searchInput').value;
                um_currentPage = 1;
                um_loadUsers();
            }, 500);
        });

        // Limit change
        document.getElementById('um_limitSelect').addEventListener('change', function () {
            um_currentLimit = parseInt(this.value);
            um_currentPage = 1;
            um_loadUsers();
        });

        // Password strength checker
        document.getElementById('um_password').addEventListener('input', function () {
            um_checkPasswordStrength(this.value);
        });
    });

    function um_loadUsers() {
        const tbody = document.getElementById('um_userTableBody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>';

        const formData = new FormData();
        formData.append('action', 'list');
        formData.append('page', um_currentPage);
        formData.append('limit', um_currentLimit);
        formData.append('search', um_currentSearch);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    um_displayUsers(data.data);
                    um_updatePagination(data.totalPages, data.page);
                } else {
                    um_showError('Failed to load users: ' + (data.message || 'Unknown error'));
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading users</td></tr>';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                um_showError('An error occurred while loading users: ' + error.message);
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error: ' + error.message + '</td></tr>';
            });
    }

    function um_displayUsers(users) {
        const tbody = document.getElementById('um_userTableBody');

        if (!users || users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <h4>No Users Found</h4>
                            <p>Try adjusting your search criteria or add a new user</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = users.map(user => `
            <tr>
                <td><strong>${um_escapeHtml(user.username)}</strong></td>
                <td>${um_escapeHtml(user.fullname)}</td>
                <td>
                    <a href="mailto:${um_escapeHtml(user.email)}" class="text-decoration-none">
                        ${um_escapeHtml(user.email)}
                    </a>
                </td>
                <td><small>${um_formatDate(user.updatedate)}</small></td>
                <td><span class="badge badge-status bg-secondary">${um_escapeHtml(user.updateby)}</span></td>
                <td>
                    <button class="btn btn-info btn-icon" onclick="um_viewUser('${um_escapeHtml(user.username)}')" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-warning btn-icon" onclick="um_editUser('${um_escapeHtml(user.username)}')" title="Edit User">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-icon" onclick="um_deleteUser('${um_escapeHtml(user.username)}')" title="Delete User">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function um_updatePagination(totalPages, currentPageNum) {
        const container = document.getElementById('um_paginationContainer');

        if (!totalPages || totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        // Previous
        html += `
            <li class="page-item ${currentPageNum === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="um_changePage(${currentPageNum - 1}); return false;">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPageNum - 2 && i <= currentPageNum + 2)) {
                html += `
                    <li class="page-item ${i === currentPageNum ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="um_changePage(${i}); return false;">${i}</a>
                    </li>
                `;
            } else if (i === currentPageNum - 3 || i === currentPageNum + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Next
        html += `
            <li class="page-item ${currentPageNum === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="um_changePage(${currentPageNum + 1}); return false;">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;

        container.innerHTML = html;
    }

    function um_changePage(page) {
        if (page < 1) return;
        um_currentPage = page;
        um_loadUsers();
    }

    function um_openCreateModal() {
        um_isEdit = false;
        document.getElementById('um_userModalLabel').innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
        document.getElementById('um_userForm').reset();
        document.getElementById('um_originalUsername').value = '';
        document.getElementById('um_username').readOnly = false;
        document.getElementById('um_sendEmailContainer').style.display = 'block';
        document.getElementById('um_passwordHelp').textContent = 'Leave blank to auto-generate a secure password';
        document.getElementById('um_passwordDisplay').style.display = 'none';
        document.getElementById('um_usernameHelp').style.display = 'block';
        document.getElementById('um_saveButtonText').textContent = 'Save User';

        const modal = new bootstrap.Modal(document.getElementById('um_userModal'));
        modal.show();
    }

    function um_editUser(username) {
        um_isEdit = true;
        document.getElementById('um_userModalLabel').innerHTML = '<i class="fas fa-user-edit"></i> Edit User';
        document.getElementById('um_saveButtonText').textContent = 'Update User';

        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('username', username);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const user = data.data;
                    document.getElementById('um_originalUsername').value = user.username;
                    document.getElementById('um_username').value = user.username;
                    document.getElementById('um_username').readOnly = true;
                    document.getElementById('um_fullname').value = user.fullname;
                    document.getElementById('um_email').value = user.email;
                    document.getElementById('um_password').value = '';
                    document.getElementById('um_sendEmailContainer').style.display = 'none';
                    document.getElementById('um_passwordHelp').textContent = 'Leave blank to keep current password';
                    document.getElementById('um_passwordDisplay').style.display = 'none';
                    document.getElementById('um_usernameHelp').style.display = 'none';

                    const modal = new bootstrap.Modal(document.getElementById('um_userModal'));
                    modal.show();
                } else {
                    um_showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                um_showError('An error occurred while fetching user data');
            });
    }

    function um_viewUser(username) {
        const formData = new FormData();
        formData.append('action', 'get');
        formData.append('username', username);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const user = data.data;
                    document.getElementById('um_viewUsername').textContent = user.username;
                    document.getElementById('um_viewFullname').textContent = user.fullname;
                    document.getElementById('um_viewEmail').textContent = user.email;
                    document.getElementById('um_viewUpdatedate').textContent = um_formatDate(user.updatedate);
                    document.getElementById('um_viewUpdateby').textContent = user.updateby;

                    const modal = new bootstrap.Modal(document.getElementById('um_viewModal'));
                    modal.show();
                } else {
                    um_showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                um_showError('An error occurred while fetching user data');
            });
    }

    function um_saveUser() {
        const form = document.getElementById('um_userForm');
        const formData = new FormData(form);

        if (um_isEdit) {
            formData.append('action', 'update');
            formData.append('username', document.getElementById('um_originalUsername').value);
        } else {
            formData.append('action', 'create');
            formData.append('send_email', document.getElementById('um_send_email').checked ? 'true' : 'false');
        }

        const saveBtn = document.querySelector('#um_userModal .btn-primary');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="loading-spinner"></span> Saving...';
        saveBtn.disabled = true;

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;

                if (data.status === 'success') {
                    if (!um_isEdit && data.password) {
                        um_showSuccess(data.message);
                        const modal = new bootstrap.Modal(document.getElementById('um_userModal'));
                        modal.hide(); // Close the modal

                        // Reset the form fields after closing the modal
                        form.reset();
                    } else {
                        um_showSuccess(data.message);
                        // Close the modal after a successful save
                        const modal = new bootstrap.Modal(document.getElementById('um_userModal'));
                        modal.hide(); // Close the modal

                        // Reset the form fields after closing the modal
                        form.reset();

                        // Optionally reset any dynamic states like password display or modal title
                        document.getElementById('um_userModalLabel').innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
                        document.getElementById('um_passwordDisplay').style.display = 'none';
                        document.getElementById('um_usernameHelp').style.display = 'block';
                        document.getElementById('um_sendEmailContainer').style.display = 'block';
                    }
                    um_loadUsers();
                } else {
                    um_showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                um_showError('An error occurred while saving user');
            });
    }


    function um_deleteUser(username) {
        um_deleteTargetUsername = username;
        document.getElementById('um_deleteUsername').textContent = username;
        const modal = new bootstrap.Modal(document.getElementById('um_deleteModal'));
        modal.show();
    }

    function um_confirmDelete() {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('username', um_deleteTargetUsername);

        fetch('api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    um_showSuccess(data.message);
                    bootstrap.Modal.getInstance(document.getElementById('um_deleteModal')).hide();
                    um_loadUsers();
                } else {
                    um_showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                um_showError('An error occurred while deleting user');
            });
    }

    function um_refreshTable() {
        um_currentPage = 1;
        um_currentSearch = '';
        document.getElementById('um_searchInput').value = '';
        um_loadUsers();
        um_showSuccess('Table refreshed');
    }

    function um_togglePassword() {
        const input = document.getElementById('um_password');
        const icon = document.querySelector('.toggle-password');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function um_checkPasswordStrength(password) {
        const strengthBar = document.getElementById('um_passwordStrength');

        if (!password) {
            strengthBar.style.display = 'none';
            return;
        }

        strengthBar.style.display = 'block';

        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z\d]/.test(password)) strength++;

        strengthBar.className = 'password-strength';
        if (strength <= 2) {
            strengthBar.classList.add('weak');
        } else if (strength <= 4) {
            strengthBar.classList.add('medium');
        } else {
            strengthBar.classList.add('strong');
        }
    }

    function um_copyPassword() {
        const password = document.getElementById('um_generatedPassword').textContent;
        navigator.clipboard.writeText(password).then(() => {
            um_showSuccess('Password copied to clipboard');
        });
    }

    function um_showSuccess(message) {
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show" role="alert">
                <div class="toast-header bg-success text-white">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function um_showError(message) {
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show" role="alert">
                <div class="toast-header bg-danger text-white">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong class="me-auto">Error</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function um_formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString.replace(' ', 'T'));
        if (isNaN(date.getTime())) return dateString;
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    function um_escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.toString().replace(/[&<>"']/g, m => map[m]) : '';
    }
</script>

<?php include dirname(__DIR__) . '/includes/admin-shell-footer.php'; ?>
