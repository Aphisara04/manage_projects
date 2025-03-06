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

// คำสั่ง SQL สำหรับดึงข้อมูลโครงงานทั้งหมดพร้อมชื่ออาจารย์ที่ปรึกษา
$sql = "SELECT 
            p.project_id, 
            p.project_name, 
            p.description, 
            p.start_date, 
            p.end_date, 
            p.status, 
            u.full_name AS advisor_name,
            t.academic_rank
        FROM 
            projects p
        LEFT JOIN 
            teachers t ON p.advisor_id = t.teacher_id
        LEFT JOIN 
            users u ON t.user_id = u.user_id
        ORDER BY 
            p.project_id";

$result = $conn->query($sql);

// ฟังก์ชั่นแปลงสถานะเป็นภาษาไทย
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
        default:
            return $status;
    }
}

// ฟังก์ชั่นแปลงวันที่เป็นรูปแบบไทย
function formatThaiDate($date) {
    if (empty($date)) return "-";
    
    // แปลงรูปแบบวันที่
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

// ฟังก์ชั่นสำหรับดึงจำนวนสมาชิกในโครงงาน
function getProjectMembersCount($conn, $project_id) {
    $sql = "SELECT COUNT(*) as member_count 
            FROM project_members 
            WHERE project_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['member_count'];
}

// ฟังก์ชั่นดึงข้อมูลสมาชิกในโครงงาน
function getProjectMembers($conn, $project_id) {
    $sql = "SELECT 
                s.student_id,
                s.student_code,
                u.full_name,
                pm.role_in_project
            FROM 
                project_members pm
            JOIN 
                students s ON pm.student_id = s.student_id
            JOIN 
                users u ON s.user_id = u.user_id
            WHERE 
                pm.project_id = ?
            ORDER BY 
                pm.role_in_project DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    return $members;
}

// ฟังก์ชั่นสำหรับดึงจำนวนไมล์สโตนของโครงงาน
function getProjectMilestonesCount($conn, $project_id) {
    $sql = "SELECT COUNT(*) as milestone_count 
            FROM project_milestones 
            WHERE project_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['milestone_count'];
}

// ฟังก์ชั่นแสดงสีตามสถานะ
function getStatusColor($status) {
    switch ($status) {
        case 'planning':
            return 'info';
        case 'in_progress':
            return 'primary';
        case 'completed':
            return 'success';
        case 'suspended':
            return 'warning';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการโครงงานทั้งหมด</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }
        .project-table th {
            background-color: #f0f0f0;
        }
        .project-table tbody tr {
            transition: background-color 0.3s;
        }
        .project-table tbody tr:hover {
            background-color: #f1f8ff;
        }
        .badge {
            font-weight: normal;
            padding: 5px 10px;
        }
        .truncate {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .actions-column {
            width: 150px;
        }
        .search-container {
            max-width: 400px;
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-planning {
            background-color: #0dcaf0;
        }
        .status-in_progress {
            background-color: #0d6efd;
        }
        .status-completed {
            background-color: #198754;
        }
        .status-suspended {
            background-color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-project-diagram me-2"></i>รายการโครงงานทั้งหมด</h2>
            <div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home"></i> หน้าหลัก
                </a>
                <a href="project_add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> เพิ่มโครงงานใหม่
                </a>
                <a href="milestone.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> กลับไปหน้าความก้าวหน้า
                </a>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <div class="search-container">
                    <div class="input-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="ค้นหาโครงงาน...">
                        <button class="btn btn-outline-secondary" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="btn-group float-end" role="group">
                    <button type="button" class="btn btn-outline-secondary filter-btn active" data-filter="all">ทั้งหมด</button>
                    <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="planning">วางแผน</button>
                    <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="in_progress">กำลังดำเนินการ</button>
                    <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="completed">เสร็จสมบูรณ์</button>
                    <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="suspended">ระงับ</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped project-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">ชื่อโครงงาน</th>
                                <th scope="col">สถานะ</th>
                                <th scope="col">อาจารย์ที่ปรึกษา</th>
                                <th scope="col">วันที่เริ่มต้น</th>
                                <th scope="col">วันที่สิ้นสุด</th>
                                <th scope="col">สมาชิก</th>
                                <th scope="col">กิจกรรม</th>
                                <th scope="col" class="actions-column">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                $counter = 1;
                                while ($row = $result->fetch_assoc()) {
                                    $statusColor = getStatusColor($row["status"]);
                                    $membersCount = getProjectMembersCount($conn, $row["project_id"]);
                                    $milestonesCount = getProjectMilestonesCount($conn, $row["project_id"]);
                            ?>
                            <tr class="project-row" data-status="<?php echo $row["status"]; ?>">
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <div class="truncate" title="<?php echo htmlspecialchars($row["project_name"]); ?>">
                                        <?php echo htmlspecialchars($row["project_name"]); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?php echo $statusColor; ?>">
                                        <span class="status-indicator status-<?php echo $row["status"]; ?>"></span>
                                        <?php echo translateStatus($row["status"]); ?>
                                    </span>
                                </td>
                                <td><?php echo !empty($row["academic_rank"]) ? $row["academic_rank"] . ' ' : ''; ?><?php echo htmlspecialchars($row["advisor_name"] ?? "ไม่ระบุ"); ?></td>
                                <td><?php echo formatThaiDate($row["start_date"]); ?></td>
                                <td><?php echo formatThaiDate($row["end_date"]); ?></td>
                                <td>
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#membersModal<?php echo $row["project_id"]; ?>">
                                        <?php echo $membersCount; ?> คน
                                    </a>
                                </td>
                                <td>
                                    <a href="milestones.php?project_id=<?php echo $row["project_id"]; ?>">
                                        <?php echo $milestonesCount; ?> กิจกรรม
                                    </a>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view_project.php?id=<?php echo $row["project_id"]; ?>" class="btn btn-sm btn-info" title="ดูรายละเอียด">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_project.php?id=<?php echo $row["project_id"]; ?>" class="btn btn-sm btn-warning" title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_project.php?id=<?php echo $row["project_id"]; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('คุณต้องการลบโครงงานนี้ใช่หรือไม่?')"
                                           title="ลบ">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php
                                    // สร้าง Modal สำหรับแสดงรายชื่อสมาชิก
                                    $members = getProjectMembers($conn, $row["project_id"]);
                            ?>
                            <!-- Modal แสดงรายชื่อสมาชิก -->
                            <div class="modal fade" id="membersModal<?php echo $row["project_id"]; ?>" tabindex="-1" aria-labelledby="membersModalLabel<?php echo $row["project_id"]; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="membersModalLabel<?php echo $row["project_id"]; ?>">รายชื่อสมาชิกโครงงาน</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <h6 class="mb-3"><?php echo htmlspecialchars($row["project_name"]); ?></h6>
                                            <?php if (count($members) > 0) { ?>
                                                <ul class="list-group">
                                                    <?php foreach ($members as $member) { ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($member["full_name"]); ?></strong><br>
                                                                <small class="text-muted">รหัสนักศึกษา: <?php echo htmlspecialchars($member["student_code"]); ?></small>
                                                            </div>
                                                            <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($member["role_in_project"]); ?></span>
                                                        </li>
                                                    <?php } ?>
                                                </ul>
                                            <?php } else { ?>
                                                <div class="alert alert-info">ยังไม่มีสมาชิกในโครงงานนี้</div>
                                            <?php } ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                            <a href="manage_members.php?project_id=<?php echo $row["project_id"]; ?>" class="btn btn-primary">จัดการสมาชิก</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="9" class="text-center">ไม่พบข้อมูลโครงงาน</td>
                            </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and custom script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ฟังก์ชั่นสำหรับค้นหาโครงงาน
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('keyup', filterProjects);

            // ฟังก์ชั่นสำหรับกรองโครงงานตามสถานะ
            const filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // ลบคลาส active จากปุ่มทั้งหมด
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    // เพิ่มคลาส active ให้ปุ่มที่ถูกคลิก
                    this.classList.add('active');
                    
                    filterProjects();
                });
            });
            
            function filterProjects() {
                const searchTerm = searchInput.value.toLowerCase();
                const activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
                const projectRows = document.querySelectorAll('.project-row');
                
                projectRows.forEach(function(row) {
                    const projectName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    const projectStatus = row.getAttribute('data-status');
                    
                    // กรองตามคำค้นหาและสถานะ
                    const matchesSearch = projectName.includes(searchTerm);
                    const matchesFilter = (activeFilter === 'all' || projectStatus === activeFilter);
                    
                    if (matchesSearch && matchesFilter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
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