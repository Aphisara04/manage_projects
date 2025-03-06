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

// ตรวจสอบว่ามีการส่ง project_id มาหรือไม่
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// เตรียมคำสั่ง SQL สำหรับดึงข้อมูลโครงงาน (ถ้ามี project_id)
$project_name = "ทุกโครงงาน"; // ค่าเริ่มต้น
if ($project_id > 0) {
    $project_sql = "SELECT project_name FROM projects WHERE project_id = ?";
    $stmt = $conn->prepare($project_sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project_result = $stmt->get_result();
    if ($project_result->num_rows > 0) {
        $project_row = $project_result->fetch_assoc();
        $project_name = $project_row['project_name'];
    }
    $stmt->close();
}

// ดึงข้อมูลโครงงานทั้งหมดสำหรับตัวเลือกกรอง
$projects_sql = "SELECT project_id, project_name FROM projects ORDER BY project_name";
$projects_result = $conn->query($projects_sql);

// คำสั่ง SQL สำหรับดึงข้อมูลการประเมิน
$sql = "SELECT 
            e.evaluation_id,
            p.project_id,
            p.project_name,
            u.full_name AS evaluator_name,
            t.academic_rank,
            e.evaluation_date,
            e.score,
            e.comments,
            (
                SELECT COUNT(*) 
                FROM project_milestones 
                WHERE project_id = p.project_id
            ) AS total_milestones,
            (
                SELECT COUNT(*) 
                FROM project_milestones 
                WHERE project_id = p.project_id AND status = 'completed'
            ) AS completed_milestones
        FROM 
            evaluations e
        JOIN 
            projects p ON e.project_id = p.project_id
        JOIN 
            teachers t ON e.evaluator_id = t.teacher_id
        JOIN 
            users u ON t.user_id = u.user_id";

// เพิ่มเงื่อนไขกรองตาม project_id (ถ้ามี)
if ($project_id > 0) {
    $sql .= " WHERE e.project_id = ?";
}

$sql .= " ORDER BY e.evaluation_date DESC";

// เตรียมและประมวลผลคำสั่ง SQL
if ($project_id > 0) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
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

// ฟังก์ชันคำนวณเปอร์เซ็นต์ความก้าวหน้า
function calculateProgress($completed, $total) {
    if ($total == 0) {
        return 0;
    }
    return round(($completed / $total) * 100);
}

// ฟังก์ชั่นแสดงสีของแถบความก้าวหน้า
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
    <title>ข้อมูลการประเมินโครงงาน</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }
        .evaluation-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .evaluation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .progress {
            height: 20px;
            border-radius: 10px;
        }
        .progress-bar {
            border-radius: 10px;
        }
        .badge {
            font-weight: normal;
            padding: 6px 10px;
            border-radius: 20px;
        }
        .comments-text {
            max-height: 120px;
            overflow-y: auto;
            padding-right: 5px;
        }
        .comments-text::-webkit-scrollbar {
            width: 6px;
        }
        .comments-text::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .comments-text::-webkit-scrollbar-thumb {
            background: #cbd3da;
            border-radius: 10px;
        }
        .search-container {
            max-width: 400px;
        }
        .score-large {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .evaluator-info {
            color: #6c757d;
        }
        .empty-state {
            text-align: center;
            padding: 50px 0;
        }
        .empty-state i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            color: #6c757d;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-star-half-alt me-2"></i>การประเมินโครงงาน: <?php echo htmlspecialchars($project_name); ?></h2>
            <div>
                <a href="milestone.php" class="btn btn-outline-secondary">
                    <i class="fas fa-tasks"></i> ความก้าวหน้า
                </a>
                <a href="projects.php" class="btn btn-outline-secondary">
                    <i class="fas fa-project-diagram"></i> โครงงาน
                </a>
                <a href="evaluation_add.php<?php echo $project_id > 0 ? '?project_id=' . $project_id : ''; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> เพิ่มการประเมิน
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="search-container">
                    <div class="input-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="ค้นหาการประเมิน...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <form method="get" id="filterForm">
                    <select class="form-select" name="project_id" id="projectFilter" onchange="this.form.submit()">
                        <option value="0">ทุกโครงงาน</option>
                        <?php
                        if ($projects_result->num_rows > 0) {
                            while($project_row = $projects_result->fetch_assoc()) {
                                $selected = ($project_id == $project_row["project_id"]) ? 'selected' : '';
                                echo '<option value="' . $project_row["project_id"] . '" ' . $selected . '>' . htmlspecialchars($project_row["project_name"]) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </form>
            </div>
        </div>

        <div class="row" id="evaluations-container">
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $progress = calculateProgress($row["completed_milestones"], $row["total_milestones"]);
                    $progress_color = getProgressColor($progress);
                    list($status_text, $status_color) = getEvaluationStatus($row["score"]);
            ?>
            <div class="col-md-6 mb-4 evaluation-item">
                <div class="card evaluation-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($row["project_name"]); ?></h5>
                        <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_text; ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center mb-3">
                            <div class="col-md-4 text-center">
                                <div class="score-large"><?php echo number_format($row["score"], 1); ?></div>
                                <div>คะแนน</div>
                            </div>
                            <div class="col-md-8">
                                <h6>ความก้าวหน้า: <?php echo $progress; ?>%</h6>
                                <div class="progress mb-2">
                                    <div class="progress-bar <?php echo $progress_color; ?>" role="progressbar" style="width: <?php echo $progress; ?>%" 
                                         aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span><?php echo $row["completed_milestones"]; ?> จาก <?php echo $row["total_milestones"]; ?> กิจกรรม</span>
                                    <a href="milestones.php?project_id=<?php echo $row["project_id"]; ?>" class="text-decoration-none">ดูกิจกรรม</a>
                                </div>
                            </div>
                        </div>

                        <div class="evaluator-info mb-3">
                            <i class="fas fa-user-tie me-1"></i> ผู้ประเมิน: <?php echo !empty($row["academic_rank"]) ? $row["academic_rank"] . ' ' : ''; ?><?php echo htmlspecialchars($row["evaluator_name"]); ?>
                            <br>
                            <i class="fas fa-calendar-day me-1"></i> วันที่ประเมิน: <?php echo formatThaiDate($row["evaluation_date"]); ?>
                        </div>

                        <div class="mb-3">
                            <h6>ความคิดเห็น:</h6>
                            <div class="comments-text p-2 bg-light rounded">
                                <?php echo !empty($row["comments"]) ? nl2br(htmlspecialchars($row["comments"])) : '<i class="text-muted">ไม่มีความคิดเห็น</i>'; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <a href="evaluation_view.php?id=<?php echo $row["evaluation_id"]; ?>" class="btn btn-sm btn-info me-2">
                                <i class="fas fa-eye"></i> รายละเอียด
                            </a>
                            <a href="evaluation_edit.php?id=<?php echo $row["evaluation_id"]; ?>" class="btn btn-sm btn-warning me-2">
                                <i class="fas fa-edit"></i> แก้ไข
                            </a>
                            <a href="evaluation_delete.php?id=<?php echo $row["evaluation_id"]; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('คุณต้องการลบการประเมินนี้ใช่หรือไม่?')">
                                <i class="fas fa-trash"></i> ลบ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php
                }
            } else {
            ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>ไม่พบข้อมูลการประเมิน</h3>
                    <p class="text-muted">ยังไม่มีการประเมินโครงงานในระบบ</p>
                    <a href="evaluation_add.php<?php echo $project_id > 0 ? '?project_id=' . $project_id : ''; ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-plus"></i> เพิ่มการประเมินใหม่
                    </a>
                </div>
            </div>
            <?php
            }
            ?>
        </div>
    </div>

    <!-- Bootstrap JS and custom script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ฟังก์ชั่นสำหรับค้นหาการประเมิน
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('keyup', filterEvaluations);
            
            function filterEvaluations() {
                const searchTerm = searchInput.value.toLowerCase();
                const evaluationItems = document.querySelectorAll('.evaluation-item');
                
                evaluationItems.forEach(function(item) {
                    const projectName = item.querySelector('.card-header h5').textContent.toLowerCase();
                    const evaluatorName = item.querySelector('.evaluator-info').textContent.toLowerCase();
                    const comments = item.querySelector('.comments-text').textContent.toLowerCase();
                    
                    if (projectName.includes(searchTerm) || evaluatorName.includes(searchTerm) || comments.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>