<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Dashboard Overview';
$activeNav = 'overview';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '📊';
$pageLabel = 'Overview';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$isAdmin = $_SESSION['user']['role'] === 'admin';

// Admin sees company-wide totals (they have no payroll_runs/timesheet_entries of their
// own); employee sees only their own. Salary is finalized runs only — a draft isn't real
// money yet. Hours are approved entries only, same rule payroll/index.php enforces.
if ($isAdmin) {
    $totalSalary = (float) $db->query(
        "SELECT COALESCE(SUM(net_pay), 0) FROM payroll_runs WHERE status = 'finalized'"
    )->fetchColumn();
    $hourEntries = $db->query(
        "SELECT time_in, time_out FROM timesheet_entries
         WHERE status = 'approved' AND time_in IS NOT NULL AND time_out IS NOT NULL"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $userId = (int) $_SESSION['user']['id'];
    $stmt = $db->prepare("SELECT COALESCE(SUM(net_pay), 0) FROM payroll_runs WHERE status = 'finalized' AND user_id = ?");
    $stmt->execute([$userId]);
    $totalSalary = (float) $stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT time_in, time_out FROM timesheet_entries
         WHERE status = 'approved' AND time_in IS NOT NULL AND time_out IS NOT NULL AND user_id = ?"
    );
    $stmt->execute([$userId]);
    $hourEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalHours = 0.0;
foreach ($hourEntries as $e) {
    $totalHours += max(0, (strtotime($e['time_out']) - strtotime($e['time_in'])) / 3600);
}

// No late-threshold or performance-scoring concept exists anywhere in the schema yet —
// showing "Not tracked" rather than fabricating a number, same rule home/index.php
// already follows for paid/unpaid leave.
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h1>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Total Salary<?php echo $isAdmin ? ' (Company)' : ''; ?></div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold">₱<?php echo number_format($totalSalary, 2); ?></div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Total Hours<?php echo $isAdmin ? ' (Company)' : ''; ?></div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold"><?php echo number_format($totalHours, 2); ?>h</div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Total Late</div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-400 dark:text-gray-500 text-sm font-bold">Not tracked</div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Performance</div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-400 dark:text-gray-500 text-sm font-bold">Not tracked</div>
    </div>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
