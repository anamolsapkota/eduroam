<?php

function guestAccountExpiresAtDb($hours = 24)
{
    return date('Y-m-d H:i:s', strtotime('+' . (int) $hours . ' hours'));
}

function guestAccountExpiresAtRadius($expiresAtDb)
{
    return date('d M Y H:i', strtotime($expiresAtDb));
}

function guestAccountExpiresAtDisplay($expiresAtDb)
{
    return date('D j M Y H:i:s T', strtotime($expiresAtDb));
}

function generateRandomPassword($length = 8)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $password;
}

function generateGuestUsernameBase($fullname)
{
    $lettersOnly = preg_replace('/[^a-z]/', '', strtolower($fullname));

    if ($lettersOnly === '') {
        $lettersOnly = 'guestx';
    }

    if (strlen($lettersOnly) < 6) {
        $lettersOnly = str_pad($lettersOnly, 6, 'x');
    }

    return substr($lettersOnly, 0, 6);
}

function generateGuestUsername(PDO $pdo, $fullname)
{
    $base = generateGuestUsernameBase($fullname);
    $domain = '@eva.nren.net.np';
    $checkQuery = "SELECT COUNT(*) FROM radcheck WHERE username = :username";
    $stmt = $pdo->prepare($checkQuery);

    do {
        $candidate = $base . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT) . $domain;
        $stmt->bindParam(':username', $candidate, PDO::PARAM_STR);
        $stmt->execute();
        $exists = (int) $stmt->fetchColumn() > 0;
        $stmt->closeCursor();
    } while ($exists);

    return $candidate;
}

function ensureGuestAccountInfrastructure(PDO $pdo)
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS guest_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL UNIQUE,
            delivery_email VARCHAR(255) NOT NULL,
            fullname VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            INDEX idx_guest_accounts_delivery_email (delivery_email),
            INDEX idx_guest_accounts_expires_at (expires_at)
        )"
    );
}

function createGuestAccount(PDO $pdo, $fullname, $email, $updatedBy = 'auto_request')
{
    ensureGuestAccountInfrastructure($pdo);
    purgeExpiredGuestAccounts($pdo);

    $email = trim(strtolower($email));
    $fullname = trim($fullname);
    $updatedBy = trim($updatedBy) === '' ? 'auto_request' : trim($updatedBy);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please provide a valid email address.');
    }

    if ($fullname === '') {
        throw new InvalidArgumentException('Please provide the full name.');
    }

    $checkGuestStmt = $pdo->prepare("SELECT COUNT(*) FROM guest_accounts WHERE delivery_email = :email AND expires_at > NOW()");
    $checkGuestStmt->execute([':email' => $email]);
    $activeGuestCount = (int) $checkGuestStmt->fetchColumn();

    $checkUserStmt = $pdo->prepare("SELECT COUNT(*) FROM userinfo WHERE email = :email");
    $checkUserStmt->execute([':email' => $email]);
    $existingUserCount = (int) $checkUserStmt->fetchColumn();

    if ($activeGuestCount > 0 || $existingUserCount > 0) {
        throw new RuntimeException('This email address already has an active account.');
    }

    $username = generateGuestUsername($pdo, $fullname);
    $password = generateRandomPassword(8);
    $createdAt = date('Y-m-d H:i:s');
    $expiresAtDb = guestAccountExpiresAtDb(24);
    $expiresAtRadius = guestAccountExpiresAtRadius($expiresAtDb);

    $pdo->beginTransaction();

    try {
        $insertRadcheckStmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (:username, 'Cleartext-Password', ':=', :password)");
        $insertRadcheckStmt->execute([
            ':username' => $username,
            ':password' => $password,
        ]);

        $insertExpiryStmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (:username, 'Expiration', ':=', :expiration)");
        $insertExpiryStmt->execute([
            ':username' => $username,
            ':expiration' => $expiresAtRadius,
        ]);

        $insertUserinfoStmt = $pdo->prepare("INSERT INTO userinfo (username, fullname, email, updateby, updatedate) VALUES (:username, :fullname, :email, :updatedby, :updatedate)");
        $insertUserinfoStmt->execute([
            ':username' => $username,
            ':fullname' => $fullname,
            ':email' => $email,
            ':updatedby' => $updatedBy,
            ':updatedate' => $createdAt,
        ]);

        $insertGuestStmt = $pdo->prepare("INSERT INTO guest_accounts (username, delivery_email, fullname, created_at, expires_at) VALUES (:username, :email, :fullname, :created_at, :expires_at)");
        $insertGuestStmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':fullname' => $fullname,
            ':created_at' => $createdAt,
            ':expires_at' => $expiresAtDb,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'username' => $username,
        'password' => $password,
        'fullname' => $fullname,
        'delivery_email' => $email,
        'created_at' => $createdAt,
        'expires_at_db' => $expiresAtDb,
        'expires_at_radius' => $expiresAtRadius,
        'expires_at_display' => guestAccountExpiresAtDisplay($expiresAtDb),
    ];
}

function buildGuestCredentialEmail($fullname, $username, $password, $expiresAtDisplay, $siteBaseUrl, $siteName)
{
    return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <div style="background-color: #007BFF; color: #fff; padding: 20px; text-align: center;">
            <h1>Eduroam Access Information</h1>
        </div>
        <div style="padding: 20px;">
            <p>Dear ' . htmlspecialchars($fullname) . ',</p>
            <p>Your 24-hour guest eduroam account has been created automatically.</p>
            <p>Here are the details to connect to Eduroam:</p>
            <ul>
                <li><strong>Network Name (SSID):</strong> eduroam</li>
                <li><strong>Username:</strong> ' . htmlspecialchars($username) . '</li>
                <li><strong>Password:</strong> ' . htmlspecialchars($password) . '</li>
                <li><strong>Valid Until:</strong> ' . htmlspecialchars($expiresAtDisplay) . '</li>
            </ul>
            <p>Select the "Eduroam" network on your device and sign in with the guest username and password above.</p>
            <p>If you need to reset the password before the account expires, use this link:
                <a href="' . htmlspecialchars($siteBaseUrl) . 'eduroam/forgotpass.php">Reset Password</a></p>
            <p>Sincerely,</p>
            <p>' . htmlspecialchars($siteName) . '</p>
        </div>
    </div>';
}

function repairExistingGuestAccounts(PDO $pdo)
{
    ensureGuestAccountInfrastructure($pdo);

    $targetsStmt = $pdo->query(
        "SELECT DISTINCT u.username, u.fullname, u.email, u.updatedate, g.created_at, g.expires_at
         FROM userinfo u
         LEFT JOIN guest_accounts g ON g.username = u.username
         WHERE u.updateby IN ('auto_request', 'admin_manual') OR g.id IS NOT NULL"
    );
    $targets = $targetsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($targets)) {
        return 0;
    }

    $pdo->beginTransaction();

    try {
        $findPasswordStmt = $pdo->prepare("SELECT value FROM radcheck WHERE username = :username AND attribute = 'Cleartext-Password' ORDER BY id ASC LIMIT 1");
        $deleteExpiryStmt = $pdo->prepare("DELETE FROM radcheck WHERE username = :username AND attribute = 'Expiration'");
        $insertExpiryStmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (:username, 'Expiration', ':=', :expiration)");
        $upsertGuestStmt = $pdo->prepare(
            "INSERT INTO guest_accounts (username, delivery_email, fullname, created_at, expires_at)
             VALUES (:username, :email, :fullname, :created_at, :expires_at)
             ON DUPLICATE KEY UPDATE
                delivery_email = VALUES(delivery_email),
                fullname = VALUES(fullname),
                created_at = VALUES(created_at),
                expires_at = VALUES(expires_at)"
        );

        $repaired = 0;

        foreach ($targets as $target) {
            $findPasswordStmt->execute([':username' => $target['username']]);
            $passwordValue = $findPasswordStmt->fetchColumn();
            $findPasswordStmt->closeCursor();

            if ($passwordValue === false) {
                continue;
            }

            $createdAt = $target['created_at'] ?: $target['updatedate'] ?: date('Y-m-d H:i:s');
            $expiresAtDb = $target['expires_at'] ?: date('Y-m-d H:i:s', strtotime($createdAt . ' +24 hours'));
            $expiresAtRadius = guestAccountExpiresAtRadius($expiresAtDb);

            $upsertGuestStmt->execute([
                ':username' => $target['username'],
                ':email' => $target['email'],
                ':fullname' => $target['fullname'],
                ':created_at' => $createdAt,
                ':expires_at' => $expiresAtDb,
            ]);

            $deleteExpiryStmt->execute([':username' => $target['username']]);
            $insertExpiryStmt->execute([
                ':username' => $target['username'],
                ':expiration' => $expiresAtRadius,
            ]);

            $repaired++;
        }

        $pdo->commit();
        return $repaired;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function purgeExpiredGuestAccounts(PDO $pdo)
{
    ensureGuestAccountInfrastructure($pdo);

    $selectStmt = $pdo->prepare("SELECT username, delivery_email FROM guest_accounts WHERE expires_at <= NOW()");
    $selectStmt->execute();
    $expiredAccounts = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expiredAccounts)) {
        $pdo->prepare("DELETE FROM password_reset WHERE expiration_time <= NOW()")->execute();
        return 0;
    }

    $pdo->beginTransaction();

    try {
        $deleteRadcheck = $pdo->prepare("DELETE FROM radcheck WHERE username = :username");
        $deleteUserinfo = $pdo->prepare("DELETE FROM userinfo WHERE username = :username");
        $deleteResets = $pdo->prepare("DELETE FROM password_reset WHERE email = :email");
        $deleteGuest = $pdo->prepare("DELETE FROM guest_accounts WHERE username = :username");

        foreach ($expiredAccounts as $account) {
            $deleteRadcheck->bindParam(':username', $account['username'], PDO::PARAM_STR);
            $deleteRadcheck->execute();

            $deleteUserinfo->bindParam(':username', $account['username'], PDO::PARAM_STR);
            $deleteUserinfo->execute();

            $deleteResets->bindParam(':email', $account['delivery_email'], PDO::PARAM_STR);
            $deleteResets->execute();

            $deleteGuest->bindParam(':username', $account['username'], PDO::PARAM_STR);
            $deleteGuest->execute();
        }

        $pdo->prepare("DELETE FROM password_reset WHERE expiration_time <= NOW()")->execute();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return count($expiredAccounts);
}
