<?php
session_start();
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
require 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle project creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    $project_name = $_POST['project_name'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $advisor_id = $_POST['advisor_id'];
    
    $sql = "INSERT INTO projects (project_name, description, start_date, end_date, status, advisor_id) 
            VALUES (?, ?, ?, ?, 'planning', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $project_name, $description, $start_date, $end_date, $advisor_id);
    
    if ($stmt->execute()) {
        $project_id = $stmt->insert_id;
        
        // Add student as project member if current user is a student
        if ($_SESSION['role'] === 'student') {
            $sql = "SELECT student_id FROM students WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($student = $result->fetch_assoc()) {
                $sql = "INSERT INTO project_members (project_id, student_id, role_in_project) VALUES (?, ?, 'member')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $project_id, $student['student_id']);
                $stmt->execute();
            }
        }
        
        $_SESSION['success'] = "สร้างโครงงานสำเร็จ";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการสร้างโครงงาน";
    }
}

// Get list of teachers for advisor selection
$teachers = [];
$sql = "SELECT t.teacher_id, u.full_name, t.academic_rank 
        FROM teachers t 
        JOIN users u ON t.user_id = u.user_id";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}

// Get projects based on user role
$projects = [];
if ($_SESSION['role'] === 'student') {
    $sql = "SELECT DISTINCT p.*, 
            t.academic_rank,
            u_teacher.full_name as advisor_name,
            GROUP_CONCAT(DISTINCT s.student_code) as student_codes,
            GROUP_CONCAT(DISTINCT u_student.full_name) as student_names
            FROM projects p
            LEFT JOIN teachers t ON p.advisor_id = t.teacher_id
            LEFT JOIN users u_teacher ON t.user_id = u_teacher.user_id
            LEFT JOIN project_members pm ON p.project_id = pm.project_id
            LEFT JOIN students s ON pm.student_id = s.student_id
            LEFT JOIN users u_student ON s.user_id = u_student.user_id
            WHERE EXISTS (
                SELECT 1 FROM project_members pm2 
                JOIN students s2 ON pm2.student_id = s2.student_id 
                WHERE pm2.project_id = p.project_id 
                AND s2.user_id = ?
            )
            GROUP BY p.project_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
} elseif ($_SESSION['role'] === 'teacher') {
    $sql = "SELECT DISTINCT p.*, 
            t.academic_rank,
            u_teacher.full_name as advisor_name,
            GROUP_CONCAT(DISTINCT s.student_code) as student_codes,
            GROUP_CONCAT(DISTINCT u_student.full_name) as student_names
            FROM projects p
            LEFT JOIN teachers t ON p.advisor_id = t.teacher_id
            LEFT JOIN users u_teacher ON t.user_id = u_teacher.user_id
            LEFT JOIN project_members pm ON p.project_id = pm.project_id
            LEFT JOIN students s ON pm.student_id = s.student_id
            LEFT JOIN users u_student ON s.user_id = u_student.user_id
            WHERE t.user_id = ?
            GROUP BY p.project_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
} else {
    // For admin, show all projects
    $sql = "SELECT DISTINCT p.*, 
            t.academic_rank,
            u_teacher.full_name as advisor_name,
            GROUP_CONCAT(DISTINCT s.student_code) as student_codes,
            GROUP_CONCAT(DISTINCT u_student.full_name) as student_names
            FROM projects p
            LEFT JOIN teachers t ON p.advisor_id = t.teacher_id
            LEFT JOIN users u_teacher ON t.user_id = u_teacher.user_id
            LEFT JOIN project_members pm ON p.project_id = pm.project_id
            LEFT JOIN students s ON pm.student_id = s.student_id
            LEFT JOIN users u_student ON s.user_id = u_student.user_id
            GROUP BY p.project_id";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

// Debug: Print the query and number of results
echo "<!-- Number of projects found: " . count($projects) . " -->";
if (count($projects) === 0) {
    echo '<div class="alert alert-info">ไม่พบข้อมูลโครงงาน</div>';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการโครงงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>จัดการโครงงาน</h2>
            <?php if ($_SESSION['role'] === 'student'): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                    <i class="bi bi-plus-circle"></i> สร้างโครงงานใหม่
                </button>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Projects List -->
        <div class="row">
            <?php foreach ($projects as $project): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($project['project_name']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                            
                            <div class="mb-3">
                                <strong>อาจารย์ที่ปรึกษา:</strong>
                                <?php echo htmlspecialchars($project['academic_rank'] . ' ' . $project['advisor_name']); ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>นักศึกษา:</strong>
                                <?php 
                                $student_names = explode(',', $project['student_names']);
                                $student_codes = explode(',', $project['student_codes']);
                                for ($i = 0; $i < count($student_names); $i++) {
                                    echo '<br>' . htmlspecialchars($student_names[$i]) . 
                                         ' (' . htmlspecialchars($student_codes[$i]) . ')';
                                }
                                ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>ระยะเวลาดำเนินการ:</strong><br>
                                <?php 
                                echo date('d/m/Y', strtotime($project['start_date'])) . 
                                     ' - ' . 
                                     date('d/m/Y', strtotime($project['end_date']));
                                ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>สถานะ:</strong>
                                <span class="badge bg-<?php 
                                    echo $project['status'] === 'completed' ? 'success' : 
                                        ($project['status'] === 'suspended' ? 'danger' : 
                                        ($project['status'] === 'in_progress' ? 'primary' : 'warning'));
                                ?>">
                                    <?php 
                                    echo $project['status'] === 'completed' ? 'เสร็จสิ้น' : 
                                        ($project['status'] === 'suspended' ? 'ระงับ' : 
                                        ($project['status'] === 'in_progress' ? 'กำลังดำเนินการ' : 'วางแผน'));
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <a href="milestone.php?project_id=<?php echo $project['project_id']; ?>" class="btn btn-info btn-sm">
                                <i class="bi bi-clock-history"></i> ติดตามความคืบหน้า
                            </a>
                            <?php if ($_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'admin'): ?>
                                <a href="evaluations.php?project_id=<?php echo $project['project_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-check-square"></i> ประเมินผล
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create Project Modal -->
    <div class="modal fade" id="createProjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">สร้างโครงงานใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="project_name" class="form-label">ชื่อโครงงาน</label>
                            <input type="text" class="form-control" id="project_name" name="project_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">รายละเอียด</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="advisor_id" class="form-label">อาจารย์ที่ปรึกษา</label>
                            <select class="form-select" id="advisor_id" name="advisor_id" required>
                                <option value="">เลือกอาจารย์ที่ปรึกษา</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['teacher_id']; ?>">
                                        <?php echo htmlspecialchars($teacher['academic_rank'] . ' ' . $teacher['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">วันที่เริ่มต้น</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">วันที่สิ้นสุด</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="create_project" class="btn btn-primary">สร้างโครงงาน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>