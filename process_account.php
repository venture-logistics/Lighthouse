<?php
define('INCLUDED_FROM_ANOTHER_SCRIPT', true);
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'create':
        $code        = trim($_POST['code']);
        $name        = trim($_POST['name']);
        $type        = $_POST['type'];
        $description = trim($_POST['description']);
        $vat_rate    = !empty($_POST['vat_rate']) ? (float) $_POST['vat_rate'] : 0;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO chart_of_accounts (code, name, type, description, vat_rate) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, $name, $type, $description, $vat_rate]);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Account '$name' created successfully."];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
        header('Location: chart_of_accounts.php');
        exit;

    case 'update':
        $id          = (int) $_POST['id'];
        $code        = trim($_POST['code']);
        $name        = trim($_POST['name']);
        $type        = $_POST['type'];
        $description = trim($_POST['description']);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $vat_rate    = !empty($_POST['vat_rate']) ? (float) $_POST['vat_rate'] : 0;

        try {
            $stmt = $pdo->prepare("
                UPDATE chart_of_accounts 
                SET code        = ?,
                    name        = ?,
                    type        = ?,
                    description = ?,
                    is_active   = ?,
                    vat_rate    = ?
                WHERE id = ?
            ");
            $stmt->execute([$code, $name, $type, $description, $is_active, $vat_rate, $id]);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => "Account updated successfully."];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
        header('Location: chart_of_accounts.php');
        exit;

    case 'delete':
        $id = (int) $_GET['id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM chart_of_accounts WHERE id = ? AND is_system = 0");
            $stmt->execute([$id]);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Account deleted.'];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
        header('Location: chart_of_accounts.php');
        exit;

    default:
        header('Location: chart_of_accounts.php');
        exit;
}