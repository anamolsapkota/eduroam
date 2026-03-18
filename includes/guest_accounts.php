<?php

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
