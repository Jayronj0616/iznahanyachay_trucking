<?php
$pageTitle = 'Admin Payroll';
$activeNav = 'payroll';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '💼';
$pageLabel = 'Payroll';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6">

  <!-- LIGHT MODE: empty state + Run Payroll -->
  <div class="dark:hidden flex flex-col min-h-[70vh]">
    <div class="flex justify-end mb-8">
      <button type="button" class="flex items-center gap-1 text-sm text-gray-600 font-medium">
        Filter by range
        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
      </button>
    </div>

    <div class="flex-1 flex flex-col items-center justify-center text-center px-6">
      <h2 class="text-lg font-bold text-gray-900">No payslips found</h2>
      <p class="text-sm text-gray-500 mt-1">Tap on RUN PAYROLL to get started</p>
    </div>

    <a href="<?php echo BASE_PATH; ?>/payroll/run/" class="block text-center bg-brand-green text-white font-bold tracking-wide rounded-full px-5 py-4 hover:opacity-90 transition">
      RUN PAYROLL
    </a>
  </div>

  <!-- DARK MODE: payroll breakdown table -->
  <div class="hidden dark:block">
    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold mb-4">Payroll Breakdown</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-3">Date</th>
              <th class="pr-6 pb-3">Days</th>
              <th class="pr-6 pb-3">Holiday</th>
              <th class="pr-6 pb-3">OT</th>
              <th class="pr-6 pb-3">Late</th>
              <th class="pr-6 pb-3">Basic</th>
              <th class="pr-6 pb-3">OT Pay</th>
              <th class="pr-6 pb-3">Holiday Pay</th>
              <th class="pr-6 pb-3">SSS</th>
              <th class="pr-6 pb-3">Pag-IBIG</th>
              <th class="pr-6 pb-3">PhilHealth</th>
              <th class="pb-3">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-t border-gray-200 dark:border-surface-border">
              <td colspan="12" class="text-center text-gray-500 dark:text-gray-400 py-6">No employee data</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>

<?php
$navBase = BASE_PATH . '/admin';
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
