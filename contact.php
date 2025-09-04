<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = htmlspecialchars($_POST['name']);
    $email   = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);

   
    $log = "contact_messages.txt";
    $data = date("Y-m-d H:i:s") . " | $name | $email | $message\n";
    file_put_contents($log, $data, FILE_APPEND);

    $success = "ส่งข้อความเรียบร้อยแล้ว ขอบคุณที่ติดต่อเรา!";
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ติดต่อเรา</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h2 class="mb-3">ติดต่อเรา</h2>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>

  <form method="post" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label for="name" class="form-label">ชื่อ-นามสกุล</label>
      <input type="text" class="form-control" id="name" name="name" required>
    </div>

    <div class="mb-3">
      <label for="email" class="form-label">อีเมล</label>
      <input type="email" class="form-control" id="email" name="email" required>
    </div>

    <div class="mb-3">
      <label for="message" class="form-label">ข้อความ</label>
      <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
    </div>

    <button type="submit" class="btn btn-primary">ส่งข้อความ</button>
  </form>

  <div class="mt-5">
    <h4>ข้อมูลการติดต่อ</h4>
    <p><b>ที่อยู่:</b> 123 ถนนหลัก เขตเมือง จังหวัดกรุงเทพ</p>
    <p><b>โทรศัพท์:</b> 081-234-5678</p>
    <p><b>อีเมล:</b> support@example.com</p>
  </div>
</div>
</body>
</html>
