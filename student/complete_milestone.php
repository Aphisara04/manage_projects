<?php
session_start();
require_once '../connect.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

// ตรวจสอบ milestone_id
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$milestone_id = (int)$_GET['id'];

// ดึงข้อมูล milestone และตรวจสอบสิทธิ์
$sql = "SELECT m.*, p.project_id, p.student_id 
        FROM project_milestones m
        JOIN projects p ON m.project_id = p.project_id
        WHERE m.milestone_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $milestone_id);
$stmt->execute();
$result = $stmt->get_result();
$milestone = $result->fetch_assoc();

// ตรวจสอบว่ามีข้อมูลและเป็นของนักศึกษาคนนี้
if (!$milestone || $milestone['student_id'] !== $_SESSION['user_id']) {
    $_SESSION['error'] = 'ไม่พบข้อมูลเป้าหมาย หรือคุณไม่มีสิทธิ์ในการแก้ไข';
    header('Location: index.php');
    exit;
}

// ตรวจสอบว่าเป้าหมายยังไม่เสร็จสิ้น
if ($milestone['status'] === 'completed') {
    $_SESSION['error'] = 'เป้าหมายนี้เสร็จสิ้นแล้ว';
    header('Location: manage_milestones.php?id=' . $milestone['project_id']);
    exit;
}

// อัพเดทสถานะเป้าหมาย
$completed_date = date('Y-m-d'); // วันที่ปัจจุบัน
$update_sql = "UPDATE project_milestones 
               SET status = 'completed', 
                   completed_date = ? 
               WHERE milestone_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $completed_date, $milestone_id);

if ($update_stmt->execute()) {
    // ตรวจสอบว่าทุกเป้าหมายเสร็จสิ้นหรือไม่
    $check_all_sql = "SELECT COUNT(*) as total, 
                             SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                      FROM project_milestones 
                      WHERE project_id = ?";
    $check_stmt = $conn->prepare($check_all_sql);
    $check_stmt->bind_param("i", $milestone['project_id']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();
    
    // ถ้าทุกเป้าหมายเสร็จสิ้น ให้อัพเดทสถานะโครงการเป็น completed
    if ($check_result['total'] > 0 && $check_result['total'] == $check_result['completed']) {
        $update_project_sql = "UPDATE projects 
                              SET status = 'completed' 
                              WHERE project_id = ?";
        $update_project_stmt = $conn->prepare($update_project_sql);
        $update_project_stmt->bind_param("i", $milestone['project_id']);
        $update_project_stmt->execute();
    }

    $_SESSION['success'] = 'อัพเดทสถานะเป้าหมายเป็นเสร็จสิ้นเรียบร้อยแล้ว';
} else {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัพเดทสถานะ: ' . $conn->error;
}

// ปิดการเชื่อมต่อ statement
$stmt->close();
$update_stmt->close();

// กลับไปยังหน้าจัดการเป้าหมาย
header('Location: manage_milestones.php?id=' . $milestone['project_id']);
exit;
?>