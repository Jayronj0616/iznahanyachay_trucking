<?php
$pageTitle = 'Timesheet Entry';
$activeNav = 'timesheet';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/biometric.php';
requireLogin();
$isAdmin = $_SESSION['user']['role'] === 'admin';

$db = getDB();
$error = null;
$success = null;

$userId = $isAdmin ? (isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0) : (int) $_SESSION['user']['id'];
$date = $_GET['date'] ?? '';

if ($isAdmin) {
    $stmt = $db->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'employee'");
    $stmt->execute([$userId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare('SELECT id, name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$employee || !$date) {
    header('Location: ' . BASE_PATH . '/timesheet/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAdmin && (isset($_POST['save_time_in']) || isset($_POST['save_time_out']))) {
        // Admin is view/approve/reject/delete only — never allowed to record time in/out, even via crafted POST.
        header('Location: ' . BASE_PATH . '/timesheet/entry/?user_id=' . $userId . '&date=' . urlencode($date));
        exit;
    }

    if (isset($_POST['save_time_in'])) {
        $timeIn = date('H:i:s');
        $photoData = $_POST['photo_data'] ?? '';

        $stmt = $db->prepare('SELECT id, time_in FROM timesheet_entries WHERE user_id = ? AND date = ?');
        $stmt->execute([$userId, $date]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && $existing['time_in']) {
            $error = 'Time in is already recorded for this date.';
        } elseif ($date !== date('Y-m-d')) {
            $error = 'Time in can only be recorded for the current date.';
        } elseif (!$isAdmin && !$photoData) {
            $error = 'A biometric photo capture is required to time in.';
        } else {
            $photoPath = null;
            if ($photoData) {
                $photoPath = saveBiometricPhoto($photoData, $userId, $date);
                if (!$photoPath) {
                    $error = 'Could not save the captured photo. Please try again.';
                }
            }

            if (!$error) {
                if ($existing) {
                    $stmt = $db->prepare('UPDATE timesheet_entries SET time_in = ?, time_in_photo = ?, type = "manual" WHERE id = ?');
                    $stmt->execute([$timeIn, $photoPath, $existing['id']]);
                } else {
                    $stmt = $db->prepare('INSERT INTO timesheet_entries (user_id, date, time_in, time_in_photo, type) VALUES (?, ?, ?, ?, "manual")');
                    $stmt->execute([$userId, $date, $timeIn, $photoPath]);
                }
                header('Location: ' . BASE_PATH . '/timesheet/' . ($isAdmin ? '?user_id=' . $userId : ''));
                exit;
            }
        }
    } elseif (isset($_POST['save_time_out'])) {
        $timeOut = date('H:i:s');

        $stmt = $db->prepare('SELECT id, time_in FROM timesheet_entries WHERE user_id = ? AND date = ?');
        $stmt->execute([$userId, $date]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing || !$existing['time_in']) {
            $error = 'You must time in before you can time out.';
        } elseif ($date !== date('Y-m-d')) {
            $error = 'Time out can only be recorded for the current date.';
        } elseif ($timeOut <= $existing['time_in']) {
            $error = 'Time out must be after time in.';
        } else {
            $stmt = $db->prepare('UPDATE timesheet_entries SET time_out = ? WHERE id = ?');
            $stmt->execute([$timeOut, $existing['id']]);
            header('Location: ' . BASE_PATH . '/timesheet/' . ($isAdmin ? '?user_id=' . $userId : ''));
            exit;
        }
    } elseif ($isAdmin && isset($_POST['approve'])) {
        $stmt = $db->prepare('UPDATE timesheet_entries SET status = "approved", rejection_reason = NULL WHERE user_id = ? AND date = ?');
        $stmt->execute([$userId, $date]);
        $success = 'Entry approved.';
    } elseif ($isAdmin && isset($_POST['reject'])) {
        // Reject is kept DB-side only (no UI trigger currently wired) per explicit direction.
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (!$reason) {
            $error = 'A rejection reason is required.';
        } else {
            $stmt = $db->prepare('UPDATE timesheet_entries SET status = "rejected", rejection_reason = ? WHERE user_id = ? AND date = ?');
            $stmt->execute([$reason, $userId, $date]);
            $success = 'Entry rejected.';
        }
    } elseif ($isAdmin && isset($_POST['delete_entry'])) {
        // Soft delete: only allowed while status is still pending. Approved entries can never be deleted from here.
        $stmt = $db->prepare("UPDATE timesheet_entries SET deleted_at = NOW() WHERE user_id = ? AND date = ? AND status = 'pending' AND deleted_at IS NULL");
        $stmt->execute([$userId, $date]);
        if ($stmt->rowCount() > 0) {
            $success = 'Entry deleted.';
        } else {
            $error = 'This entry cannot be deleted (already approved or already deleted).';
        }
    }
}

$stmt = $db->prepare('SELECT * FROM timesheet_entries WHERE user_id = ? AND date = ?');
$stmt->execute([$userId, $date]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

// Deleted entries are terminal for everyone: no recovery via this page, treated as if no entry exists.
$isDeleted = $entry && !empty($entry['deleted_at']);
if ($isDeleted) {
    $entry = null;
}

// Employee sees a generic contact-admin message for rejected entries, no status/reason exposed.
// Admin sees real state and can still approve a rejected entry.
$isRejectedForEmployee = !$isAdmin && $entry && $entry['status'] === 'rejected';

include __DIR__ . '/../../includes/head.php';

$pageIcon = '⏱️';
$pageLabel = 'Timesheet';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-2xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">

  <div>
    <a href="<?php echo BASE_PATH; ?>/timesheet/<?php echo $isAdmin ? '?user_id=' . $userId : ''; ?>" class="text-brand-orange text-sm font-semibold">&larr; Back to calendar</a>
  </div>

  <h1 class="text-gray-900 dark:text-white font-bold text-lg">
    <?php echo htmlspecialchars($employee['name']); ?> — <?php echo htmlspecialchars($date); ?>
  </h1>

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

  <?php $isToday = ($date === date('Y-m-d')); ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Time In / Time Out</h2>

    <?php if ($isRejectedForEmployee): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">There was an issue with this entry — please contact your admin.</p>

    <?php elseif ($isAdmin): ?>
      <?php if (!$entry || !$entry['time_in']): ?>
        <p class="text-gray-500 dark:text-gray-400 text-sm">No entry recorded for this date.</p>
      <?php else: ?>
        <div class="mb-3">
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Time In (recorded)</label>
          <input type="time" value="<?php echo htmlspecialchars($entry['time_in']); ?>" disabled class="w-full bg-gray-100 dark:bg-surface/50 border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-500 dark:text-gray-400">
        </div>
        <?php if (!empty($entry['time_in_photo'])): ?>
        <div class="mb-3">
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Time In Photo</label>
          <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($entry['time_in_photo']); ?>" alt="Time in photo" class="w-full rounded-lg border border-gray-300 dark:border-surface-border">
        </div>
        <?php endif; ?>
        <?php if ($entry['time_out']): ?>
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Time Out (recorded)</label>
          <input type="time" value="<?php echo htmlspecialchars($entry['time_out']); ?>" disabled class="w-full bg-gray-100 dark:bg-surface/50 border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-500 dark:text-gray-400">
        </div>
        <?php else: ?>
        <p class="text-gray-500 dark:text-gray-400 text-sm">Time out not yet recorded.</p>
        <?php endif; ?>
      <?php endif; ?>

    <?php elseif (!$isToday && (!$entry || !$entry['time_in'])): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">
        <?php echo $date > date('Y-m-d') ? 'This is a future date — nothing to show yet.' : 'No entry was recorded for this date.'; ?>
      </p>

    <?php elseif (!$entry || !$entry['time_in']): ?>
      <form method="POST" class="space-y-3" id="time-in-form" data-confirm="Confirm time in now?">
        <input type="time" name="time_in" required value="<?php echo date('H:i'); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <input type="hidden" name="photo_data" id="photo_data" value="">

        <video id="camera-video" autoplay playsinline class="w-full rounded-lg border border-gray-300 dark:border-surface-border hidden"></video>
        <canvas id="camera-canvas" class="hidden"></canvas>
        <button type="button" id="time-in-trigger" class="block w-full text-center bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Time In</button>
        <button type="button" id="camera-capture-btn" class="hidden block w-full text-center bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Capture Photo</button>
        <button type="submit" name="save_time_in" value="1" id="time-in-submit" class="hidden"></button>
      </form>

      <script>
      (function () {
        var video = document.getElementById('camera-video');
        var canvas = document.getElementById('camera-canvas');
        var triggerBtn = document.getElementById('time-in-trigger');
        var captureBtn = document.getElementById('camera-capture-btn');
        var photoInput = document.getElementById('photo_data');
        var form = document.getElementById('time-in-form');
        var stream = null;

        triggerBtn.addEventListener('click', function () {
          navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } }).then(function (s) {
            stream = s;
            video.srcObject = s;
            video.classList.remove('hidden');
            triggerBtn.classList.add('hidden');
            captureBtn.classList.remove('hidden');
          }).catch(function () {
            alert('Camera access is required to time in. Please allow camera access and try again.');
          });
        });

        captureBtn.addEventListener('click', function () {
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          canvas.getContext('2d').drawImage(video, 0, 0);
          photoInput.value = canvas.toDataURL('image/jpeg', 0.85);

          if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
          }
          video.classList.add('hidden');
          captureBtn.classList.add('hidden');

          form.requestSubmit(document.getElementById('time-in-submit'));
        });
      })();
      </script>

    <?php elseif (!$entry['time_out']): ?>
      <div class="mb-3">
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Time In (recorded)</label>
        <input type="time" value="<?php echo htmlspecialchars($entry['time_in']); ?>" disabled class="w-full bg-gray-100 dark:bg-surface/50 border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-500 dark:text-gray-400">
      </div>
      <?php if (!empty($entry['time_in_photo'])): ?>
      <div class="mb-3">
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Time In Photo</label>
        <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($entry['time_in_photo']); ?>" alt="Time in photo" class="w-full rounded-lg border border-gray-300 dark:border-surface-border">
      </div>
      <?php endif; ?>
      <form method="POST" class="space-y-3" data-confirm="Confirm time out now?">
        <input type="time" disabled value="<?php echo date('H:i'); ?>" style="color-scheme: light;" class="w-full bg-gray-100 dark:bg-surface/50 border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-500 dark:text-gray-400">
        <button type="submit" name="save_time_out" value="1" class="block w-full text-center bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Time Out</button>
      </form>

    <?php else: ?>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Time In</label>
          <input type="time" value="<?php echo htmlspecialchars($entry['time_in']); ?>" disabled class="w-full bg-gray-100 dark:bg-surface/50 border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-500 dark:text-gray-400">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Time Out</label>
          <input type="time" value="<?php echo htmlspecialchars($entry['time_out']); ?>" disabled class="w-full bg-gray-100 dark:bg-surface/50 border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-500 dark:text-gray-400">
        </div>
      </div>
      <?php if (!empty($entry['time_in_photo'])): ?>
      <div class="mt-3">
        <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Time In Photo</label>
        <img src="<?php echo BASE_PATH . '/' . htmlspecialchars($entry['time_in_photo']); ?>" alt="Time in photo" class="w-full rounded-lg border border-gray-300 dark:border-surface-border">
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($entry && !$isRejectedForEmployee): ?>
    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold mb-4">Approval Status</h2>
      <?php if (!isset($entry['status'])): ?><?php $entry['status'] = 'pending'; ?><?php endif; ?>

      <?php if ($isAdmin): ?>
      <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
        Current status:
        <span class="font-semibold">
          <?php echo htmlspecialchars(ucfirst($entry['status'])); ?>
        </span>
        <?php if ($entry['status'] === 'rejected' && $entry['rejection_reason']): ?>
          — Reason: <?php echo htmlspecialchars($entry['rejection_reason']); ?>
        <?php endif; ?>
      </p>

      <div class="flex gap-3">
        <?php if ($entry['status'] !== 'approved'): ?>
        <form method="POST">
          <button type="submit" name="approve" value="1" class="bg-brand-orange text-white text-sm font-semibold px-4 py-2 rounded-full hover:opacity-90 transition">Approve</button>
        </form>
        <?php endif; ?>
        <?php if ($entry['status'] === 'pending'): ?>
        <form method="POST" data-confirm="Delete this entry? This cannot be undone.">
          <button type="submit" name="delete_entry" value="1" class="bg-red-600 text-white text-sm font-semibold px-4 py-2 rounded-full hover:opacity-90 transition">Delete</button>
        </form>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <p class="text-sm text-gray-700 dark:text-gray-300">
        Current status:
        <span class="font-semibold"><?php echo htmlspecialchars(ucfirst($entry['status'])); ?></span>
      </p>
      <?php endif; ?>
    </div>
  <?php elseif ($entry === null && !$isRejectedForEmployee): ?>
    <p class="text-gray-500 dark:text-gray-400 text-sm">No entry yet for this date — save a time first before approving or rejecting.</p>
  <?php endif; ?>

</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
