<?php
/**
 * Yönetici Çıkışı (Logout)
 */
define('IN_ADMIN', true);
require_once __DIR__ . '/../config/init.php';

unset($_SESSION['admin_user_id'], $_SESSION['admin_username'], $_SESSION['admin_display_name']);
session_destroy();

header('Location: login.php');
exit;
