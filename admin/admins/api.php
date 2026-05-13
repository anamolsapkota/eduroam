<?php
// JSON API for Admin Management

session_start();

if (!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/config.php';
include dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$action = $_POST['action'];

switch ($action) {
    case 'create':
        adm_createAdmin();
        break;
    case 'update':
        adm_updateAdmin();
        break;
    case 'delete':
        adm_deleteAdmin();
        break;
    case 'get':
        adm_getAdmin();
        break;
    case 'list':
        adm_listAdmins();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

exit;

function adm_createAdmin()
{
    global $pdo;

    try {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $fullname === '' || $email === '' || $password === '') {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            return;
        }

        // Check if username exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rmadmin WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
            return;
        }

        $hashedPassword = sha1($password);

        $stmt = $pdo->prepare("INSERT INTO rmadmin (username, password, fullname, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, $fullname, $email]);

        echo json_encode(['status' => 'success', 'message' => 'Admin created successfully']);

    } catch (PDOException $e) {
        error_log("Database error in adm_createAdmin: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

function adm_updateAdmin()
{
    global $pdo;

    try {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $fullname === '' || $email === '') {
            echo json_encode(['status' => 'error', 'message' => 'Full name and email are required']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            return;
        }

        $fields = 'fullname = :fullname, email = :email';
        $params = [':fullname' => $fullname, ':email' => $email, ':username' => $username];

        if ($password !== '') {
            $fields .= ', password = :password';
            $params[':password'] = sha1($password);
        }

        $stmt = $pdo->prepare("UPDATE rmadmin SET $fields WHERE username = :username");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
            return;
        }

        // Update session if editing self
        if (isset($_SESSION['user']['username']) && $_SESSION['user']['username'] === $username) {
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['email'] = $email;
        }

        echo json_encode(['status' => 'success', 'message' => 'Admin updated successfully']);

    } catch (PDOException $e) {
        error_log("Database error in adm_updateAdmin: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

function adm_deleteAdmin()
{
    global $pdo;

    try {
        $username = trim($_POST['username'] ?? '');

        if ($username === '') {
            echo json_encode(['status' => 'error', 'message' => 'Username is required']);
            return;
        }

        // Prevent deleting self
        if (isset($_SESSION['user']['username']) && $_SESSION['user']['username'] === $username) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete your own account']);
            return;
        }

        $pdo->beginTransaction();

        // Prevent deleting the last admin (locked read to avoid race condition)
        $countStmt = $pdo->query("SELECT COUNT(*) FROM rmadmin FOR UPDATE");
        $adminCount = (int) $countStmt->fetchColumn();

        if ($adminCount <= 1) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete the last administrator']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM rmadmin WHERE username = :username");
        $stmt->execute([':username' => $username]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Admin deleted successfully']);
        } else {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in adm_deleteAdmin: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

function adm_getAdmin()
{
    global $pdo;

    try {
        $username = trim($_POST['username'] ?? '');

        if ($username === '') {
            echo json_encode(['status' => 'error', 'message' => 'Username is required']);
            return;
        }

        $stmt = $pdo->prepare("SELECT username, fullname, email FROM rmadmin WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            echo json_encode(['status' => 'success', 'data' => $admin]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Admin not found']);
        }

    } catch (PDOException $e) {
        error_log("Database error in adm_getAdmin: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

function adm_listAdmins()
{
    global $pdo;

    try {
        $search = trim($_POST['search'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $limit = intval($_POST['limit'] ?? 10);
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        $offset = ($page - 1) * $limit;

        $whereClause = '';
        $params = [];

        if ($search !== '') {
            $whereClause = "WHERE username LIKE :search OR fullname LIKE :search OR email LIKE :search";
            $params[':search'] = "%$search%";
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM rmadmin $whereClause");
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $selectStmt = $pdo->prepare("SELECT username, fullname, email FROM rmadmin $whereClause ORDER BY username ASC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $selectStmt->bindValue($key, $value);
        }
        $selectStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $selectStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $selectStmt->execute();

        $admins = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $admins,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
        ]);

    } catch (PDOException $e) {
        error_log("Database error in adm_listAdmins: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}
