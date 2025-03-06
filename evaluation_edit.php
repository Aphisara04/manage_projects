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

// ตรวจสอบว่ามีการส่ง id มาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: evaluations.php');
    exit;
}

$evaluation_id = intval($_GET['id']);

// สำหรับเก็บข้อความแจ้งเตือน
$alert_message = '';
$alert_type = '';

// ตรวจสอบว่ามีการส่งแบบฟอร์มหรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ตรวจสอบข้อมูลที่ส่งมา
    $evaluator_id = isset($_POST['evaluator_id']) ? intval($_POST['evaluator_id']) : 0;
    $evaluation_date = isset($_POST['evaluation_date']) ? $_POST['evaluation_date'] : '';
    $score = isset($_POST['score']) ? floatval($_POST['score']) : 0;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';

    // ตรวจสอบว่าข้อมูลถูกต้องหรือไม่
    $errors = [];
    if ($evaluator_id <= 0) {
        $errors[] = "กรุณาเลือกผู้ประเมิน";
    }
    if (empty($evaluation_date)) {
        $errors[] = "กรุณาระบุวันที่ประเมิน";
    }
    if ($score < 0 || $score > 100) {
        $errors[] = "กรุณาระบุคะแนนระหว่าง 0-100";
    }

    // ถ้าไม่มีข้อผิดพลาด ให้อัปเดตข้อมูล
    if (empty($errors)) {
        $sql = "UPDATE evaluations 
                SET evaluator_id = ?, evaluation_date = ?, score = ?, comments = ?
                WHERE evaluation_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isdsi", $evaluator_id, $evaluation_date, $score, $comments, $evaluation_id);
        
        if ($stmt->execute()) {
            $alert_message = "อัปเดตการประเมินเรียบร้อยแล้ว";
            $alert_type = "success";
            
            // ถ้าต้องการให้กลับไปหน้า evaluation_view.php หลังจากอัปเดตสำเร็จ
            // header("Location: evaluation_view.php?id=" . $evaluation_id);
            // exit;
        } else {
            $alert_message = "เกิดข้อผิดพลาดในการอัปเดต: " . $stmt->error;
            $alert_type = "danger";
        }
        
        $stmt->close();
    } else {
        // ถ้ามีข้อผิดพลาด ให้แสดงข้อความแจ้งเตือน
        $alert_message = "กรุณาแก้ไขข้อผิดพลาด:<br>" . implode("<br>", $errors);
        $alert_type = "danger";
    }
}

// ดึงข้อมูลการประเมินที่ต้องการแก้ไข
$sql = "SELECT 
            e.evaluation_id,
            e.project_id,
            p.project_name,
            e.evaluator_id,
            e.evaluation_date,
            e.score,
            e.comments
        FROM 
            evaluations e
        JOIN 
            projects p ON e.project_id = p.project_id
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

// ดึงข้อมูลอาจารย์ทั้งหมดเพื่อเลือกผู้ประเมิน
$sql_evaluators = "SELECT 
                      t.teacher_id,
                      u.full_name,
                      t.academic_rank
                   FROM teachers t
                   JOIN users u ON t.user_id = u.user_id
                   ORDER BY u.full_name";
$evaluators_result = $conn->query($sql_evaluators);

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
    <title>แก้ไขการประเมินโครงงาน</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }
        .form-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-top: 20px;
        }
        .form-label {
            font-weight: 500;
        }
        .score-input {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
        }
        .score-range-container {
            padding: 0 15px;
        }
        .score-range {
            width: 100%;
            margin-top: 10px;
        }
        .title-icon {
            margin-right: 10px;
            color: #ffc107;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .project-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .project-name {
            font-weight: bold;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-edit title-icon"></i>แก้ไขการประเมินโครงงาน</h2>
            <div>
                <a href="evaluation_view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้ารายละเอียด
                </a>
                <a href="evaluations.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list"></i> รายการประเมินทั้งหมด
                </a>
            </div>
        </div>

        <?php if (!empty($alert_message)): ?>
        <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $alert_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="project-info">
                <h5>แก้ไขการประเมินโครงงาน</h5>
                <p class="project-name"><?php echo htmlspecialchars($evaluation["project_name"]); ?></p>
                <?php list($status_text, $status_color) = getEvaluationStatus($evaluation["score"]); ?>
                <p>สถานะปัจจุบัน: 
                    <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_text; ?></span>
                    <span class="ms-2">(<?php echo $evaluation["score"]; ?> คะแนน)</span>
                </p>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $evaluation_id); ?>">
                <input type="hidden" name="project_id" value="<?php echo $evaluation["project_id"]; ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="evaluator_id" class="form-label required-field">ผู้ประเมิน</label>
                        <select class="form-select" id="evaluator_id" name="evaluator_id" required>
                            <option value="">-- เลือกผู้ประเมิน --</option>
                            <?php
                            if ($evaluators_result->num_rows > 0) {
                                while($evaluator_row = $evaluators_result->fetch_assoc()) {
                                    $display_name = !empty($evaluator_row["academic_rank"]) ? 
                                        $evaluator_row["academic_rank"] . ' ' . $evaluator_row["full_name"] : 
                                        $evaluator_row["full_name"];
                                    
                                    $selected = ($evaluation["evaluator_id"] == $evaluator_row["teacher_id"]) ? 'selected' : '';
                                    echo '<option value="' . $evaluator_row["teacher_id"] . '" ' . $selected . '>' . htmlspecialchars($display_name) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="evaluation_date" class="form-label required-field">วันที่ประเมิน</label>
                        <input type="date" class="form-control" id="evaluation_date" name="evaluation_date" 
                               value="<?php echo $evaluation["evaluation_date"]; ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 offset-md-3">
                        <label for="score" class="form-label required-field">คะแนน (0-100)</label>
                        <input type="number" class="form-control score-input" id="score" name="score" min="0" max="100" step="0.01" 
                               value="<?php echo $evaluation["score"]; ?>" required>
                        
                        <div class="score-range-container">
                            <input type="range" class="form-range score-range" id="score_range" min="0" max="100" step="1" 
                                   value="<?php echo $evaluation["score"]; ?>">
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">0</small>
                            <small class="text-muted">50</small>
                            <small class="text-muted">100</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="comments" class="form-label">ความคิดเห็น/ข้อเสนอแนะ</label>
                    <textarea class="form-control" id="comments" name="comments" rows="5"><?php echo htmlspecialchars($evaluation["comments"]); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-12 text-end">
                        <a href="evaluation_view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> ยกเลิก
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="mt-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> คำแนะนำการให้คะแนน</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>เกณฑ์การให้คะแนน</h6>
                            <ul>
                                <li><strong>80-100 คะแนน:</strong> ดีเยี่ยม - โครงงานมีคุณภาพสูง มีความคิดสร้างสรรค์ และใช้งานได้จริง</li>
                                <li><strong>70-79 คะแนน:</strong> ดี - โครงงานมีคุณภาพดี อาจมีจุดที่ต้องปรับปรุงเล็กน้อย</li>
                                <li><strong>60-69 คะแนน:</strong> ผ่าน - โครงงานผ่านเกณฑ์ขั้นต่ำ แต่ยังมีข้อบกพร่องที่ควรปรับปรุง</li>
                                <li><strong>ต่ำกว่า 60 คะแนน:</strong> ต้องปรับปรุง - โครงงานยังไม่ผ่านเกณฑ์ขั้นต่ำ ต้องแก้ไขปรับปรุง</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>ประเด็นที่ควรพิจารณา</h6>
                            <ul>
                                <li>ความคิดสร้างสรรค์และนวัตกรรม</li>
                                <li>การวิเคราะห์และออกแบบระบบ</li>
                                <li>คุณภาพของการพัฒนาระบบ</li>
                                <li>การทดสอบและประเมินผล</li>
                                <li>การนำเสนอและเอกสารประกอบ</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // สคริปต์สำหรับทำให้ช่องคะแนนและแถบเลื่อนทำงานร่วมกัน
        document.addEventListener('DOMContentLoaded', function() {
            const scoreInput = document.getElementById('score');
            const scoreRange = document.getElementById('score_range');
            
            // เมื่อค่าใน input เปลี่ยน ให้อัปเดตค่าใน range
            scoreInput.addEventListener('input', function() {
                scoreRange.value = this.value;
            });
            
            // เมื่อค่าใน range เปลี่ยน ให้อัปเดตค่าใน input
            scoreRange.addEventListener('input', function() {
                scoreInput.value = this.value;
            });
        });
    </script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>