<?php

// Improved Email Sender Function
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL); 
ini_set('display_errors', 1);

// Include config
require_once 'includes/config.php';

/**
 * Send email with improved security and error handling
 * 
 * @param string $to Recipient email address
 * @param string $fullname Recipient full name
 * @param string $subject Email subject
 * @param string $message Email message content (HTML supported)
 * @return array Returns array with 'success' boolean and 'message' or 'error' string
 */
function sendEmail($to, $fullname, $subject, $message) {
    global $mail_hostname, $mail_secure, $mail_port, $mail_username, $mail_password, 
           $admin_email, $site_name, $allowed_domains;
    
    // Configuration with defaults
    $config = [
        'mail_hostname' => $mail_hostname ?? '',
        'mail_secure' => $mail_secure ?? 'tls',
        'mail_port' => $mail_port ?? 587,
        'mail_username' => $mail_username ?? '',
        'mail_password' => $mail_password ?? '',
        'admin_email' => $admin_email ?? '',
        'site_name' => $site_name ?? 'Website',
        'allowed_domains' => $allowed_domains ?? [],
        'max_emails_per_hour' => 10
    ];
    
    // Check for missing required config
    if (empty($config['mail_hostname']) || empty($config['mail_username']) || empty($config['mail_password']) || empty($config['admin_email'])) {
        return ['success' => false, 'error' => 'Email configuration is incomplete'];
    }
    
    // Validate inputs
    $validation_result = validateEmailInputs($to, $fullname, $subject, $message);
    if (!$validation_result['valid']) {
        return ['success' => false, 'error' => $validation_result['error']];
    }
    
    // Validate email domain
    if (!validateEmailDomain($to, $config['allowed_domains'])) {
        return ['success' => false, 'error' => 'Email domain not allowed'];
    }
    
    try {
        return sendSMTPEmail($to, $fullname, $subject, $message, $config);
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to send email: ' . $e->getMessage()];
    }
}

/**
 * Validate all email inputs
 */
function validateEmailInputs($to, $fullname, $subject, $message) {
    // Check if all required fields are present
    if (empty($to) || empty($fullname) || empty($subject) || empty($message)) {
        return ['valid' => false, 'error' => 'All fields are required'];
    }
    
    // Validate email format
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'error' => 'Invalid email format'];
    }
    
    // Check for reasonable length limits
    if (strlen($fullname) > 100) {
        return ['valid' => false, 'error' => 'Full name is too long (max 100 characters)'];
    }
    
    if (strlen($subject) > 200) {
        return ['valid' => false, 'error' => 'Subject is too long (max 200 characters)'];
    }
    
    if (strlen($message) > 10000) {
        return ['valid' => false, 'error' => 'Message is too long (max 10000 characters)'];
    }
    
    // Check for potential header injection
    if (preg_match('/[\r\n]/', $to) || preg_match('/[\r\n]/', $fullname) || preg_match('/[\r\n]/', $subject)) {
        return ['valid' => false, 'error' => 'Invalid characters detected in headers'];
    }
    
    return ['valid' => true];
}

/**
 * Validate email domain against allowed list
 */
function validateEmailDomain($email, $allowed_domains) {
    if (empty($allowed_domains)) {
        return true; // No domain restrictions
    }
    
    $domain = substr(strrchr($email, "@"), 1);
    return in_array($domain, $allowed_domains);
}

/**
 * Send email via SMTP with improved error handling
 */
function sendSMTPEmail($to, $fullname, $subject, $message, $config) {
    // Create socket connection
    $socket = createSMTPConnection($config);
    if (!$socket) {
        throw new Exception("Failed to create SMTP connection");
    }
    
    try {
        // SMTP conversation
        readSMTPResponse($socket, '220');
        sendSMTPCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
        
        // Handle TLS
        if ($config['mail_secure'] === 'tls') {
            sendSMTPCommand($socket, "STARTTLS", '220');
            
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS encryption failed");
            }
            
            sendSMTPCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250');
        }
        
        // Authentication
        authenticateSMTP($socket, $config);
        
        // Send email
        sendSMTPCommand($socket, "MAIL FROM: <{$config['admin_email']}>", '250');
        sendSMTPCommand($socket, "RCPT TO: <$to>", '250');
        sendSMTPCommand($socket, "DATA", '354');
        
        // Send headers and message
        $email_content = buildEmailContent($to, $fullname, $subject, $message, $config);
        sendSMTPCommand($socket, $email_content . "\r\n.", '250');
        
        sendSMTPCommand($socket, "QUIT", '221');
        
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }
}

/**
 * Create SMTP connection
 */
function createSMTPConnection($config) {
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    if ($config['mail_secure'] === 'ssl') {
        $socket = stream_socket_client(
            "ssl://{$config['mail_hostname']}:{$config['mail_port']}",
            $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
        );
    } else {
        $socket = stream_socket_client(
            "tcp://{$config['mail_hostname']}:{$config['mail_port']}",
            $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
        );
    }
    
    if (!$socket) {
        throw new Exception("SMTP connection failed: $errstr ($errno)");
    }
    
    return $socket;
}

/**
 * Send SMTP command and verify response
 */
function sendSMTPCommand($socket, $command, $expected_code) {
    fputs($socket, $command . "\r\n");
    return readSMTPResponse($socket, $expected_code);
}

/**
 * Read and verify SMTP response
 */
function readSMTPResponse($socket, $expected_code) {
    $response = '';
    do {
        $line = fgets($socket, 1024);
        if ($line === false) {
            throw new Exception("Failed to read SMTP response");
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');
    
    if (substr($response, 0, 3) !== $expected_code) {
        throw new Exception("SMTP Error: Expected $expected_code, got $response");
    }
    
    return $response;
}

/**
 * Handle SMTP authentication
 */
function authenticateSMTP($socket, $config) {
    sendSMTPCommand($socket, "AUTH LOGIN", '334');
    sendSMTPCommand($socket, base64_encode($config['mail_username']), '334');
    sendSMTPCommand($socket, base64_encode($config['mail_password']), '235');
}

/**
 * Build email content with proper headers
 */
function buildEmailContent($to, $fullname, $subject, $message, $config) {
    $headers = [];
    $headers[] = "From: {$config['site_name']} <{$config['admin_email']}>";
    $headers[] = "To: " . sanitizeEmailHeader($fullname) . " <$to>";
    $headers[] = "Subject: " . sanitizeEmailHeader($subject);
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";
    $headers[] = "Date: " . date('r');
    $headers[] = "Message-ID: <" . uniqid() . "@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">";
    $headers[] = "X-Mailer: Custom PHP Mailer";
    
    return implode("\r\n", $headers) . "\r\n\r\n" . $message;
}

/**
 * Sanitize email headers to prevent injection
 */
function sanitizeEmailHeader($header) {
    return str_replace(["\r", "\n", "\t"], '', $header);
}

?>
