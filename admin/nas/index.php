<?php
// Start the session if not already started
session_start();

// Check if 'basic_auth' session variable is set
if(!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    // Redirect to the login page
    header('Location: login.php');
    exit; // Terminate script execution after redirection
}

// Get basic auth from session
$basic_auth = base64_decode($_SESSION['basic_auth']);
$authUser = explode(':', $basic_auth)[0];
$authPass = explode(':', $basic_auth)[1];

$_SERVER['PHP_AUTH_USER'] = $authUser;
$_SERVER['PHP_AUTH_PW'] = $authPass;

// exit if not ($_SERVER['PHP_AUTH_USER'] === $authUser && $_SERVER['PHP_AUTH_PW'] === $authPass)
if ($_SERVER['PHP_AUTH_USER'] !== $authUser || $_SERVER['PHP_AUTH_PW'] !== $authPass) {
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Access Denied';
    exit;
}

// Include the config.php file
require_once '../../includes/config.php';

?>

<?php

// Include database connection
include '../../db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create':
            createNAS();
            break;
        case 'update':
            updateNAS();
            break;
        case 'delete':
            deleteNAS();
            break;
        case 'get':
            getNAS();
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    exit;
}

// Function to create a new NAS
function createNAS() {
    global $pdo;
    
    try {
        $nasname = trim($_POST['nasname']);
        $shortname = trim($_POST['shortname']);
        $type = trim($_POST['type']);
        $ports = intval($_POST['ports']);
        $secret = trim($_POST['secret']);
        $server = trim($_POST['server']);
        $community = trim($_POST['community']);
        $description = trim($_POST['description']);
        
        // Validate required fields
        if (empty($nasname) || empty($shortname) || empty($secret)) {
            echo json_encode(['status' => 'error', 'message' => 'NAS Name, Short Name, and Secret are required']);
            return;
        }
        
        // Check if nasname already exists
        $checkQuery = "SELECT id FROM nas WHERE nasname = :nasname";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':nasname', $nasname);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'NAS Name already exists']);
            return;
        }
        
        $insertQuery = "INSERT INTO nas (nasname, shortname, type, ports, secret, server, community, description) VALUES (:nasname, :shortname, :type, :ports, :secret, :server, :community, :description)";
        $stmt = $pdo->prepare($insertQuery);
        $stmt->bindParam(':nasname', $nasname);
        $stmt->bindParam(':shortname', $shortname);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':ports', $ports);
        $stmt->bindParam(':secret', $secret);
        $stmt->bindParam(':server', $server);
        $stmt->bindParam(':community', $community);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'NAS created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create NAS']);
        }
    } catch (PDOException $e) {
        error_log("Database error in createNAS: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

// Function to update an existing NAS
function updateNAS() {
    global $pdo;
    
    try {
        $id = intval($_POST['id']);
        $nasname = trim($_POST['nasname']);
        $shortname = trim($_POST['shortname']);
        $type = trim($_POST['type']);
        $ports = intval($_POST['ports']);
        $secret = trim($_POST['secret']);
        $server = trim($_POST['server']);
        $community = trim($_POST['community']);
        $description = trim($_POST['description']);
        
        // Validate required fields
        if (empty($nasname) || empty($shortname) || empty($secret)) {
            echo json_encode(['status' => 'error', 'message' => 'NAS Name, Short Name, and Secret are required']);
            return;
        }
        
        // Check if nasname already exists for other records
        $checkQuery = "SELECT id FROM nas WHERE nasname = :nasname AND id != :id";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':nasname', $nasname);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'NAS Name already exists']);
            return;
        }
        
        $updateQuery = "UPDATE nas SET nasname = :nasname, shortname = :shortname, type = :type, ports = :ports, secret = :secret, server = :server, community = :community, description = :description WHERE id = :id";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nasname', $nasname);
        $stmt->bindParam(':shortname', $shortname);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':ports', $ports);
        $stmt->bindParam(':secret', $secret);
        $stmt->bindParam(':server', $server);
        $stmt->bindParam(':community', $community);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'NAS updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update NAS']);
        }
    } catch (PDOException $e) {
        error_log("Database error in updateNAS: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

// Function to delete a NAS
function deleteNAS() {
    global $pdo;
    
    try {
        $id = intval($_POST['id']);
        
        $deleteQuery = "DELETE FROM nas WHERE id = :id";
        $stmt = $pdo->prepare($deleteQuery);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'NAS deleted successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'NAS not found']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete NAS']);
        }
    } catch (PDOException $e) {
        error_log("Database error in deleteNAS: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

// Function to get a single NAS for editing
function getNAS() {
    global $pdo;
    
    try {
        $id = intval($_POST['id']);
        
        $selectQuery = "SELECT * FROM nas WHERE id = :id";
        $stmt = $pdo->prepare($selectQuery);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $nas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nas) {
            echo json_encode(['status' => 'success', 'data' => $nas]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'NAS not found']);
        }
    } catch (PDOException $e) {
        error_log("Database error in getNAS: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

// Get all NAS records for display
try {
    $selectAllQuery = "SELECT * FROM nas ORDER BY nasname";
    $stmt = $pdo->prepare($selectAllQuery);
    $stmt->execute();
    $nasList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in NAS listing: " . $e->getMessage());
    $nasList = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> - NAS Management</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <?php include '../../template_parts/head.php'; ?>
</head>
<body>
    <?php include '../../template_parts/nav.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-server"></i> NAS Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nasModal" onclick="openCreateModal()">
                        <i class="fas fa-plus"></i> Add New NAS
                    </button>
                </div>

                <!-- NAS List Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">NAS List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <!-- <th>ID</th> -->
                                        <th>NAS Host</th>
                                        <th>Short Name</th>
                                        <th>Type</th>
                                        <th>Ports</th>
                                        <th>Server</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($nasList)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No NAS records found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($nasList as $nas): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($nas['nasname']); ?></td>
                                                <td><?php echo htmlspecialchars($nas['shortname']); ?></td>
                                                <td><?php echo htmlspecialchars($nas['type']); ?></td>
                                                <td><?php echo htmlspecialchars($nas['ports']); ?></td>
                                                <td><?php echo htmlspecialchars($nas['server']); ?></td>
                                                <td><?php echo htmlspecialchars($nas['description']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick="editNAS(<?php echo $nas['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteNAS(<?php echo $nas['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NAS Modal -->
    <div class="modal fade" id="nasModal" tabindex="-1" aria-labelledby="nasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nasModalLabel">Add New NAS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="nasForm">
                        <input type="hidden" id="nasId" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nasname" class="form-label">NAS Host <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nasname" name="nasname" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="shortname" class="form-label">Short Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="shortname" name="shortname" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Type</label>
                                    <select class="form-select" id="type" name="type">
                                        <option value="">Select Type</option>
                                        <option value="cisco">Cisco</option>
                                        <option value="mikrotik">MikroTik</option>
                                        <option value="ubiquiti">Ubiquiti</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ports" class="form-label">Ports</label>
                                    <input type="number" class="form-control" id="ports" name="ports" min="0" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="secret" class="form-label">Secret <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="secret" name="secret" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="server" class="form-label">Server</label>
                                    <input type="text" class="form-control" id="server" name="server">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="community" class="form-label">Community</label>
                            <input type="text" class="form-control" id="community" name="community">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveNAS()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../template_parts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let isEdit = false;

        function openCreateModal() {
            isEdit = false;
            document.getElementById('nasModalLabel').textContent = 'Add New NAS';
            document.getElementById('nasForm').reset();
            document.getElementById('nasId').value = '';
        }

        function editNAS(id) {
            isEdit = true;
            document.getElementById('nasModalLabel').textContent = 'Edit NAS';
            
            // Fetch NAS data
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const nas = data.data;
                    document.getElementById('nasId').value = nas.id;
                    document.getElementById('nasname').value = nas.nasname;
                    document.getElementById('shortname').value = nas.shortname;
                    document.getElementById('type').value = nas.type;
                    document.getElementById('ports').value = nas.ports;
                    document.getElementById('secret').value = nas.secret;
                    document.getElementById('server').value = nas.server;
                    document.getElementById('community').value = nas.community;
                    document.getElementById('description').value = nas.description;
                    
                    const modal = new bootstrap.Modal(document.getElementById('nasModal'));
                    modal.show();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while fetching NAS data');
            });
        }

        function saveNAS() {
            const form = document.getElementById('nasForm');
            const formData = new FormData(form);
            
            if (isEdit) {
                formData.append('action', 'update');
            } else {
                formData.append('action', 'create');
            }
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving NAS');
            });
        }

        function deleteNAS(id) {
            if (confirm('Are you sure you want to delete this NAS?')) {
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting NAS');
                });
            }
        }
    </script>
</body>
</html>