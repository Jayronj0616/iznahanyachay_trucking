<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
requireLogin();

$pageTitle = 'Timesheet';
$activeNav = 'timesheet';
include __DIR__ . '/../includes/head.php';

$pageIcon = '⏱️';
$pageLabel = 'Timesheet';
$isAdminForTopbar = $_SESSION['user']['role'] === 'admin';
$topbarExtra = !$isAdminForTopbar
    ? '<a href="' . BASE_PATH . '/timesheet/log/" class="inline-flex items-center gap-2 bg-brand-orange text-white text-sm font-semibold px-4 py-2 rounded-full hover:opacity-90 transition">🕘 History</a>'
    : '';
include __DIR__ . '/../includes/topbar.php';

$db = getDB();
$isAdmin = $_SESSION['user']['role'] === 'admin';
$employees = [];

if ($isAdmin) {
    $employees = $db->query("SELECT id, name FROM users WHERE role = 'employee' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    if (!$selectedUserId && !empty($employees)) {
        $selectedUserId = (int) $employees[0]['id'];
    }
} else {
    $selectedUserId = (int) $_SESSION['user']['id'];
}

$daysInMonth = (int) date('t');
$monthAbbr = date('M');
$today = (int) date('j');

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$entries = [];
if ($selectedUserId) {
    $stmt = $db->prepare(
        'SELECT te.*, u.name AS employee_name
         FROM timesheet_entries te
         JOIN users u ON u.id = te.user_id
         WHERE te.user_id = ? AND te.date BETWEEN ? AND ?
         ORDER BY te.date DESC'
    );
    $stmt->execute([$selectedUserId, $monthStart, $monthEnd]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$entriesByDate = [];
foreach ($entries as $entry) {
    $entriesByDate[$entry['date']] = $entry;
}

// Driver and helper are paid per completed trip and never punch a timesheet, so
// present/absent is not a concept that applies to them -- classifying their month
// would mark every working day absent. They keep the plain day list.
$position = null;
if ($selectedUserId) {
    $stmt = $db->prepare('SELECT position FROM employee_profiles WHERE user_id = ?');
    $stmt->execute([$selectedUserId]);
    $position = $stmt->fetchColumn() ?: null;
}
$tracksAttendance = $selectedUserId && !in_array($position, ['driver', 'helper'], true);

$attendance = ['days' => []];
if ($tracksAttendance) {
    $attendance = classifyAttendance(
        $monthStart,
        $monthEnd,
        $entriesByDate,
        employeeRestDays($db, $selectedUserId),
        date('Y-m-d')
    );
}

// Each state gets its own chip so the distinction survives the orange "today" row,
// which text colour alone would not.
$stateChip = [
    'present' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    'absent' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    'rest' => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    'worked_rest' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
];
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">

  <?php if ($isAdmin): ?>
  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-4 flex items-center justify-between gap-3">
    <form method="GET" class="flex items-center gap-3 flex-1">
      <label for="timesheet-user-id" class="text-sm font-medium text-gray-700 dark:text-gray-300">Employee:</label>
      <select id="timesheet-user-id" name="user_id" onchange="this.form.submit()" class="flex-1 bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-3 py-2 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <?php foreach ($employees as $emp): ?>
          <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selectedUserId === (int) $emp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="<?php echo BASE_PATH; ?>/timesheet/review/" class="bg-brand-orange text-white text-sm font-semibold px-4 py-2 rounded-full hover:opacity-90 transition whitespace-nowrap">Review Period</a>
  </div>
  <?php endif; ?>

  <div>
    <div class="flex items-center justify-between pb-3 mb-2 border-b border-gray-200 dark:border-surface-border">
      <span class="flex items-center gap-2 text-gray-800 dark:text-gray-200 font-medium text-sm">
        📅 This Month (<?php echo $monthAbbr; ?>)
      </span>
      <a href="#today" class="text-brand-orange text-sm font-semibold">Today</a>
    </div>

    <div class="divide-y divide-gray-200 dark:divide-surface-border">
      <?php for ($d = 1; $d <= $daysInMonth; $d++):
        $ts = mktime(0, 0, 0, (int) date('n'), $d, (int) date('Y'));
        $dateStr = date('Y-m-d', $ts);
        $dayAbbr = date('D', $ts);
        $isToday = $d === $today;
        $entryHref = BASE_PATH . '/timesheet/entry/?date=' . $dateStr . ($isAdmin ? '&user_id=' . $selectedUserId : '');

        $state = $attendance['days'][$dateStr] ?? 'upcoming';
        $entry = $entriesByDate[$dateStr] ?? null;
        $times = $entry && $entry['time_in']
            ? substr($entry['time_in'], 0, 5) . ($entry['time_out'] ? ' – ' . substr($entry['time_out'], 0, 5) : '')
            : '';
        $dayLabel = $isAdmin ? 'Tap to View' : 'Tap to Add';
      ?>
      <a
        <?php echo $isToday ? 'id="today"' : ''; ?>
        href="<?php echo $entryHref; ?>"
        class="flex items-center justify-between py-4 px-4 -mx-4 <?php echo $isToday ? 'bg-brand-orange rounded-lg text-white' : 'text-gray-800 dark:text-gray-200'; ?>"
      >
        <span class="flex items-center gap-3">
          <span class="text-right w-6 font-semibold"><?php echo $d; ?></span>
          <span class="text-xs <?php echo $isToday ? 'text-white/80' : 'text-gray-400 dark:text-gray-500'; ?> w-10"><?php echo $dayAbbr; ?></span>
          <?php if ($tracksAttendance && $state !== 'upcoming'): ?>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo $stateChip[$state]; ?>"><?php echo htmlspecialchars(attendanceLabel($state)); ?></span>
            <?php if ($times !== ''): ?>
              <span class="text-xs <?php echo $isToday ? 'text-white/90' : 'text-gray-500 dark:text-gray-400'; ?>"><?php echo htmlspecialchars($times); ?></span>
            <?php endif; ?>
          <?php else: ?>
            <span class="text-sm italic <?php echo $isToday ? 'text-white/90' : 'text-gray-400 dark:text-gray-500'; ?>"><?php echo htmlspecialchars($dayLabel); ?></span>
          <?php endif; ?>
        </span>
        <svg class="w-4 h-4 <?php echo $isToday ? 'text-white' : 'text-gray-400 dark:text-gray-500'; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 5v14M5 12h14"></path>
        </svg>
      </a>
      <?php endfor; ?>
    </div>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Records (This Month)</h2>
    <?php if (empty($entries)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No entries yet this month.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Date</th>
              <th class="pr-6 pb-2">Time In</th>
              <th class="pr-6 pb-2">Time Out</th>
              <th class="pb-2">Type</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['date']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['time_in'] ?? ''); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['time_out'] ?? ''); ?></td>
                <td class="py-2"><?php echo htmlspecialchars($entry['type']); ?></td>
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
include __DIR__ . '/../includes/bottom-nav.php';
include __DIR__ . '/../includes/confirm-modal.php';
include __DIR__ . '/../includes/foot.php';
?>
