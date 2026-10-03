<?php
require_once __DIR__ . '/../includes/auth.php';

// No mailer exists in this app, so nothing here can email a reset link. Two recovery
// paths, both reachable from this one page since there's only one shared login now
// (the client asked not to show a separate Owner/Admin sign-in at all):
//   - Employee / Payroll Master: creates a password_reset_requests row. Admin/Owner
//     reviews it in more/staff/ and issues a temporary password.
//   - Admin/Owner: has nobody above them to route a request to, so they answer the
//     security question they set in more/staff/ instead and get a temporary
//     password immediately.
// Which path a given email takes is invisible to the visitor until they submit it --
// the form never asks "are you an admin?".

$pageTitle = 'Forgot Password';
$db = getDB();

$step = 'email';
$email = trim($_POST['email'] ?? '');
$question = null;
$error = '';
$message = '';
$tempPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage'])) {
    if ($_POST['stage'] === 'lookup') {
        if ($email === '') {
            $error = 'Please enter your email or username.';
        } else {
            $stmt = $db->prepare('SELECT id, role, security_question FROM users WHERE email = ? OR username = ? ORDER BY (email = ?) DESC LIMIT 1');
            $stmt->execute([$email, $email, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['role'] === 'admin' && $user['security_question']) {
                $question = $user['security_question'];
                $step = 'answer';
            } else {
                // Same confirmation whether or not the account exists (and for an admin
                // account that never set a security question -- nothing to challenge
                // them with), so this form can't be used to test which emails are
                // registered.
                if ($user && $user['role'] !== 'admin') {
                    $stmt = $db->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending'");
                    $stmt->execute([$user['id']]);
                    if (!$stmt->fetch()) {
                        $stmt = $db->prepare('INSERT INTO password_reset_requests (user_id) VALUES (?)');
                        $stmt->execute([$user['id']]);
                    }
                }
                $message = "If that email has an account, we've sent a reset request to the admin. You'll be given a temporary password once it's reviewed.";
                $step = 'sent'; // anything other than 'email' -- otherwise the template's own
                                 // first branch (step === 'email') would redraw the form instead
                                 // of this message, since $step was never otherwise changed here.
            }
        }
    } elseif ($_POST['stage'] === 'answer') {
        $answer = trim($_POST['answer'] ?? '');
        $stmt = $db->prepare("SELECT id, security_question, security_answer_hash FROM users WHERE (email = ? OR username = ?) AND role = 'admin'");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['security_answer_hash'] || !password_verify(mb_strtolower($answer), $user['security_answer_hash'])) {
            $error = 'That answer did not match.';
            $question = $user['security_question'] ?? null;
            $step = 'answer';
        } else {
            $tempPassword = bin2hex(random_bytes(4)); // e.g. "a1b2c3d4" -- short enough to read off screen, long enough not to guess
            $stmt = $db->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?');
            $stmt->execute([password_hash($tempPassword, PASSWORD_DEFAULT), $user['id']]);
            $step = 'done';
        }
    }
}

include __DIR__ . '/../includes/head.php';
?>

<main class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-md bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-2xl px-8 py-10 shadow-xl">
    <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" class="h-14 w-14 rounded-full mx-auto">
    <h1 class="mt-4 text-2xl font-extrabold text-gray-900 dark:text-white text-center mb-2">Forgot Password</h1>

    <?php if ($error): ?>
      <p class="mb-4 text-center text-sm text-red-500"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($step === 'email'): ?>
      <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-8">
        Enter your email or username. There's no automatic email reset yet.
      </p>
      <form method="post" class="space-y-4">
        <input type="hidden" name="stage" value="lookup">
        <input
          type="text"
          name="email"
          autocomplete="username"
          autocapitalize="none"
          placeholder="Enter your email or username"
          required
          class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
        >
        <button type="submit" class="block w-full text-center bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
          CONTINUE
        </button>
      </form>

    <?php elseif ($step === 'answer'): ?>
      <p class="text-center text-sm text-gray-600 dark:text-gray-300 mb-6"><?php echo htmlspecialchars($question); ?></p>
      <form method="post" class="space-y-4">
        <input type="hidden" name="stage" value="answer">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>">
        <input
          type="text"
          name="answer"
          placeholder="Your answer"
          required
          autofocus
          class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
        >
        <button type="submit" class="block w-full text-center bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
          VERIFY
        </button>
      </form>

    <?php elseif ($step === 'done'): ?>
      <p class="text-center text-sm text-gray-600 dark:text-gray-300 mb-3">Verified. Your temporary password is:</p>
      <p class="text-center text-2xl font-mono font-bold text-brand-orange tracking-widest mb-6"><?php echo htmlspecialchars($tempPassword); ?></p>
      <p class="text-center text-xs text-gray-500 dark:text-gray-400 mb-6">Shown once. You'll be asked to set your own password on next login.</p>

    <?php elseif ($message): ?>
      <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm mb-2"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="mt-6 text-center text-sm">
      <a href="<?php echo BASE_PATH; ?>/" class="text-gray-500 dark:text-gray-400 hover:underline">Back to Login</a>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/foot.php'; ?>
