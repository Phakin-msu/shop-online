<?php
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>วิธีสั่งซื้อ & ชำระเงิน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h2 class="mb-3">วิธีการสั่งซื้อ & การชำระเงิน</h2>

  <ol class="list-group list-group-numbered">
    <li class="list-group-item">เลือกสินค้าที่ต้องการ แล้วกดปุ่ม <b>ใส่ตะกร้า</b></li>
    <li class="list-group-item">ไปที่หน้า <b>ตะกร้าสินค้า</b> → ตรวจสอบจำนวนสินค้า</li>
    <li class="list-group-item">กด <b>ไปชำระเงิน</b> → กรอกข้อมูลที่อยู่จัดส่ง</li>
    <li class="list-group-item">เลือกวิธีชำระเงิน 
        <ul>
          <li>โอนผ่าน PromptPay / บัญชีธนาคาร</li>
          <li>เก็บเงินปลายทาง (COD)</li>
        </ul>
    </li>
    <li class="list-group-item">ยืนยันคำสั่งซื้อ → อัปโหลดสลิปโอนเงิน (ถ้ามี)</li>
  </ol>

  <div class="mt-4">
    <h5>ตัวอย่าง QR PromptPay</h5>
    <img src="img/qrcode.png" alt="PromptPay" class="img-fluid border rounded" style="max-width:200px">
  </div>
</div>
</body>
</html>
