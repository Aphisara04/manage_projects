<?php
session_start();
require_once '../connect.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

// ตรวจสอบว่ามีการส่งข้อมูลผ่าน POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($_POST['project_id']) || empty($_POST['milestone_name']) || empty($_POST['due_date'])) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
        header('Location: manage_milestones.php?id=' . $_POST['project_id']);
        exit;
    }

    // ทำความสะอาดและเตรียมข้อมูล
    $project_id = (int)$_POST['project_id'];
    $milestone_name = trim($_POST['milestone_name']);
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'];

    // ตรวจสอบว่าโครงการนี้เป็นของนักศึกษาคนนี้จริงๆ
    $check_sql = "SELECT project_id FROM projects WHERE project_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $project_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        $_SESSION['error'] = 'ไม่พบข้อมูลโครงการ หรือคุณไม่มีสิทธิ์ในการจัดการโครงการนี้';
        header('Location: index.php');
        exit;
    }

    // เพิ่มข้อมูลลงในฐานข้อมูล
    $sql = "INSERT INTO project_milestones (project_id, milestone_name, description, due_date, status) 
            VALUES (?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $project_id, $milestone_name, $description, $due_date);

    if ($stmt->execute()) {
        // บันทึกสำเร็จ
        $_SESSION['success'] = 'เพิ่มเป้าหมายใหม่เรียบร้อยแล้ว';
    } else {
        // เกิดข้อผิดพลาด
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $conn->error;
    }

    // ปิดการเชื่อมต่อ statement
    $stmt->close();
    
    // กลับไปยังหน้าจัดการเป้าหมาย
    header('Location: manage_milestones.php?id=' . $project_id);
    exit;
} else {
    // ถ้าเข้าถึงไฟล์โดยตรงโดยไม่ผ่านฟอร์ม ให้กลับไปหน้าหลัก
    header('Location: index.php');
    exit;
}
?>