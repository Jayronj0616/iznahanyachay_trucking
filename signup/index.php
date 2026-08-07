<?php
$pageTitle = 'Sign Up';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'employee']);
            $newUserId = (int) $db->lastInsertId();

            $stmt = $db->prepare('INSERT INTO employee_profiles (user_id, status) VALUES (?, ?)');
            $stmt->execute([$newUserId, 'pending']);

            $db->commit();
            $success = true;
        } catch (PDOException $e) {
            $db->rollBack();
            if ((int) $e->getCode() === 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'That email is already registered.';
            } else {
                $error = 'Sign up failed. Please try again.';
            }
        }
    }
}

include __DIR__ . '/../includes/head.php';
?>

<div class="relative min-h-screen flex items-center justify-center bg-white dark:bg-surface px-4 py-12">
  <!-- Placeholder backdrop: swap for a real road/truck photo at assets/images/signup-truck.jpg -->
  <div class="absolute inset-0 bg-gradient-to-br from-white via-orange-50 to-white dark:from-surface dark:via-[#0F1830] dark:to-[#1A0F08]"></div>
  <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 20%, rgba(251,191,36,0.12), transparent 40%), radial-gradient(circle at 85% 80%, rgba(249,115,22,0.12), transparent 45%);"></div>

  <div class="absolute top-6 right-6">
    <?php include __DIR__ . '/../includes/theme-toggle.php'; ?>
  </div>

  <div class="relative w-full max-w-md bg-white/90 dark:bg-surface-card/90 border border-gray-200 dark:border-surface-border rounded-2xl px-8 py-10 shadow-sm dark:shadow-none">
    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white text-center mb-8">Sign Up</h1>

    <?php if ($success): ?>
      <p class="mb-4 text-center text-sm text-green-600 dark:text-green-400">Account created. It's awaiting admin approval before you can log in.</p>
      <a href="<?php echo BASE_PATH; ?>/login/" class="block text-center bg-brand-orange text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
        GO TO LOGIN
      </a>
    <?php else: ?>

    <?php if ($error): ?>
      <p class="mb-4 text-center text-sm text-red-500"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <input
        type="text"
        name="name"
        placeholder="Full Name"
        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
        required
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
      <input
        type="email"
        name="email"
        placeholder="Enter Email"
        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
        required
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
      <input
        type="password"
        name="password"
        placeholder="Enter Password"
        minlength="8"
        required
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >
      <input
        type="password"
        name="confirm_password"
        placeholder="Confirm Password"
        minlength="8"
        required
        class="w-full bg-transparent border border-gray-300 dark:border-surface-border rounded-full px-5 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow transition"
      >

      <button type="submit" class="block w-full text-center bg-brand-orange text-white font-bold rounded-full px-5 py-3 hover:opacity-90 transition">
        SIGN UP
      </button>
    </form>

    <?php endif; ?>

    <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
      Already have an account?
      <a href="<?php echo BASE_PATH; ?>/login/" class="font-bold text-gray-900 dark:text-white underline">LOGIN</a>
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/foot.php'; ?>
