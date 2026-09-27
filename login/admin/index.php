<?php
require_once __DIR__ . '/../../includes/auth.php';

// Already signed in as Admin/Owner? Nothing to do here.
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    header('Location: ' . BASE_PATH . '/home/');
    exit;
}

$pageTitle = 'Admin Login';
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $loginError = 'Please enter your username and password.';
    } else {
        $failReason = null;
        if (attemptAdminLogin($username, $password, $failReason)) {
            header('Location: ' . BASE_PATH . '/home/');
            exit;
        } else {
            $loginError = 'Invalid username or password.';
        }
    }
}

include __DIR__ . '/../../includes/head.php';
?>

<!-- Deliberately its own page, not a modal like the employee login -- the panel
     asked that Owner/Admin sign-in look and behave differently from the employee
     sign-in, not just share a form with a role toggle. Dark, badge-style framing
     instead of the bright card the employee modal uses. -->
<main class="min-h-screen flex items-center justify-center px-4 bg-gray-900 dark:bg-surface">
  <div class="w-full max-w-md bg-surface-card border border-surface-border rounded-2xl px-8 py-10 shadow-xl">
    <div class="flex justify-center">
      <span class="inline-flex items-center gap-2 bg-brand-orange/15 text-brand-orange text-xs font-bold uppercase tracking-widest px-3 py-1.5 rounded-full">
        🔒 Owner / Admin
      </span>
    </div>
    <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" class="h-14 w-14 rounded-full mx-auto mt-5">
    <h1 class="mt-4 text-2xl font-extrabold text-white text-center mb-1">Admin Login</h1>
    <p class="text-center text-sm text-gray-400 mb-8">Restricted to Owner/Admin accounts.</p>

    <?php if ($loginError): ?>
      <p class="mb-4 text-center text-sm text-red-400"><?php echo htmlspecialchars($loginError); ?></p>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <input
        type="text"
        name="username"
        autocomplete="username"
        placeholder="Username"
        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
        required
        class="w-full bg-transparent border border-surface-border rounded-full px-5 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-brand-yellow transition"
      >
      <input
        type="password"
        name="password"
        autocomplete="current-password"
        placeholder="Password"
        required
        class="w-full bg-transparent border border-surface-border rounded-full px-5 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-brand-yellow transition"
      >
      <button type="submit" class="block w-full text-center bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
        LOGIN
      </button>
    </form>

    <div class="mt-5 flex items-center justify-between text-sm">
      <a href="<?php echo BASE_PATH; ?>/login/admin/forgot-password/" class="text-gray-400 hover:text-brand-orange transition">Forgot password?</a>
      <a href="<?php echo BASE_PATH; ?>/" class="font-semibold text-brand-orange hover:underline">Employee? Sign in here</a>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../includes/foot.php'; ?>
