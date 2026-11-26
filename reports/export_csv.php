<?php
/**
 * Generic CSV Export Endpoint
 * Academics School Management System
 *
 * SECURITY (C3): every export is role-gated AND strictly school-scoped.
 * - requireStaff()/requireAdmin() before any query
 * - every WHERE clause binds school_id with equality (no `OR school_id IS NULL`)
 * - tasks export is staff-only and school-scoped
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();

$type = (string)($_GET['type'] ?? 'students');
$allowedTypes = ['students', 'courses', 'tasks', 'attendance', 'fees'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unsupported export type.');
}

// Resolve a strict school scope. Never allow a null/zero scope to fall through
// into an unscoped query: that is exactly the leak this endpoint had.
$schoolId = currentSchoolId();
if ($schoolId === null && !isSuperAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'School context is required for exports.';
    exit;
}

// Filename and headers
$filename = 'export_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $type) . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

if ($type === 'students') {
    requireStaff();
    fputcsv($out, ['Student Number', 'Full Name', 'Email', 'Phone', 'Gender', 'Status', 'Enrollment Date']);
    $stmt = $pdo->prepare("SELECT student_number, full_name, email, phone, gender, status, enrollment_date
                           FROM students WHERE school_id = ? ORDER BY id DESC");
    $stmt->execute([$schoolId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, array_map('safeCsvCell', array_values($row)));
    }
} elseif ($type === 'courses') {
    requireStaff();
    fputcsv($out, ['Course Code', 'Course Name', 'Credits', 'Status', 'Start Date', 'End Date']);
    $stmt = $pdo->prepare("SELECT course_code, course_name, credits, status, start_date, end_date
                           FROM courses WHERE school_id = ? ORDER BY id DESC");
    $stmt->execute([$schoolId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, array_map('safeCsvCell', array_values($row)));
    }
} elseif ($type === 'tasks') {
    // Tasks are staff-only data (they can contain private grading notes).
    requireStaff();
    fputcsv($out, ['Task Title', 'Priority', 'Status', 'Due Date', 'Completed At']);
    $stmt = $pdo->prepare("SELECT title, priority, status, due_date, completed_at
                           FROM tasks WHERE school_id = ? ORDER BY id DESC");
    $stmt->execute([$schoolId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, array_map('safeCsvCell', array_values($row)));
    }
} elseif ($type === 'attendance') {
    requireStaff();
    fputcsv($out, ['Student Number', 'Student Name', 'Date', 'Status', 'Remarks']);
    $stmt = $pdo->prepare("SELECT s.student_number, s.full_name, a.attendance_date, a.status, a.remarks
                           FROM attendance a
                           JOIN students s ON a.student_id = s.id AND s.school_id = a.school_id
                           WHERE a.school_id = ?
                           ORDER BY a.attendance_date DESC");
    $stmt->execute([$schoolId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, array_map('safeCsvCell', array_values($row)));
    }
} elseif ($type === 'fees') {
    requireAdmin();
    fputcsv($out, ['Invoice #', 'Student Name', 'Title', 'Amount', 'Due Date', 'Status', 'Paid Date']);
    $stmt = $pdo->prepare("SELECT i.invoice_number, s.full_name, i.title, i.amount, i.due_date, i.status, i.paid_at
                           FROM invoices i
                           JOIN students s ON i.student_id = s.id AND s.school_id = i.school_id
                           WHERE i.school_id = ?
                           ORDER BY i.issued_at DESC");
    $stmt->execute([$schoolId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, array_map('safeCsvCell', array_values($row)));
    }
} else {
    fputcsv($out, ['Error', 'Unsupported export type']);
}

fclose($out);
exit;
