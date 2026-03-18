<?php
require_once 'includes/config.php';

function setup_admin()
{
    try {
        // Create PDO connection
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );

        // Check if any users exist
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            die("Administrator already exists. Setup cancelled.");
        }

        // Default admin credentials (these would normally come from install form)
        $admin_user = [
            'username' => 'Admin',
            'email' => 'info@supersimpleserver.org',
            'password' => 'Lillylee2006$',
            'role' => 'admin'
        ];

        // Hash the password
        $hashed_password = password_hash($admin_user['password'], PASSWORD_DEFAULT);

        // Prepare and execute the insert
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, status) 
            VALUES (:username, :email, :password, :role, 'active')
        ");

        $stmt->execute([
            'username' => $admin_user['username'],
            'email' => $admin_user['email'],
            'password' => $hashed_password,
            'role' => $admin_user['role']
        ]);

        echo "Administrator account created successfully!\n";
        echo "Username: " . $admin_user['username'] . "\n";
        echo "Password: " . $admin_user['password'] . "\n";
        echo "Please change these credentials immediately after logging in.\n";

    } catch (PDOException $e) {
        die("Setup failed: " . $e->getMessage());
    }
}

// Run the setup
setup_admin();