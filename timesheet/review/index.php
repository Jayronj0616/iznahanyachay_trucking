<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Review Timesheet';
$activeNav = 'timesheet';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '✅';
$pageLabel = 'Review Period';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;

$employees = $db->query("SELECT id, name FROM users WHERE role = 'employee' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if (!$selectedUserId && !empty($employees)) {
    $selectedUserId = (int) $employees[0]['id'];
}

$periodStart = $_GET['period_start'] ?? date('Y-m-01');
$periodEnd = $_GET['period_end'] ?? date('Y-m-t');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_entry_id'])) {
    $entryId = (int) $_POST['reject_entry_id'];
    $reason = trim($_POST['rejection_reason'] ?? '');
    if ($reason === '') {
        $error = 'Rejection reason is required.';
    } else {
        $stmt = $db->prepare("UPDATE timesheet_entries SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $entryId]);
        $success = 'Entry rejected.';
    }
    $selectedUserId = (int) ($_POST['user_id'] ?? $selectedUserId);
    $periodStart = $_POST['period_start'] ?? $periodStart;
    $periodEnd = $_POST['period_end'] ?? $periodEnd;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_period'])) {
    $selectedUserId = (int) ($_POST['user_id'] ?? 0);
    $periodStart = $_POST['period_start'] ?? '';
    $periodEnd = $_POST['period_end'] ?? '';

    if (!$selectedUserId || !$periodStart || !$periodEnd) {
        $error = 'Employee and period are required.';
    } else {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "UPDATE timesheet_entries SET status = 'approved'
                 WHERE user_id = ? AND date BETWEEN ? AND ? AND status = 'pending'"
            );
            $stmt->execute([$selectedUserId, $periodStart, $periodEnd]);

            $stmt = $db->prepare(
                'INSERT INTO timesheet_approvals (user_id, period_start, period_end, approved_by, approved_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$selectedUserId, $periodStart, $periodEnd, $_SESSION['user']['id']]);

            $db->commit();
            $success = 'Period approved.';
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Approval failed: ' . $e->getMessage();
        }
    }
}

$entries = [];
if ($selectedUserId && $periodStart && $periodEnd) {
    $stmt = $db->prepare(
        'SELECT * FROM timesheet_entries
         WHERE user_id = ? AND date BETWEEN ? AND ?
         ORDER BY date ASC'
    );
    $stmt->execute([$selectedUserId, $periodStart, $periodEnd]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pendingCount = count(array_filter($entries, fn($e) => $e['status'] === 'pending'));
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">

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

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-4">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee</label>
        <select name="user_id" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-3 py-2 text-gray-900 dark:text-white">
          <?php foreach ($employees as $emp): ?>
            <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selectedUserId === (int) $emp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
        <input type="date" name="period_start" value="<?php echo htmlspecialchars($periodStart); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-3 py-2 text-gray-900 dark:text-white">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
        <input type="date" name="period_end" value="<?php echo htmlspecialchars($periodEnd); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-3 py-2 text-gray-900 dark:text-white">
      </div>
      <div class="sm:col-span-4">
        <button type="submit" class="bg-brand-yellow text-gray-900 font-bold rounded-full px-5 py-2 hover:opacity-90 transition">Load Entries</button>
      </div>
    </form>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Entries in Period</h2>
    <?php if (empty($entries)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No entries in this range.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Date</th>
              <th class="pr-6 pb-2">Time In</th>
              <th class="pr-6 pb-2">Time Out</th>
              <th class="pr-6 pb-2">Status</th>
              <th class="pb-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['date']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['time_in']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['time_out']); ?></td>
                <td class="pr-6 py-2">
                  <?php if ($entry['status'] === 'approved'): ?>
                    <span class="inline-block bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-semibold px-2 py-1 rounded-full">Approved</span>
                  <?php elseif ($entry['status'] === 'rejected'): ?>
                    <span class="inline-block bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-semibold px-2 py-1 rounded-full" title="<?php echo htmlspecialchars($entry['rejection_reason'] ?? ''); ?>">Rejected</span>
                  <?php else: ?>
                    <span class="inline-block bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-semibold px-2 py-1 rounded-full">Pending</span>
                  <?php endif; ?>
                </td>
                <td class="py-2">
                  <?php if ($entry['status'] === 'pending'): ?>
                    <form method="POST" id="reject-form-<?php echo (int) $entry['id']; ?>" class="inline">
                      <input type="hidden" name="reject_entry_id" value="<?php echo (int) $entry['id']; ?>">
                      <input type="hidden" name="user_id" value="<?php echo $selectedUserId; ?>">
                      <input type="hidden" name="period_start" value="<?php echo htmlspecialchars($periodStart); ?>">
                      <input type="hidden" name="period_end" value="<?php echo htmlspecialchars($periodEnd); ?>">
                      <input type="hidden" name="rejection_reason" class="reject-reason-input">
                    </form>
                    <button type="button" onclick="openRejectModal(<?php echo (int) $entry['id']; ?>)" class="bg-red-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Reject</button>
                  <?php else: ?>
                    <span class="text-gray-400 text-xs">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <form method="POST" data-confirm="Approve all pending entries in this period? This will lock them in for payroll." class="mt-6">
        <input type="hidden" name="approve_period" value="1">
        <input type="hidden" name="user_id" value="<?php echo $selectedUserId; ?>">
        <input type="hidden" name="period_start" value="<?php echo htmlspecialchars($periodStart); ?>">
        <input type="hidden" name="period_end" value="<?php echo htmlspecialchars($periodEnd); ?>">
        <button type="submit" class="bg-brand-green text-white font-bold rounded-full px-6 py-3 hover:opacity-90 transition" <?php echo $pendingCount === 0 ? 'disabled' : ''; ?>>
          Approve Period (<?php echo $pendingCount; ?> pending)
        </button>
      </form>
    <?php endif; ?>
  </div>

</main>

<div id="reject-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
  <div id="reject-modal-backdrop" class="absolute inset-0 bg-black/50"></div>
  <div class="relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl shadow-xl max-w-sm w-full p-6">
    <h3 class="text-gray-900 dark:text-white font-bold text-base mb-2">Reject Entry</h3>
    <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">Please provide a reason for rejecting this entry.</p>
    <textarea id="reject-modal-reason" rows="3" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white mb-2" placeholder="Reason for rejection"></textarea>
    <p id="reject-modal-error" class="hidden text-xs text-red-600 dark:text-red-400 mb-3">Reason is required.</p>
    <div class="flex justify-end gap-3">
      <button type="button" id="reject-modal-cancel" class="text-sm font-semibold text-gray-600 dark:text-gray-300 px-4 py-2 rounded-full hover:bg-gray-100 dark:hover:bg-white/5 transition">Cancel</button>
      <button type="button" id="reject-modal-confirm" class="text-sm font-bold text-white bg-red-600 px-5 py-2 rounded-full hover:opacity-90 transition">Reject</button>
    </div>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('reject-modal');
  var backdrop = document.getElementById('reject-modal-backdrop');
  var reasonEl = document.getElementById('reject-modal-reason');
  var errorEl = document.getElementById('reject-modal-error');
  var confirmBtn = document.getElementById('reject-modal-confirm');
  var cancelBtn = document.getElementById('reject-modal-cancel');
  var pendingEntryId = null;

  window.openRejectModal = function (entryId) {
    pendingEntryId = entryId;
    reasonEl.value = '';
    errorEl.classList.add('hidden');
    modal.classList.remove('hidden');
    reasonEl.focus();
  };

  function closeModal() {
    modal.classList.add('hidden');
    pendingEntryId = null;
  }

  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);

  confirmBtn.addEventListener('click', function () {
    var reason = reasonEl.value.trim();
    if (!reason) {
      errorEl.classList.remove('hidden');
      return;
    }
    var form = document.getElementById('reject-form-' + pendingEntryId);
    form.querySelector('.reject-reason-input').value = reason;
    form.submit();
  });
})();
</script>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
