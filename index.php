<?php $pageTitle = 'Home'; ?>
<?php include __DIR__ . '/includes/head.php'; ?>

<!-- Navbar -->
<header class="fixed top-0 left-0 right-0 z-30 bg-white/95 dark:bg-surface/95 backdrop-blur border-b border-gray-200 dark:border-surface-border">
  <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
    <span class="text-orange-600 dark:text-brand-yellow font-extrabold tracking-wide text-lg">TRUCKING SYSTEM</span>
    <div class="flex items-center gap-3">
      <?php include __DIR__ . '/includes/theme-toggle.php'; ?>
      <a href="<?php echo BASE_PATH; ?>/signup/" class="bg-orange-500 dark:bg-brand-yellow text-white dark:text-surface font-semibold text-sm px-5 py-2.5 rounded-full hover:opacity-90 transition">
        Sign Up
      </a>
    </div>
  </div>
</header>

<!-- Hero -->
<section class="relative pt-16 min-h-screen flex items-center overflow-hidden bg-white dark:bg-surface">
  <!-- Placeholder backdrop: swap for a real road/truck photo at assets/images/hero-truck.jpg -->
  <div class="absolute inset-0 bg-gradient-to-br from-white via-orange-50 to-white dark:from-surface dark:via-[#0F1830] dark:to-[#1A0F08]"></div>
  <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 30%, rgba(251,191,36,0.15), transparent 40%), radial-gradient(circle at 80% 70%, rgba(249,115,22,0.12), transparent 45%);"></div>

  <div class="relative max-w-5xl mx-auto px-6 py-24 text-center">
    <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold text-gray-900 dark:text-white leading-tight">
      Heavy Loads. Light Worries.<br>
      <span class="text-orange-600 dark:text-brand-yellow">Trucking System</span> Has You Covered.
    </h1>
    <p class="mt-6 text-gray-600 dark:text-gray-300 text-base sm:text-lg max-w-2xl mx-auto">
      Reliable trucking services that deliver your goods safely, fast, and stress-free.
    </p>
    <a href="<?php echo BASE_PATH; ?>/login/" class="inline-block mt-8 bg-orange-500 dark:bg-brand-yellow text-white dark:text-surface font-bold px-8 py-3.5 rounded-full hover:opacity-90 transition">
      Get Started
    </a>

    <div class="mt-16 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-3xl mx-auto">
      <div class="bg-white dark:bg-surface-card/80 border border-gray-200 dark:border-surface-border rounded-xl px-4 py-5 shadow-sm dark:shadow-none">
        <div class="text-orange-600 dark:text-brand-yellow text-2xl font-extrabold">500+</div>
        <div class="text-gray-600 dark:text-gray-300 text-sm mt-1">Deliveries Completed</div>
      </div>
      <div class="bg-white dark:bg-surface-card/80 border border-gray-200 dark:border-surface-border rounded-xl px-4 py-5 shadow-sm dark:shadow-none">
        <div class="text-orange-600 dark:text-brand-yellow text-2xl font-extrabold">98%</div>
        <div class="text-gray-600 dark:text-gray-300 text-sm mt-1">On-Time Delivery</div>
      </div>
      <div class="bg-white dark:bg-surface-card/80 border border-gray-200 dark:border-surface-border rounded-xl px-4 py-5 shadow-sm dark:shadow-none">
        <div class="text-orange-600 dark:text-brand-yellow text-2xl font-extrabold">100+</div>
        <div class="text-gray-600 dark:text-gray-300 text-sm mt-1">Clients</div>
      </div>
      <div class="bg-white dark:bg-surface-card/80 border border-gray-200 dark:border-surface-border rounded-xl px-4 py-5 shadow-sm dark:shadow-none">
        <div class="text-orange-600 dark:text-brand-yellow text-2xl font-extrabold">24/7</div>
        <div class="text-gray-600 dark:text-gray-300 text-sm mt-1">Availability</div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/foot.php'; ?>
