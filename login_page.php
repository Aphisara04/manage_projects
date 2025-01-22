<?php
// เปิดการแสดงข้อผิดพลาด
ini_set('display_errors', 1);
// เริ่ม session
session_start();
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
require 'connect.php';

// ตรวจสอบว่ามีการส่งข้อมูล POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // เตรียม SQL query ด้วย prepared statement
    $stmt = $conn->prepare("SELECT user_id, username, password, role, full_name FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        // ตรวจสอบรหัสผ่าน
        if (password_verify($password, $row['password'])) {
            // ตั้งค่า session variables
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['full_name'] = $row['full_name'];
            
            // สร้าง session token สำหรับความปลอดภัย
            $_SESSION['token'] = bin2hex(random_bytes(32));
            
            // เปลี่ยนเส้นทางตาม role
            $redirect_page = 'index.php';
            switch($row['role']) {
                case 'admin':
                    $redirect_page = 'admin/index.php';
                    break;
                case 'teacher':
                    $redirect_page = 'index.php';
                    break;
                case 'student':
                    $redirect_page = 'student/index.php';
                    break;
            }
            
            echo "<script>
                alert('เข้าสู่ระบบสำเร็จ ยินดีต้อนรับคุณ " . htmlspecialchars($row['full_name']) . "');
                window.location.href = '$redirect_page';
            </script>";
            exit;
        }
    }
    
    // กรณีล็อกอินไม่สำเร็จ
    echo "<script>
        alert('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        window.location.href = 'login.php';
    </script>";
}

// ปิดการเชื่อมต่อ
$conn->close();
?>