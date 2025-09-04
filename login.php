<?php
session_start();
require_once 'config.php'; // ต้องมี $pdo ที่ชี้ไปยัง shopdb (ดูไฟล์ config.php ที่แก้ไว้ก่อนหน้า)

$error_message = '';


header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

function str_starts_with_compat($haystack, $needle) {
    return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if ($email === '' || $password === '') {
        $error_message = 'กรุณากรอกอีเมลและรหัสผ่าน'; //email or password (admin@gmail.com,123456)
    } else {
        try {
            // ดึงผู้ใช้
            $stmt = $pdo->prepare("SELECT id, email, password, name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $validPassword = false;
            if ($user) {
                $hashed = (string)$user['password'];

               
                if ($hashed !== '' && str_starts_with_compat($hashed, '$2y$')) {
                    $validPassword = password_verify($password, $hashed);

                    
                    if ($validPassword && password_needs_rehash($hashed, PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $up->execute([$newHash, (int)$user['id']]);
                    }
                } else {
                   
                    $validPassword = ($password === $hashed);
                    if ($validPassword) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $up->execute([$newHash, (int)$user['id']]);
                    }
                }
            }

            if ($user && $validPassword) {
                // ปิด session fixation
                session_regenerate_id(true);

                // เก็บ session ที่จำเป็น
                $_SESSION['user_id']    = (int)$user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = isset($user['name']) ? $user['name'] : '';

                $_SESSION['logged_in']  = true;

                // บันทึกเวลาล็อกอินล่าสุด
                $upd = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                $upd->execute([(int)$user['id']]);

                header('Location: index.php');
                exit;
            } else {
                $error_message = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (PDOException $e) {
            // เก็บ log ภายในถ้าต้องการ แล้วค่อยโชว์ข้อความรวมๆ ให้ผู้ใช้
            $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <style>
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0; padding: 0; min-height: 100vh;
            display: flex; justify-content: center; align-items: center;
        }
        .login-container {
            background: #fff; padding: 2rem; border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,.12);
            width: 100%; max-width: 400px;
        }
        .login-header { text-align: center; margin-bottom: 1.25rem; }
        .login-header h1 { color:#222; margin-bottom:.25rem; font-size:1.75rem; }
        .login-header p { color:#666; margin:0; font-size:.95rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display:block; margin-bottom:.5rem; color:#333; font-weight:600; }
        .form-group input {
            width:100%; padding:.75rem; border:2px solid #e7e9ef; border-radius:8px;
            font-size:1rem; transition:border-color .2s ease; box-sizing:border-box;
        }
        .form-group input:focus { outline:none; border-color:#667eea; }
        .error-message {
            background:#fee; color:#b00020; padding:.75rem; border-radius:8px;
            margin-bottom:1rem; border-left:4px solid #b00020; font-size:.95rem;
        }
        .login-btn {
            width:100%; padding:.8rem 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color:#fff; border:none; border-radius:8px;
            font-size:1rem; font-weight:700; cursor:pointer; transition:transform .15s ease, filter .15s ease;
        }
        .login-btn:hover { transform: translateY(-1px); filter: brightness(1.03); }
        .login-btn:active { transform: translateY(0); }
        .forgot-password { text-align:center; margin-top:1rem; }
        .forgot-password a { color:#667eea; text-decoration:none; font-size:.9rem; }
        .forgot-password a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h1>เข้าสู่ระบบ</h1>
        <p>กรุณาใส่อีเมลและรหัสผ่านของคุณ</p>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="error-message"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">อีเมล:</label>
            <input type="email" id="email" name="email" required autocomplete="username"
                   placeholder="กรุณาใส่อีเมลของคุณ"
                   value="<?= isset($_POST['email']) ? htmlspecialchars((string)$_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>">
        </div>

        <div class="form-group">
            <label for="password">รหัสผ่าน:</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   placeholder="กรุณาใส่รหัสผ่านของคุณ">
        </div>

        <button type="submit" class="login-btn">เข้าสู่ระบบ</button>
    </form>

    <div class="forgot-password">
        <a href="forgot-password.php">ลืมรหัสผ่าน?</a>
    </div>
</div>
</body>
</html> 