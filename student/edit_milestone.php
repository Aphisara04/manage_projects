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
$sql = "SELECT m.*, p.project_id, p.project_name, p.student_id 
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

// ประมวลผลการแก้ไขข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['milestone_name']) || empty($_POST['due_date'])) {
        $_SESSION['error'] = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน';
    } else {
        // เตรียมข้อมูล
        $milestone_name = trim($_POST['milestone_name']);
        $description = trim($_POST['description'] ?? '');
        $due_date = $_POST['due_date'];
        $status = $_POST['status'];
        $completed_date = ($status === 'completed') ? date('Y-m-d') : null;

        // อัพเดทข้อมูล
        $update_sql = "UPDATE project_milestones 
                      SET milestone_name = ?, description = ?, due_date = ?, 
                          status = ?, completed_date = ?
                      WHERE milestone_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssssi", 
            $milestone_name, $description, $due_date, 
            $status, $completed_date, $milestone_id
        );

        if ($update_stmt->execute()) {
            $_SESSION['success'] = 'อัพเดทข้อมูลเป้าหมายเรียบร้อยแล้ว';
            header('Location: manage_milestones.php?id=' . $milestone['project_id']);
            exit;
        } else {
            $_SESSION['error'] = 'เกิดข้อผิดพลาดในการอัพเดทข้อมูล: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขเป้าหมายโครงการ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">ระบบจัดการโครงงาน</a>
            <div class="navbar-text text-white">
                ยินดีต้อนรับ: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </div>
            <a href="../logout.php" class="btn btn-outline-light">ออกจากระบบ</a>
        </div>
    </nav>

    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
                <li class="breadcrumb-item">
                    <a href="manage_milestones.php?id=<?php echo $milestone['project_id']; ?>">
                        จัดการเป้าหมายโครงการ: <?php echo htmlspecialchars($milestone['project_name']); ?>
                    </a>
                </li>
                <li class="breadcrumb-item active">แก้ไขเป้าหมาย</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">แก้ไขเป้าหมาย</h4>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form action="edit_milestone.php?id=<?php echo $milestone_id; ?>" method="POST">
                    <div class="mb-3">
                        <label class="form-label">ชื่อเป้าหมาย</label>
                        <input type="text" class="form-control" name="milestone_name" 
                               value="<?php echo htmlspecialchars($milestone['milestone_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">รายละเอียด</label>
                        <textarea class="form-control" name="description" rows="3"
                        ><?php echo htmlspecialchars($milestone['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">กำหนดส่ง</label>
                        <input type="date" class="form-control" name="due_date" 
                               value="<?php echo $milestone['due_date']; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">สถานะ</label>
                        <select class="form-select" name="status">
                            <option value="pending" <?php echo $milestone['status'] === 'pending' ? 'selected' : ''; ?>>
                                รอดำเนินการ
                            </option>
                            <option value="completed" <?php echo $milestone['status'] === 'completed' ? 'selected' : ''; ?>>
                                เสร็จสิ้น
                            </option>
                            <option value="overdue" <?php echo $milestone['status'] === 'overdue' ? 'selected' : ''; ?>>
                                เลยกำหนด
                            </option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="manage_milestones.php?id=<?php echo $milestone['project_id']; ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> ยกเลิก
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>