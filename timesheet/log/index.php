<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

if ($_SESSION['user']['role'] === 'admin') {
    header('Location: ' . BASE_PATH . '/timesheet/');
    exit;
}

$pageTitle = 'Time Log History';
$activeNav = 'timesheet';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '🕘';
$pageLabel = 'Time Log History';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$userId = (int) $_SESSION['user']['id'];

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$monthLabel = date('F Y', strtotime($monthStart));
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));

$stmt = $db->prepare(
    'SELECT date, time_in, time_out, time_in_photo
     FROM timesheet_entries
     WHERE user_id = ? AND date BETWEEN ? AND ?
     ORDER BY date DESC'
);
$stmt->execute([$userId, $monthStart, $monthEnd]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-4">

  <a href="<?php echo BASE_PATH; ?>/timesheet/" class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400 text-sm font-medium">
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"></path></svg>
    Back to Timesheet
  </a>

  <div class="flex items-center justify-between bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-3">
    <a href="?month=<?php echo $prevMonth; ?>" class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"></path></svg>
    </a>
    <span class="text-gray-900 dark:text-white font-bold"><?php echo $monthLabel; ?></span>
    <a href="?month=<?php echo $nextMonth; ?>" class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"></path></svg>
    </a>
  </div>

  <?php if (empty($entries)): ?>
    <p class="text-gray-500 dark:text-gray-400 text-sm text-center py-8">No entries for <?php echo $monthLabel; ?>.</p>
  <?php else: ?>
    <?php foreach ($entries as $entry):
      $dateLabel = date('F d, Y', strtotime($entry['date']));
    ?>
      <div>
        <div class="bg-gray-100 dark:bg-surface-card text-gray-700 dark:text-gray-300 text-sm font-semibold px-4 py-2 rounded-t-lg">
          <?php echo $dateLabel; ?>
        </div>
        <div class="border border-t-0 border-gray-200 dark:border-surface-border rounded-b-lg overflow-hidden bg-white dark:bg-surface">
          <div class="flex items-center justify-between gap-3 p-3">
            <div class="flex items-center gap-3">
              <?php if ($entry['time_in']): ?>
                <?php if ($entry['time_in_photo']): ?>
                  <img src="<?php echo BASE_PATH; ?>/<?php echo htmlspecialchars($entry['time_in_photo']); ?>" alt="" class="w-12 h-12 rounded-full object-cover border border-gray-200 dark:border-surface-border">
                <?php else: ?>
                  <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-surface-border flex items-center justify-center text-gray-400 text-xs">—</div>
                <?php endif; ?>
                <span class="w-2.5 h-2.5 rounded-full bg-green-500 flex-shrink-0"></span>
                <div>
                  <span class="text-gray-900 dark:text-white font-medium block"><?php echo date('h:i:s A', strtotime($entry['time_in'])); ?></span>
                  <span class="text-gray-400 dark:text-gray-500 text-xs">Time In</span>
                </div>
              <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
              <?php if ($entry['time_out']): ?>
                <span class="w-2.5 h-2.5 rounded-full bg-red-500 flex-shrink-0"></span>
                <div class="text-right">
                  <span class="text-gray-900 dark:text-white font-medium block"><?php echo date('h:i:s A', strtotime($entry['time_out'])); ?></span>
                  <span class="text-gray-400 dark:text-gray-500 text-xs">Time Out</span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
