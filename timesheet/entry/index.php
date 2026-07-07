<?php
$pageTitle = 'Timesheet Entry';
$activeNav = 'timesheet';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$isAdmin = $_SESSION['user']['role'] === 'admin';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⏱️';
$pageLabel = 'Timesheet';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;

$userId = $isAdmin ? (isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0) : (int) $_SESSION['user']['id'];
$date = $_GET['date'] ?? '';

if ($isAdmin) {
    $stmt = $db->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'employee'");
    $stmt->execute([$userId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare('SELECT id, name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$employee || !$date) {
    header('Location: ' . BASE_PATH . '/timesheet/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_time'])) {
        $timeIn = $_POST['time_in'] ?? '';
        $timeOut = $_POST['time_out'] ?? '';

        if (!$timeIn || !$timeOut) {
            $error = 'Time in and time out are required.';
        } elseif ($timeOut <= $timeIn) {
            $error = 'Time out must be after time in.';
        } else {
            $stmt = $db->prepare('SELECT id FROM timesheet_entries WHERE user_id = ? AND date = ?');
            $stmt->execute([$userId, $date]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $db->prepare('UPDATE timesheet_entries SET time_in = ?, time_out = ?, type = "manual" WHERE id = ?');
                $stmt->execute([$timeIn, $timeOut, $existing['id']]);
            } else {
                $stmt = $db->prepare('INSERT INTO timesheet_entries (user_id, date, time_in, time_out, type) VALUES (?, ?, ?, ?, "manual")');
                $stmt->execute([$userId, $date, $timeIn, $timeOut]);
            }
            $success = 'Entry saved.';
        }
    } elseif ($isAdmin && isset($_POST['approve'])) {
        $stmt = $db->prepare('UPDATE timesheet_entries SET status = "approved", rejection_reason = NULL WHERE user_id = ? AND date = ?');
        $stmt->execute([$userId, $date]);
        $success = 'Entry approved.';
    } elseif ($isAdmin && isset($_POST['reject'])) {
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (!$reason) {
            $error = 'A rejection reason is required.';
        } else {
            $stmt = $db->prepare('UPDATE timesheet_entries SET status = "rejected", rejection_reason = ? WHERE user_id = ? AND date = ?');
            $stmt->execute([$reason, $userId, $date]);
            $success = 'Entry rejected.';
        }
    }
}

$stmt = $db->prepare('SELECT * FROM timesheet_entries WHERE user_id = ? AND date = ?');
$stmt->execute([$userId, $date]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<main class="max-w-2xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">

  <div>
    <a href="<?php echo BASE_PATH; ?>/timesheet/<?php echo $isAdmin ? '?user_id=' . $userId : ''; ?>" class="text-brand-green text-sm font-semibold">&larr; Back to calendar</a>
  </div>

  <h1 class="text-gray-900 dark:text-white font-bold text-lg">
    <?php echo htmlspecialchars($employee['name']); ?> — <?php echo htmlspecialchars($date); ?>
  </h1>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm">
      <?php echo htmlspecialchars($success); ?>
    </div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Time In / Time Out</h2>
    <form method="POST" class="space-y-3">
      <input type="time" name="time_in" required value="<?php echo htmlspecialchars($entry['time_in'] ?? ''); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      <input type="time" name="time_out" required value="<?php echo htmlspecialchars($entry['time_out'] ?? ''); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      <button type="submit" name="save_time" value="1" class="block w-full text-center bg-brand-green text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save Time</button>
    </form>
  </div>

  <?php if ($entry): ?>
    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold mb-4">Approval Status</h2>
      <?php if (!isset($entry['status'])): ?><?php $entry['status'] = 'pending'; ?><?php endif; ?>

      <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
        Current status:
        <span class="font-semibold">
          <?php echo htmlspecialchars(ucfirst($entry['status'])); ?>
        </span>
        <?php if ($entry['status'] === 'rejected' && $entry['rejection_reason']): ?>
          — Reason: <?php echo htmlspecialchars($entry['rejection_reason']); ?>
        <?php endif; ?>
      </p>

      <?php if ($isAdmin): ?>
      <div class="flex gap-3 mb-4">
        <form method="POST">
          <button type="submit" name="approve" value="1" class="bg-brand-green text-white text-sm font-semibold px-4 py-2 rounded-full hover:opacity-90 transition">Approve</button>
        </form>
      </div>

      <form method="POST" class="space-y-3">
        <input type="text" name="rejection_reason" placeholder="Reason for rejection" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow">
        <button type="submit" name="reject" value="1" class="bg-brand-orange text-white text-sm font-semibold px-4 py-2 rounded-full hover:opacity-90 transition">Reject</button>
      </form>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p class="text-gray-500 dark:text-gray-400 text-sm">No entry yet for this date — save a time first before approving or rejecting.</p>
  <?php endif; ?>

</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
