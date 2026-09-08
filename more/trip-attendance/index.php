<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Trip Attendance';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();

$employeeFilter = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$driversHelpers = $db->query(
    "SELECT u.id, u.name FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
     WHERE ep.position IN ('driver', 'helper') ORDER BY u.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];

if ($employeeFilter > 0) {
    $where[] = 'ta.user_id = ?';
    $params[] = $employeeFilter;
}
if ($dateFrom !== '') {
    $where[] = 'ta.date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'ta.date <= ?';
    $params[] = $dateTo;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare(
    "SELECT ta.date, ta.role, u.name AS employee_name, r.destination
     FROM trip_attendance ta
     JOIN users u ON u.id = ta.user_id
     JOIN trips_new t ON t.id = ta.trip_id
     JOIN routes r ON r.id = t.route_id
     $whereSql
     ORDER BY ta.date DESC, ta.id DESC
     LIMIT 200"
);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Trip Attendance</h1>
  <p class="text-sm text-gray-500 dark:text-gray-400">Automatically recorded when a trip is marked completed. Read-only — presence is verified by trip completion, no approval needed.</p>

  <form method="GET" class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee</label>
      <select name="user_id" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <option value="">All drivers/helpers</option>
        <?php foreach ($driversHelpers as $u): ?>
          <option value="<?php echo (int) $u['id']; ?>" <?php echo $employeeFilter === (int) $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
      <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
      <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <div class="sm:col-span-3 flex gap-3">
      <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-2.5 hover:opacity-90 transition">Filter</button>
      <a href="<?php echo BASE_PATH; ?>/more/trip-attendance/" class="border border-gray-300 dark:border-surface-border text-gray-700 dark:text-gray-300 font-bold rounded-lg px-5 py-2.5 hover:bg-gray-100 dark:hover:bg-white/5 transition">Clear</a>
    </div>
  </form>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Records (latest 200)</h2>
    <?php if (empty($records)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No attendance records found.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Date</th>
              <th class="pr-6 pb-2">Employee</th>
              <th class="pr-6 pb-2">Role</th>
              <th class="pb-2">Route</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $rec): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($rec['date']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($rec['employee_name']); ?></td>
                <td class="pr-6 py-2"><?php echo ucfirst($rec['role']); ?></td>
                <td class="py-2"><?php echo htmlspecialchars($rec['destination']); ?></td>
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
include __DIR__ . '/../../includes/foot.php';
?>
