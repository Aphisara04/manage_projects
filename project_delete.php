<?php
require 'connect.php';

// ตัวแปรสำหรับเก็บข้อความแจ้งเตือน
$error_message = "";
$success_message = "";

// ตรวจสอบว่ามีการส่ง ID โครงงานมาหรือไม่
if (isset($_GET['id'])) {
    $project_id = $_GET['id'];
    
    // ดึงข้อมูลโครงงานเพื่อแสดงข้อมูลยืนยันการลบ
    $sql = "SELECT p.project_name, t.academic_rank, u.full_name as advisor_name,
            COUNT(pm.member_id) as total_members
            FROM projects p
            JOIN teachers t ON p.advisor_id = t.teacher_id 
            JOIN users u ON t.user_id = u.user_id
            LEFT JOIN project_members pm ON p.project_id = pm.project_id
            WHERE p.project_id = ?
            GROUP BY p.project_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $error_message = "ไม่พบข้อมูลโครงงานที่ต้องการลบ";
    } else {
        $project = $result->fetch_assoc();
        
        // เมื่อมีการยืนยันการลบข้อมูล
        if (isset($_POST['confirm_delete'])) {
            // เริ่ม transaction
            $conn->begin_transaction();
            
            try {
                // ลบข้อมูลสมาชิกโครงงานก่อน (เนื่องจากมี foreign key constraint)
                $sql = "DELETE FROM project_members WHERE project_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                
                // ลบข้อมูลโครงงาน
                $sql = "DELETE FROM projects WHERE project_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                
                $conn->commit();
                $success_message = "ลบข้อมูลโครงงานเรียบร้อยแล้ว";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
} else {
    $error_message = "ไม่ได้ระบุรหัสโครงงานที่ต้องการลบ";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลบข้อมูลโครงงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>ลบข้อมูลโครงงาน</h2>
            <a href="project_add.php" class="btn btn-secondary">กลับไปหน้าจัดการโครงงาน</a>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
            <a href="project_add.php" class="btn btn-primary">กลับไปหน้าจัดการโครงงาน</a>
        
        <?php elseif (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
            <a href="project_add.php" class="btn btn-primary">กลับไปหน้าจัดการโครงงาน</a>
        
        <?php elseif (!isset($_POST['confirm_delete'])): ?>
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">ยืนยันการลบข้อมูลโครงงาน</h5>
                </div>
                <div class="card-body">
                    <p class="fw-bold">คุณกำลังจะลบโครงงานต่อไปนี้:</p>
                    
                    <table class="table">
                        <tr>
                            <th width="30%">ชื่อโครงงาน:</th>
                            <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                        </tr>
                        <tr>
                            <th>อาจารย์ที่ปรึกษา:</th>
                            <td><?php echo htmlspecialchars($project['academic_rank'] . ' ' . $project['advisor_name']); ?></td>
                        </tr>
                        <tr>
                            <th>จำนวนสมาชิก:</th>
                            <td><?php echo $project['total_members']; ?> คน</td>
                        </tr>
                    </table>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i> คำเตือน: การลบข้อมูลโครงงานจะไม่สามารถกู้คืนได้ 
                        และข้อมูลสมาชิกโครงงานทั้งหมดจะถูกลบด้วย
                    </div>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $project_id); ?>">
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="project_add.php" class="btn btn-secondary">ยกเลิก</a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger">ยืนยันการลบข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>