<?php
session_start();
require_once '../connect.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

// ตรวจสอบ project_id
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$project_id = (int)$_GET['id'];

// ดึงข้อมูลโครงการ
$project_sql = "SELECT project_name FROM projects WHERE project_id = ?";
$stmt = $conn->prepare($project_sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project_result = $stmt->get_result();
$project = $project_result->fetch_assoc();

if (!$project) {
    header('Location: index.php');
    exit;
}

// ดึงข้อมูล milestones
$milestone_sql = "SELECT * FROM project_milestones WHERE project_id = ? ORDER BY due_date ASC";
$stmt = $conn->prepare($milestone_sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$milestones = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเป้าหมายโครงการ - <?php echo htmlspecialchars($project['project_name']); ?></title>
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
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">หน้าหลัก</a></li>
                        <li class="breadcrumb-item active">จัดการเป้าหมายโครงการ: <?php echo htmlspecialchars($project['project_name']); ?></li>
                    </ol>
                </nav>
                <div class="d-flex justify-content-between align-items-center">
                    <h2>จัดการเป้าหมายโครงการ</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
                        <i class="fas fa-plus"></i> เพิ่มเป้าหมายใหม่
                    </button>
                </div>
            </div>
        </div>

        <!-- แสดงรายการเป้าหมาย -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>เป้าหมาย</th>
                        <th>รายละเอียด</th>
                        <th>กำหนดส่ง</th>
                        <th>วันที่เสร็จ</th>
                        <th>สถานะ</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($milestones->num_rows > 0): ?>
                        <?php while ($milestone = $milestones->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($milestone['milestone_name']); ?></td>
                                <td><?php echo htmlspecialchars($milestone['description']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($milestone['due_date'])); ?></td>
                                <td>
                                    <?php echo $milestone['completed_date'] 
                                        ? date('d/m/Y', strtotime($milestone['completed_date']))
                                        : '-'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getMilestoneStatusColor($milestone['status']); ?>">
                                        <?php echo getMilestoneStatusText($milestone['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary"
                                                onclick="editMilestone(<?php echo $milestone['milestone_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($milestone['status'] !== 'completed'): ?>
                                        <button type="button" class="btn btn-sm btn-success"
                                                onclick="completeMilestone(<?php echo $milestone['milestone_id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="deleteMilestone(<?php echo $milestone['milestone_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">ยังไม่มีเป้าหมายสำหรับโครงการนี้</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal เพิ่มเป้าหมายใหม่ -->
    <div class="modal fade" id="addMilestoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">เพิ่มเป้าหมายใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_milestone.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">ชื่อเป้าหมาย</label>
                            <input type="text" class="form-control" name="milestone_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">รายละเอียด</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">กำหนดส่ง</label>
                            <input type="date" class="form-control" name="due_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editMilestone(milestoneId) {
        window.location.href = `edit_milestone.php?id=${milestoneId}`;
    }

    function completeMilestone(milestoneId) {
        if (confirm('ยืนยันการทำเป้าหมายนี้ให้เสร็จสิ้น?')) {
            window.location.href = `complete_milestone.php?id=${milestoneId}`;
        }
    }

    function deleteMilestone(milestoneId) {
        if (confirm('คุณแน่ใจหรือไม่ที่จะลบเป้าหมายนี้?')) {
            window.location.href = `delete_milestone.php?id=${milestoneId}`;
        }
    }
    </script>

    <?php
    function getMilestoneStatusColor($status) {
        switch ($status) {
            case 'completed':
                return 'success';
            case 'overdue':
                return 'danger';
            case 'pending':
                return 'warning';
            default:
                return 'secondary';
        }
    }

    function getMilestoneStatusText($status) {
        switch ($status) {
            case 'completed':
                return 'เสร็จสิ้น';
            case 'overdue':
                return 'เลยกำหนด';
            case 'pending':
                return 'รอดำเนินการ';
            default:
                return 'ไม่ระบุ';
        }
    }
    ?>
</body>
</html>