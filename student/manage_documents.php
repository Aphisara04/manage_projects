<?php
session_start();
require_once '../connect.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

// ตรวจสอบว่ามีการส่ง project_id มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$project_id = intval($_GET['id']);
$user_id = intval($_SESSION['user_id']);

// ดึงข้อมูลโครงงาน
try {
    $project_sql = "SELECT p.* FROM projects p 
                    JOIN project_members pm ON p.project_id = pm.project_id 
                    JOIN students s ON pm.student_id = s.student_id 
                    WHERE p.project_id = {$project_id} AND s.user_id = {$user_id}";
    $project_result = $conn->query($project_sql);
    
    if (!$project_result) {
        throw new Exception("ไม่สามารถดึงข้อมูลโครงงานได้: " . $conn->error);
    }
    
    if ($project_result->num_rows === 0) {
        // ถ้าไม่ใช่โครงงานของนักศึกษาคนนี้ ให้เรียกดูข้อมูลโครงงานโดยไม่ตรวจสอบความเป็นเจ้าของ
        $project_sql = "SELECT * FROM projects WHERE project_id = {$project_id}";
        $project_result = $conn->query($project_sql);
        
        if (!$project_result || $project_result->num_rows === 0) {
            header('Location: index.php');
            exit;
        }
    }
    
    $project = $project_result->fetch_assoc();
} catch (Exception $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}

// สร้างโฟลเดอร์สำหรับเก็บไฟล์ถ้ายังไม่มี
$upload_dir = "../uploads/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// จัดการการอัปโหลดเอกสาร
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $document_name = $conn->real_escape_string($_POST['document_name']);
    $document_type = $conn->real_escape_string($_POST['document_type']);
    $remarks = $conn->real_escape_string($_POST['remarks']);
    
    // ตรวจสอบไฟล์
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_name = $_FILES['document_file']['name'];
        $file_size = $_FILES['document_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // ตรวจสอบนามสกุลไฟล์ที่อนุญาต
        $allowed_ext = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt');
        
        if (in_array($file_ext, $allowed_ext)) {
            if ($file_size <= 10000000) { // ขนาดไม่เกิน 10MB
                // สร้างชื่อไฟล์ใหม่เพื่อป้องกันการซ้ำกัน
                $new_file_name = "project_" . $project_id . "_" . time() . "." . $file_ext;
                
                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    // บันทึกข้อมูลลงฐานข้อมูล
                    // เช็คว่าตาราง project_documents มีคอลัมน์ที่ต้องใช้หรือไม่
                    $sql = "INSERT INTO project_documents (project_id, document_type, file_path, upload_date, uploaded_by) 
                            VALUES ({$project_id}, '{$document_type}', '{$new_file_name}', NOW(), {$user_id})";
                    
                    if ($conn->query($sql)) {
                        $message = "อัปโหลดเอกสารสำเร็จ";
                        $message_type = "success";
                    } else {
                        $message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
                        $message_type = "danger";
                    }
                } else {
                    $message = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
                    $message_type = "danger";
                }
            } else {
                $message = "ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 10MB)";
                $message_type = "danger";
            }
        } else {
            $message = "นามสกุลไฟล์ไม่ได้รับอนุญาต กรุณาอัปโหลดไฟล์ประเภท: " . implode(', ', $allowed_ext);
            $message_type = "danger";
        }
    } else {
        $message = "กรุณาเลือกไฟล์ที่ต้องการอัปโหลด";
        $message_type = "danger";
    }
}

// การลบเอกสาร
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['doc_id'])) {
    $doc_id = intval($_GET['doc_id']);
    
    try {
        // ดึงข้อมูลไฟล์ก่อนลบ
        $file_sql = "SELECT * FROM project_documents WHERE document_id = {$doc_id} AND project_id = {$project_id}";
        $file_result = $conn->query($file_sql);
        
        if (!$file_result) {
            throw new Exception("ไม่สามารถดึงข้อมูลไฟล์: " . $conn->error);
        }
        
        if ($file_result->num_rows > 0) {
            $file_data = $file_result->fetch_assoc();
            $file_to_delete = $upload_dir . $file_data['file_path'];
            
            // ลบข้อมูลจากฐานข้อมูล
            $delete_sql = "DELETE FROM project_documents WHERE document_id = {$doc_id}";
            
            if ($conn->query($delete_sql)) {
                // ลบไฟล์จากเซิร์ฟเวอร์
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
                $message = "ลบเอกสารสำเร็จ";
                $message_type = "success";
            } else {
                $message = "เกิดข้อผิดพลาดในการลบเอกสาร: " . $conn->error;
                $message_type = "danger";
            }
        } else {
            $message = "ไม่พบเอกสารที่ต้องการลบ";
            $message_type = "danger";
        }
    } catch (Exception $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ดึงรายการเอกสารทั้งหมด
try {
    $docs_sql = "SELECT pd.*, u.full_name as uploader_name 
                FROM project_documents pd
                LEFT JOIN users u ON pd.uploaded_by = u.user_id
                WHERE pd.project_id = {$project_id} 
                ORDER BY pd.upload_date DESC";
    $docs_result = $conn->query($docs_sql);
    
    if (!$docs_result) {
        throw new Exception("ไม่สามารถดึงข้อมูลเอกสาร: " . $conn->error);
    }
} catch (Exception $e) {
    $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    $message_type = "danger";
    $docs_result = null;
}

// ฟังก์ชันแปลงประเภทเอกสาร
function getDocumentTypeText($type) {
    switch ($type) {
        case 'proposal':
            return 'เอกสารข้อเสนอโครงงาน';
        case 'progress_report':
            return 'รายงานความก้าวหน้า';
        case 'final_report':
            return 'รายงานฉบับสมบูรณ์';
        case 'presentation':
            return 'สไลด์นำเสนอ';
        default:
            return 'ไม่ระบุประเภท';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเอกสารโครงงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .document-card {
            transition: all 0.3s;
        }
        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .file-icon {
            font-size: 2.5rem;
            color: #6c757d;
        }
        .pdf-icon { color: #dc3545; }
        .doc-icon { color: #0d6efd; }
        .xls-icon { color: #198754; }
        .ppt-icon { color: #fd7e14; }
        .txt-icon { color: #6c757d; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">ระบบจัดการโครงงาน</a>
            <div class="navbar-text text-white">
                ยินดีต้อนรับ: <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
            </div>
            <a href="../logout.php" class="btn btn-outline-light">ออกจากระบบ</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>จัดการเอกสารโครงงาน: <?php echo htmlspecialchars($project['project_name']); ?></h2>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> กลับไปหน้าหลัก
            </a>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-upload"></i> อัปโหลดเอกสารใหม่</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="document_name" class="form-label">ชื่อเอกสาร</label>
                                <input type="text" class="form-control" id="document_name" name="document_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="document_type" class="form-label">ประเภทเอกสาร</label>
                                <select class="form-select" id="document_type" name="document_type" required>
                                    <option value="">-- เลือกประเภทเอกสาร --</option>
                                    <option value="proposal">เอกสารข้อเสนอโครงงาน</option>
                                    <option value="progress_report">รายงานความก้าวหน้า</option>
                                    <option value="final_report">รายงานฉบับสมบูรณ์</option>
                                    <option value="presentation">สไลด์นำเสนอ</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="document_file" class="form-label">เลือกไฟล์</label>
                                <input type="file" class="form-control" id="document_file" name="document_file" required>
                                <small class="text-muted">รองรับไฟล์: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT (ขนาดสูงสุด 10MB)</small>
                            </div>
                            <div class="mb-3">
                                <label for="remarks" class="form-label">หมายเหตุ</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                            </div>
                            <button type="submit" name="upload" class="btn btn-primary w-100">
                                <i class="fas fa-cloud-upload-alt"></i> อัปโหลดเอกสาร
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-file-alt"></i> รายการเอกสาร</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$docs_result || $docs_result->num_rows === 0): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> ยังไม่มีเอกสารในโครงงานนี้
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php while ($doc = $docs_result->fetch_assoc()): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card document-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="me-3">
                                                        <?php
                                                        $file_ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                                                        $icon_class = 'file-icon';
                                                        
                                                        switch($file_ext) {
                                                            case 'pdf':
                                                                $icon_class .= ' pdf-icon';
                                                                echo '<i class="far fa-file-pdf '.$icon_class.'"></i>';
                                                                break;
                                                            case 'doc':
                                                            case 'docx':
                                                                $icon_class .= ' doc-icon';
                                                                echo '<i class="far fa-file-word '.$icon_class.'"></i>';
                                                                break;
                                                            case 'xls':
                                                            case 'xlsx':
                                                                $icon_class .= ' xls-icon';
                                                                echo '<i class="far fa-file-excel '.$icon_class.'"></i>';
                                                                break;
                                                            case 'ppt':
                                                            case 'pptx':
                                                                $icon_class .= ' ppt-icon';
                                                                echo '<i class="far fa-file-powerpoint '.$icon_class.'"></i>';
                                                                break;
                                                            case 'txt':
                                                                $icon_class .= ' txt-icon';
                                                                echo '<i class="far fa-file-alt '.$icon_class.'"></i>';
                                                                break;
                                                            default:
                                                                echo '<i class="far fa-file '.$icon_class.'"></i>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($doc['document_type']); ?></h5>
                                                        <small class="text-muted">
                                                            <?php echo getDocumentTypeText($doc['document_type']); ?> | 
                                                            <?php echo date('d/m/Y H:i', strtotime($doc['upload_date'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <p class="card-text small">
                                                    <strong>อัปโหลดโดย:</strong> <?php echo htmlspecialchars($doc['uploader_name'] ?? 'ไม่ระบุ'); ?>
                                                </p>
                                                
                                                <div class="d-flex justify-content-between mt-3">
                                                    <a href="<?php echo $upload_dir . $doc['file_path']; ?>" class="btn btn-sm btn-success" target="_blank">
                                                        <i class="fas fa-download"></i> ดาวน์โหลด
                                                    </a>
                                                    <a href="?id=<?php echo $project_id; ?>&action=edit&doc_id=<?php echo $doc['document_id']; ?>" 
                                                        class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i> แก้ไข
                                                    </a>
                                                    <a href="?id=<?php echo $project_id; ?>&action=delete&doc_id=<?php echo $doc['document_id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบเอกสารนี้?')">
                                                        <i class="fas fa-trash"></i> ลบ
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>