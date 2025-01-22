<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
require 'connect.php';

// เมื่อมีการส่งฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับค่าจากฟอร์ม
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // เข้ารหัสรหัสผ่าน
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $department = $_POST['department'];
    $student_code = $_POST['student_code'];
    $year_of_study = $_POST['year_of_study'];
    $major = $_POST['major'];

    // เริ่ม transaction
    $conn->begin_transaction();

    try {
        // เพิ่มข้อมูลในตาราง users ก่อน
        $sql_user = "INSERT INTO users (username, password, email, full_name, role, department) 
                     VALUES (?, ?, ?, ?, 'student', ?)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("sssss", $username, $password, $email, $full_name, $department);
        $stmt_user->execute();
        
        // ดึง user_id ที่เพิ่งเพิ่มเข้าไป
        $user_id = $conn->insert_id;

        // เพิ่มข้อมูลในตาราง students
        $sql_student = "INSERT INTO students (user_id, student_code, year_of_study, major) 
                       VALUES (?, ?, ?, ?)";
        $stmt_student = $conn->prepare($sql_student);
        $stmt_student->bind_param("isis", $user_id, $student_code, $year_of_study, $major);
        $stmt_student->execute();

        // ยืนยัน transaction
        $conn->commit();
        echo "<div class='alert alert-success'>เพิ่มข้อมูลนักศึกษาสำเร็จ</div>";
    } catch (Exception $e) {
        // ถ้ามีข้อผิดพลาดให้ rollback
        $conn->rollback();
        echo "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มข้อมูลนักศึกษา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4">เพิ่มข้อมูลนักศึกษา</h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
            <div class="row">
                <!-- ข้อมูลผู้ใช้ -->
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">ชื่อผู้ใช้:</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">รหัสผ่าน:</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">อีเมล:</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">ชื่อ-นามสกุล:</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="department" class="form-label">ภาควิชา:</label>
                    <input type="text" class="form-control" id="department" name="department" required>
                </div>

                <!-- ข้อมูลนักศึกษา -->
                <div class="col-md-6 mb-3">
                    <label for="student_code" class="form-label">รหัสนักศึกษา:</label>
                    <input type="text" class="form-control" id="student_code" name="student_code" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="year_of_study" class="form-label">ชั้นปี:</label>
                    <select class="form-select" id="year_of_study" name="year_of_study" required>
                        <option value="">เลือกชั้นปี</option>
                        <option value="1">ปี 1</option>
                        <option value="2">ปี 2</option>
                        <option value="3">ปี 3</option>
                        <option value="4">ปี 4</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="major" class="form-label">สาขาวิชา:</label>
                    <input type="text" class="form-control" id="major" name="major" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
        </form>
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
    <script>
    function confirmDelete(studentId) {
        if (confirm('คุณต้องการลบข้อมูลนักศึกษานี้ใช่หรือไม่?')) {
            window.location.href = 'student_delete.php?id=' + studentId;
        }
    }
</script>
</body>
<!-- เพิ่มตารางแสดงข้อมูล -->
<div class="mt-5">
    <h3>ข้อมูลนักศึกษาทั้งหมด</h3>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>รหัสนักศึกษา</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>อีเมล</th>
                    <th>ภาควิชา</th>
                    <th>ชั้นปี</th>
                    <th>สาขาวิชา</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // คำสั่ง SQL สำหรับดึงข้อมูล
                $sql = "SELECT s.*, u.full_name, u.email, u.department 
                        FROM students s 
                        JOIN users u ON s.user_id = u.user_id 
                        ORDER BY s.student_code";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>".$row['student_code']."</td>";
                        echo "<td>".$row['full_name']."</td>";
                        echo "<td>".$row['email']."</td>";
                        echo "<td>".$row['department']."</td>";
                        echo "<td>".$row['year_of_study']."</td>";
                        echo "<td>".$row['major']."</td>";
                        echo "<td>";
                        echo "<a href='student_edit.php?id=".$row['student_id']."' class='btn btn-warning btn-sm me-2'>แก้ไข</a>";
                        echo "<button onclick='confirmDelete(".$row['student_id'].")' class='btn btn-danger btn-sm'>ลบ</button>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='text-center'>ไม่พบข้อมูลนักศึกษา</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</html>