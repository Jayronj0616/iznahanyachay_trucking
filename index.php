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
?>
<?php include __DIR__ . '/includes/head.php'; ?>

<!-- Navbar -->
<header class="fixed top-0 left-0 right-0 z-30 bg-white/95 dark:bg-surface/95 backdrop-blur border-b border-gray-200 dark:border-surface-border">
  <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
    <span class="flex items-center gap-2 min-w-0">
      <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="Iznahanyachay Trucking Services" class="h-9 w-9 rounded-full shrink-0">
      <span class="hidden sm:inline text-orange-600 dark:text-brand-yellow font-extrabold tracking-wide text-lg truncate">IZNAHANYACHAY</span>
    </span>
    <div class="flex items-center gap-3">
      <?php include __DIR__ . '/includes/theme-toggle.php'; ?>
      <button type="button" onclick="openLoginModal()" class="bg-orange-500 dark:bg-brand-yellow text-white dark:text-surface font-semibold text-sm px-5 py-2.5 rounded-full hover:opacity-90 transition">
        Login
      </button>
    </div>
  </div>
</header>

<!-- Hero -->
<section class="relative pt-16 min-h-screen flex items-center overflow-hidden bg-white dark:bg-surface">
  <!-- Placeholder backdrop: swap for a real road/truck photo at assets/images/hero-truck.jpg -->
  <div class="absolute inset-0 bg-gradient-to-br from-white via-orange-50 to-white dark:from-surface dark:via-[#0F1830] dark:to-[#1A0F08]"></div>
  <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.15), transparent 40%), radial-gradient(circle at 80% 70%, rgba(249,115,22,0.12), transparent 45%);"></div>

  <!-- Client-requested: company logo on both sides of the hero. Only shown from xl (1280px)
       up, where the centered max-w-5xl content actually leaves enough side margin (128px+)
       to not collide with it; sized to grow with the available margin at larger widths. -->
  <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="Iznahanyachay Trucking Services logo" class="hidden xl:block absolute left-6 2xl:left-12 top-1/2 -translate-y-1/2 w-28 2xl:w-40 opacity-90">
  <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" aria-hidden="true" class="hidden xl:block absolute right-6 2xl:right-12 top-1/2 -translate-y-1/2 w-28 2xl:w-40 opacity-90">

  <div class="relative max-w-5xl mx-auto px-6 py-24 text-center">
    <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold text-gray-900 dark:text-white leading-tight">
      Payroll and Attendance,<br>
      <span class="text-orange-600 dark:text-brand-yellow">Automated</span> for Your Fleet.
    </h1>
    <p class="mt-6 text-gray-600 dark:text-gray-300 text-base sm:text-lg max-w-2xl mx-auto">
      Trip-based attendance, driver and helper commission payroll, and employee management — all in one internal system.
    </p>
    <button type="button" onclick="openLoginModal()" class="inline-block mt-8 bg-orange-500 dark:bg-brand-yellow text-white dark:text-surface font-bold px-8 py-3.5 rounded-full hover:opacity-90 transition">
      Get Started
    </button>

    <div class="mt-16 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-3xl mx-auto">
      <div class="bg-white dark:bg-surface-card/80 border border-gray-200 dark:border-surface-border rounded-xl px-4 py-5 shadow-sm dark:shadow-none">
        <div class="text-orange-600 dark:text-brand-yellow text-lg font-extrabold min-h-[3.5rem]">Automated</div>
        <div class="text-gray-600 dark:text-gray-300 text-sm mt-1">Payroll</div>
      </div>
      <div class="bg-white dark:bg-surface-card/80 border border-gray-200 dark:border-surface-border rounded-xl px-4 py-5 shadow-sm dark:shadow-none">
        <div class="text-orange-600 dark:text-brand-yellow text-lg font-extrabold min-h-[3.5rem]">Trip-Based</div>
        <div class="text-gray-600 dark:text-gray-300 text-sm mt-1">Attendance</div>
      </div>
      <div class="bg-white dark:bg-surface-card/80 border border-gray-200 dark:border-surface-border rounded-xl px-4 py-5 shadow-sm dark:shadow-none">
        <div class="text-orange-600 dark:text-brand-yellow text-lg font-extrabold min-h-[3.5rem]">Driver &amp; Helper</div>
        <div class="text-gray-600 dark:text-gray-300 text-sm mt-1">Commission Tracking</div>
      </div>
      <div class="bg-white dark:bg-surface-card/80 border border-gray-200 dark:border-surface-border rounded-xl px-4 py-5 shadow-sm dark:shadow-none">
        <div class="text-orange-600 dark:text-brand-yellow text-lg font-extrabold min-h-[3.5rem]">Admin-Controlled</div>
        <div class="text-gray-600 dark:text-gray-300 text-sm mt-1">Account Access</div>
      </div>
    </div>
  </div>
</section>

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

    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white text-center mb-8">Login</h1>

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

      <button type="submit" class="block w-full text-center bg-brand-orange text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
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

  window.openLoginModal = function () {
    modal.classList.remove('hidden');
  };

  function closeLoginModal() {
    modal.classList.add('hidden');
  }

  closeBtn.addEventListener('click', closeLoginModal);
  backdrop.addEventListener('click', closeLoginModal);

  <?php if ($loginError): ?>
  openLoginModal();
  <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/foot.php'; ?>
