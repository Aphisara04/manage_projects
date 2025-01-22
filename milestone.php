<?php
session_start();
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
require 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_milestone'])) {
        $project_id = $_POST['project_id'];
        $milestone_name = $_POST['milestone_name'];
        $description = $_POST['description'];
        $due_date = $_POST['due_date'];
        
        $sql = "INSERT INTO project_milestones (project_id, milestone_name, description, due_date, status) 
                VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $project_id, $milestone_name, $description, $due_date);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "เพิ่มเป้าหมายความคืบหน้าสำเร็จ";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการเพิ่มเป้าหมาย";
        }
    }
    
    if (isset($_POST['update_status'])) {
        $milestone_id = $_POST['milestone_id'];
        $status = $_POST['status'];
        $completed_date = $status === 'completed' ? date('Y-m-d') : NULL;
        
        $sql = "UPDATE project_milestones SET status = ?, completed_date = ? WHERE milestone_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $completed_date, $milestone_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "อัปเดตสถานะสำเร็จ";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตสถานะ";
        }
    }
}

// Get user's role and associated projects
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$projects = [];
if ($user_role === 'student') {
    $sql = "SELECT p.* FROM projects p 
            JOIN project_members pm ON p.project_id = pm.project_id
            JOIN students s ON pm.student_id = s.student_id
            WHERE s.user_id = ?";
} elseif ($user_role === 'teacher') {
    $sql = "SELECT p.* FROM projects p 
            JOIN teachers t ON p.advisor_id = t.teacher_id
            WHERE t.user_id = ?";
} else {
    $sql = "SELECT * FROM projects";
}

$stmt = $conn->prepare($sql);
if ($user_role !== 'admin') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามความคืบหน้าโครงงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2>ติดตามความคืบหน้าโครงงาน</h2>
        
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

        <?php foreach ($projects as $project): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($project['project_name']); ?></h3>
                </div>
                <div class="card-body">
                    <!-- Milestone List -->
                    <div class="mb-4">
                        <h4>เป้าหมายความคืบหน้า</h4>
                        <?php
                        $sql = "SELECT * FROM project_milestones WHERE project_id = ? ORDER BY due_date";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $project['project_id']);
                        $stmt->execute();
                        $milestones = $stmt->get_result();
                        ?>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>เป้าหมาย</th>
                                    <th>รายละเอียด</th>
                                    <th>กำหนดส่ง</th>
                                    <th>วันที่เสร็จ</th>
                                    <th>สถานะ</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($milestone = $milestones->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($milestone['milestone_name']); ?></td>
                                        <td><?php echo htmlspecialchars($milestone['description']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($milestone['due_date'])); ?></td>
                                        <td>
                                            <?php 
                                            echo $milestone['completed_date'] 
                                                ? date('d/m/Y', strtotime($milestone['completed_date']))
                                                : '-';
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $milestone['status'] === 'completed' ? 'success' : 
                                                    ($milestone['status'] === 'overdue' ? 'danger' : 'warning');
                                            ?>">
                                                <?php 
                                                echo $milestone['status'] === 'completed' ? 'เสร็จสิ้น' : 
                                                    ($milestone['status'] === 'overdue' ? 'เลยกำหนด' : 'รอดำเนินการ');
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="milestone_id" value="<?php echo $milestone['milestone_id']; ?>">
                                                    <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                        <option value="pending" <?php echo $milestone['status'] === 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                                                        <option value="completed" <?php echo $milestone['status'] === 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                                                        <option value="overdue" <?php echo $milestone['status'] === 'overdue' ? 'selected' : ''; ?>>เลยกำหนด</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add Milestone Form -->
                    <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5>เพิ่มเป้าหมายใหม่</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="project_id" value="<?php echo $project['project_id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="milestone_name" class="form-label">ชื่อเป้าหมาย</label>
                                        <input type="text" class="form-control" id="milestone_name" name="milestone_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">รายละเอียด</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="due_date" class="form-label">กำหนดส่ง</label>
                                        <input type="date" class="form-control" id="due_date" name="due_date" required>
                                    </div>
                                    
                                    <button type="submit" name="add_milestone" class="btn btn-primary">เพิ่มเป้าหมาย</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>