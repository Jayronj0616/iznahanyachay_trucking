<?php
require_once __DIR__ . '/../includes/auth.php';

// No mailer exists in this app, so a reset can't be emailed -- this creates a
// password_reset_requests row instead, and whoever outranks the account (Admin/Owner
// reviews every request, in more/staff/) issues a temporary password from there.
// Admin/Owner accounts have nobody to route a request to, so they are pointed at the
// security-question recovery on /login/admin/forgot-password/ instead.

$pageTitle = 'Forgot Password';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Same confirmation whether or not the account exists, so this form can't be
        // used to test which emails are registered.
        if ($user && $user['role'] !== 'admin') {
            $stmt = $db->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$user['id']]);
            if (!$stmt->fetch()) {
                $stmt = $db->prepare('INSERT INTO password_reset_requests (user_id) VALUES (?)');
                $stmt->execute([$user['id']]);
            }
        }
        $message = "If that email has an account, we've sent a reset request to the admin. You'll be given a temporary password once it's reviewed.";
    }
}

include __DIR__ . '/../includes/head.php';
?>

<main class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-md bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-2xl px-8 py-10 shadow-xl">
    <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" class="h-14 w-14 rounded-full mx-auto">
    <h1 class="mt-4 text-2xl font-extrabold text-gray-900 dark:text-white text-center mb-2">Forgot Password</h1>
    <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-8">
      We'll send a reset request to your admin — there's no automatic email reset yet.
    </p>

    <?php if ($message): ?>
      <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm mb-6"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <p class="mb-4 text-center text-sm text-red-500"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (!$message): ?>
    <form method="post" class="space-y-4">
      <input
        type="email"
        name="email"
        autocomplete="email"
        placeholder="Enter your account email"
        required
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
      <button type="submit" class="block w-full text-center bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
        SEND REQUEST
      </button>
    </form>
    <?php endif; ?>

    <div class="mt-6 text-center text-sm">
      <a href="<?php echo BASE_PATH; ?>/" class="text-gray-500 dark:text-gray-400 hover:underline">Back to Login</a>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/foot.php'; ?>
