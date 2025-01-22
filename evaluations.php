<?php
// เพิ่มโค้ดส่วนนี้ต่อจาก if ($_SERVER['REQUEST_METHOD'] === 'POST') ที่มีอยู่แล้ว

    // จัดการการเพิ่มการประเมินผล
    if (isset($_POST['add_evaluation'])) {
        $milestone_id = $_POST['milestone_id'];
        $score = $_POST['score'];
        $feedback = $_POST['feedback'];
        $evaluated_by = $_SESSION['user_id'];
        
        $sql = "INSERT INTO milestone_evaluations (milestone_id, score, feedback, evaluated_by, evaluation_date) 
                VALUES (?, ?, ?, ?, CURRENT_DATE)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isis", $milestone_id, $score, $feedback, $evaluated_by);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "บันทึกการประเมินผลสำเร็จ";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกการประเมินผล";
        }
    }

    // จัดการการแก้ไขการประเมินผล
    if (isset($_POST['update_evaluation'])) {
        $evaluation_id = $_POST['evaluation_id'];
        $score = $_POST['score'];
        $feedback = $_POST['feedback'];
        
        $sql = "UPDATE milestone_evaluations 
                SET score = ?, feedback = ?, evaluation_date = CURRENT_DATE 
                WHERE evaluation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $score, $feedback, $evaluation_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "อัปเดตการประเมินผลสำเร็จ";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตการประเมินผล";
        }
    }

// แก้ไขส่วนแสดงผลตารางโดยเพิ่มคอลัมน์การประเมิน
?>
                            <thead>
                                <tr>
                                    <th>เป้าหมาย</th>
                                    <th>รายละเอียด</th>
                                    <th>กำหนดส่ง</th>
                                    <th>วันที่เสร็จ</th>
                                    <th>สถานะ</th>
                                    <th>คะแนน</th>
                                    <th>ความคิดเห็น</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($milestone = $milestones->fetch_assoc()): 
                                    // ดึงข้อมูลการประเมินล่าสุด
                                    $eval_sql = "SELECT me.*, u.username as evaluator_name 
                                                FROM milestone_evaluations me 
                                                LEFT JOIN users u ON me.evaluated_by = u.user_id 
                                                WHERE me.milestone_id = ? 
                                                ORDER BY me.evaluation_date DESC LIMIT 1";
                                    $eval_stmt = $conn->prepare($eval_sql);
                                    $eval_stmt->bind_param("i", $milestone['milestone_id']);
                                    $eval_stmt->execute();
                                    $evaluation = $eval_stmt->get_result()->fetch_assoc();
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($milestone['milestone_name']); ?></td>
                                        <td><?php echo htmlspecialchars($milestone['description']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($milestone['due_date'])); ?></td>
                                        <td>
                                            <?php 
                                            echo $milestone['completed_date'] 
                                                ? date('d/m/Y', strtotime($milestone['completed_date']))
                                                : '-';
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $milestone['status'] === 'completed' ? 'success' : 
                                                    ($milestone['status'] === 'overdue' ? 'danger' : 'warning');
                                            ?>">
                                                <?php 
                                                echo $milestone['status'] === 'completed' ? 'เสร็จสิ้น' : 
                                                    ($milestone['status'] === 'overdue' ? 'เลยกำหนด' : 'รอดำเนินการ');
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo $evaluation ? $evaluation['score'] . '/10' : '-'; ?></td>
                                        <td>
                                            <?php if ($evaluation): ?>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-bs-toggle="tooltip" 
                                                        title="<?php echo htmlspecialchars($evaluation['feedback']); ?>">
                                                    ดูความคิดเห็น
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                                                <!-- ปุ่มเปิด Modal ประเมินผล -->
                                                <button type="button" class="btn btn-primary btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#evaluationModal<?php echo $milestone['milestone_id']; ?>">
                                                    <?php echo $evaluation ? 'แก้ไขการประเมิน' : 'ประเมินผล'; ?>
                                                </button>

                                                <!-- Modal สำหรับการประเมินผล -->
                                                <div class="modal fade" id="evaluationModal<?php echo $milestone['milestone_id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">
                                                                    ประเมินผล: <?php echo htmlspecialchars($milestone['milestone_name']); ?>
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form method="POST">
                                                                    <?php if ($evaluation): ?>
                                                                        <input type="hidden" name="evaluation_id" 
                                                                               value="<?php echo $evaluation['evaluation_id']; ?>">
                                                                        <input type="hidden" name="update_evaluation" value="1">
                                                                    <?php else: ?>
                                                                        <input type="hidden" name="milestone_id" 
                                                                               value="<?php echo $milestone['milestone_id']; ?>">
                                                                        <input type="hidden" name="add_evaluation" value="1">
                                                                    <?php endif; ?>

                                                                    <div class="mb-3">
                                                                        <label class="form-label">คะแนน (0-10)</label>
                                                                        <input type="number" class="form-control" name="score" 
                                                                               min="0" max="10" required 
                                                                               value="<?php echo $evaluation ? $evaluation['score'] : ''; ?>">
                                                                    </div>

                                                                    <div class="mb-3">
                                                                        <label class="form-label">ความคิดเห็น</label>
                                                                        <textarea class="form-control" name="feedback" 
                                                                                  rows="3"><?php echo $evaluation ? htmlspecialchars($evaluation['feedback']) : ''; ?></textarea>
                                                                    </div>

                                                                    <button type="submit" class="btn btn-primary">บันทึกการประเมิน</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>