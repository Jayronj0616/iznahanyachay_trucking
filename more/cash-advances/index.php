<?php
require_once __DIR__ . '/../../includes/auth.php';
requirePayrollMaster();

$pageTitle = 'Cash Advances';
$activeNav = 'more';
$db = getDB();
$error = null;
$success = null;
$currentUserId = (int) $_SESSION['user']['id'];

// Payroll Master or Admin can record one directly -- no propose/approve split like the
// trip-rate workflow, since this is a bookkeeping entry (recovering money already handed
// over) rather than a policy decision. Recovery itself happens automatically: the next
// payroll run for this employee deducts the full outstanding balance in one shot
// (applyCashAdvances() in includes/payroll.php, called from payroll/index.php), capped at
// whatever gross survives the statutory deductions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $amount = $_POST['amount'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'employee'");
        $stmt->execute([$userId]);

        if (!$stmt->fetch()) {
            $error = 'Please select a valid employee.';
        } elseif (!is_numeric($amount) || (float) $amount <= 0) {
            $error = 'Amount must be a positive number.';
        } else {
            $stmt = $db->prepare(
                'INSERT INTO cash_advances (user_id, amount, reason, given_by) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, (float) $amount, $reason ?: null, $currentUserId]);
            $success = 'Cash advance recorded. It will be deducted in full from this employee\'s next payroll run.';
        }
    } elseif ($action === 'cancel') {
        $advanceId = (int) ($_POST['advance_id'] ?? 0);
        // Only an outstanding advance can be cancelled -- once a payroll run has deducted
        // it, undoing that here would silently disagree with a payslip that already exists.
        $stmt = $db->prepare("UPDATE cash_advances SET status = 'cancelled' WHERE id = ? AND status = 'outstanding'");
        $stmt->execute([$advanceId]);
        if ($stmt->rowCount() > 0) {
            $success = 'Cash advance cancelled.';
        } else {
            $error = 'This advance can no longer be cancelled (already deducted, or not found).';
        }
    }
}

$employees = $db->query(
    "SELECT u.id, u.name FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
     WHERE u.role = 'employee' AND ep.status = 'active' ORDER BY u.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$advances = $db->query(
    "SELECT ca.*, u.name AS employee_name, gb.name AS given_by_name
     FROM cash_advances ca
     JOIN users u ON u.id = ca.user_id
     JOIN users gb ON gb.id = ca.given_by
     ORDER BY FIELD(ca.status, 'outstanding', 'deducted', 'cancelled'), ca.given_at DESC
     LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/head.php';

$pageIcon = '💵';
$pageLabel = 'Cash Advances';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Cash Advances</h1>
  <p class="text-sm text-gray-500 dark:text-gray-400 -mt-4">
    A one-time flat deduction — the full outstanding amount is recovered in a single payroll run, not spread across several.
  </p>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Record Cash Advance</h2>
    <?php if (empty($employees)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No active employees found.</p>
    <?php else: ?>
      <form method="POST" data-confirm="Record this cash advance? It will be deducted from the employee's next payroll run." class="space-y-4">
        <input type="hidden" name="action" value="create">
        <div>
          <label for="ca-user-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee</label>
          <select id="ca-user-id" name="user_id" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <option value="">Select employee</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo (int) $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="ca-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount (₱)</label>
          <input id="ca-amount" type="number" name="amount" step="0.01" min="0.01" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <div>
          <label for="ca-reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason (optional)</label>
          <input id="ca-reason" type="text" name="reason" maxlength="255" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Record Advance</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">All Advances</h2>
    <?php if (empty($advances)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No cash advances recorded yet.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Employee</th>
              <th class="pr-6 pb-2 text-right">Amount</th>
              <th class="pr-6 pb-2">Reason</th>
              <th class="pr-6 pb-2">Given By</th>
              <th class="pr-6 pb-2">Status</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php
              $statusClasses = [
                  'outstanding' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300',
                  'deducted' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                  'cancelled' => 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400',
              ];
            ?>
            <?php foreach ($advances as $adv): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($adv['employee_name']); ?></td>
                <td class="pr-6 py-2 text-right">₱<?php echo number_format((float) $adv['amount'], 2); ?></td>
                <td class="pr-6 py-2 text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($adv['reason'] ?? '—'); ?></td>
                <td class="pr-6 py-2 text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($adv['given_by_name']); ?></td>
                <td class="pr-6 py-2">
                  <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full <?php echo $statusClasses[$adv['status']]; ?>"><?php echo ucfirst($adv['status']); ?></span>
                </td>
                <td class="py-2">
                  <?php if ($adv['status'] === 'outstanding'): ?>
                    <form method="POST" data-confirm="Cancel this cash advance?">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="advance_id" value="<?php echo (int) $adv['id']; ?>">
                      <button type="submit" class="bg-gray-400 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Cancel</button>
                    </form>
                  <?php endif; ?>
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
