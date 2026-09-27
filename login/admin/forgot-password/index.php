<?php
require_once __DIR__ . '/../../../includes/auth.php';

// Admin/Owner is the top of the role hierarchy -- there's nobody above them to
// approve a password_reset_requests row (that's how Employee/Payroll Master recover,
// see /forgot-password/), so this is a self-service security-question check instead.
// The question/answer are set from more/staff/.
//
// Two steps, both POSTs to this same page: step 1 looks up the username and shows
// its question; step 2 checks the answer and issues a temporary password. The
// username travels between steps as a hidden field rather than a second session key,
// since nothing here is sensitive until the answer is actually checked.

$pageTitle = 'Admin Password Recovery';
$step = 'lookup';
$username = trim($_POST['username'] ?? '');
$question = null;
$error = '';
$tempPassword = null;

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage'])) {
    if ($_POST['stage'] === 'lookup') {
        $stmt = $db->prepare("SELECT security_question FROM users WHERE username = ? AND role = 'admin'");
        $stmt->execute([$username]);
        $found = $stmt->fetchColumn();

        if ($found === false || $found === null) {
            $error = 'No admin account with that username has a security question set. Contact another Owner/Admin.';
        } else {
            $question = $found;
            $step = 'answer';
        }
    } elseif ($_POST['stage'] === 'answer') {
        $answer = trim($_POST['answer'] ?? '');
        $stmt = $db->prepare("SELECT id, security_question, security_answer_hash FROM users WHERE username = ? AND role = 'admin'");
        $stmt->execute([$username]);
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

include __DIR__ . '/../../../includes/head.php';
?>

<main class="min-h-screen flex items-center justify-center px-4 bg-gray-900 dark:bg-surface">
  <div class="w-full max-w-md bg-surface-card border border-surface-border rounded-2xl px-8 py-10 shadow-xl">
    <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" class="h-14 w-14 rounded-full mx-auto">
    <h1 class="mt-4 text-2xl font-extrabold text-white text-center mb-2">Admin Password Recovery</h1>

    <?php if ($error): ?>
      <p class="mb-4 text-center text-sm text-red-400"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($step === 'lookup'): ?>
      <p class="text-center text-sm text-gray-400 mb-6">Enter your admin username to see your security question.</p>
      <form method="post" class="space-y-4">
        <input type="hidden" name="stage" value="lookup">
        <input type="text" name="username" placeholder="Username" required
               class="w-full bg-transparent border border-surface-border rounded-full px-5 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-brand-yellow transition">
        <button type="submit" class="block w-full text-center bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">CONTINUE</button>
      </form>

    <?php elseif ($step === 'answer'): ?>
      <p class="text-center text-sm text-gray-300 mb-6"><?php echo htmlspecialchars($question); ?></p>
      <form method="post" class="space-y-4">
        <input type="hidden" name="stage" value="answer">
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES); ?>">
        <input type="text" name="answer" placeholder="Your answer" required autofocus
               class="w-full bg-transparent border border-surface-border rounded-full px-5 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-brand-yellow transition">
        <button type="submit" class="block w-full text-center bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">VERIFY</button>
      </form>

    <?php elseif ($step === 'done'): ?>
      <p class="text-center text-sm text-gray-300 mb-3">Verified. Your temporary password is:</p>
      <p class="text-center text-2xl font-mono font-bold text-brand-yellow tracking-widest mb-6"><?php echo htmlspecialchars($tempPassword); ?></p>
      <p class="text-center text-xs text-gray-500 mb-6">Shown once. You'll be asked to set your own password on next login.</p>
      <a href="<?php echo BASE_PATH; ?>/login/admin/" class="block w-full text-center bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">GO TO LOGIN</a>
    <?php endif; ?>

    <div class="mt-6 text-center text-sm">
      <a href="<?php echo BASE_PATH; ?>/login/admin/" class="text-gray-400 hover:underline">Back to Admin Login</a>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../../includes/foot.php'; ?>
