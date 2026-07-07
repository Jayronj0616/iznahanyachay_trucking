<?php
$pageTitle = 'Admin Timesheet';
$activeNav = 'timesheet';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⏱️';
$pageLabel = 'Timesheet';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;

$employees = $db->query("SELECT id, name FROM users WHERE role = 'employee' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if (!$selectedUserId && !empty($employees)) {
    $selectedUserId = (int) $employees[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $timeIn = $_POST['time_in'] ?? '';
    $timeOut = $_POST['time_out'] ?? '';

    if (!$userId || !$date || !$timeIn || !$timeOut) {
        $error = 'All fields are required.';
    } elseif ($timeOut <= $timeIn) {
        $error = 'Time out must be after time in.';
    } else {
        $stmt = $db->prepare('SELECT id FROM timesheet_entries WHERE user_id = ? AND date = ?');
        $stmt->execute([$userId, $date]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $db->prepare('UPDATE timesheet_entries SET time_in = ?, time_out = ?, type = "manual" WHERE id = ?');
            $stmt->execute([$timeIn, $timeOut, $existing['id']]);
            $success = 'Entry updated.';
        } else {
            $stmt = $db->prepare('INSERT INTO timesheet_entries (user_id, date, time_in, time_out, type) VALUES (?, ?, ?, ?, "manual")');
            $stmt->execute([$userId, $date, $timeIn, $timeOut]);
            $success = 'Entry saved.';
        }
        $selectedUserId = $userId;
    }
}

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

$daysInMonth = (int) date('t');
$monthAbbr = date('M');
$today = (int) date('j');
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-4">
    <form method="GET" class="flex items-center gap-3">
      <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Employee:</label>
      <select name="user_id" onchange="this.form.submit()" class="flex-1 bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-3 py-2 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <?php foreach ($employees as $emp): ?>
          <option value="<?php echo (int) $emp['id']; ?>" <?php echo $selectedUserId === (int) $emp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div>
    <div class="flex items-center justify-between pb-3 mb-2 border-b border-gray-200 dark:border-surface-border">
      <span class="flex items-center gap-2 text-gray-800 dark:text-gray-200 font-medium text-sm">
        📅 This Month (<?php echo $monthAbbr; ?>)
      </span>
      <a href="#today" class="text-brand-green text-sm font-semibold">Today</a>
    </div>

    <div class="divide-y divide-gray-200 dark:divide-surface-border">
      <?php for ($d = 1; $d <= $daysInMonth; $d++):
        $ts = mktime(0, 0, 0, (int) date('n'), $d, (int) date('Y'));
        $dayAbbr = date('D', $ts);
        $isToday = $d === $today;
      ?>
      <a
        <?php echo $isToday ? 'id="today"' : ''; ?>
        href="<?php echo BASE_PATH; ?>/timesheet/entry/?user_id=<?php echo $selectedUserId; ?>&date=<?php echo date('Y-m-d', $ts); ?>"
        class="flex items-center justify-between py-4 px-4 -mx-4 <?php echo $isToday ? 'bg-brand-orange rounded-lg text-white' : 'text-gray-800 dark:text-gray-200'; ?>"
      >
        <span class="flex items-center gap-3">
          <span class="text-right w-6 font-semibold"><?php echo $d; ?></span>
          <span class="text-xs <?php echo $isToday ? 'text-white/80' : 'text-gray-400 dark:text-gray-500'; ?> w-10"><?php echo $dayAbbr; ?></span>
          <span class="text-sm italic <?php echo $isToday ? 'text-white/90' : 'text-gray-400 dark:text-gray-500'; ?>">Tap to Add</span>
        </span>
        <svg class="w-4 h-4 <?php echo $isToday ? 'text-white' : 'text-gray-400 dark:text-gray-500'; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 5v14M5 12h14"></path>
        </svg>
      </a>
      <?php endfor; ?>
    </div>
  </div>

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
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Add Time (Manual)</h2>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="user_id" value="<?php echo $selectedUserId; ?>">
      <input type="date" name="date" required style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      <input type="time" name="time_in" required style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      <input type="time" name="time_out" required style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      <button type="submit" class="block w-full text-center bg-brand-green text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save</button>
    </form>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-1">Scan QR (Auto Time In/Out)</h2>
    <p class="text-gray-500 dark:text-gray-400 text-sm">Waiting for scan...</p>
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
              <th class="pr-6 pb-2">Type</th>
              <th class="pb-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['date']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['time_in']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['time_out']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($entry['type']); ?></td>
                <td class="py-2"><?php echo htmlspecialchars($entry['status']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</main>

<?php
$navBase = BASE_PATH . '/admin';
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
