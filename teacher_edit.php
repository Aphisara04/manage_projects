<?php
require 'connect.php';

// ดึงข้อมูลอาจารย์ที่ต้องการแก้ไข
if (isset($_GET['id'])) {
    $teacher = $_GET['id'];
    $sql = "SELECT s.*, u.* 
            FROM teachers s 
            JOIN users u ON s.user_id = u.user_id 
            WHERE s.teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
}

// เมื่อมีการส่งฟอร์มแก้ไข
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teacher_id = $_POST['teacher_id'];
    $user_id = $_POST['user_id'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $department = $_POST['department'];
    $teacher_code = $_POST['teacher_code'];
    $year_of_study = $_POST['year_of_study'];
    $major = $_POST['major'];

    $conn->begin_transaction();
    
    try {
        // อัพเดตข้อมูลในตาราง users
        $sql = "UPDATE users SET email = ?, full_name = ?, department = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $email, $full_name, $department, $user_id);
        $stmt->execute();

        // อัพเดตข้อมูลในตาราง teachers
        $sql = "UPDATE teachers SET teacher_code = ?, year_of_study = ?, major = ? WHERE teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisi", $teacher_code, $year_of_study, $major, $teacher_id);
        $stmt->execute();

        $conn->commit();
        header("Location: teacher_add.php");
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
    <title>แก้ไขข้อมูลอาจารย์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>แก้ไขข้อมูลอาจารย์</h2>
        <form method="POST">
            <input type="hidden" name="teacher_id" value="<?php echo $row['teacher_id']; ?>">
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
                <label class="form-label">ตำแหน่งทางวิชาการ:</label>
                <input type="text" class="form-control" name="teacher_code" value="<?php echo $row['teacher_code']; ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">ความเชี่ยวชาญ:</label>
                <input type="text" class="form-control" name="Expertise" value="<?php echo $row['Expertise']; ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            <a href="teacher_add.php" class="btn btn-secondary">ยกเลิก</a>
        </form>
    </div>
</body>
</html>