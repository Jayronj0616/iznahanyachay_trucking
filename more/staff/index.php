<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Staff Accounts';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;
$createdPassword = null;
$currentUserId = (int) $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_admin_login') {
        $username = trim($_POST['username'] ?? '');
        $securityQuestion = trim($_POST['security_question'] ?? '');
        $securityAnswer = trim($_POST['security_answer'] ?? '');

        if ($username === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            $error = 'Username must be 3-50 characters (letters, numbers, . _ -  only).';
        } else {
            try {
                if ($securityQuestion !== '' && $securityAnswer !== '') {
                    // Case-insensitive on purpose -- an exact-case match is a common way
                    // for a legitimate answer to fail a recovery check by accident.
                    $stmt = $db->prepare('UPDATE users SET username = ?, security_question = ?, security_answer_hash = ? WHERE id = ?');
                    $stmt->execute([$username, $securityQuestion, password_hash(mb_strtolower($securityAnswer), PASSWORD_DEFAULT), $currentUserId]);
                } else {
                    $stmt = $db->prepare('UPDATE users SET username = ? WHERE id = ?');
                    $stmt->execute([$username, $currentUserId]);
                }
                $success = 'Your admin sign-in was updated. The shared employee login no longer accepts your email — use Admin Login with your username instead.';
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate entry') ? 'That username is already taken.' : 'Update failed: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'create_payroll_master') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$password) {
            $error = 'Name, email, and temporary password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            try {
                $stmt = $db->prepare('INSERT INTO users (name, email, password, role, must_change_password) VALUES (?, ?, ?, ?, 1)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'payroll_master']);
                $success = 'Payroll Master account created.';
                $createdPassword = $password;
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate entry') ? 'That email is already in use by another account.' : 'Creation failed: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'deactivate_payroll_master') {
        // No status column on a staff account (unlike employee_profiles) -- removing
        // access is done by scrambling the password so no known credential works,
        // rather than deleting the row and orphaning their payroll_runs history.
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'payroll_master'");
        $stmt->execute([password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $targetId]);
        $success = 'Payroll Master account access revoked.';
    } elseif ($action === 'resolve_reset_request') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';

        if (!in_array($decision, ['approved', 'declined'], true)) {
            $error = 'Invalid decision.';
        } else {
            $stmt = $db->prepare("SELECT * FROM password_reset_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                $error = 'Request not found or already resolved.';
            } else {
                $db->beginTransaction();
                try {
                    if ($decision === 'approved') {
                        $tempPassword = bin2hex(random_bytes(4));
                        $stmt = $db->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?');
                        $stmt->execute([password_hash($tempPassword, PASSWORD_DEFAULT), $request['user_id']]);
                        $createdPassword = $tempPassword;
                    }
                    $stmt = $db->prepare("UPDATE password_reset_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $stmt->execute([$decision, $currentUserId, $requestId]);
                    $db->commit();
                    $success = $decision === 'approved' ? 'Temporary password issued.' : 'Request declined.';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Could not resolve request: ' . $e->getMessage();
                }
            }
        }
    }
}

$me = $db->prepare('SELECT username, security_question FROM users WHERE id = ?');
$me->execute([$currentUserId]);
$myLogin = $me->fetch(PDO::FETCH_ASSOC);

$payrollMasters = $db->query("SELECT id, name, email, must_change_password FROM users WHERE role = 'payroll_master' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pendingRequests = $db->query(
    "SELECT prr.id, prr.requested_at, u.name, u.email, u.role
     FROM password_reset_requests prr
     JOIN users u ON u.id = prr.user_id
     WHERE prr.status = 'pending'
     ORDER BY prr.requested_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Staff Accounts</h1>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm space-y-1">
      <p><?php echo htmlspecialchars($success); ?></p>
      <?php if ($createdPassword): ?>
        <p>Temporary password: <span class="font-mono font-bold"><?php echo htmlspecialchars($createdPassword); ?></span> — share this directly. It will not be shown again.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-1">Your Admin Sign-in</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
      A username for <a href="<?php echo BASE_PATH; ?>/login/admin/" class="underline">Admin Login</a>, separate from the employee sign-in, plus a security question for recovering your own password (you have nobody above you to route a reset request to).
      <?php if ($myLogin['username']): ?>
        Current username: <span class="font-mono font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($myLogin['username']); ?></span>.
      <?php endif; ?>
    </p>
    <form method="POST" data-confirm="Update your admin sign-in?" class="space-y-4">
      <input type="hidden" name="action" value="update_admin_login">
      <div>
        <label for="staff-username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
        <input id="staff-username" type="text" name="username" value="<?php echo htmlspecialchars($myLogin['username'] ?? ''); ?>" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="staff-question" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Security Question</label>
        <input id="staff-question" type="text" name="security_question" value="<?php echo htmlspecialchars($myLogin['security_question'] ?? ''); ?>" maxlength="255" placeholder="e.g. What was your first company's name?" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="staff-answer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Security Answer</label>
        <input id="staff-answer" type="text" name="security_answer" placeholder="<?php echo $myLogin['security_question'] ? 'Leave blank to keep your current answer' : ''; ?>" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Not case-sensitive. Fill both question and answer together to change either.</p>
      </div>
      <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save</button>
    </form>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Add Payroll Master</h2>
    <form method="POST" data-confirm="Create this Payroll Master account?" class="space-y-4">
      <input type="hidden" name="action" value="create_payroll_master">
      <div>
        <label for="pm-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
        <input id="pm-name" type="text" name="name" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="pm-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
        <input id="pm-email" type="email" name="email" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="pm-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Temporary Password</label>
        <input id="pm-password" type="text" name="password" minlength="8" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Min 8 characters. They'll be required to set their own on first login.</p>
      </div>
      <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Create Account</button>
    </form>

    <?php if (!empty($payrollMasters)): ?>
      <div class="mt-6 overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Name</th>
              <th class="pr-6 pb-2">Email</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payrollMasters as $pm): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($pm['name']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($pm['email']); ?></td>
                <td class="py-2">
                  <form method="POST" data-confirm="Revoke this Payroll Master's access? They will need a new temporary password to sign in again.">
                    <input type="hidden" name="action" value="deactivate_payroll_master">
                    <input type="hidden" name="user_id" value="<?php echo (int) $pm['id']; ?>">
                    <button type="submit" class="bg-gray-400 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Revoke Access</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-1">Password Reset Requests</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">From Employee and Payroll Master accounts that used "Forgot password?" on the login page.</p>
    <?php if (empty($pendingRequests)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No pending requests.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Name</th>
              <th class="pr-6 pb-2">Email</th>
              <th class="pr-6 pb-2">Role</th>
              <th class="pr-6 pb-2">Requested</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingRequests as $req): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($req['name']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($req['email']); ?></td>
                <td class="pr-6 py-2"><?php echo $req['role'] === 'payroll_master' ? 'Payroll Master' : 'Employee'; ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($req['requested_at']); ?></td>
                <td class="py-2">
                  <div class="flex gap-2">
                    <form method="POST" data-confirm="Issue a temporary password to <?php echo htmlspecialchars($req['name'], ENT_QUOTES); ?>?">
                      <input type="hidden" name="action" value="resolve_reset_request">
                      <input type="hidden" name="request_id" value="<?php echo (int) $req['id']; ?>">
                      <input type="hidden" name="decision" value="approved">
                      <button type="submit" class="bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Issue Temp Password</button>
                    </form>
                    <form method="POST" data-confirm="Decline this reset request?">
                      <input type="hidden" name="action" value="resolve_reset_request">
                      <input type="hidden" name="request_id" value="<?php echo (int) $req['id']; ?>">
                      <input type="hidden" name="decision" value="declined">
                      <button type="submit" class="bg-gray-400 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Decline</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
