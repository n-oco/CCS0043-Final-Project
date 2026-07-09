<?php
// functions.php

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: student_auth.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: student_auth.php');
        exit;
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function status_badge_class(string $status): string
{
    return match (strtolower($status)) {
        'pending' => 'badge badge-pending',
        'approved' => 'badge badge-approved',
        'completed' => 'badge badge-completed',
        'declined' => 'badge badge-declined',
        'cancelled' => 'badge badge-cancelled',
        default => 'badge badge-neutral',
    };
}

function appointment_action_label(string $status): string
{
    return match (strtolower($status)) {
        'pending' => 'Approve',
        'approved' => 'Mark Completed',
        'declined' => 'View',
        'completed' => 'View',
        'cancelled' => 'View',
        default => 'Review',
    };
}

function format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }
    return date('M d, Y', strtotime($date));
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    return date('M d, Y h:i A', strtotime($datetime));
}
