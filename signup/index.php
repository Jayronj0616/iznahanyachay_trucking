<?php $pageTitle = 'Sign Up'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

<div class="relative min-h-screen flex items-center justify-center bg-white dark:bg-surface px-4 py-12">
  <!-- Placeholder backdrop: swap for a real road/truck photo at assets/images/signup-truck.jpg -->
  <div class="absolute inset-0 bg-gradient-to-br from-white via-orange-50 to-white dark:from-surface dark:via-[#0F1830] dark:to-[#1A0F08]"></div>
  <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 20%, rgba(251,191,36,0.12), transparent 40%), radial-gradient(circle at 85% 80%, rgba(249,115,22,0.12), transparent 45%);"></div>

  <div class="absolute top-6 right-6">
    <?php include __DIR__ . '/../includes/theme-toggle.php'; ?>
  </div>

  <div class="relative w-full max-w-md bg-white/90 dark:bg-surface-card/90 border border-gray-200 dark:border-surface-border rounded-2xl px-8 py-10 shadow-sm dark:shadow-none">
    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white text-center mb-8">Sign Up</h1>

    <div class="space-y-4">
      <input
        type="text"
        placeholder="Full Name"
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
      <input
        type="email"
        placeholder="Enter Email"
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
      <input
        type="password"
        placeholder="Enter Password"
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
      <input
        type="password"
        placeholder="Confirm Password"
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
    </div>

    <div class="mt-6">
      <a href="<?php echo BASE_PATH; ?>/login/" class="block text-center bg-brand-orange text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
        SIGN UP
      </a>
    </div>

    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
      Already have an account?
      <a href="<?php echo BASE_PATH; ?>/login/" class="font-bold text-gray-900 dark:text-white underline">LOGIN</a>
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/foot.php'; ?>
