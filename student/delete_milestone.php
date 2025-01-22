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
    $_SESSION['error'] = 'ไม่พบข้อมูลเป้าหมาย หรือคุณไม่มีสิทธิ์ในการลบ';
    header('Location: index.php');
    exit;
}

// เริ่ม transaction
$conn->begin_transaction();

try {
    // ลบเป้าหมาย
    $delete_sql = "DELETE FROM project_milestones WHERE milestone_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $milestone_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('ไม่สามารถลบเป้าหมายได้');
    }

    // ตรวจสอบสถานะเป้าหมายที่เหลือ
    $check_sql = "SELECT COUNT(*) as total,
                         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                  FROM project_milestones 
                  WHERE project_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $milestone['project_id']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();

    // อัพเดทสถานะโครงการตามเป้าหมายที่เหลือ
    $project_status = 'in_progress';
    if ($check_result['total'] == 0) {
        $project_status = 'planning';
    } elseif ($check_result['total'] > 0 && $check_result['total'] == $check_result['completed']) {
        $project_status = 'completed';
    }

    $update_project_sql = "UPDATE projects 
                          SET status = ? 
                          WHERE project_id = ?";
    $update_project_stmt = $conn->prepare($update_project_sql);
    $update_project_stmt->bind_param("si", $project_status, $milestone['project_id']);
    
    if (!$update_project_stmt->execute()) {
        throw new Exception('ไม่สามารถอัพเดทสถานะโครงการได้');
    }

    // ยืนยัน transaction
    $conn->commit();
    $_SESSION['success'] = 'ลบเป้าหมายเรียบร้อยแล้ว';

} catch (Exception $e) {
    // ถ้าเกิดข้อผิดพลาด ให้ rollback การเปลี่ยนแปลงทั้งหมด
    $conn->rollback();
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบเป้าหมาย: ' . $e->getMessage();
}

// ปิดการเชื่อมต่อ statement
$stmt->close();
if (isset($delete_stmt)) $delete_stmt->close();
if (isset($check_stmt)) $check_stmt->close();
if (isset($update_project_stmt)) $update_project_stmt->close();

// กลับไปยังหน้าจัดการเป้าหมาย
header('Location: manage_milestones.php?id=' . $milestone['project_id']);
exit;
?>