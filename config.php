<?php
// config.php - ไฟล์การตั้งค่าฐานข้อมูล
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "shopdb"; // ← ใช้ชื่อฐานข้อมูลที่สร้างไว้

try {
    // DSN ที่ถูกต้อง
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);

    // ตั้งค่า Error Mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
