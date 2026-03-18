<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

// Get and clean input
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Basic validation
if (empty($username) || empty($password)) {
    $_SESSION['message'] = 'Please fill in all fields';
    $_SESSION['message_type'] = 'danger';
    header('Location: login.php');
    exit();
}

try {
    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    // Prepare and execute query
    $stmt = $pdo->prepare("
        SELECT id, username, password, role, status 
        FROM users 
        WHERE username = :username
        LIMIT 1
    ");

    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify user exists and password is correct
    if ($user && password_verify($password, $user['password'])) {
        // Check if account is active
        if ($user['status'] !== 'active') {
            $_SESSION['message'] = 'This account is not active. Please contact an administrator.';
            $_SESSION['message_type'] = 'warning';
            header('Location: login.php');
            exit();
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Update last login timestamp
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_login = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $user['id']]);

        // Handle "Remember Me"
        if ($remember) {
            // Generate a secure token
            $token = bin2hex(random_bytes(32));

            // Store token in database
            $stmt = $pdo->prepare("
                UPDATE users 
                SET remember_token = :token 
                WHERE id = :id
            ");
            $stmt->execute([
                'token' => $token,
                'id' => $user['id']
            ]);

            // Set cookie for 30 days
            setcookie(
                'remember_token',
                $token,
                time() + (30 * 24 * 60 * 60),
                '/',
                '',  // domain
                true, // secure
                true  // httponly
            );
        }

        // Log successful login
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, action, ip_address)
            VALUES (:user_id, 'login', :ip)
        ");
        $stmt->execute([
            'user_id' => $user['id'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);

        // Redirect to dashboard
        header('Location: dashboard.php');
        exit();

    } else {
        // Log failed login attempt
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (username, action, ip_address)
            VALUES (:username, 'failed_login', :ip)
        ");
        $stmt->execute([
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);

        $_SESSION['message'] = 'Invalid username or password';
        $_SESSION['message_type'] = 'danger';
        header('Location: login.php');
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['message'] = 'System error. Please try again later.';
    $_SESSION['message_type'] = 'danger';

    if (DEBUG_MODE) {
        $_SESSION['message'] .= ' Error: ' . $e->getMessage();
    }

    header('Location: login.php');
    exit();
}