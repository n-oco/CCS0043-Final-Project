<?php
require_once 'db.php';
require_once 'functions.php';
require_role('student');

$user_id = (int) $_SESSION['user_id'];

// Book appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    $schedule_id = (int) ($_POST['schedule_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($schedule_id <= 0 || $reason === '') {
        set_flash('error', 'Please choose a schedule slot and enter a reason for the visit.');
    } else {
        $stmt = $mysqli->prepare('SELECT schedule_id, is_available FROM clinic_schedule WHERE schedule_id = ? LIMIT 1');
        $stmt->bind_param('i', $schedule_id);
        $stmt->execute();
        $slot = $stmt->get_result()->fetch_assoc();

        if (!$slot || (int) $slot['is_available'] !== 1) {
            set_flash('error', 'That time slot is no longer available.');
        } else {
            $mysqli->begin_transaction();
            try {
                $stmt = $mysqli->prepare('INSERT INTO appointments (student_id, schedule_id, reason, status, notes, created_at, updated_at) VALUES (?, ?, ?, "Pending", ?, NOW(), NOW())');
                $stmt->bind_param('iiss', $user_id, $schedule_id, $reason, $notes);
                $stmt->execute();

                $stmt = $mysqli->prepare('UPDATE clinic_schedule SET is_available = 0 WHERE schedule_id = ?');
                $stmt->bind_param('i', $schedule_id);
                $stmt->execute();

                $mysqli->commit();
                set_flash('success', 'Appointment request submitted.');
                header('Location: student_dashboard.php');
                exit;
            } catch (Throwable $e) {
                $mysqli->rollback();
                set_flash('error', 'Unable to submit your appointment request.');
            }
        }
    }
}

// Reschedule appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reschedule') {
    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
    $schedule_id = (int) ($_POST['schedule_id'] ?? 0);

    $stmt = $mysqli->prepare('SELECT appointment_id, status FROM appointments WHERE appointment_id = ? AND student_id = ? LIMIT 1');
    $stmt->bind_param('ii', $appointment_id, $user_id);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();

    if (!$appointment || strtolower($appointment['status']) !== 'pending') {
        set_flash('error', 'Only pending appointments can be rescheduled.');
    } else {
        $stmt = $mysqli->prepare('SELECT schedule_id, is_available FROM clinic_schedule WHERE schedule_id = ? LIMIT 1');
        $stmt->bind_param('i', $schedule_id);
        $stmt->execute();
        $slot = $stmt->get_result()->fetch_assoc();

        if (!$slot || (int) $slot['is_available'] !== 1) {
            set_flash('error', 'The selected slot is not available.');
        } else {
            $stmt = $mysqli->prepare('SELECT schedule_id FROM appointments WHERE appointment_id = ? AND student_id = ? LIMIT 1');
            $stmt->bind_param('ii', $appointment_id, $user_id);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc();
            if (!$current) {
                set_flash('error', 'Appointment not found.');
            } else {
                $mysqli->begin_transaction();
                try {
                    $stmt = $mysqli->prepare('UPDATE appointments SET schedule_id = ?, updated_at = NOW() WHERE appointment_id = ? AND student_id = ?');
                    $stmt->bind_param('iii', $schedule_id, $appointment_id, $user_id);
                    $stmt->execute();

                    $stmt = $mysqli->prepare('UPDATE clinic_schedule SET is_available = 1 WHERE schedule_id = ?');
                    $old_schedule_id = (int) $current['schedule_id'];
                    $stmt->bind_param('i', $old_schedule_id);
                    $stmt->execute();

                    $stmt = $mysqli->prepare('UPDATE clinic_schedule SET is_available = 0 WHERE schedule_id = ?');
                    $stmt->bind_param('i', $schedule_id);
                    $stmt->execute();

                    $mysqli->commit();
                    set_flash('success', 'Appointment rescheduled successfully.');
                } catch (Throwable $e) {
                    $mysqli->rollback();
                    set_flash('error', 'Unable to reschedule the appointment.');
                }
            }
        }
    }
    header('Location: student_dashboard.php');
    exit;
}

// Cancel appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
    $stmt = $mysqli->prepare('SELECT schedule_id FROM appointments WHERE appointment_id = ? AND student_id = ? AND status IN ("Pending", "Approved") LIMIT 1');
    $stmt->bind_param('ii', $appointment_id, $user_id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    if ($current) {
        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare('UPDATE appointments SET status = "Cancelled", updated_at = NOW() WHERE appointment_id = ? AND student_id = ?');
            $stmt->bind_param('ii', $appointment_id, $user_id);
            $stmt->execute();

            $stmt = $mysqli->prepare('UPDATE clinic_schedule SET is_available = 1 WHERE schedule_id = ?');
            $schedule_id = (int) $current['schedule_id'];
            $stmt->bind_param('i', $schedule_id);
            $stmt->execute();

            $mysqli->commit();
            set_flash('success', 'Appointment cancelled.');
        } catch (Throwable $e) {
            $mysqli->rollback();
            set_flash('error', 'Unable to cancel the appointment.');
        }
    } else {
        set_flash('error', 'Unable to cancel the appointment.');
    }
    header('Location: student_dashboard.php');
    exit;
}

$flash = get_flash();

$stmt = $mysqli->prepare('SELECT s.student_id, s.student_number, s.full_name, s.course, s.contact_no, u.email FROM students s JOIN users u ON u.user_id = s.student_id WHERE s.student_id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$selected_date = $_GET['date'] ?? date('Y-m-d');
$stmt = $mysqli->prepare('SELECT schedule_id, date, time_slot, is_available FROM clinic_schedule WHERE date = ? AND is_available = 1 ORDER BY time_slot ASC');
$stmt->bind_param('s', $selected_date);
$stmt->execute();
$available_slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $mysqli->prepare('SELECT status, COUNT(*) AS total FROM appointments WHERE student_id = ? GROUP BY status');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$status_counts = ['Pending' => 0, 'Approved' => 0, 'Completed' => 0, 'Cancelled' => 0];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $status_counts[$row['status']] = (int) $row['total'];
}

$stmt = $mysqli->prepare('SELECT a.appointment_id, a.reason, a.status, a.notes, a.created_at, a.updated_at, s.date, s.time_slot FROM appointments a JOIN clinic_schedule s ON s.schedule_id = a.schedule_id WHERE a.student_id = ? ORDER BY s.date DESC, s.time_slot DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$edit_id = (int) ($_GET['edit'] ?? 0);
$edit_appointment = null;
if ($edit_id > 0) {
    $stmt = $mysqli->prepare('SELECT a.appointment_id, a.status, a.reason, a.notes, s.date, s.time_slot FROM appointments a JOIN clinic_schedule s ON s.schedule_id = a.schedule_id WHERE a.appointment_id = ? AND a.student_id = ? LIMIT 1');
    $stmt->bind_param('ii', $edit_id, $user_id);
    $stmt->execute();
    $edit_appointment = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand">
            <div class="brand-mark">CM</div>
            <div>
                <div class="brand-title">FEU Tech Student Clinic Appointment System</div>
                <div class="brand-subtitle">Student dashboard and booking form</div>
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
        <h2>Student Menu</h2>
        <nav class="side-nav">
            <a class="active" href="student_dashboard.php">Dashboard</a>
            <a href="#book">Book Appointment</a>
            <a href="#my-appointments">My Appointments</a>
            <a href="#profile">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <section class="main-content">
        <?php if ($flash): ?>
            <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <div class="stat-grid">
            <div class="stat-card"><div class="stat-label">Today</div><div class="stat-value"><?= (int) date('d') ?></div></div>
            <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value"><?= (int) $status_counts['Pending'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Approved</div><div class="stat-value"><?= (int) $status_counts['Approved'] ?></div></div>
            <div class="stat-card"><div class="stat-label">Cancelled</div><div class="stat-value"><?= (int) $status_counts['Cancelled'] ?></div></div>
        </div>

        <div class="split-grid">
            <div class="form-card" id="book">
                <h2 class="panel-title">Book Appointment</h2>
                <p class="panel-subtitle">Choose a purpose, date, and available time slot.</p>
                <form method="get" class="inline" style="margin-bottom:14px;">
                    <label class="label" style="margin-bottom:0;">Date</label>
                    <input class="input" style="max-width:220px;" type="date" name="date" value="<?= e($selected_date) ?>">
                    <button class="btn-outline" type="submit">Check Availability</button>
                </form>
                <form method="post">
                    <input type="hidden" name="action" value="book">
                    <div class="field">
                        <label class="label">Purpose</label>
                        <input class="input" type="text" name="reason" placeholder="Checkup, Certificate, Consultation" required>
                    </div>
                    <div class="field">
                        <label class="label">Preferred Date</label>
                        <input class="input" type="date" value="<?= e($selected_date) ?>" disabled>
                    </div>
                    <div class="field">
                        <label class="label">Available Time Slot</label>
                        <select class="select" name="schedule_id" required>
                            <option value="">Select a slot</option>
                            <?php foreach ($available_slots as $slot): ?>
                                <option value="<?= (int) $slot['schedule_id'] ?>"><?= e(date('h:i A', strtotime($slot['time_slot']))) ?> — <?= e(format_date($slot['date'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label">Notes</label>
                        <textarea class="textarea" name="notes" placeholder="Optional notes"></textarea>
                    </div>
                    <button class="btn" type="submit">Submit request</button>
                </form>
            </div>

            <div class="panel">
                <h2 class="panel-title">Availability Calendar</h2>
                <div class="grid-3">
                    <?php if ($available_slots): ?>
                        <?php foreach (array_slice($available_slots, 0, 12) as $slot): ?>
                            <div class="feature-card" style="padding:14px; text-align:center;">Slot<br><strong><?= e(date('h:i A', strtotime($slot['time_slot']))) ?></strong></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="help-text">No available slots for this date. Choose another day.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($edit_appointment): ?>
        <div class="form-card">
            <h2 class="panel-title">Reschedule Appointment</h2>
            <p class="panel-subtitle">Edit your pending booking only.</p>
            <div class="help-text" style="margin-bottom:12px;">Current appointment: <?= e(format_date($edit_appointment['date'])) ?> at <?= e(date('h:i A', strtotime($edit_appointment['time_slot']))) ?> — <?= e($edit_appointment['reason']) ?> (<?= e($edit_appointment['status']) ?>)</div>
            <form method="get" class="inline" style="margin-bottom:16px;">
                <label class="label" style="margin-bottom:0;">New Date</label>
                <input class="input" style="max-width:220px;" type="date" name="date" value="<?= e($selected_date) ?>">
                <input type="hidden" name="edit" value="<?= (int) $edit_id ?>">
                <button class="btn-outline" type="submit">Load Slots</button>
            </form>
            <form method="post" class="inline">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="appointment_id" value="<?= (int) $edit_appointment['appointment_id'] ?>">
                <select class="select" name="schedule_id" required>
                    <option value="">Select new slot</option>
                    <?php foreach ($available_slots as $slot): ?>
                        <option value="<?= (int) $slot['schedule_id'] ?>"><?= e(date('h:i A', strtotime($slot['time_slot']))) ?> — <?= e(format_date($slot['date'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Save changes</button>
                <a class="btn-outline" href="student_dashboard.php">Cancel</a>
            </form>
        </div>
        <?php endif; ?>

        <div class="table-card" id="my-appointments">
            <h2 class="panel-title">My Appointments</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= e(format_date($appointment['date'])) ?></td>
                            <td><?= e(date('h:i A', strtotime($appointment['time_slot']))) ?></td>
                            <td><?= e($appointment['reason']) ?></td>
                            <td><span class="<?= e(status_badge_class($appointment['status'])) ?>"><?= e($appointment['status']) ?></span></td>
                            <td>
                                <div class="actions">
                                    <?php if (strtolower($appointment['status']) === 'pending'): ?>
                                        <a class="btn-outline" href="student_dashboard.php?edit=<?= (int) $appointment['appointment_id'] ?>&date=<?= e($selected_date) ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Cancel this appointment?');">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointment_id" value="<?= (int) $appointment['appointment_id'] ?>">
                                            <button class="btn-danger" type="submit">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted small">View only</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="split-grid" id="profile">
            <div class="panel">
                <h2 class="panel-title">Profile</h2>
                <div class="help-text"><strong>Name:</strong> <?= e($student['full_name'] ?? '') ?></div>
                <div class="help-text"><strong>Student No.:</strong> <?= e($student['student_number'] ?? '') ?></div>
                <div class="help-text"><strong>Course:</strong> <?= e($student['course'] ?? '') ?></div>
                <div class="help-text"><strong>Contact:</strong> <?= e($student['contact_no'] ?? '') ?></div>
                <div class="help-text"><strong>Email:</strong> <?= e($student['email'] ?? '') ?></div>
            </div>
            <div class="panel">
                <h2 class="panel-title">Status Summary</h2>
                <div class="help-text">Pending: <?= (int) $status_counts['Pending'] ?> appointments</div>
                <div class="help-text">Approved: <?= (int) $status_counts['Approved'] ?> appointments</div>
                <div class="help-text">Completed: <?= (int) $status_counts['Completed'] ?> appointments</div>
                <div class="help-text">Cancelled: <?= (int) $status_counts['Cancelled'] ?> appointments</div>
            </div>
        </div>
    </section>
</main>
</body>
</html>
