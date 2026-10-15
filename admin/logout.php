<?php
/**
 * Yönetici Çıkışı (Logout)
 */
define('IN_ADMIN', true);
require_once __DIR__ . '/../config/init.php';

$token = $_POST['token'] ?? ($_GET['token'] ?? '');
if (!csrf_verify($token)) {
    header('Location: /admin/index.php');
    exit;
}

clear_admin_session();
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header('Location: /admin/login.php');
exit;
