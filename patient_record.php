<?php
require_once 'db.php';
require_once 'functions.php';
require_role('admin');

$flash = null;
$student = null;
$record = null;
$appointments = [];
$search = trim($_GET['search'] ?? $_POST['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_record') {
    $student_id = (int) ($_POST['student_id'] ?? 0);
    $allergies = trim($_POST['allergies'] ?? '');
    $conditions = trim($_POST['conditions'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');

    $stmt = $mysqli->prepare('UPDATE patient_records SET allergies = ?, conditions = ?, emergency_contact = ?, updated_at = NOW() WHERE student_id = ?');
    $stmt->bind_param('sssi', $allergies, $conditions, $emergency_contact, $student_id);
    $stmt->execute();

    $stmt = $mysqli->prepare('UPDATE students SET contact_no = ? WHERE student_id = ?');
    $stmt->bind_param('si', $contact_no, $student_id);
    $stmt->execute();

    set_flash('success', 'Patient record updated.');
    header('Location: patient_record.php?search=' . urlencode($search ?: ($student_id ? (string) $student_id : '')));
    exit;
}

if ($search !== '') {
    if (preg_match('/^[0-9]+$/', $search)) {
        $stmt = $mysqli->prepare('SELECT s.student_id, s.student_number, s.full_name, s.course, s.contact_no, u.email FROM students s JOIN users u ON u.user_id = s.student_id WHERE s.student_number = ? OR s.student_id = ? LIMIT 1');
        $student_id_int = (int) $search;
        $stmt->bind_param('si', $search, $student_id_int);
    } else {
        $like = '%' . $search . '%';
        $stmt = $mysqli->prepare('SELECT s.student_id, s.student_number, s.full_name, s.course, s.contact_no, u.email FROM students s JOIN users u ON u.user_id = s.student_id WHERE s.full_name LIKE ? OR s.student_number LIKE ? LIMIT 1');
        $stmt->bind_param('ss', $like, $like);
    }
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if ($student) {
        $stmt = $mysqli->prepare('SELECT * FROM patient_records WHERE student_id = ? LIMIT 1');
        $stmt->bind_param('i', $student['student_id']);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();

        $stmt = $mysqli->prepare('SELECT a.appointment_id, a.reason, a.status, s.date, s.time_slot FROM appointments a JOIN clinic_schedule s ON s.schedule_id = a.schedule_id WHERE a.student_id = ? ORDER BY s.date DESC, s.time_slot DESC');
        $stmt->bind_param('i', $student['student_id']);
        $stmt->execute();
        $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!$record && $student) {
    $record = ['allergies' => '', 'conditions' => '', 'emergency_contact' => ''];
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Record View / Edit</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand">
            <div class="brand-mark">CM</div>
            <div>
                <div class="brand-title">FEU Tech Student Clinic Appointment System</div>
                <div class="brand-subtitle">Patient record view and edit</div>
            </div>
        </div>
        <nav class="nav-links">
            <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="container" style="padding:24px 0 48px;">
    <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="form-card" style="margin-bottom:18px;">
        <h2 class="panel-title">Search student name / student number</h2>
        <form method="get" class="inline">
            <input class="input" style="flex:1; min-width:240px;" type="text" name="search" value="<?= e($search) ?>" placeholder="Enter name or number">
            <button class="btn" type="submit">Search</button>
        </form>
    </div>

    <?php if ($student): ?>
    <div class="split-grid">
        <div class="panel">
            <h2 class="panel-title">Student Profile</h2>
            <div class="help-text"><strong>Name:</strong> <?= e($student['full_name']) ?></div>
            <div class="help-text"><strong>Course:</strong> <?= e($student['course']) ?></div>
            <div class="help-text"><strong>Student No.:</strong> <?= e($student['student_number']) ?></div>
            <div class="help-text"><strong>Contact:</strong> <?= e($student['contact_no']) ?></div>
            <div class="help-text"><strong>Email:</strong> <?= e($student['email']) ?></div>
        </div>

        <div class="form-card">
            <h2 class="panel-title">Editable Medical Fields</h2>
            <form method="post">
                <input type="hidden" name="action" value="save_record">
                <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
                <input type="hidden" name="search" value="<?= e($search) ?>">
                <div class="field">
                    <label class="label">Contact No.</label>
                    <input class="input" type="text" name="contact_no" value="<?= e($student['contact_no']) ?>">
                </div>
                <div class="field">
                    <label class="label">Allergies</label>
                    <textarea class="textarea" name="allergies"><?= e($record['allergies'] ?? '') ?></textarea>
                </div>
                <div class="field">
                    <label class="label">Existing Conditions</label>
                    <textarea class="textarea" name="conditions"><?= e($record['conditions'] ?? '') ?></textarea>
                </div>
                <div class="field">
                    <label class="label">Emergency Contact</label>
                    <textarea class="textarea" name="emergency_contact"><?= e($record['emergency_contact'] ?? '') ?></textarea>
                </div>
                <button class="btn" type="submit">Save</button>
                <a class="btn-outline" href="patient_record.php">Reset</a>
            </form>
        </div>
    </div>

    <div class="table-card" style="margin-top:18px;">
        <h2 class="panel-title">Appointment History</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $item): ?>
                    <tr>
                        <td><?= e(format_date($item['date'])) ?></td>
                        <td><?= e(date('h:i A', strtotime($item['time_slot']))) ?></td>
                        <td><?= e($item['reason']) ?></td>
                        <td><span class="<?= e(status_badge_class($item['status'])) ?>"><?= e($item['status']) ?></span></td>
                        <td>View note</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$appointments): ?>
                    <tr><td colspan="5">No appointments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($search !== ''): ?>
        <div class="flash info">No student matched your search.</div>
    <?php endif; ?>
</main>
</body>
</html>
