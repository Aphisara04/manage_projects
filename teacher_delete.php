<?php
require 'connect.php';

// ตัวแปรสำหรับเก็บข้อความแจ้งเตือน
$error_message = "";
$success_message = "";

// ตรวจสอบว่ามีการส่ง ID อาจารย์มาหรือไม่
if (isset($_GET['id'])) {
    $teacher_id = $_GET['id'];
    
    // ดึงข้อมูลอาจารย์เพื่อแสดงข้อมูลยืนยันการลบ
    $sql = "SELECT t.teacher_id, t.academic_rank, u.user_id, u.full_name, u.email, u.department 
            FROM teachers t
            JOIN users u ON t.user_id = u.user_id
            WHERE t.teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $error_message = "ไม่พบข้อมูลอาจารย์ที่ต้องการลบ";
    } else {
        $teacher = $result->fetch_assoc();
        $user_id = $teacher['user_id'];
        
        // ตรวจสอบว่าอาจารย์เป็นที่ปรึกษาโครงงานอยู่หรือไม่
        $sql = "SELECT COUNT(*) as project_count FROM projects WHERE advisor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project_count = $result->fetch_assoc()['project_count'];
        
        // เมื่อมีการยืนยันการลบข้อมูล
        if (isset($_POST['confirm_delete'])) {
            // ตรวจสอบอีกครั้งว่าอาจารย์ไม่ได้เป็นที่ปรึกษาโครงงานอยู่
            if ($project_count > 0) {
                $error_message = "ไม่สามารถลบข้อมูลอาจารย์ได้ เนื่องจากเป็นที่ปรึกษาโครงงานอยู่ " . $project_count . " โครงงาน";
            } else {
                // เริ่ม transaction
                $conn->begin_transaction();
                
                try {
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
                    $success_message = "ลบข้อมูลอาจารย์เรียบร้อยแล้ว";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
                }
            }
        }
    }
} else {
    $error_message = "ไม่ได้ระบุรหัสอาจารย์ที่ต้องการลบ";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลบข้อมูลอาจารย์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>ลบข้อมูลอาจารย์</h2>
            <a href="teacher_add.php" class="btn btn-secondary">กลับไปหน้าจัดการอาจารย์</a>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
            <a href="teacher_add.php" class="btn btn-primary">กลับไปหน้าจัดการอาจารย์</a>
        
        <?php elseif (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
            <a href="teacher_add.php" class="btn btn-primary">กลับไปหน้าจัดการอาจารย์</a>
        
        <?php elseif (!isset($_POST['confirm_delete'])): ?>
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">ยืนยันการลบข้อมูลอาจารย์</h5>
                </div>
                <div class="card-body">
                    <p class="fw-bold">คุณกำลังจะลบข้อมูลอาจารย์ต่อไปนี้:</p>
                    
                    <table class="table">
                        <tr>
                            <th width="30%">ชื่อ-นามสกุล:</th>
                            <td><?php echo htmlspecialchars($teacher['academic_rank'] . ' ' . $teacher['full_name']); ?></td>
                        </tr>
                        <tr>
                            <th>อีเมล:</th>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                        </tr>
                        <tr>
                            <th>ภาควิชา:</th>
                            <td><?php echo htmlspecialchars($teacher['department']); ?></td>
                        </tr>
                    </table>
                    
                    <?php if ($project_count > 0): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> ไม่สามารถลบข้อมูลอาจารย์ได้ เนื่องจากเป็นที่ปรึกษาโครงงานอยู่ <?php echo $project_count; ?> โครงงาน
                            <p class="mt-2 mb-0">กรุณาเปลี่ยนอาจารย์ที่ปรึกษาในโครงงานเหล่านั้นก่อน</p>
                        </div>
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="teacher_add.php" class="btn btn-primary">กลับไปหน้าจัดการอาจารย์</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i> คำเตือน: การลบข้อมูลอาจารย์จะไม่สามารถกู้คืนได้
                        </div>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $teacher_id); ?>">
                            <div class="mt-4 d-flex justify-content-between">
                                <a href="teacher_add.php" class="btn btn-secondary">ยกเลิก</a>
                                <button type="submit" name="confirm_delete" class="btn btn-danger">ยืนยันการลบข้อมูล</button>
                            </div>
                        </form>
                    <?php endif; ?>
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