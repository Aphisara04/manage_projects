<?php
// กำหนดการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "manage_projects";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// ตั้งค่า charset เป็น utf8
$conn->set_charset("utf8");

// สำหรับเก็บข้อความแจ้งเตือน
$alert_message = '';
$alert_type = '';

// ตรวจสอบว่ามีการส่ง id มาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: evaluations.php');
    exit;
}

$evaluation_id = intval($_GET['id']);

// ดึงข้อมูลการประเมินที่จะลบ เพื่อแสดงข้อมูลยืนยัน
$sql = "SELECT 
            e.evaluation_id,
            e.project_id,
            p.project_name,
            e.evaluator_id,
            u.full_name AS evaluator_name,
            t.academic_rank,
            e.evaluation_date,
            e.score
        FROM 
            evaluations e
        JOIN 
            projects p ON e.project_id = p.project_id
        JOIN 
            teachers t ON e.evaluator_id = t.teacher_id
        JOIN 
            users u ON t.user_id = u.user_id
        WHERE 
            e.evaluation_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $evaluation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // ถ้าไม่พบข้อมูลการประเมิน ให้กลับไปหน้า evaluations.php
    header('Location: evaluations.php');
    exit;
}

$evaluation = $result->fetch_assoc();
$stmt->close();

// ถ้ามีการยืนยันการลบ (จากการกดปุ่มลบในแบบฟอร์ม)
if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'yes') {
    // ลบข้อมูลในตาราง evaluations
    $sql_delete = "DELETE FROM evaluations WHERE evaluation_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $evaluation_id);
    
    if ($stmt_delete->execute()) {
        // ลบสำเร็จ ให้แสดงข้อความแจ้งเตือนและกลับไปหน้า evaluations.php หลังจาก 3 วินาที
        $alert_message = "ลบการประเมินเรียบร้อยแล้ว กำลังกลับไปหน้ารายการประเมิน...";
        $alert_type = "success";
        header("refresh:3;url=evaluations.php?project_id=" . $evaluation['project_id']);
    } else {
        // ลบไม่สำเร็จ ให้แสดงข้อความแจ้งเตือน
        $alert_message = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $stmt_delete->error;
        $alert_type = "danger";
    }
    
    $stmt_delete->close();
}

// ฟังก์ชันแปลงวันที่เป็นรูปแบบไทย
function formatThaiDate($date) {
    if (empty($date)) return "-";
    
    $thai_months = [
        "", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน",
        "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];
    
    $date_parts = explode('-', $date);
    
    // ตรวจสอบรูปแบบวันที่
    if (count($date_parts) != 3) return $date;
    
    $year = intval($date_parts[0]);
    $month = intval($date_parts[1]);
    $day = intval($date_parts[2]);
    
    // แปลงเป็นปีพุทธศักราช
    if ($year > 2500) {
        // กรณีเป็นปี พ.ศ. อยู่แล้ว
        $thai_year = $year;
    } else {
        // กรณีเป็นปี ค.ศ. แปลงเป็น พ.ศ.
        $thai_year = $year + 543;
    }
    
    return "$day {$thai_months[$month]} $thai_year";
}

// ฟังก์ชันแสดงสถานะการประเมินตามคะแนน
function getEvaluationStatus($score) {
    if ($score >= 80) {
        return ['ดีเยี่ยม', 'success'];
    } elseif ($score >= 70) {
        return ['ดี', 'primary'];
    } elseif ($score >= 60) {
        return ['ผ่าน', 'info'];
    } else {
        return ['ต้องปรับปรุง', 'danger'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลบการประเมินโครงงาน</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }
        .content-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-top: 20px;
        }
        .delete-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .evaluation-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .warning-text {
            color: #dc3545;
            font-weight: bold;
        }
        .title-icon {
            margin-right: 10px;
            color: #dc3545;
        }
        .score-value {
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-trash-alt title-icon"></i>ลบการประเมินโครงงาน</h2>
            <div>
                <a href="evaluations.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้ารายการประเมิน
                </a>
            </div>
        </div>

        <?php if (!empty($alert_message)): ?>
        <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $alert_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="content-container text-center">
            <?php if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] != 'yes' || !empty($alert_message)): ?>
                <div class="delete-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                
                <h3 class="mb-4">คุณต้องการลบการประเมินนี้ใช่หรือไม่?</h3>
                
                <div class="evaluation-details">
                    <h5>รายละเอียดการประเมิน</h5>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 text-start">
                            <p><strong>ชื่อโครงงาน:</strong> <?php echo htmlspecialchars($evaluation["project_name"]); ?></p>
                            <p><strong>ผู้ประเมิน:</strong> 
                                <?php 
                                echo !empty($evaluation["academic_rank"]) ? htmlspecialchars($evaluation["academic_rank"]) . ' ' : '';
                                echo htmlspecialchars($evaluation["evaluator_name"]); 
                                ?>
                            </p>
                            <p><strong>วันที่ประเมิน:</strong> <?php echo formatThaiDate($evaluation["evaluation_date"]); ?></p>
                        </div>
                        <div class="col-md-6 text-center">
                            <?php list($status_text, $status_color) = getEvaluationStatus($evaluation["score"]); ?>
                            <p><strong>ผลการประเมิน:</strong> 
                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_text; ?></span>
                            </p>
                            <div class="score-value"><?php echo number_format($evaluation["score"], 1); ?></div>
                            <div>คะแนน</div>
                        </div>
                    </div>
                </div>
                
                <div class="my-4">
                    <p class="warning-text"><i class="fas fa-exclamation-triangle me-2"></i>คำเตือน: การดำเนินการนี้ไม่สามารถเรียกคืนได้</p>
                </div>

                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $evaluation_id); ?>">
                    <input type="hidden" name="confirm_delete" value="yes">
                    
                    <div class="d-flex justify-content-center mt-4">
                        <a href="evaluation_view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-secondary me-3">
                            <i class="fas fa-times"></i> ยกเลิก
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> ยืนยันการลบ
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                    <p class="mt-3">กำลังกลับไปยังหน้ารายการประเมิน...</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>