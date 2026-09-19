<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$loginError = '';

// Login used to be its own page (/login/) — client asked for it as a modal on
// this landing page instead. Same attemptLogin() logic as before, just POSTing
// back here. /login/ itself now just redirects here (see login/index.php).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $loginError = 'Please enter email and password.';
    } else {
        $failReason = null;
        if (attemptLogin($email, $password, $failReason)) {
            header('Location: ' . BASE_PATH . '/home/');
            exit;
        } elseif ($failReason === 'pending') {
            $loginError = 'Your account is awaiting admin approval.';
        } else {
            $loginError = 'Invalid email or password.';
        }
    }
}

// Live counts for the stats strip. Deliberately limited to non-sensitive
// operational totals — no salary, commission or payslip figures, since this page
// is reachable without logging in. Wrapped in a try/catch because this is the
// public front door: if MySQL is down the page must still render rather than
// fatal, so the strip simply disappears instead of taking the site with it.
$stats = null;
try {
    $db = getDB();
    $stats = [
        'routes'    => (int) $db->query("SELECT COUNT(*) FROM routes WHERE active = 1")->fetchColumn(),
        'trips'     => (int) $db->query("SELECT COUNT(*) FROM trips_new WHERE status = 'completed'")->fetchColumn(),
        'employees' => (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn(),
        'periods'   => (int) $db->query("SELECT COUNT(DISTINCT CONCAT(period_start, '|', period_end)) FROM payroll_runs")->fetchColumn(),
    ];
} catch (Exception $e) {
    $stats = null;
}

$sessionUser = currentUser();
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<div class="bg-gray-50 dark:bg-surface">

<!-- Floating pill navbar -->
<header class="fixed top-3 sm:top-5 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-1.5rem)] max-w-6xl">
  <nav class="bg-white/90 dark:bg-surface-card/90 backdrop-blur border border-gray-200 dark:border-surface-border rounded-full shadow-sm px-3 sm:px-5 h-14 sm:h-16 flex items-center justify-between gap-3">
    <a href="#top" class="flex items-center gap-2 min-w-0 shrink-0">
      <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="Iznahanyachay Trucking Services" class="h-9 w-9 sm:h-10 sm:w-10 rounded-full shrink-0">
      <span class="hidden sm:block leading-tight min-w-0">
        <span class="block text-brand-orange dark:text-brand-yellow font-extrabold tracking-wide text-sm lg:text-base truncate">IZNAHANYACHAY</span>
        <span class="block text-[10px] tracking-[0.2em] text-gray-400 dark:text-gray-500 uppercase">Trucking Services</span>
      </span>
    </a>

    <div class="hidden lg:flex items-center gap-7 text-sm font-medium text-gray-600 dark:text-gray-300">
      <a href="#how-it-works" class="hover:text-brand-orange dark:hover:text-brand-yellow transition">How It Works</a>
      <a href="#features" class="hover:text-brand-orange dark:hover:text-brand-yellow transition">Features</a>
      <a href="#pay-model" class="hover:text-brand-orange dark:hover:text-brand-yellow transition">Pay Model</a>
      <a href="#about" class="hover:text-brand-orange dark:hover:text-brand-yellow transition">About</a>
    </div>

    <div class="flex items-center gap-2 shrink-0">
      <?php include __DIR__ . '/includes/theme-toggle.php'; ?>
      <?php if ($sessionUser): ?>
        <a href="<?php echo BASE_PATH; ?>/home/" class="bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-semibold text-sm px-4 sm:px-5 py-2.5 rounded-full hover:opacity-90 transition whitespace-nowrap">
          Dashboard
        </a>
      <?php else: ?>
        <button type="button" onclick="openLoginModal()" class="bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-semibold text-sm px-4 sm:px-5 py-2.5 rounded-full hover:opacity-90 transition whitespace-nowrap">
          Login
        </button>
      <?php endif; ?>
      <button type="button" id="ts-menu-btn" aria-label="Open menu" aria-expanded="false" class="lg:hidden h-10 w-10 grid place-items-center rounded-full text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-surface transition">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5">
          <line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>
    </div>
  </nav>

  <!-- Mobile anchor menu -->
  <div id="ts-menu" class="hidden lg:hidden mt-2 bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-2xl shadow-lg p-2">
    <a href="#how-it-works" class="ts-menu-link block px-4 py-3 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-surface transition">How It Works</a>
    <a href="#features" class="ts-menu-link block px-4 py-3 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-surface transition">Features</a>
    <a href="#pay-model" class="ts-menu-link block px-4 py-3 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-surface transition">Pay Model</a>
    <a href="#about" class="ts-menu-link block px-4 py-3 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-surface transition">About</a>
  </div>
</header>

<main id="top" class="pt-24 sm:pt-28">

  <!-- ==================== HERO ==================== -->
  <section class="px-3 sm:px-6">
    <div class="relative max-w-6xl mx-auto rounded-3xl overflow-hidden min-h-[30rem] sm:min-h-[34rem] flex items-end">
      <!-- Backdrop. A real fleet/road photo would drop in here as an <img> behind
           the overlay; until one exists this is a built gradient plus a road-lane
           motif rather than a stock placeholder. -->
      <div class="absolute inset-0 bg-gradient-to-br from-[#1A1005] via-[#2A1608] to-[#0B0F1A]"></div>
      <div class="absolute inset-0 opacity-60" style="background-image: radial-gradient(circle at 15% 20%, rgba(251,191,36,0.28), transparent 45%), radial-gradient(circle at 85% 80%, rgba(249,115,22,0.22), transparent 50%);"></div>
      <div class="absolute inset-x-0 bottom-0 h-40 opacity-[0.12]" style="background-image: repeating-linear-gradient(105deg, #fff 0 60px, transparent 60px 140px);"></div>
      <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" aria-hidden="true" class="absolute right-[-3rem] top-1/2 -translate-y-1/2 w-72 sm:w-96 lg:w-[30rem] opacity-[0.07] grayscale pointer-events-none select-none">

      <div class="relative w-full px-6 sm:px-10 lg:px-14 py-12 sm:py-16">
        <span class="inline-block bg-white/10 border border-white/20 backdrop-blur text-brand-yellow text-[11px] sm:text-xs font-semibold tracking-[0.18em] uppercase px-4 py-2 rounded-full">
          Fleet Payroll &amp; Trip Management
        </span>

        <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-[1.1] max-w-3xl">
          Every trip accounted for,<br>
          <span class="text-brand-yellow">every peso explained.</span>
        </h1>

        <p class="mt-5 text-gray-300 text-base sm:text-lg leading-relaxed max-w-xl">
          Iznahanyachay runs trip assignment, attendance and payroll in one place — with an
          admin acceptance step before any trip becomes payable.
        </p>

        <!-- Deliberately no Login button in the hero. The navbar carries a persistent
             one and the closing CTA carries the other; a third made the page read as
             three requests to log in rather than something to look through. These two
             point inward instead, which is the whole point of the page. -->
        <div class="mt-8 flex flex-wrap items-center gap-3">
          <?php if ($sessionUser): ?>
            <a href="<?php echo BASE_PATH; ?>/home/" class="group inline-flex items-center gap-2 bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold px-7 py-3.5 rounded-full hover:opacity-90 transition">
              Go to Dashboard
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1 motion-reduce:transform-none"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </a>
          <?php else: ?>
            <a href="#how-it-works" class="group inline-flex items-center gap-2 bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold px-7 py-3.5 rounded-full hover:opacity-90 transition">
              See how it works
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 transition-transform duration-200 group-hover:translate-y-1 motion-reduce:transform-none"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
            </a>
          <?php endif; ?>
          <a href="#features" class="group inline-flex items-center gap-2.5 bg-white/95 text-gray-900 font-semibold px-6 py-3.5 rounded-full hover:bg-white transition">
            <span class="h-8 w-8 grid place-items-center rounded-full bg-brand-orange/10 text-brand-orange shrink-0 transition-transform duration-200 group-hover:scale-110 motion-reduce:transform-none">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </span>
            <span class="text-left leading-tight">
              <span class="block text-[10px] uppercase tracking-wider text-gray-500">What's inside</span>
              <span class="block text-sm">Explore the features</span>
            </span>
          </a>
        </div>

        <div class="mt-8 inline-flex items-center gap-3 bg-white/10 border border-white/15 backdrop-blur rounded-full pl-4 pr-5 py-2.5">
          <span class="h-7 w-7 grid place-items-center rounded-full bg-brand-green/20 text-brand-green shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
          </span>
          <span class="text-xs sm:text-sm text-gray-200">No trip is paid until an admin accepts the delivery.</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ==================== STATS ==================== -->
  <?php if ($stats): ?>
  <section class="px-3 sm:px-6 -mt-8 sm:-mt-10 relative z-10">
    <!-- overflow-hidden, NOT per-cell first:/last: rounding. This grid is 4-up on
         desktop but 2x2 on mobile, where "first" and "last" are the top-left and
         bottom-right cells — so rounding them left the other two corners square and
         the cell hover tint painted over the container's radius. Clipping at the
         container handles both layouts. -->
    <div class="max-w-5xl mx-auto bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-2xl overflow-hidden shadow-sm grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-200 dark:divide-surface-border">
      <?php
      $statCards = [
          ['value' => $stats['routes'],    'label' => 'Active routes'],
          ['value' => $stats['trips'],     'label' => 'Trips completed'],
          ['value' => $stats['employees'], 'label' => 'Registered employees'],
          ['value' => $stats['periods'],   'label' => 'Payroll periods run'],
      ];
      foreach ($statCards as $card): ?>
        <!-- Tint + number scale rather than a lift: these sit in a divided grid, and
             translating one card would tear it away from its dividers. -->
        <div class="group px-4 py-6 text-center transition-colors duration-200 hover:bg-orange-50/60 dark:hover:bg-brand-yellow/5">
          <div class="text-3xl sm:text-4xl font-extrabold text-brand-orange dark:text-brand-yellow transition-transform duration-200 group-hover:scale-110 motion-reduce:transform-none"><?php echo $card['value']; ?></div>
          <div class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400"><?php echo $card['label']; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ==================== HOW IT WORKS ==================== -->
  <section id="how-it-works" class="px-3 sm:px-6 pt-20 sm:pt-28 scroll-mt-28">
    <div class="max-w-6xl mx-auto">
      <div class="grid lg:grid-cols-2 gap-6 lg:gap-12 items-end">
        <div>
          <span class="text-brand-orange dark:text-brand-yellow text-xs font-bold tracking-[0.2em] uppercase">The trip lifecycle</span>
          <h2 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900 dark:text-white leading-tight">
            Four steps from<br>assignment to payslip.
          </h2>
        </div>
        <p class="text-gray-600 dark:text-gray-300 leading-relaxed lg:pb-3">
          A trip cannot be completed and paid in a single click. The driver reports the run finished,
          and the admin accepts it separately — so a delivery can still be reviewed, returned or
          cancelled before any money is committed.
        </p>
      </div>

      <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        $steps = [
            [
                'n' => '01', 'title' => 'Assign',
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
                'body' => 'The admin puts a driver, and optionally a helper, on an active route. Anyone already on an open trip is blocked from being double-booked.',
            ],
            [
                'n' => '02', 'title' => 'Delivered',
                'icon' => '<rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
                'body' => 'The run is reported finished. Nothing is paid and no attendance is written yet — this step is fully reversible.',
            ],
            [
                'n' => '03', 'title' => 'Accepted',
                'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                'body' => 'The admin accepts the delivery. Only now is attendance recorded, dated to the day of delivery rather than the day of acceptance.',
            ],
            [
                'n' => '04', 'title' => 'Payable',
                'icon' => '<rect x="2" y="7" width="20" height="10" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01"/><path d="M18 12h.01"/>',
                'body' => 'The trip becomes eligible for the next payroll run at the driver or helper commission rate on that route.',
            ],
        ];
        foreach ($steps as $step): ?>
          <div class="group relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-2xl p-6 transition duration-200 hover:-translate-y-1 hover:shadow-lg hover:border-brand-orange/40 dark:hover:border-brand-yellow/30 motion-reduce:transform-none">
            <span class="absolute top-5 right-6 text-2xl font-extrabold text-gray-200 dark:text-surface-border select-none transition-colors duration-200 group-hover:text-brand-orange/30 dark:group-hover:text-brand-yellow/20"><?php echo $step['n']; ?></span>
            <span class="h-11 w-11 grid place-items-center rounded-xl bg-brand-orange/10 dark:bg-brand-yellow/10 text-brand-orange dark:text-brand-yellow transition-transform duration-200 group-hover:scale-110 motion-reduce:transform-none">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><?php echo $step['icon']; ?></svg>
            </span>
            <h3 class="mt-4 text-lg font-bold text-gray-900 dark:text-white"><?php echo $step['title']; ?></h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed"><?php echo $step['body']; ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ==================== FEATURES ==================== -->
  <section id="features" class="px-3 sm:px-6 pt-20 sm:pt-28 scroll-mt-28">
    <div class="max-w-6xl mx-auto">
      <div class="grid lg:grid-cols-2 gap-6 lg:gap-12 items-end">
        <div>
          <span class="text-brand-orange dark:text-brand-yellow text-xs font-bold tracking-[0.2em] uppercase">What the system does</span>
          <h2 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900 dark:text-white leading-tight">
            One system for the<br>whole operation.
          </h2>
        </div>
        <p class="text-gray-600 dark:text-gray-300 leading-relaxed lg:pb-3">
          Routes, trips, attendance, timesheets and payroll share the same records, so a figure on a
          payslip can always be traced back to the trip or the shift that produced it.
        </p>
      </div>

      <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php
        $features = [
            [
                'n' => '01', 'title' => 'Trip Assignment',
                'icon' => '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
                'body' => 'Routes carry their own rate. Assign a driver and helper, edit or cancel while the trip is still in progress, with a guard against double-booking a crew.',
            ],
            [
                // NOT "biometric" — the system stores a photo, it does not match anyone
                // against an enrolled record. There is no face recognition anywhere in
                // this project. Keep this label describing what actually happens.
                'n' => '02', 'title' => 'Photo-Verified Time-In',
                'icon' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
                'body' => 'The employee captures a webcam photo when they time in. It is stored with the entry and shown to the admin during approval, so attendance can be checked against a face instead of taken on trust.',
            ],
            [
                'n' => '03', 'title' => 'Timesheet Approval',
                'icon' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><polyline points="9 14 11 16 15 12"/>',
                'body' => 'Entries start pending. The admin approves or rejects per entry, or approves a whole period at once. Payroll only ever counts approved hours.',
            ],
            [
                'n' => '04', 'title' => 'Automated Payroll',
                'icon' => '<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="11" x2="8.01" y2="11"/><line x1="12" y1="11" x2="12.01" y2="11"/><line x1="16" y1="11" x2="16.01" y2="11"/><line x1="8" y1="16" x2="8.01" y2="16"/><line x1="12" y1="16" x2="12.01" y2="16"/><line x1="16" y1="16" x2="16.01" y2="16"/>',
                'body' => 'SSS, PhilHealth and Pag-IBIG applied per semi-monthly cutoff and itemised per employee, with a draft run that must be finalized before it locks.',
            ],
            [
                'n' => '05', 'title' => 'Trip Attendance',
                'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/>',
                'body' => 'Presence is recorded automatically when a delivery is accepted, and the report is filterable by employee and date range.',
            ],
            [
                'n' => '06', 'title' => 'Controlled Access',
                'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
                'body' => 'Accounts are created by the admin only — there is no public signup — and every new account must set its own password at first login.',
            ],
        ];
        foreach ($features as $f): ?>
          <div class="group relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-2xl p-6 transition duration-200 hover:-translate-y-1 hover:shadow-lg hover:border-brand-orange/40 dark:hover:border-brand-yellow/30 motion-reduce:transform-none">
            <span class="absolute top-5 right-6 text-sm font-bold text-gray-300 dark:text-surface-border select-none transition-colors duration-200 group-hover:text-brand-orange/40 dark:group-hover:text-brand-yellow/25"><?php echo $f['n']; ?></span>
            <span class="h-11 w-11 grid place-items-center rounded-xl bg-brand-orange/10 dark:bg-brand-yellow/10 text-brand-orange dark:text-brand-yellow transition-transform duration-200 group-hover:scale-110 motion-reduce:transform-none">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><?php echo $f['icon']; ?></svg>
            </span>
            <h3 class="mt-4 text-lg font-bold text-gray-900 dark:text-white pr-8"><?php echo $f['title']; ?></h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed"><?php echo $f['body']; ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ==================== PAY MODEL ==================== -->
  <!-- This band exists to break the rhythm of the white sections around it. In light
       mode the orange tint does that on its own; surface-card/40 was too close to the
       page background to read as a separate section in dark mode, so dark gets the
       full card colour instead of a transparent wash. -->
  <section id="pay-model" class="mt-20 sm:mt-28 scroll-mt-28 bg-orange-50/70 dark:bg-surface-card border-y border-orange-100 dark:border-surface-border">
    <div class="max-w-6xl mx-auto px-6 sm:px-8 py-16 sm:py-20 grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
      <div>
        <span class="text-brand-orange dark:text-brand-yellow text-xs font-bold tracking-[0.2em] uppercase">How people are paid</span>
        <h2 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900 dark:text-white leading-tight">
          Two pay models,<br>one payroll run.
        </h2>
        <p class="mt-5 text-gray-600 dark:text-gray-300 leading-relaxed">
          Crew on the road are paid per accepted trip; office and support staff are paid by the hour.
          Both are computed in the same run, with the same statutory deductions applied.
        </p>
      </div>

      <div class="space-y-4">
        <?php
        $payModels = [
            ['title' => 'Driver — 15% commission',  'body' => 'Fifteen percent of the route rate for every accepted trip. No hourly component and no timesheet required.'],
            ['title' => 'Helper — 8% commission',   'body' => 'Eight percent of the same route rate, recorded against the same trip as the driver.'],
            ['title' => 'Hourly staff — ₱100 / hr', 'body' => 'Dispatcher, secretary, maintenance, liaison and operations manager, at ₱110 per hour beyond eight hours in a day.'],
            ['title' => 'Statutory deductions',     'body' => 'SSS, PhilHealth and Pag-IBIG computed per cutoff and itemised, so every deduction on a run can be opened and read.'],
        ];
        foreach ($payModels as $pm): ?>
          <!-- dark:bg-surface, not surface-card: the band behind these is now
               surface-card, so these sit as darker tiles on a lighter band. Using
               the card colour here would make them disappear into it. -->
          <div class="group flex gap-4 bg-white dark:bg-surface border border-gray-200 dark:border-surface-border rounded-2xl p-5 transition duration-200 hover:-translate-y-1 hover:shadow-lg hover:border-brand-orange/40 dark:hover:border-brand-yellow/30 motion-reduce:transform-none">
            <span class="h-7 w-7 mt-0.5 grid place-items-center rounded-full bg-brand-green/15 text-brand-green shrink-0 transition-transform duration-200 group-hover:scale-110 motion-reduce:transform-none">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </span>
            <div>
              <h3 class="font-bold text-gray-900 dark:text-white"><?php echo $pm['title']; ?></h3>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 leading-relaxed"><?php echo $pm['body']; ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ==================== ABOUT / CTA ==================== -->
  <section id="about" class="px-3 sm:px-6 py-20 sm:py-28 scroll-mt-28">
    <div class="relative max-w-6xl mx-auto rounded-3xl overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-[#2A1608] via-[#1A1005] to-[#0B0F1A]"></div>
      <div class="absolute inset-0 opacity-60" style="background-image: radial-gradient(circle at 80% 30%, rgba(251,191,36,0.25), transparent 50%);"></div>
      <div class="relative px-6 sm:px-12 py-14 sm:py-20 grid lg:grid-cols-2 gap-8 items-center">
        <div>
          <span class="text-brand-yellow text-xs font-bold tracking-[0.2em] uppercase">About Iznahanyachay</span>
          <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-white leading-tight">
            Built for the way<br>the fleet actually runs.
          </h2>
          <p class="mt-5 text-gray-300 leading-relaxed max-w-lg">
            An internal system for Iznahanyachay Trucking Services — used by the admin to dispatch and
            pay, and by drivers, helpers and office staff to record the work they have done. Access is
            by invitation from the admin.
          </p>
        </div>
        <div class="lg:justify-self-end flex flex-wrap gap-3">
          <?php if ($sessionUser): ?>
            <a href="<?php echo BASE_PATH; ?>/home/" class="inline-flex items-center gap-2 bg-white text-gray-900 font-bold px-7 py-3.5 rounded-full hover:opacity-90 transition">
              Go to Dashboard
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </a>
          <?php else: ?>
            <button type="button" onclick="openLoginModal()" class="inline-flex items-center gap-2 bg-white text-gray-900 font-bold px-7 py-3.5 rounded-full hover:opacity-90 transition">
              Login to your account
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

</main>

<!-- ==================== FOOTER ==================== -->
<footer class="bg-surface border-t border-surface-border">
  <div class="max-w-6xl mx-auto px-6 py-10 flex flex-col sm:flex-row items-center justify-between gap-6 text-center sm:text-left">
    <div class="flex items-center gap-3">
      <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" class="h-11 w-11 rounded-full">
      <span class="leading-tight">
        <span class="block text-brand-yellow font-extrabold tracking-wide">IZNAHANYACHAY</span>
        <span class="block text-[10px] tracking-[0.2em] text-gray-500 uppercase">Trucking Services</span>
      </span>
    </div>
    <p class="text-sm text-gray-400 max-w-sm">
      Trip assignment, attendance and payroll for the Iznahanyachay fleet.
    </p>
    <p class="text-xs text-gray-500">&copy; <?php echo date('Y'); ?> Iznahanyachay Trucking Services</p>
  </div>
</footer>

</div>

<!-- Login modal — replaces the old standalone /login/ page. Same form/validation
     as before, just POSTing back to this page instead of a separate route. -->
<div id="ts-login-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
  <div id="ts-login-backdrop" class="absolute inset-0 bg-black/50"></div>
  <div class="relative w-full max-w-md bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-2xl px-8 py-10 shadow-xl">
    <button type="button" id="ts-login-close" aria-label="Close" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </button>

    <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" class="h-14 w-14 rounded-full mx-auto">
    <h1 class="mt-4 text-3xl font-extrabold text-gray-900 dark:text-white text-center mb-8">Login</h1>

    <?php if ($loginError): ?>
      <p class="mb-4 text-center text-sm text-red-500"><?php echo htmlspecialchars($loginError); ?></p>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <input
        type="email"
        name="email"
        autocomplete="email"
        placeholder="Enter Email"
        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
        required
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
      <input
        type="password"
        name="password"
        autocomplete="current-password"
        placeholder="Enter Password"
        required
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >

      <button type="submit" class="block w-full text-center bg-gradient-to-r from-brand-orange to-brand-yellow text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
        LOGIN
      </button>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('ts-login-modal');
  var backdrop = document.getElementById('ts-login-backdrop');
  var closeBtn = document.getElementById('ts-login-close');
  var menuBtn = document.getElementById('ts-menu-btn');
  var menu = document.getElementById('ts-menu');

  window.openLoginModal = function () {
    modal.classList.remove('hidden');
  };

  function closeLoginModal() {
    modal.classList.add('hidden');
  }

  function closeMenu() {
    menu.classList.add('hidden');
    menuBtn.setAttribute('aria-expanded', 'false');
  }

  closeBtn.addEventListener('click', closeLoginModal);
  backdrop.addEventListener('click', closeLoginModal);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeLoginModal();
      closeMenu();
    }
  });

  menuBtn.addEventListener('click', function () {
    var isOpen = menu.classList.toggle('hidden') === false;
    menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  Array.prototype.forEach.call(document.querySelectorAll('.ts-menu-link'), function (link) {
    link.addEventListener('click', closeMenu);
  });

  <?php if ($loginError): ?>
  openLoginModal();
  <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/foot.php'; ?>
