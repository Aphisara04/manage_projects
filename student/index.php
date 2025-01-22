<?php
session_start();
require_once '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

// ดึงข้อมูลโครงงานพร้อมจำนวน milestones และความคืบหน้า
$sql = "SELECT p.*, t.full_name as advisor_name,
        COUNT(m.milestone_id) as total_milestones,
        SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) as completed_milestones
        FROM projects p
        LEFT JOIN users t ON p.advisor_id = t.user_id
        LEFT JOIN project_milestones m ON p.project_id = m.project_id
        GROUP BY p.project_id
        ORDER BY p.start_date DESC";
$result = $conn->query($sql);

if (!$result) {
    die('เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการโครงงาน - หน้าหลักนักศึกษา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">ระบบจัดการโครงงาน</a>
            <div class="navbar-text text-white">
                ยินดีต้อนรับ: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </div>
            <a href="../logout.php" class="btn btn-outline-light">ออกจากระบบ</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2>จัดการโครงงานของฉัน</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                    <i class="fas fa-plus"></i> เพิ่มโครงงานใหม่
                </button>
            </div>
        </div>

        <!-- แสดงรายการโครงงาน -->
        <div class="row">
            <?php while ($project = $result->fetch_assoc()): ?>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?php echo htmlspecialchars($project['project_name']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            <strong>คำอธิบาย:</strong> <?php echo nl2br(htmlspecialchars($project['description'])); ?><br>
                            <strong>อาจารย์ที่ปรึกษา:</strong> <?php echo htmlspecialchars($project['advisor_name'] ?? 'ยังไม่ได้กำหนด'); ?><br>
                            <strong>ระยะเวลาโครงงาน:</strong> <?php echo date('d/m/Y', strtotime($project['start_date'])); ?> - 
                            <?php echo date('d/m/Y', strtotime($project['end_date'])); ?><br>
                            <strong>สถานะ:</strong> 
                            <span class="badge bg-<?php echo getStatusColor($project['status']); ?>">
                                <?php echo getStatusText($project['status']); ?>
                            </span>
                        </p>
                        
                        <!-- แสดงความคืบหน้าของ Milestones -->
                        <div class="mb-3">
                            <strong>ความคืบหน้า:</strong>
                            <?php
                            $total = $project['total_milestones'] ?: 0;
                            $completed = $project['completed_milestones'] ?: 0;
                            $progress = $total > 0 ? ($completed / $total) * 100 : 0;
                            ?>
                            <div class="progress mt-2">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $progress; ?>%"
                                     aria-valuenow="<?php echo $progress; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo number_format($progress, 1); ?>%
                                </div>
                            </div>
                            <small class="text-muted">
                                (<?php echo $completed; ?>/<?php echo $total; ?> เป้าหมาย)
                            </small>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn btn-primary btn-sm" 
                                    onclick="editProject(<?php echo $project['project_id']; ?>)">
                                <i class="fas fa-edit"></i> แก้ไข
                            </button>
                            <button type="button" class="btn btn-info btn-sm" 
                                    onclick="uploadDocument(<?php echo $project['project_id']; ?>)">
                                <i class="fas fa-upload"></i> จัดการเอกสาร
                            </button>
                            <button type="button" class="btn btn-success btn-sm" 
                                    onclick="manageMilestones(<?php echo $project['project_id']; ?>)">
                                <i class="fas fa-tasks"></i> จัดการเป้าหมาย
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" 
                                    onclick="deleteProject(<?php echo $project['project_id']; ?>)">
                                <i class="fas fa-trash"></i> ลบ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal เพิ่มโครงงานใหม่ (ไม่มีการเปลี่ยนแปลง) -->
    <!-- ... คงเดิม ... -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editProject(projectId) {
        window.location.href = `edit_project.php?id=${projectId}`;
    }

    function uploadDocument(projectId) {
        window.location.href = `manage_documents.php?id=${projectId}`;
    }

    function manageMilestones(projectId) {
        window.location.href = `manage_milestones.php?id=${projectId}`;
    }

    function deleteProject(projectId) {
        if (confirm('คุณแน่ใจหรือไม่ที่จะลบโครงงานนี้?')) {
            window.location.href = `delete_project.php?id=${projectId}`;
        }
    }
    </script>

    <?php
    // ฟังก์ชันสำหรับแสดงสี status (คงเดิม)
    function getStatusColor($status) {
        switch ($status) {
            case 'planning':
                return 'secondary';
            case 'in_progress':
                return 'primary';
            case 'completed':
                return 'success';
            case 'suspended':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    // ฟังก์ชันสำหรับแสดงข้อความ status (คงเดิม)
    function getStatusText($status) {
        switch ($status) {
            case 'planning':
                return 'วางแผน';
            case 'in_progress':
                return 'กำลังดำเนินการ';
            case 'completed':
                return 'เสร็จสมบูรณ์';
            case 'suspended':
                return 'ระงับชั่วคราว';
            default:
                return 'ไม่ระบุ';
        }
    }
    ?>
</body>
</html>