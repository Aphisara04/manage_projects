<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
require 'connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ข้อความแสดงผลสถานะ
$status_message = "";

// Handle delete
if (isset($_POST['delete_id'])) {
    $id = $_POST['delete_id'];
    $conn->begin_transaction();
    
    try {
        // Delete from teachers first due to foreign key constraint
        $stmt = $conn->prepare("DELETE FROM teachers WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $conn->commit();
        $status_message = "<div class='alert alert-success'>ลบข้อมูลสำเร็จ</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $status_message = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}

// เมื่อมีการส่งฟอร์มเพิ่มข้อมูล
if (isset($_POST['add_teacher'])) {
    // รับค่าจากฟอร์ม
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // เข้ารหัสรหัสผ่าน
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $department = $_POST['department'];
    $academic_rank = $_POST['academic_rank'];
    $expertise = $_POST['expertise'];

    // เริ่ม transaction
    $conn->begin_transaction();

    try {
        // เพิ่มข้อมูลในตาราง users ก่อน
        $sql_user = "INSERT INTO users (username, password, email, full_name, role, department) 
                     VALUES (?, ?, ?, ?, 'teacher', ?)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("sssss", $username, $password, $email, $full_name, $department);
        $stmt_user->execute();
        
        // ดึง user_id ที่เพิ่งเพิ่มเข้าไป
        $user_id = $conn->insert_id;

        // เพิ่มข้อมูลในตาราง teachers
        $sql_teacher = "INSERT INTO teachers (user_id, academic_rank, expertise) 
                       VALUES (?, ?, ?)";
        $stmt_teacher = $conn->prepare($sql_teacher);
        $stmt_teacher->bind_param("iss", $user_id, $academic_rank, $expertise);
        $stmt_teacher->execute();

        // ยืนยัน transaction
        $conn->commit();
        $status_message = "<div class='alert alert-success'>เพิ่มข้อมูลอาจารย์สำเร็จ</div>";
    } catch (Exception $e) {
        // ถ้ามีข้อผิดพลาดให้ rollback
        $conn->rollback();
        $status_message = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}

// Fetch teachers data
$sql = "SELECT u.user_id, u.username, u.email, u.full_name, u.department, 
        t.teacher_id, t.academic_rank, t.expertise
        FROM users u
        JOIN teachers t ON u.user_id = t.user_id
        WHERE u.role = 'teacher'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลอาจารย์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <!-- แสดงข้อความสถานะ -->
        <?php echo $status_message; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>เพิ่มข้อมูลอาจารย์</h2>
            <a href="index.php" class="btn btn-secondary">กลับไปหน้าหลัก</a>
        </div>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
            <input type="hidden" name="add_teacher" value="1">
            <div class="row">
                <!-- ข้อมูลผู้ใช้ -->
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">ชื่อผู้ใช้:</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                    <div class="invalid-feedback">กรุณากรอกชื่อผู้ใช้</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">รหัสผ่าน:</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="invalid-feedback">กรุณากรอกรหัสผ่าน</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">อีเมล:</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                    <div class="invalid-feedback">กรุณากรอกอีเมลให้ถูกต้อง</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">ชื่อ-นามสกุล:</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                    <div class="invalid-feedback">กรุณากรอกชื่อ-นามสกุล</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="department" class="form-label">ภาควิชา:</label>
                    <input type="text" class="form-control" id="department" name="department" required>
                    <div class="invalid-feedback">กรุณากรอกภาควิชา</div>
                </div>

                <!-- ข้อมูลอาจารย์ -->
                <div class="col-md-6 mb-3">
                    <label for="academic_rank" class="form-label">ตำแหน่งทางวิชาการ:</label>
                    <select class="form-select" id="academic_rank" name="academic_rank" required>
                        <option value="">เลือกตำแหน่งทางวิชาการ</option>
                        <option value="อาจารย์">อาจารย์</option>
                        <option value="ผู้ช่วยศาสตราจารย์">ผู้ช่วยศาสตราจารย์</option>
                        <option value="รองศาสตราจารย์">รองศาสตราจารย์</option>
                        <option value="ศาสตราจารย์">ศาสตราจารย์</option>
                    </select>
                    <div class="invalid-feedback">กรุณาเลือกตำแหน่งทางวิชาการ</div>
                </div>
                <div class="col-md-12 mb-3">
                    <label for="expertise" class="form-label">ความเชี่ยวชาญ:</label>
                    <textarea class="form-control" id="expertise" name="expertise" rows="3" required></textarea>
                    <div class="invalid-feedback">กรุณาระบุความเชี่ยวชาญ</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
        </form>
        <hr class="my-5">

        <div class="d-flex justify-content-between align-items-center mb-4">
         
        <h2>รายชื่ออาจารย์</h2>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ชื่อ-นามสกุล</th>
                        <th>อีเมล</th>
                        <th>ภาควิชา</th>
                        <th>ตำแหน่งทางวิชาการ</th>
                        <th>ความเชี่ยวชาญ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result && $result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td><?php echo htmlspecialchars($row['academic_rank']); ?></td>
                        <td><?php echo htmlspecialchars($row['expertise']); ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="teacher_edit.php?id=<?php echo $row['teacher_id']; ?>" 
                                   class="btn btn-warning btn-sm">แก้ไข</a>
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" 
                                      style="display: inline;" 
                                      onsubmit="return confirm('ยืนยันการลบข้อมูล?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $row['user_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                    <tr>
                        <td colspan="6" class="text-center">ไม่พบข้อมูลอาจารย์</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // เพิ่ม JavaScript สำหรับการตรวจสอบฟอร์ม
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
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>