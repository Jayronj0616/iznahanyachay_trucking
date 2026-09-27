<?php
require_once __DIR__ . '/../../includes/auth.php';
requirePayrollMaster();

$pageTitle = 'Correction Requests';
$activeNav = 'timesheet';
$db = getDB();
$error = null;
$success = null;
$currentUserId = (int) $_SESSION['user']['id'];

// Deliberately Approve/Decline only -- no remarks field, matching the panel's sample
// screen. Approve upserts the timesheet_entries row for that user/date with the
// requested time in/out, pre-approved, since a human already reviewed it here.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    if (!in_array($decision, ['approved', 'declined'], true)) {
        $error = 'Invalid decision.';
    } else {
        $stmt = $db->prepare("SELECT * FROM attendance_correction_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $error = 'Request not found or already resolved.';
        } else {
            $db->beginTransaction();
            try {
                if ($decision === 'approved') {
                    $stmt = $db->prepare('SELECT id, time_in, time_out FROM timesheet_entries WHERE user_id = ? AND date = ?');
                    $stmt->execute([$request['user_id'], $request['date']]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                    $newTimeIn = $request['requested_time_in'] ?? ($existing['time_in'] ?? null);
                    $newTimeOut = $request['requested_time_out'] ?? ($existing['time_out'] ?? null);

                    if ($existing) {
                        $stmt = $db->prepare(
                            "UPDATE timesheet_entries SET time_in = ?, time_out = ?, status = 'approved', rejection_reason = NULL, deleted_at = NULL WHERE id = ?"
                        );
                        $stmt->execute([$newTimeIn, $newTimeOut, $existing['id']]);
                    } else {
                        $stmt = $db->prepare(
                            "INSERT INTO timesheet_entries (user_id, date, time_in, time_out, type, status) VALUES (?, ?, ?, ?, 'manual', 'approved')"
                        );
                        $stmt->execute([$request['user_id'], $request['date'], $newTimeIn, $newTimeOut]);
                    }
                }

                $stmt = $db->prepare("UPDATE attendance_correction_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$decision, $currentUserId, $requestId]);
                $db->commit();
                $success = $decision === 'approved' ? 'Correction approved and applied to the timesheet.' : 'Request declined.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Could not resolve request: ' . $e->getMessage();
            }
        }
    }
}

$pendingRequests = $db->query(
    "SELECT acr.*, u.name AS employee_name
     FROM attendance_correction_requests acr
     JOIN users u ON u.id = acr.user_id
     WHERE acr.status = 'pending'
     ORDER BY acr.created_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/head.php';

$pageIcon = '⏱️';
$pageLabel = 'Timesheet';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <div>
    <a href="<?php echo BASE_PATH; ?>/timesheet/" class="text-brand-orange text-sm font-semibold">&larr; Back to calendar</a>
  </div>

  <h1 class="text-gray-900 dark:text-white font-bold text-lg">Attendance Correction Requests</h1>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if (empty($pendingRequests)): ?>
    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <p class="text-gray-500 dark:text-gray-400 text-sm">No pending correction requests.</p>
    </div>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($pendingRequests as $r): ?>
        <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
          <div class="flex items-start justify-between gap-3 mb-3">
            <div>
              <p class="font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($r['employee_name']); ?></p>
              <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($r['date']); ?></p>
            </div>
            <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">Pending</span>
          </div>

          <div class="grid grid-cols-2 gap-4 mb-3 text-sm">
            <div>
              <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">Requested Time In</span>
              <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($r['requested_time_in'] ?? '—'); ?></span>
            </div>
            <div>
              <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">Requested Time Out</span>
              <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($r['requested_time_out'] ?? '—'); ?></span>
            </div>
          </div>

          <div class="mb-3">
            <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Reason</span>
            <p class="text-sm text-gray-700 dark:text-gray-300"><?php echo nl2br(htmlspecialchars($r['reason'])); ?></p>
          </div>

          <?php if ($r['proof_photo_path']): ?>
          <div class="mb-4">
            <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Proof Photo</span>
            <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($r['proof_photo_path']); ?>" alt="Proof" class="w-full max-w-xs rounded-lg border border-gray-300 dark:border-surface-border">
          </div>
          <?php endif; ?>

          <div class="flex gap-3">
            <form method="POST" data-confirm="Approve this correction? It will overwrite the timesheet entry for this date.">
              <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
              <input type="hidden" name="decision" value="approved">
              <button type="submit" class="bg-brand-green text-white text-sm font-semibold px-4 py-2 rounded-full hover:opacity-90 transition">Approve</button>
            </form>
            <form method="POST" data-confirm="Decline this correction request?">
              <input type="hidden" name="request_id" value="<?php echo (int) $r['id']; ?>">
              <input type="hidden" name="decision" value="declined">
              <button type="submit" class="bg-gray-400 text-white text-sm font-semibold px-4 py-2 rounded-full hover:opacity-90 transition">Decline</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
