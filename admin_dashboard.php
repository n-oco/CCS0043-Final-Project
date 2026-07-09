<?php
require_once 'db.php';
require_once 'functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? 'Pending';

    $allowed = ['Pending', 'Approved', 'Declined', 'Completed', 'Cancelled'];
    if (!in_array($new_status, $allowed, true)) {
        set_flash('error', 'Invalid status selected.');
    } else {
        $stmt = $mysqli->prepare('SELECT schedule_id, status FROM appointments WHERE appointment_id = ? LIMIT 1');
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();

        if (!$current) {
            set_flash('error', 'Appointment not found.');
        } else {
            $mysqli->begin_transaction();
            try {
                $stmt = $mysqli->prepare('UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ?');
                $stmt->bind_param('si', $new_status, $appointment_id);
                $stmt->execute();

                if (in_array($new_status, ['Declined', 'Cancelled'], true)) {
                    $schedule_id = (int) $current['schedule_id'];
                    $stmt = $mysqli->prepare('UPDATE clinic_schedule SET is_available = 1 WHERE schedule_id = ?');
                    $stmt->bind_param('i', $schedule_id);
                    $stmt->execute();
                }

                if ($new_status === 'Approved') {
                    $schedule_id = (int) $current['schedule_id'];
                    $stmt = $mysqli->prepare('UPDATE clinic_schedule SET is_available = 0 WHERE schedule_id = ?');
                    $stmt->bind_param('i', $schedule_id);
                    $stmt->execute();
                }

                $mysqli->commit();
                set_flash('success', 'Appointment status updated.');
            } catch (Throwable $e) {
                $mysqli->rollback();
                set_flash('error', 'Unable to update status.');
            }
        }
    }
    header('Location: admin_dashboard.php');
    exit;
}

$flash = get_flash();

$today = date('Y-m-d');
$stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM appointments WHERE DATE(created_at) = ?');
$stmt->bind_param('s', $today);
$stmt->execute();
$total_today = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

$stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM appointments WHERE status = "Pending"');
$stmt->execute();
$pending_total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

$stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM appointments WHERE status = "Approved"');
$stmt->execute();
$approved_total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

$stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM appointments WHERE status = "Completed"');
$stmt->execute();
$completed_total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

$stmt = $mysqli->prepare('SELECT a.appointment_id, a.reason, a.status, a.created_at, s.date, s.time_slot, st.full_name, st.student_number, st.course, st.contact_no FROM appointments a JOIN clinic_schedule s ON s.schedule_id = a.schedule_id JOIN students st ON st.student_id = a.student_id WHERE s.date = ? ORDER BY s.time_slot ASC');
$stmt->bind_param('s', $today);
$stmt->execute();
$queue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $mysqli->prepare('SELECT s.schedule_id, s.date, s.time_slot, s.is_available FROM clinic_schedule s WHERE s.date >= ? ORDER BY s.date ASC, s.time_slot ASC LIMIT 8');
$stmt->bind_param('s', $today);
$stmt->execute();
$schedule_snapshot = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand">
            <div class="brand-mark">CM</div>
            <div>
                <div class="brand-title">FEU Tech Student Clinic Appointment System</div>
                <div class="brand-subtitle">Admin dashboard and appointment queue</div>
            </div>
        </div>
        <nav class="nav-links">
            <a class="nav-link" href="index.php">Home</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="container dashboard">
    <aside class="sidebar">
        <h2>Admin Menu</h2>
        <nav class="side-nav">
            <a class="active" href="admin_dashboard.php">Dashboard</a>
            <a href="admin_dashboard.php#queue">Appointments</a>
            <a href="patient_record.php">Patients</a>
            <a href="#schedule">Schedule</a>
            <a href="#reports">Reports</a>
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <section class="main-content">
        <?php if ($flash): ?>
            <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <div class="stat-grid">
            <div class="stat-card"><div class="stat-label">Total</div><div class="stat-value"><?= $total_today ?></div></div>
            <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value"><?= $pending_total ?></div></div>
            <div class="stat-card"><div class="stat-label">Approved</div><div class="stat-value"><?= $approved_total ?></div></div>
            <div class="stat-card"><div class="stat-label">Completed</div><div class="stat-value"><?= $completed_total ?></div></div>
        </div>

        <div class="three-grid" id="queue">
            <div class="table-card">
                <h2 class="panel-title">Today's Appointment Queue</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($queue as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['full_name']) ?></strong><br>
                                    <span class="muted small"><?= e($row['student_number']) ?> · <?= e($row['course']) ?></span>
                                </td>
                                <td><?= e(date('h:i A', strtotime($row['time_slot']))) ?></td>
                                <td><?= e($row['reason']) ?></td>
                                <td><span class="<?= e(status_badge_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                                <td>
                                    <div class="actions">
                                        <?php if (strtolower($row['status']) === 'pending'): ?>
                                            <form method="post">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?= (int) $row['appointment_id'] ?>">
                                                <input type="hidden" name="new_status" value="Approved">
                                                <button class="btn-outline" type="submit">Approve</button>
                                            </form>
                                            <form method="post">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?= (int) $row['appointment_id'] ?>">
                                                <input type="hidden" name="new_status" value="Declined">
                                                <button class="btn-danger" type="submit">Decline</button>
                                            </form>
                                        <?php elseif (strtolower($row['status']) === 'approved'): ?>
                                            <form method="post">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?= (int) $row['appointment_id'] ?>">
                                                <input type="hidden" name="new_status" value="Completed">
                                                <button class="btn" type="submit">Mark Completed</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted small">View only</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$queue): ?>
                            <tr><td colspan="5">No appointments for today.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel" id="schedule">
                <h2 class="panel-title">Schedule Snapshot</h2>
                <div class="grid-2">
                    <?php foreach ($schedule_snapshot as $slot): ?>
                        <div class="feature-card" style="padding:14px;">
                            <strong><?= e(format_date($slot['date'])) ?></strong><br>
                            <span class="muted small"><?= e(date('h:i A', strtotime($slot['time_slot']))) ?></span><br>
                            <span class="<?= (int) $slot['is_available'] === 1 ? 'badge badge-approved' : 'badge badge-cancelled' ?>" style="margin-top:8px;"> <?= (int) $slot['is_available'] === 1 ? 'Open slot' : 'Blocked' ?> </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="reports" style="margin-top:18px;">
                    <h2 class="panel-title">Reports & Analytics</h2>
                    <div class="feature-card">
                        <div class="help-text">Chart / summary placeholder</div>
                        <div class="help-text">Total appointments, pending approvals, completed visits, and trend reporting can be added here.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
</body>
</html>
