<?php
session_start();

// ลบข้อมูล session ทั้งหมด
session_destroy();

// เปลี่ยนเส้นทางกลับไปหน้า login
header('Location: login.php');
exit();
?>