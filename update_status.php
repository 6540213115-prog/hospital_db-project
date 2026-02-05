<?php
session_start();
require_once 'db.php';

// --------------------------------------------------------
// 🔒 1. ตรวจสอบสิทธิ์ (ต้องเป็น Admin หรือ ช่าง เท่านั้น)
// --------------------------------------------------------
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'technician'])) {
    header("Location: repair_form.php");
    exit();
}

$id = $_GET['id'] ?? 0;
$msg = "";
$alert_type = "";

// --------------------------------------------------------
// 💾 2. บันทึกข้อมูล (Update Data)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'];
    $tech_note  = $_POST['technician_note'];
    $tech_id    = $_SESSION['user_id']; 

    // SQL พื้นฐาน
    $sql_update = "UPDATE tickets SET 
                    status = :status, 
                    technician_id = :tech_id, 
                    technician_note = :note, 
                    updated_at = NOW() 
                   WHERE id = :id";
    
    // Logic พิเศษ: ถ้าสถานะเป็น 'completed' ให้ลงเวลาจบงาน (completed_at) ด้วย
    if ($new_status == 'completed') {
        $sql_update = "UPDATE tickets SET 
                        status = :status, 
                        technician_id = :tech_id, 
                        technician_note = :note, 
                        updated_at = NOW(),
                        completed_at = NOW() 
                       WHERE id = :id";
    }

    try {
        $stmt = $pdo->prepare($sql_update);
        $result = $stmt->execute([
            ':status' => $new_status,
            ':tech_id' => $tech_id,
            ':note' => $tech_note,
            ':id' => $id
        ]);

        if($result){
            $msg = "บันทึกข้อมูลเรียบร้อยแล้ว!";
            $alert_type = "success";
            // Refresh หน้าเพื่อแสดงข้อมูลใหม่หลังจาก 1 วินาที
            header("Refresh:1"); 
        }

    } catch (PDOException $e) {
        $msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// --------------------------------------------------------
// 📥 3. ดึงข้อมูล Ticket มาแสดง
// --------------------------------------------------------
try {
    // ดึงข้อมูลใบแจ้งซ่อม (สามารถ Join User ได้ที่นี่ถ้าต้องการชื่อจริง)
    // ตรงนี้ผมใช้ tickets.* และดึงชื่อผู้แจ้งจากฟิลด์ requester_id โดยตรงไปก่อน เพื่อป้องกัน Error หากไม่มีตาราง users
    $sql = "SELECT * FROM tickets WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) { die("ไม่พบข้อมูลใบงานนี้ (ID: " . htmlspecialchars($id) . ")"); }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการใบงาน #<?php echo htmlspecialchars($ticket['ticket_no'] ?? $id); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f0f2f5; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; padding: 1.2rem; border-radius: 12px 12px 0 0 !important; }
        .label-head { font-size: 0.85rem; font-weight: 600; color: #6c757d; text-transform: uppercase; }
        .info-txt { font-size: 1rem; color: #212529; font-weight: 500; }
        .img-preview { cursor: zoom-in; transition: transform 0.2s; border-radius: 8px; }
        .img-preview:hover { transform: scale(1.02); }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-primary shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin_dashboard.php"><i class="bi bi-tools"></i> Admin Zone</a>
        <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
        </a>
    </div>
</nav>

<div class="container pb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark">
            ใบงานเลขที่: <span class="text-primary">#<?php echo htmlspecialchars($ticket['ticket_no'] ?? $ticket['id']); ?></span>
        </h3>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-file-text me-2"></i>รายละเอียดการแจ้งซ่อม</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="label-head">ผู้แจ้ง</div>
                            <div class="info-txt"><?php echo htmlspecialchars($ticket['requester_id']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="label-head">วันที่แจ้ง</div>
                            <div class="info-txt"><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?> น.</div>
                        </div>
                        <div class="col-md-12">
                            <div class="label-head">สถานที่ / อาคาร / ห้อง</div>
                            <div class="info-txt">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                <?php echo htmlspecialchars(($ticket['place_building'] ?? '-') . " " . ($ticket['place_room'] ?? '-')); ?>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <div class="mb-3">
                        <div class="label-head mb-2">รายละเอียดปัญหา</div>
                        <div class="p-3 bg-light rounded border-start border-4 border-warning text-dark">
                            <?php echo nl2br(htmlspecialchars($ticket['description'] ?? '')); ?>
                        </div>
                    </div>

                    <?php if(!empty($ticket['img_before'])): ?>
                    <div class="mb-3">
                        <div class="label-head mb-2">รูปภาพประกอบ</div>
                        <a href="uploads/<?php echo $ticket['img_before']; ?>" target="_blank">
                            <img src="uploads/<?php echo $ticket['img_before']; ?>" class="img-fluid img-preview shadow-sm" style="max-height: 300px; width: auto;">
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100 border-top border-4 border-primary">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-gear-fill me-2"></i>ส่วนบันทึกการซ่อม</h6>
                </div>
                <div class="card-body p-4">
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">สถานะงาน (Status)</label>
                            <select name="status" class="form-select form-select-lg shadow-sm border-secondary-subtle">
                                <option value="pending" <?php echo ($ticket['status']=='pending')?'selected':''; ?>> รอรับเรื่อง / รอซ่อม</option>
                                <option value="in_progress" <?php echo ($ticket['status']=='in_progress')?'selected':''; ?>> กำลังดำเนินการ</option>
                                <option value="completed" <?php echo ($ticket['status']=='completed')?'selected':''; ?>> ดำเนินการเสร็จสิ้น</option>
                                <option value="cancelled" <?php echo ($ticket['status']=='cancelled')?'selected':''; ?>> ยกเลิกรายการ</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">บันทึกช่าง / วิธีแก้ไข (Solution)</label>
                            <textarea name="technician_note" rows="6" class="form-control shadow-sm" placeholder="ระบุสาเหตุ, อะไหล่ที่เปลี่ยน หรือผลการซ่อม..."><?php echo htmlspecialchars($ticket['technician_note'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <div class="label-head">ผู้ดำเนินการล่าสุด</div>
                            <div class="d-flex align-items-center mt-2">
                                <i class="bi bi-person-circle fs-4 me-2 text-primary"></i>
                                <span class="fw-bold text-dark">
                                    <?php echo htmlspecialchars($ticket['technician_id'] ?? 'ยังไม่มีผู้รับงาน'); ?>
                                </span>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm rounded-pill">
                                <i class="bi bi-save me-2"></i> บันทึกสถานะ
                            </button>
                            </div>
                    </form>

                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>