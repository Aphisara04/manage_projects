<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
require 'connect.php';

// ตัวแปรสำหรับเก็บข้อความแจ้งเตือน
$status_message = "";

// ลบโครงงาน
if (isset($_POST['delete_project'])) {
    $project_id = $_POST['project_id'];
    $conn->begin_transaction();
    
    try {
        // ลบสมาชิกโครงงาน
        $stmt = $conn->prepare("DELETE FROM project_members WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        // ลบโครงงาน
        $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        
        $conn->commit();
        $status_message = "<div class='alert alert-success'>ลบโครงงานสำเร็จ</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $status_message = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}

// ดึงรายชื่ออาจารย์ที่ปรึกษา
$sql_advisors = "SELECT t.teacher_id, u.full_name, t.academic_rank 
                 FROM teachers t 
                 JOIN users u ON t.user_id = u.user_id 
                 ORDER BY u.full_name";
$result_advisors = $conn->query($sql_advisors);

// เมื่อมีการส่งฟอร์มเพิ่มโครงงาน
if (isset($_POST['add_project'])) {
    // รับค่าจากฟอร์ม
    $project_name = $_POST['project_name'];
    $description = $_POST['description'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    $advisor_id = $_POST['advisor_id'];
    
    // ตรวจสอบว่ามีสมาชิกหรือไม่
    if(!isset($_POST['student_ids']) || empty($_POST['student_ids'])) {
        $status_message = "<div class='alert alert-danger'>กรุณาเลือกสมาชิกโครงงานอย่างน้อยหนึ่งคน</div>";
    } else {
        $student_ids = $_POST['student_ids']; // array ของรหัสนักศึกษา

        // เริ่ม transaction
        $conn->begin_transaction();

        try {
            // เพิ่มข้อมูลในตาราง projects
            $sql_project = "INSERT INTO projects (project_name, description, start_date, end_date, status, advisor_id) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_project = $conn->prepare($sql_project);
            $stmt_project->bind_param("sssssi", $project_name, $description, $start_date, $end_date, $status, $advisor_id);
            $stmt_project->execute();
            
            // ดึง project_id ที่เพิ่งเพิ่มเข้าไป
            $project_id = $conn->insert_id;

            // เพิ่มข้อมูลสมาชิกโครงงาน
            $sql_member = "INSERT INTO project_members (project_id, student_id, role_in_project) VALUES (?, ?, 'member')";
            $stmt_member = $conn->prepare($sql_member);
            
            foreach ($student_ids as $student_id) {
                $stmt_member->bind_param("ii", $project_id, $student_id);
                $stmt_member->execute();
            }

            // ยืนยัน transaction
            $conn->commit();
            $status_message = "<div class='alert alert-success'>เพิ่มข้อมูลโครงงานสำเร็จ</div>";
        } catch (Exception $e) {
            // ถ้ามีข้อผิดพลาดให้ rollback
            $conn->rollback();
            $status_message = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
        }
    }
}

// ดึงรายชื่อนักศึกษาที่ยังไม่มีโครงงาน
$sql_students = "SELECT s.student_id, u.full_name, s.student_code 
                 FROM students s 
                 JOIN users u ON s.user_id = u.user_id 
                 LEFT JOIN project_members pm ON s.student_id = pm.student_id 
                 WHERE pm.member_id IS NULL 
                 ORDER BY u.full_name";
$result_students = $conn->query($sql_students);

// ดึงข้อมูลโครงงาน
$sql = "SELECT p.*, u.full_name as advisor_name, t.academic_rank,
        GROUP_CONCAT(CONCAT(s.student_code, ' - ', us.full_name) SEPARATOR '<br>') as members
        FROM projects p
        JOIN teachers t ON p.advisor_id = t.teacher_id
        JOIN users u ON t.user_id = u.user_id
        LEFT JOIN project_members pm ON p.project_id = pm.project_id
        LEFT JOIN students s ON pm.student_id = s.student_id
        LEFT JOIN users us ON s.user_id = us.user_id
        GROUP BY p.project_id
        ORDER BY p.start_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลโครงงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <!-- แสดงข้อความสถานะ -->
        <?php echo $status_message; ?>

        <!-- แท็บเมนู -->
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab" aria-controls="list" aria-selected="true">รายการโครงงาน</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab" aria-controls="add" aria-selected="false">เพิ่มโครงงานใหม่</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- แท็บรายการโครงงาน -->
            <div class="tab-pane fade show active" id="list" role="tabpanel" aria-labelledby="list-tab">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>รายการโครงงาน</h2>
                    <a href="index.php" class="btn btn-secondary">กลับไปหน้าหลัก</a>
                </div>

                <div class="table-responsive">
                    <table id="projectTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>ชื่อโครงงาน</th>
                                <th>อาจารย์ที่ปรึกษา</th>
                                <th>สมาชิก</th>
                                <th>วันที่เริ่ม</th>
                                <th>วันที่สิ้นสุด</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['project_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['academic_rank'] . ' ' . $row['advisor_name']); ?></td>
                                    <td><?php echo $row['members']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['start_date'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['end_date'])); ?></td>
                                    <td>
                                        <?php
                                        $status_map = [
                                            'planning' => 'วางแผน',
                                            'in_progress' => 'กำลังดำเนินการ',
                                            'completed' => 'เสร็จสิ้น',
                                            'suspended' => 'ระงับ'
                                        ];
                                        echo $status_map[$row['status']] ?? $row['status'];
                                        ?>
                                    </td>
                                    <td>
                                        <a href="project_edit.php?id=<?php echo $row['project_id']; ?>" 
                                           class="btn btn-warning btn-sm">แก้ไข</a>
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" 
                                              style="display: inline;" 
                                              onsubmit="return confirm('ยืนยันการลบโครงงาน?');">
                                            <input type="hidden" name="project_id" value="<?php echo $row['project_id']; ?>">
                                            <button type="submit" name="delete_project" 
                                                    class="btn btn-danger btn-sm">ลบ</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">ไม่พบข้อมูลโครงงาน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- แท็บเพิ่มโครงงานใหม่ -->
            <div class="tab-pane fade" id="add" role="tabpanel" aria-labelledby="add-tab">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>เพิ่มข้อมูลโครงงาน</h2>
                    <a href="index.php" class="btn btn-secondary">กลับไปหน้าหลัก</a>
                </div>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
                    <input type="hidden" name="add_project" value="1">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="project_name" class="form-label">ชื่อโครงงาน:</label>
                            <input type="text" class="form-control" id="project_name" name="project_name" required>
                            <div class="invalid-feedback">กรุณากรอกชื่อโครงงาน</div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">รายละเอียดโครงงาน:</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            <div class="invalid-feedback">กรุณากรอกรายละเอียดโครงงาน</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">วันที่เริ่มต้น:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                            <div class="invalid-feedback">กรุณาเลือกวันที่เริ่มต้น</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">วันที่สิ้นสุด:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                            <div class="invalid-feedback">กรุณาเลือกวันที่สิ้นสุด</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">สถานะ:</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">เลือกสถานะ</option>
                                <option value="planning">วางแผน</option>
                                <option value="in_progress">กำลังดำเนินการ</option>
                                <option value="completed">เสร็จสิ้น</option>
                                <option value="suspended">ระงับ</option>
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
                                        echo "<option value='" . $row["teacher_id"] . "'>" . 
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
                                        echo "<option value='" . $row["student_id"] . "'>" . 
                                             htmlspecialchars($row["student_code"] . " - " . $row["full_name"]) . 
                                             "</option>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกสมาชิกโครงงานอย่างน้อย 1 คน</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
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
            
            // Initialize DataTable
            $('#projectTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json"
                },
                "responsive": true
            });

            // จัดการเมื่อมีการคลิกแท็บ
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                // ถ้ามีการแสดง error และ user คลิกไปที่แท็บ add
                if (e.target.id === 'add-tab') {
                    document.querySelectorAll('.alert').forEach(function(alert) {
                        alert.style.display = 'none';
                    });
                }
                // กำหนดให้ datatable redraw เมื่อแท็บแสดง
                $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
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