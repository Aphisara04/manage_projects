<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">ระบบจัดการโครงงาน</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                            หน้าหลัก
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'projects.php' ? 'active' : ''; ?>" href="projects.php">
                            โครงงานทั้งหมด
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'milestone.php' ? 'active' : ''; ?>" href="milestone.php">
                            ติดตามความคืบหน้า
                        </a>
                    </li>

                    <?php if ($_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'evaluations.php' ? 'active' : ''; ?>" href="evaluations.php">
                                การประเมินผล
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                จัดการระบบ
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                <li>
                                    <a class="dropdown-item <?php echo $current_page === 'manage_users.php' ? 'active' : ''; ?>" href="manage_users.php">
                                        จัดการผู้ใช้
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo $current_page === 'manage_courses.php' ? 'active' : ''; ?>" href="manage_courses.php">
                                        จัดการรายวิชา
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item <?php echo $current_page === 'system_settings.php' ? 'active' : ''; ?>" href="system_settings.php">
                                        ตั้งค่าระบบ
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- User Profile and Logout -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                            <span class="badge bg-secondary ms-1">
                                <?php
                                    switch($_SESSION['role']) {
                                        case 'student':
                                            echo 'นักศึกษา';
                                            break;
                                        case 'teacher':
                                            echo 'อาจารย์';
                                            break;
                                        case 'admin':
                                            echo 'ผู้ดูแลระบบ';
                                            break;
                                    }
                                ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                                    <i class="bi bi-person-circle me-2"></i>ข้อมูลส่วนตัว
                                </a>
                            </li>
                            <?php if ($_SESSION['role'] === 'student'): ?>
                                <li>
                                    <a class="dropdown-item <?php echo $current_page === 'my_projects.php' ? 'active' : ''; ?>" href="my_projects.php">
                                        <i class="bi bi-folder me-2"></i>โครงงานของฉัน
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            <?php else: ?>
                <!-- Login/Register Links for non-authenticated users -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'login.php' ? 'active' : ''; ?>" href="login.php">เข้าสู่ระบบ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'register.php' ? 'active' : ''; ?>" href="register.php">ลงทะเบียน</a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Add Bootstrap Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">