<?php
require 'connect.php';

if (isset($_GET['id'])) {
    $teacher_id = $_GET['id'];
    
    // เริ่ม transaction
    $conn->begin_transaction();
    
    try {
        // ดึง user_id จากตาราง teachers
        $sql = "SELECT user_id FROM teachers WHERE teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'];
        
        // ลบข้อมูลจากตาราง teachers
        $sql = "DELETE FROM teachers WHERE teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        
        // ลบข้อมูลจากตาราง users
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        $conn->commit();
        
        header("Location: teacher_add.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>