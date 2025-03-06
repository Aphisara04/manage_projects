<?php
require 'connect.php';

// ตัวแปรสำหรับเก็บข้อความแจ้งเตือน
$error_message = "";

// ดึงข้อมูลอาจารย์ที่ต้องการแก้ไข
if (isset($_GET['id'])) {
    $teacher_id = $_GET['id'];
    $sql = "SELECT t.*, u.* 
            FROM teachers t 
            JOIN users u ON t.user_id = u.user_id 
            WHERE t.teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        $error_message = "ไม่พบข้อมูลอาจารย์ที่ต้องการแก้ไข";
    }
} else {
    $error_message = "ไม่ได้ระบุรหัสอาจารย์ที่ต้องการแก้ไข";
}

// เมื่อมีการส่งฟอร์มแก้ไข
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teacher_id = $_POST['teacher_id'];
    $user_id = $_POST['user_id'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $department = $_POST['department'];
    $academic_rank = $_POST['academic_rank'];
    $expertise = $_POST['expertise'];

    $conn->begin_transaction();
    
    try {
        // อัพเดตข้อมูลในตาราง users
        $sql = "UPDATE users SET email = ?, full_name = ?, department = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $email, $full_name, $department, $user_id);
        $stmt->execute();

        // อัพเดตข้อมูลในตาราง teachers
        $sql = "UPDATE teachers SET academic_rank = ?, expertise = ? WHERE teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $academic_rank, $expertise, $teacher_id);
        $stmt->execute();

        $conn->commit();
        header("Location: teacher_add.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
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
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <a href="teacher_add.php" class="btn btn-primary">กลับไปหน้าจัดการข้อมูลอาจารย์</a>
        <?php elseif (isset($row)): ?>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $teacher_id); ?>">
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
                    <select class="form-select" name="academic_rank" required>
                        <option value="">เลือกตำแหน่งทางวิชาการ</option>
                        <option value="อาจารย์" <?php if ($row['academic_rank'] == 'อาจารย์') echo 'selected'; ?>>อาจารย์</option>
                        <option value="ผู้ช่วยศาสตราจารย์" <?php if ($row['academic_rank'] == 'ผู้ช่วยศาสตราจารย์') echo 'selected'; ?>>ผู้ช่วยศาสตราจารย์</option>
                        <option value="รองศาสตราจารย์" <?php if ($row['academic_rank'] == 'รองศาสตราจารย์') echo 'selected'; ?>>รองศาสตราจารย์</option>
                        <option value="ศาสตราจารย์" <?php if ($row['academic_rank'] == 'ศาสตราจารย์') echo 'selected'; ?>>ศาสตราจารย์</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">ความเชี่ยวชาญ:</label>
                    <textarea class="form-control" name="expertise" rows="3" required><?php echo isset($row['expertise']) ? $row['expertise'] : ''; ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                <a href="teacher_add.php" class="btn btn-secondary">ยกเลิก</a>
            </form>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>