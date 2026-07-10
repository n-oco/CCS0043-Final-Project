<?php
require_once 'db.php';
require_once 'functions.php';

$tab = $_GET['tab'] ?? 'student';
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student') {
        header('Location: student_dashboard.php');
        exit;
    }
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $register_role = ($tab === 'admin') ? 'admin' : 'student';
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $student_number = trim($_POST['student_number'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $contact_no = trim($_POST['contact_no'] ?? '');

        if ($email === '' || $password === '') {
            $errors[] = 'Please complete the required registration fields.';
        }
        if (!preg_match('/^[A-Za-z0-9._%+-]+@feutech\.edu\.ph$/i', $email)) {
            $errors[] = 'Use a valid FEU Tech school email address.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if ($register_role === 'student') {
            if ($student_number === '' || $full_name === '' || $course === '') {
                $errors[] = 'Please complete all student registration fields.';
            }
        }

        if (!$errors) {
            $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = 'Email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $mysqli->prepare('INSERT INTO users (role, email, password_hash, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->bind_param('sss', $register_role, $email, $hash);
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;

                    if ($register_role === 'student') {
                        $stmt2 = $mysqli->prepare('INSERT INTO students (student_id, student_number, full_name, course, contact_no) VALUES (?, ?, ?, ?, ?)');
                        $stmt2->bind_param('issss', $user_id, $student_number, $full_name, $course, $contact_no);
                        $stmt2->execute();

                        $stmt3 = $mysqli->prepare('INSERT INTO patient_records (student_id, allergies, conditions, emergency_contact, updated_at) VALUES (?, "", "", "", NOW())');
                        $stmt3->bind_param('i', $user_id);
                        $stmt3->execute();

                        set_flash('success', 'Registration successful. Please log in.');
                        header('Location: student_auth.php');
                        exit;
                    }

                    $_SESSION['user_id'] = (int) $user_id;
                    $_SESSION['role'] = 'admin';
                    $_SESSION['email'] = $email;
                    set_flash('success', 'Admin account created successfully.');
                    header('Location: admin_dashboard.php');
                    exit;
                }
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'student';

        if ($email === '' || $password === '') {
            $errors[] = 'Please enter your email and password.';
        } else {
            $stmt = $mysqli->prepare('SELECT user_id, role, email, password_hash FROM users WHERE email = ? AND role = ? LIMIT 1');
            $stmt->bind_param('ss', $email, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];

                if ($user['role'] === 'student') {
                    header('Location: student_dashboard.php');
                } else {
                    header('Location: admin_dashboard.php');
                }
                exit;
            }
            $errors[] = 'Invalid login details.';
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration / Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand">
            <div class="brand-mark">CM</div>
            <div>
                <div class="brand-title">FEU Tech Student Clinic Appointment System</div>
                <div class="brand-subtitle">Student registration and login portal</div>
            </div>
        </div>
        <nav class="nav-links">
            <a class="nav-link" href="index.php">Home</a>
            <a class="nav-link" href="student_auth.php?tab=student">Student Login</a>
            <a class="nav-link" href="student_auth.php?tab=admin">Admin Login</a>
        </nav>
    </div>
</header>

<main class="container auth-wrap">
    <section class="auth-card">
        <h1 class="section-title" style="font-size:2rem;">Student Portal</h1>
        <p class="help-text"><?php echo $tab === 'admin' ? 'Create an admin account or log in to access the dashboard and clinic management features.' : 'Register using a school email and log in with your password.'; ?></p>

        <?php if ($flash): ?>
            <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="flash error"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab <?= $tab === 'student' ? 'active' : '' ?>" type="button" onclick="location.href='student_auth.php?tab=student'">Student</button>
            <button class="tab <?= $tab === 'admin' ? 'active' : '' ?>" type="button" onclick="location.href='student_auth.php?tab=admin'">Staff</button>
        </div>

        <div class="grid-2">
            <form class="form-card" method="post" action="student_auth.php?tab=<?= e($tab) ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="role" value="<?= $tab === 'admin' ? 'admin' : 'student' ?>">
                <h2 class="card-title">Login</h2>
                <p class="help-text">Use your registered email and password.</p>
                <div class="field">
                    <label class="label">Email</label>
                    <input class="input" type="email" name="email" placeholder="School email" required>
                </div>
                <div class="field">
                    <label class="label">Password</label>
                    <input class="input" type="password" name="password" placeholder="Password" required>
                </div>
                <button class="btn" type="submit">Login</button>
            </form>

            <?php if ($tab === 'student'): ?>
            <form class="form-card" method="post" action="student_auth.php?tab=student">
                <input type="hidden" name="action" value="register">
                <h2 class="card-title">Register</h2>
                <p class="help-text">Create a student account to request clinic appointments.</p>
                <div class="field">
                    <label class="label">Student Number</label>
                    <input class="input" type="text" name="student_number" placeholder="2024-0001" required>
                </div>
                <div class="field">
                    <label class="label">Full Name</label>
                    <input class="input" type="text" name="full_name" placeholder="Juan Dela Cruz" required>
                </div>
                <div class="field">
                    <label class="label">Course</label>
                    <input class="input" type="text" name="course" placeholder="BSIT" required>
                </div>
                <div class="field">
                    <label class="label">Contact No.</label>
                    <input class="input" type="text" name="contact_no" placeholder="09xx xxx xxxx">
                </div>
                <div class="field">
                    <label class="label">School Email</label>
                    <input class="input" type="email" name="email" placeholder="name@feutech.edu.ph" required>
                </div>
                <div class="field">
                    <label class="label">Password</label>
                    <input class="input" type="password" name="password" placeholder="At least 6 characters" required>
                </div>
                <button class="btn-secondary" type="submit">Register</button>
            </form>
            <?php else: ?>
            <form class="form-card" method="post" action="student_auth.php?tab=admin">
                <input type="hidden" name="action" value="register">
                <h2 class="card-title">Register Admin</h2>
                <p class="help-text">Create a new admin account to access the dashboard and admin features.</p>
                <div class="field">
                    <label class="label">Email</label>
                    <input class="input" type="email" name="email" placeholder="name@feutech.edu.ph" required>
                </div>
                <div class="field">
                    <label class="label">Password</label>
                    <input class="input" type="password" name="password" placeholder="At least 6 characters" required>
                </div>
                <p class="help-text">After registration, you will be redirected to the Admin Dashboard automatically.</p>
                <button class="btn-secondary" type="submit">Create Admin Account</button>
            </form>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
