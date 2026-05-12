<?php
// JSON API for Advanced User Management

// Start the session if not already started
session_start();

// Check if 'basic_auth' session variable is set
if(!isset($_SESSION['basic_auth']) || empty($_SESSION['basic_auth'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Get basic auth from session
$basic_auth = base64_decode($_SESSION['basic_auth']);
$authUser = explode(':', $basic_auth)[0];
$authPass = explode(':', $basic_auth)[1];

$_SERVER['PHP_AUTH_USER'] = $authUser;
$_SERVER['PHP_AUTH_PW'] = $authPass;

// exit if not authenticated
if ($_SERVER['PHP_AUTH_USER'] !== $authUser || $_SERVER['PHP_AUTH_PW'] !== $authPass) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Include the config.php file
require_once dirname(__DIR__, 2) . '/includes/config.php';

// Include database connection
include dirname(__DIR__, 2) . '/db.php';

// If you use PHPMailer via Composer autoload, include it here, e.g.:
// require 'vendor/autoload.php';

// Function to generate a random password
function um_generateRandomPassword($length = 12)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $password   = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

function um_sendEmail($to, $fullname, $subject, $message)
{
    global $mail_hostname, $mail_secure, $mail_port, $mail_username, $mail_password, $admin_email, $site_name;

    if (!class_exists('PHPMailer')) {
        // PHPMailer not available; just skip sending but don't break API
        return false;
    }

    $Mail = new PHPMailer();
    $Mail->isSMTP();
    $Mail->SMTPAuth   = true;
    $Mail->Host       = $mail_hostname;
    $Mail->SMTPSecure = $mail_secure;
    $Mail->Port       = $mail_port;
    $Mail->Username   = $mail_username;
    $Mail->Password   = $mail_password;
    $Mail->From       = $admin_email;
    $Mail->FromName   = $site_name;
    $Mail->addReplyTo($Mail->From, $Mail->FromName);
    $Mail->isHTML(true);
    $Mail->XMailer = $site_name;
    $Mail->addAddress($to, $fullname);
    $Mail->Subject = $subject;
    $Mail->Body    = $message;

    return $Mail->send();
}

// Ensure JSON header
header('Content-Type: application/json');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$action = $_POST['action'];

switch ($action) {
    case 'create':
        um_createUser();
        break;
    case 'update':
        um_updateUser();
        break;
    case 'delete':
        um_deleteUser();
        break;
    case 'get':
        um_getUser();
        break;
    case 'list':
        um_listUsers();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

exit;

// Function to create a new user
function um_createUser()
{
    global $pdo, $site_name, $site_baseurl;

    try {
        $username   = trim($_POST['username'] ?? '');
        $fullname   = trim($_POST['fullname'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = isset($_POST['password']) && $_POST['password'] !== '' ? trim($_POST['password']) : um_generateRandomPassword(12);
        $sendEmail  = isset($_POST['send_email']) && ($_POST['send_email'] === 'true' || $_POST['send_email'] === '1' || $_POST['send_email'] === 'on');
        $updateby   = $_SERVER['PHP_AUTH_USER'];
        $updatedate = date("Y-m-d H:i:s");

        if ($username === '' || $fullname === '' || $email === '') {
            echo json_encode(['status' => 'error', 'message' => 'Username, Full Name, and Email are required']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            return;
        }

        // Check if username exists
        $checkQuery = "SELECT COUNT(*) FROM radcheck WHERE username = ?";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([$username]);
        $userExists = $stmt->fetchColumn();

        if ($userExists > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
            return;
        }

        $pdo->beginTransaction();

        // Insert into radcheck
        $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
        $stmt->execute([$username, $password]);

        // Insert into userinfo
        $stmt = $pdo->prepare("INSERT INTO userinfo (username, fullname, email, updateby, updatedate) VALUES (?,?,?,?,?)");
        $stmt->execute([$username, $fullname, $email, $updateby, $updatedate]);

        $pdo->commit();

        if ($sendEmail) {
            $subject = 'Eduroam Access Information';
            $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background-color: #007BFF; color: #fff; padding: 20px; text-align: center;">
                        <h1>Eduroam Access Information</h1>
                    </div>
                    <div style="padding: 20px;">
                        <p>Dear ' . htmlspecialchars($fullname) . ',</p>
                        <p>We are pleased to provide you with access to Eduroam, a secure and convenient Wi-Fi network service
                            available at educational institutions worldwide.</p>
                        <p>Here are your access details:</p>
                        <ul>
                            <li><strong>Network Name (SSID):</strong> eduroam</li>
                            <li><strong>Username: </strong>' . htmlspecialchars($username) . '</li>
                            <li><strong>Password:</strong> ' . htmlspecialchars($password) . '</li>
                        </ul>
                        <p>Simply select the "Eduroam" network on your device, enter your username and password, and you will
                            have secure internet access.</p>
                        <p>If you ever forget your password and need to reset it, you can do so by clicking the following link:
                            <a href="' . $site_baseurl . 'eduroam/forgotpass.php">Reset Password</a></p>
                        <p>We hope you enjoy the benefits of secure and hassle-free internet access.</p>
                        <p>Sincerely,</p>
                        <p>' . htmlspecialchars($site_name) . '</p>
                    </div>
                    </div>';

            @um_sendEmail($email, $fullname, $subject, $message);
        }

        echo json_encode(['status' => 'success', 'message' => 'User created successfully', 'password' => $password]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in um_createUser: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

// Function to update an existing user
function um_updateUser()
{
    global $pdo;

    try {
        $username   = trim($_POST['username'] ?? '');
        $fullname   = trim($_POST['fullname'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        $updateby   = $_SERVER['PHP_AUTH_USER'];
        $updatedate = date("Y-m-d H:i:s");

        if ($username === '' || $fullname === '' || $email === '') {
            echo json_encode(['status' => 'error', 'message' => 'Username, Full Name, and Email are required']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            return;
        }

        $pdo->beginTransaction();

        // Update userinfo
        $updateQuery = "UPDATE userinfo SET fullname = :fullname, email = :email, updateby = :updateby, updatedate = :updatedate WHERE username = :username";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(':fullname', $fullname);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':updateby', $updateby);
        $stmt->bindParam(':updatedate', $updatedate);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        // Update password if provided
        if ($password !== '') {
            $updatePasswordQuery = "UPDATE radcheck SET value = :password WHERE username = :username AND attribute = 'Cleartext-Password'";
            $stmt = $pdo->prepare($updatePasswordQuery);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
        }

        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => 'User updated successfully']);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in um_updateUser: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

// Function to delete a user
function um_deleteUser()
{
    global $pdo;

    try {
        $username = trim($_POST['username'] ?? '');

        if ($username === '') {
            echo json_encode(['status' => 'error', 'message' => 'Username is required']);
            return;
        }

        $pdo->beginTransaction();

        // Delete from radcheck
        $deleteQuery = "DELETE FROM radcheck WHERE username = :username";
        $stmt = $pdo->prepare($deleteQuery);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        // Delete from userinfo
        $deleteQuery = "DELETE FROM userinfo WHERE username = :username";
        $stmt = $pdo->prepare($deleteQuery);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        $affected = $stmt->rowCount();
        $pdo->commit();

        if ($affected > 0) {
            echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in um_deleteUser: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

// Function to get a single user
function um_getUser()
{
    global $pdo;

    try {
        $username = trim($_POST['username'] ?? '');

        if ($username === '') {
            echo json_encode(['status' => 'error', 'message' => 'Username is required']);
            return;
        }

        $selectQuery = "SELECT u.*
                       FROM userinfo u
                       WHERE u.username = :username";
        $stmt = $pdo->prepare($selectQuery);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode(['status' => 'success', 'data' => $user]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
        }
    } catch (PDOException $e) {
        error_log("Database error in um_getUser: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}

// Function to list users with pagination and search
function um_listUsers()
{
    global $pdo;

    try {
        $search = trim($_POST['search'] ?? '');
        $page   = intval($_POST['page'] ?? 1);
        $limit  = intval($_POST['limit'] ?? 10);
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        $offset = ($page - 1) * $limit;

        $whereClause = '';
        $params = [];

        if ($search !== '') {
            $whereClause = "WHERE u.username LIKE :search OR u.fullname LIKE :search OR u.email LIKE :search";
            $params[':search'] = "%$search%";
        }

        // Total count
        $countQuery = "SELECT COUNT(*) as total FROM userinfo u $whereClause";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Users
        $selectQuery = "SELECT u.*
                       FROM userinfo u
                       $whereClause
                       ORDER BY u.updatedate DESC
                       LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($selectQuery);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'     => 'success',
            'data'       => $users,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => ceil($total / $limit)
        ]);

    } catch (PDOException $e) {
        error_log("Database error in um_listUsers: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    }
}
