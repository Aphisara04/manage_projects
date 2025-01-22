<?php
require 'connect.php';

// ดึงข้อมูลนักศึกษาที่ต้องการแก้ไข
if (isset($_GET['id'])) {
    $student_id = $_GET['id'];
    $sql = "SELECT s.*, u.* 
            FROM students s 
            JOIN users u ON s.user_id = u.user_id 
            WHERE s.student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
}

// เมื่อมีการส่งฟอร์มแก้ไข
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'];
    $user_id = $_POST['user_id'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $department = $_POST['department'];
    $student_code = $_POST['student_code'];
    $year_of_study = $_POST['year_of_study'];
    $major = $_POST['major'];

    $conn->begin_transaction();
    
    try {
        // อัพเดตข้อมูลในตาราง users
        $sql = "UPDATE users SET email = ?, full_name = ?, department = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $email, $full_name, $department, $user_id);
        $stmt->execute();

        // อัพเดตข้อมูลในตาราง students
        $sql = "UPDATE students SET student_code = ?, year_of_study = ?, major = ? WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisi", $student_code, $year_of_study, $major, $student_id);
        $stmt->execute();

        $conn->commit();
        header("Location: student_add.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลนักศึกษา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>แก้ไขข้อมูลนักศึกษา</h2>
        <form method="POST">
            <input type="hidden" name="student_id" value="<?php echo $row['student_id']; ?>">
            <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
            
            <div class="mb-3">
                <label class="form-label">อีเมล:</label>
                <input type="email" class="form-control" name="email" value="<?php echo $row['email']; ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">ชื่อ-นามสกุล:</label>
                <input type="text" class="form-control" name="full_name" value="<?php echo $row['full_name']; ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">ภาควิชา:</label>
                <input type="text" class="form-control" name="department" value="<?php echo $row['department']; ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">รหัสนักศึกษา:</label>
                <input type="text" class="form-control" name="student_code" value="<?php echo $row['student_code']; ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">ชั้นปี:</label>
                <select class="form-select" name="year_of_study" required>
                    <?php
                    for ($i = 1; $i <= 4; $i++) {
                        $selected = ($i == $row['year_of_study']) ? 'selected' : '';
                        echo "<option value='$i' $selected>ปี $i</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">สาขาวิชา:</label>
                <input type="text" class="form-control" name="major" value="<?php echo $row['major']; ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            <a href="student_add.php" class="btn btn-secondary">ยกเลิก</a>
        </form>
    </div>
</body>
</html>