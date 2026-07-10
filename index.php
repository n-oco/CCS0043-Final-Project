<?php
require_once 'db.php';
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FEU Tech Student Clinic Appointment System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand">
            <div class="brand-mark">CM</div>
            <div>
                <div class="brand-title">FEU Tech Student Clinic Appointment System</div>
                <div class="brand-subtitle">Book appointments, track status, and manage clinic workflows</div>
            </div>
        </div>
        <nav class="nav-links">
            <a class="nav-link" href="student_auth.php">Student Login</a>
            <a class="nav-link" href="student_auth.php?tab=admin">Admin Login</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="hero">
        <div class="hero-grid">
            <div>
                <h1>Your Health Is Our Priority</h1>
                <p>Book clinic appointments, track your status, and access student medical support online. The system follows the clinic wireframe structure with a clear patient portal, a staff portal, and a calm green-and-gold visual theme.</p>
                <div class="hero-cta">
                    <a class="btn" href="student_auth.php">Student Login</a>
                    <a class="btn-outline" href="student_auth.php?tab=admin">Admin Login</a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="illustration">
                    Hero Illustration / Clinic Image Placeholder
                    <div class="small muted" style="margin-top:12px;">Centered, friendly, and low-fatigue landing page layout</div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-grid" aria-label="About and feature cards">
        <article class="feature-card">
            <div class="feature-icon">◎</div>
            <h3>Easiest way around</h3>
            <p>Book in a few clicks with a simple student flow that keeps the important actions close together.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon">⌂</div>
            <h3>Anywhere, anytime</h3>
            <p>Students can request appointments remotely, then check their status without going to the clinic.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon">❤</div>
            <h3>Track Your Health</h3>
            <p>Clinic staff can review requests, update records, and manage the day’s queue from one screen.</p>
        </article>
    </section>

    <section class="section-grid" style="grid-template-columns: repeat(3, 1fr); margin-top:-10px;">
        <article class="feature-card">
            <div class="feature-icon">👤</div>
            <h3>Student</h3>
            <p>Student portal for login, booking, tracking, and profile viewing.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon">🏥</div>
            <h3>Clinic</h3>
            <p>Clinic schedule management, appointment queue, and daily operations.</p>
        </article>
        <article class="feature-card">
            <div class="feature-icon">🩺</div>
            <h3>Doctor / Nurse</h3>
            <p>Admin records and approval workflow for medical support and appointments.</p>
        </article>
    </section>
</main>

<div class="footer-note">FEU Tech Student Clinic Appointment System — native PHP, MySQLi, HTML, and CSS</div>
</body>
</html>
