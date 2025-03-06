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

// ตรวจสอบว่ามีการส่ง evaluation_id มาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: evaluations.php');
    exit;
}

$evaluation_id = intval($_GET['id']);

// คำสั่ง SQL สำหรับดึงข้อมูลการประเมิน
$sql = "SELECT 
            e.evaluation_id,
            e.project_id,
            p.project_name,
            p.description AS project_description,
            p.start_date,
            p.end_date,
            p.status AS project_status,
            e.evaluator_id,
            t.academic_rank,
            u.full_name AS evaluator_name,
            t.expertise,
            e.evaluation_date,
            e.score,
            e.comments,
            a.full_name AS advisor_name,
            at.academic_rank AS advisor_rank
        FROM 
            evaluations e
        JOIN 
            projects p ON e.project_id = p.project_id
        JOIN 
            teachers t ON e.evaluator_id = t.teacher_id
        JOIN 
            users u ON t.user_id = u.user_id
        LEFT JOIN 
            teachers at ON p.advisor_id = at.teacher_id
        LEFT JOIN 
            users a ON at.user_id = a.user_id
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

// ดึงข้อมูลสมาชิกโครงงาน
$sql_members = "SELECT 
                  pm.role_in_project,
                  s.student_code,
                  u.full_name,
                  s.major,
                  s.year_of_study
                FROM 
                  project_members pm
                JOIN 
                  students s ON pm.student_id = s.student_id
                JOIN 
                  users u ON s.user_id = u.user_id
                WHERE 
                  pm.project_id = ?
                ORDER BY 
                  pm.role_in_project DESC, u.full_name";

$stmt_members = $conn->prepare($sql_members);
$stmt_members->bind_param("i", $evaluation['project_id']);
$stmt_members->execute();
$members_result = $stmt_members->get_result();

// ดึงข้อมูลกิจกรรม (milestones)
$sql_milestones = "SELECT 
                     milestone_id, 
                     milestone_name, 
                     description, 
                     due_date, 
                     completed_date, 
                     status
                   FROM 
                     project_milestones
                   WHERE 
                     project_id = ?
                   ORDER BY 
                     due_date";

$stmt_milestones = $conn->prepare($sql_milestones);
$stmt_milestones->bind_param("i", $evaluation['project_id']);
$stmt_milestones->execute();
$milestones_result = $stmt_milestones->get_result();

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function translateStatus($status) {
    switch ($status) {
        case 'planning':
            return 'วางแผน';
        case 'in_progress':
            return 'กำลังดำเนินการ';
        case 'completed':
            return 'เสร็จสมบูรณ์';
        case 'suspended':
            return 'ระงับชั่วคราว';
        case 'pending':
            return 'รอดำเนินการ';
        case 'overdue':
            return 'เลยกำหนด';
        default:
            return $status;
    }
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

// ฟังก์ชันคำนวณความก้าวหน้าของโครงงาน
function calculateProgress($milestones) {
    $total = $milestones->num_rows;
    if ($total == 0) return 0;
    
    $completed = 0;
    mysqli_data_seek($milestones, 0);
    while ($row = $milestones->fetch_assoc()) {
        if ($row['status'] == 'completed') {
            $completed++;
        }
    }
    
    return round(($completed / $total) * 100);
}

// ฟังก์ชันแสดงสีของแถบความก้าวหน้า
function getProgressColor($percentage) {
    if ($percentage < 25) {
        return 'bg-danger';
    } elseif ($percentage < 50) {
        return 'bg-warning';
    } elseif ($percentage < 75) {
        return 'bg-info';
    } else {
        return 'bg-success';
    }
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
    <title>รายละเอียดการประเมิน - <?php echo htmlspecialchars($evaluation["project_name"]); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }
        .content-section {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .section-title {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #0d6efd;
        }
        .score-display {
            font-size: 3rem;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
        }
        .score-label {
            text-align: center;
            font-size: 1.2rem;
            color: #6c757d;
        }
        .info-label {
            font-weight: 500;
            color: #495057;
        }
        .progress {
            height: 20px;
            border-radius: 10px;
        }
        .progress-bar {
            border-radius: 10px;
        }
        .milestone-item {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 4px solid #ccc;
            background-color: #f8f9fa;
        }
        .milestone-completed {
            border-left-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }
        .milestone-pending {
            border-left-color: #17a2b8;
            background-color: rgba(23, 162, 184, 0.1);
        }
        .milestone-overdue {
            border-left-color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
        .table-members th, .table-members td {
            padding: 8px 15px;
        }
        .comments-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
        }
        .badge {
            font-weight: normal;
            padding: 6px 10px;
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-star-half-alt me-2"></i>รายละเอียดการประเมิน</h2>
            <div>
                <a href="evaluations.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้าการประเมิน
                </a>
                <a href="evaluation_edit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> แก้ไขการประเมิน
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- ข้อมูลโครงงาน -->
                <div class="content-section">
                    <h4 class="section-title"><i class="fas fa-project-diagram me-2"></i>ข้อมูลโครงงาน</h4>
                    
                    <div class="mb-3">
                        <h5><?php echo htmlspecialchars($evaluation["project_name"]); ?></h5>
                        <p><?php echo nl2br(htmlspecialchars($evaluation["project_description"])); ?></p>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><span class="info-label">สถานะโครงงาน:</span> 
                                <span class="badge bg-<?php echo getProgressColor(calculateProgress($milestones_result)); ?>">
                                    <?php echo translateStatus($evaluation["project_status"]); ?>
                                </span>
                            </p>
                            <p><span class="info-label">วันที่เริ่มต้น:</span> <?php echo formatThaiDate($evaluation["start_date"]); ?></p>
                            <p><span class="info-label">วันที่สิ้นสุด:</span> <?php echo formatThaiDate($evaluation["end_date"]); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><span class="info-label">อาจารย์ที่ปรึกษา:</span> 
                                <?php 
                                echo !empty($evaluation["advisor_rank"]) ? htmlspecialchars($evaluation["advisor_rank"]) . ' ' : '';
                                echo htmlspecialchars($evaluation["advisor_name"] ?? "ไม่ระบุ"); 
                                ?>
                            </p>
                            <p><span class="info-label">จำนวนสมาชิก:</span> <?php echo $members_result->num_rows; ?> คน</p>
                            <p><span class="info-label">จำนวนกิจกรรม:</span> <?php echo $milestones_result->num_rows; ?> กิจกรรม</p>
                        </div>
                    </div>
                    
                    <h6>ความก้าวหน้าของโครงงาน</h6>
                    <?php
                    $progress = calculateProgress($milestones_result);
                    $progress_color = getProgressColor($progress);
                    ?>
                    <div class="progress mb-2">
                        <div class="progress-bar <?php echo $progress_color; ?>" role="progressbar" 
                             style="width: <?php echo $progress; ?>%" 
                             aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $progress; ?>%
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลการประเมิน -->
                <div class="content-section">
                    <h4 class="section-title"><i class="fas fa-clipboard-check me-2"></i>ข้อมูลการประเมิน</h4>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><span class="info-label">ผู้ประเมิน:</span> 
                                <?php 
                                echo !empty($evaluation["academic_rank"]) ? htmlspecialchars($evaluation["academic_rank"]) . ' ' : '';
                                echo htmlspecialchars($evaluation["evaluator_name"]); 
                                ?>
                            </p>
                            <p><span class="info-label">ความเชี่ยวชาญ:</span> <?php echo htmlspecialchars($evaluation["expertise"] ?? "ไม่ระบุ"); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><span class="info-label">วันที่ประเมิน:</span> <?php echo formatThaiDate($evaluation["evaluation_date"]); ?></p>
                            <?php
                            list($status_text, $status_color) = getEvaluationStatus($evaluation["score"]);
                            ?>
                            <p><span class="info-label">ผลการประเมิน:</span> 
                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_text; ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="score-display"><?php echo number_format($evaluation["score"], 1); ?></div>
                            <div class="score-label">คะแนน</div>
                        </div>
                        <div class="col-md-8">
                            <h6>ความคิดเห็น/ข้อเสนอแนะ</h6>
                            <div class="comments-section">
                                <?php
                                if (!empty($evaluation["comments"])) {
                                    echo nl2br(htmlspecialchars($evaluation["comments"]));
                                } else {
                                    echo '<span class="text-muted">ไม่มีความคิดเห็น</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- รายชื่อสมาชิก -->
                <div class="content-section">
                    <h4 class="section-title"><i class="fas fa-users me-2"></i>สมาชิกโครงงาน</h4>
                    
                    <?php if ($members_result->num_rows > 0): ?>
                        <table class="table table-sm table-members">
                            <thead>
                                <tr>
                                    <th>ชื่อ-สกุล</th>
                                    <th>บทบาท</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($member = $members_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($member["full_name"]); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($member["student_code"]); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($member["role_in_project"]); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">ไม่พบข้อมูลสมาชิก</div>
                    <?php endif; ?>
                </div>

                <!-- รายการกิจกรรม -->
                <div class="content-section">
                    <h4 class="section-title"><i class="fas fa-tasks me-2"></i>กิจกรรม (Milestones)</h4>
                    
                    <?php
                    if ($milestones_result->num_rows > 0) {
                        mysqli_data_seek($milestones_result, 0);
                        while($milestone = $milestones_result->fetch_assoc()) {
                            $milestone_class = "";
                            if ($milestone["status"] == "completed") {
                                $milestone_class = "milestone-completed";
                            } else if ($milestone["status"] == "pending") {
                                $milestone_class = "milestone-pending";
                                // ตรวจสอบว่าเลยกำหนดหรือไม่
                                $due_date = strtotime($milestone["due_date"]);
                                $today = strtotime(date("Y-m-d"));
                                if ($due_date < $today) {
                                    $milestone_class = "milestone-overdue";
                                }
                            } else if ($milestone["status"] == "overdue") {
                                $milestone_class = "milestone-overdue";
                            }
                    ?>
                    <div class="milestone-item <?php echo $milestone_class; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <?php 
                                if ($milestone["status"] == "completed") {
                                    echo '<i class="fas fa-check-circle text-success me-1"></i>';
                                } else if ($milestone_class == "milestone-overdue") {
                                    echo '<i class="fas fa-exclamation-circle text-danger me-1"></i>';
                                } else {
                                    echo '<i class="fas fa-clock text-info me-1"></i>';
                                }
                                echo htmlspecialchars($milestone["milestone_name"]); 
                                ?>
                            </h6>
                            <span class="badge bg-<?php echo ($milestone["status"] == "completed") ? "success" : (($milestone_class == "milestone-overdue") ? "danger" : "info"); ?>">
                                <?php echo translateStatus($milestone["status"]); ?>
                            </span>
                        </div>
                        <div class="small mt-1">
                            <?php 
                            if (!empty($milestone["description"])) {
                                echo htmlspecialchars($milestone["description"]) . '<br>';
                            }
                            ?>
                            <span class="text-muted">กำหนดส่ง: <?php echo formatThaiDate($milestone["due_date"]); ?></span>
                            <?php if (!empty($milestone["completed_date"])): ?>
                            <br><span class="text-success">วันที่เสร็จสิ้น: <?php echo formatThaiDate($milestone["completed_date"]); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                        }
                    } else {
                    ?>
                    <div class="alert alert-info">ไม่พบข้อมูลกิจกรรม</div>
                    <?php
                    }
                    ?>
                </div>
                
                <!-- ข้อมูลเพิ่มเติม -->
                <div class="content-section">
                    <h4 class="section-title"><i class="fas fa-info-circle me-2"></i>ข้อมูลเพิ่มเติม</h4>
                    
                    <div class="d-grid gap-2">
                        <a href="project_view.php?id=<?php echo $evaluation['project_id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-project-diagram me-1"></i> ดูรายละเอียดโครงงาน
                        </a>
                        <a href="milestone_view.php?project_id=<?php echo $evaluation['project_id']; ?>" class="btn btn-outline-info">
                            <i class="fas fa-tasks me-1"></i> ดูความก้าวหน้าโครงงาน
                        </a>
                        <a href="evaluations.php?project_id=<?php echo $evaluation['project_id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i> ดูการประเมินทั้งหมดของโครงงานนี้
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 text-center">
            <a href="evaluations.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> กลับไปหน้าการประเมิน
            </a>
            <a href="evaluation_edit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> แก้ไขการประเมิน
            </a>
            <a href="evaluation_delete.php?id=<?php echo $evaluation_id; ?>" 
               class="btn btn-danger"
               onclick="return confirm('คุณต้องการลบการประเมินนี้ใช่หรือไม่?')">
                <i class="fas fa-trash"></i> ลบการประเมิน
            </a>
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