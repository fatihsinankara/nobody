<?php
/**
 * Yönetici Girişi (Admin Login)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

$error = '';

if (is_admin_logged_in()) {
    header('Location: /admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen tekrar deneyin.';
    } elseif (!check_rate_limit('admin_login', 5, 600)) {
        $error = 'Çok fazla başarısız giriş denemesi yapıldı. Lütfen 10 dakika sonra tekrar deneyin.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Kullanıcı adı ve parola gereklidir.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Zamanlama (Timing) saldırılarına karşı sabit süreli doğrulama
            $dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUU1234567890';
            $passwordValid = false;
            if ($user && !empty($user['password_hash'])) {
                $passwordValid = password_verify($password, $user['password_hash']);
            } else {
                password_verify($password, $dummyHash);
            }

            if ($passwordValid) {
                // Session Fixation saldırılarına karşı oturum kimliğini yenile
                session_regenerate_id(true);

                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_display_name'] = $user['display_name'];
                $_SESSION['admin_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $_SESSION['admin_last_activity'] = time();

                reset_rate_limit('admin_login');

                header('Location: /admin/index.php');
                exit;
            } else {
                record_rate_limit_hit('admin_login');
                $error = 'Hatalı kullanıcı adı veya şifre!';
            }
        }
    }
}

$page_title = 'Yönetici Girişi';
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 380px; margin: 30px auto; background-color: #f6f5ef; border: 2px solid #777; padding: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 18px; margin-bottom: 12px; border-bottom: 1px solid #aaa; padding-bottom: 4px;">
        Yönetici Girişi (Webmaster)
    </h2>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form action="/admin/login.php" method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="username">Kullanıcı Adı:</label>
            <input type="text" id="username" name="username" required autofocus value="<?= isset($_POST['username']) ? e($_POST['username']) : 'nobody' ?>">
        </div>

        <div class="form-group">
            <label for="password">Şifre:</label>
            <input type="password" id="password" name="password" required value="nobody">
        </div>

        <div style="margin-top: 15px;">
            <button type="submit" class="btn" style="width: 100%;">Giriş Yap &raquo;</button>
        </div>

        <div style="margin-top: 15px; font-family: 'Courier New', monospace; font-size: 11px; color: #666; border-top: 1px dotted #ccc; padding-top: 8px;">
            Varsayılan: <strong>nobody</strong> / <strong>nobody</strong>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
