<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void {
    if (!isset($_SESSION['user'])) {
        header('Location: ' . BASE_PATH . '/login/');
        exit;
    }

    $changePasswordPath = BASE_PATH . '/more/change-password/';
    $onChangePasswordPage = strpos($_SERVER['REQUEST_URI'], $changePasswordPath) === 0;
    if (!empty($_SESSION['user']['must_change_password']) && !$onChangePasswordPage) {
        header('Location: ' . $changePasswordPath);
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user']['role'] !== 'admin') {
        header('Location: ' . BASE_PATH . '/home/');
        exit;
    }
}

// Payroll Master is "the one in charge of salary of computation" (the panel's own
// phrasing) -- Admin/Owner outranks Payroll Master and can do anything Payroll
// Master can, so this passes for either role. Anywhere Admin/Owner alone should act
// (finalizing a payslip, approving a proposed trip rate, editing contribution
// brackets) keeps using requireAdmin() instead.
function requirePayrollMaster(): void {
    requireLogin();
    if (!in_array($_SESSION['user']['role'], ['admin', 'payroll_master'], true)) {
        header('Location: ' . BASE_PATH . '/home/');
        exit;
    }
}

// The dashboard "logged in as" indicator (includes/topbar.php) and anywhere else a
// role needs to read as a person rather than a database value.
function roleLabel(?string $role, ?string $position = null): string {
    if ($role === 'admin') {
        return 'Owner/Admin';
    }
    if ($role === 'payroll_master') {
        return 'Payroll Master';
    }
    $positionLabels = [
        'driver' => 'Driver',
        'helper' => 'Helper',
        'dispatcher' => 'Dispatcher',
        'secretary' => 'Secretary',
        'maintenance' => 'Maintenance',
        'liaison' => 'Liaison',
        'operator_manager' => 'Operator Manager',
    ];
    return $positionLabels[$position] ?? 'Employee';
}

function attemptLogin(string $email, string $password, ?string &$failReason = null): bool {
    $stmt = getDB()->prepare('SELECT id, name, email, password, role, must_change_password FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        $failReason = 'invalid';
        return false;
    }

    // Position is read here rather than on every page render, because the bottom
    // nav needs it to decide which tabs to show and the nav is included by every
    // single page. One query at login instead of one per request.
    $position = null;
    if ($user['role'] === 'employee') {
        $stmt = getDB()->prepare('SELECT status, position FROM employee_profiles WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (($profile['status'] ?? null) === 'pending') {
            $failReason = 'pending';
            return false;
        }
        $position = $profile['position'] ?? null;
    }

    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'must_change_password' => (bool) $user['must_change_password'],
        'position' => $position,
    ];
    return true;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}
