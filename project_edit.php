<?php
require 'connect.php';

// ตัวแปรสำหรับเก็บข้อความแจ้งเตือน
$error_message = "";

// ดึงข้อมูลโครงงานที่ต้องการแก้ไข
if (isset($_GET['id'])) {
    $project_id = $_GET['id'];
    
    // ดึงข้อมูลโครงงาน
    $sql = "SELECT * FROM projects WHERE project_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $project = $result->fetch_assoc();
        
        // ดึงข้อมูลสมาชิกโครงงาน
        $sql_members = "SELECT pm.student_id FROM project_members pm WHERE pm.project_id = ?";
        $stmt_members = $conn->prepare($sql_members);
        $stmt_members->bind_param("i", $project_id);
        $stmt_members->execute();
        $result_members = $stmt_members->get_result();
        
        $current_members = [];
        while ($member = $result_members->fetch_assoc()) {
            $current_members[] = $member['student_id'];
        }
    } else {
        $error_message = "ไม่พบข้อมูลโครงงานที่ต้องการแก้ไข";
    }
} else {
    $error_message = "ไม่ได้ระบุรหัสโครงงานที่ต้องการแก้ไข";
}

// ดึงรายชื่ออาจารย์ที่ปรึกษา
$sql_advisors = "SELECT t.teacher_id, u.full_name, t.academic_rank 
                FROM teachers t 
                JOIN users u ON t.user_id = u.user_id 
                ORDER BY u.full_name";
$result_advisors = $conn->query($sql_advisors);

// ดึงรายชื่อนักศึกษาทั้งหมด (สำหรับการแก้ไข ต้องแสดงทั้งนักศึกษาที่มีโครงงานแล้วและยังไม่มี)
$sql_students = "SELECT s.student_id, u.full_name, s.student_code 
                FROM students s 
                JOIN users u ON s.user_id = u.user_id 
                ORDER BY u.full_name";
$result_students = $conn->query($sql_students);

// เมื่อมีการส่งฟอร์มแก้ไข
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_project'])) {
    $project_id = $_POST['project_id'];
    $project_name = $_POST['project_name'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    $advisor_id = $_POST['advisor_id'];
    
    // ตรวจสอบว่ามีสมาชิกหรือไม่
    if (!isset($_POST['student_ids']) || empty($_POST['student_ids'])) {
        $error_message = "กรุณาเลือกสมาชิกโครงงานอย่างน้อยหนึ่งคน";
    } else {
        $student_ids = $_POST['student_ids']; // array ของรหัสนักศึกษา
        
        // เริ่ม transaction
        $conn->begin_transaction();
        
        try {
            // อัพเดตข้อมูลโครงงาน
            $sql = "UPDATE projects SET 
                    project_name = ?, 
                    description = ?, 
                    start_date = ?, 
                    end_date = ?, 
                    status = ?, 
                    advisor_id = ? 
                    WHERE project_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssii", $project_name, $description, $start_date, $end_date, $status, $advisor_id, $project_id);
            $stmt->execute();
            
            // ลบข้อมูลสมาชิกโครงงานเดิม
            $sql = "DELETE FROM project_members WHERE project_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            
            // เพิ่มข้อมูลสมาชิกโครงงานใหม่
            $sql = "INSERT INTO project_members (project_id, student_id, role_in_project) VALUES (?, ?, 'member')";
            $stmt = $conn->prepare($sql);
            
            foreach ($student_ids as $student_id) {
                $stmt->bind_param("ii", $project_id, $student_id);
                $stmt->execute();
            }
            
            $conn->commit();
            header("Location: project_add.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลโครงงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>แก้ไขข้อมูลโครงงาน</h2>
            <a href="project_add.php" class="btn btn-secondary">กลับไปหน้าจัดการโครงงาน</a>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
            <a href="project_add.php" class="btn btn-primary">กลับไปหน้าจัดการโครงงาน</a>
        <?php elseif (isset($project)): ?>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $project_id); ?>" class="needs-validation" novalidate>
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <input type="hidden" name="update_project" value="1">
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="project_name" class="form-label">ชื่อโครงงาน:</label>
                        <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo htmlspecialchars($project['project_name']); ?>" required>
                        <div class="invalid-feedback">กรุณากรอกชื่อโครงงาน</div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label for="description" class="form-label">รายละเอียดโครงงาน:</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($project['description']); ?></textarea>
                        <div class="invalid-feedback">กรุณากรอกรายละเอียดโครงงาน</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">วันที่เริ่มต้น:</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $project['start_date']; ?>" required>
                        <div class="invalid-feedback">กรุณาเลือกวันที่เริ่มต้น</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">วันที่สิ้นสุด:</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $project['end_date']; ?>" required>
                        <div class="invalid-feedback">กรุณาเลือกวันที่สิ้นสุด</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">สถานะ:</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">เลือกสถานะ</option>
                            <option value="planning" <?php if ($project['status'] == 'planning') echo 'selected'; ?>>วางแผน</option>
                            <option value="in_progress" <?php if ($project['status'] == 'in_progress') echo 'selected'; ?>>กำลังดำเนินการ</option>
                            <option value="completed" <?php if ($project['status'] == 'completed') echo 'selected'; ?>>เสร็จสิ้น</option>
                            <option value="suspended" <?php if ($project['status'] == 'suspended') echo 'selected'; ?>>ระงับ</option>
                        </select>
                        <div class="invalid-feedback">กรุณาเลือกสถานะ</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="advisor_id" class="form-label">อาจารย์ที่ปรึกษา:</label>
                        <select class="form-select" id="advisor_id" name="advisor_id" required>
                            <option value="">เลือกอาจารย์ที่ปรึกษา</option>
                            <?php 
                            if ($result_advisors && $result_advisors->num_rows > 0) {
                                while($row = $result_advisors->fetch_assoc()) {
                                    $selected = ($row['teacher_id'] == $project['advisor_id']) ? 'selected' : '';
                                    echo "<option value='" . $row["teacher_id"] . "' $selected>" . 
                                         htmlspecialchars($row["academic_rank"] . " " . $row["full_name"]) . 
                                         "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">กรุณาเลือกอาจารย์ที่ปรึกษา</div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label for="student_ids" class="form-label">สมาชิกโครงงาน:</label>
                        <select class="form-select" id="student_ids" name="student_ids[]" multiple required>
                            <?php 
                            if ($result_students && $result_students->num_rows > 0) {
                                while($row = $result_students->fetch_assoc()) {
                                    $selected = in_array($row['student_id'], $current_members) ? 'selected' : '';
                                    echo "<option value='" . $row["student_id"] . "' $selected>" . 
                                         htmlspecialchars($row["student_code"] . " - " . $row["full_name"]) . 
                                         "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">กรุณาเลือกสมาชิกโครงงานอย่างน้อย 1 คน</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                <a href="project_add.php" class="btn btn-secondary">ยกเลิก</a>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('#student_ids').select2({
                placeholder: 'เลือกสมาชิกโครงงาน',
                allowClear: true
            });
            
            $('#advisor_id').select2({
                placeholder: 'เลือกอาจารย์ที่ปรึกษา'
            });
        });

        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        // ตรวจสอบการเลือกสมาชิกโครงงาน
                        var studentSelect = document.getElementById('student_ids');
                        if (studentSelect.selectedOptions.length === 0) {
                            event.preventDefault();
                            studentSelect.setCustomValidity('กรุณาเลือกสมาชิกโครงงานอย่างน้อย 1 คน');
                        } else {
                            studentSelect.setCustomValidity('');
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
        
        // ตรวจสอบวันที่
        document.getElementById('end_date').addEventListener('change', function() {
            var startDate = new Date(document.getElementById('start_date').value);
            var endDate = new Date(this.value);
            
            if (endDate < startDate) {
                this.setCustomValidity('วันที่สิ้นสุดต้องมาหลังวันที่เริ่มต้น');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>