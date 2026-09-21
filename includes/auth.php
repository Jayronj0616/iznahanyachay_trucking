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
