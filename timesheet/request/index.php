<?php
// Panel feedback (sample slides 4-6): photo-verified time-in only works for today
// (timesheet/entry/ hard-blocks any other date and has no edit path once a punch is
// recorded), so a camera failure or a missed punch has no way to be corrected. This
// is that correction request -- what the time in/out should have been, why, and
// optionally a proof photo -- reviewed by Payroll Master/Admin in
// timesheet/requests/.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/biometric.php';
requireLogin();

if ($_SESSION['user']['role'] !== 'employee') {
    header('Location: ' . BASE_PATH . '/timesheet/');
    exit;
}

$pageTitle = 'Request Correction';
$activeNav = 'timesheet';
$userId = (int) $_SESSION['user']['id'];
$db = getDB();
$error = null;
$success = null;

$date = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $timeIn = trim($_POST['requested_time_in'] ?? '');
    $timeOut = trim($_POST['requested_time_out'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $photoData = $_POST['photo_data'] ?? '';

    if (!$date || strtotime($date) === false) {
        $error = 'Please choose a valid date.';
    } elseif ($date > date('Y-m-d')) {
        $error = 'You cannot request a correction for a future date.';
    } elseif (!$timeIn && !$timeOut) {
        $error = 'Enter at least a time in or a time out.';
    } elseif ($timeIn && $timeOut && $timeOut <= $timeIn) {
        $error = 'Time out must be after time in.';
    } elseif (!$reason) {
        $error = 'Please explain what happened.';
    } else {
        $stmt = $db->prepare("SELECT id FROM attendance_correction_requests WHERE user_id = ? AND date = ? AND status = 'pending'");
        $stmt->execute([$userId, $date]);
        if ($stmt->fetch()) {
            $error = 'You already have a pending correction request for this date.';
        } else {
            $photoPath = null;
            if ($photoData) {
                $photoPath = saveBiometricPhoto($photoData, $userId, $date, 'corrections');
            }

            $stmt = $db->prepare(
                'INSERT INTO attendance_correction_requests (user_id, date, requested_time_in, requested_time_out, reason, proof_photo_path)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $date, $timeIn ?: null, $timeOut ?: null, $reason, $photoPath]);
            $success = 'Correction request submitted. Payroll Master/Admin will review it.';
        }
    }
}

$myRequests = $db->prepare(
    'SELECT * FROM attendance_correction_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10'
);
$myRequests->execute([$userId]);
$myRequests = $myRequests->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/head.php';

$pageIcon = '⏱️';
$pageLabel = 'Timesheet';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-2xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <div>
    <a href="<?php echo BASE_PATH; ?>/timesheet/" class="text-brand-orange text-sm font-semibold">&larr; Back to calendar</a>
  </div>

  <h1 class="text-gray-900 dark:text-white font-bold text-lg">Request a Correction</h1>
  <p class="text-sm text-gray-500 dark:text-gray-400 -mt-4">
    Missed a punch, or the camera didn't work? Say what actually happened and Payroll Master/Admin will review it.
  </p>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <form method="POST" class="space-y-4" id="correction-form" data-confirm="Submit this correction request?">
      <div>
        <label for="req-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
        <input id="req-date" type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" max="<?php echo date('Y-m-d'); ?>" required style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="req-time-in" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Time In</label>
          <input id="req-time-in" type="time" name="requested_time_in" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <div>
          <label for="req-time-out" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Time Out</label>
          <input id="req-time-out" type="time" name="requested_time_out" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
      </div>
      <div>
        <label for="req-reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason</label>
        <textarea id="req-reason" name="reason" rows="3" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow"></textarea>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proof Photo (optional)</label>
        <input type="hidden" name="photo_data" id="photo_data" value="">
        <video id="camera-video" autoplay playsinline class="w-full rounded-lg border border-gray-300 dark:border-surface-border hidden mb-2"></video>
        <canvas id="camera-canvas" class="hidden"></canvas>
        <img id="photo-preview" class="hidden w-full rounded-lg border border-gray-300 dark:border-surface-border mb-2" alt="Captured proof">
        <div class="flex gap-2">
          <button type="button" id="camera-trigger" class="flex-1 border border-gray-300 dark:border-surface-border text-gray-700 dark:text-gray-300 font-semibold rounded-lg px-4 py-2.5 hover:bg-gray-100 dark:hover:bg-white/5 transition">Take Photo</button>
          <button type="button" id="camera-capture" class="hidden flex-1 bg-brand-orange text-white font-semibold rounded-lg px-4 py-2.5 hover:opacity-90 transition">Capture</button>
          <button type="button" id="camera-retake" class="hidden flex-1 border border-gray-300 dark:border-surface-border text-gray-700 dark:text-gray-300 font-semibold rounded-lg px-4 py-2.5 hover:bg-gray-100 dark:hover:bg-white/5 transition">Retake</button>
        </div>
      </div>

      <button type="submit" class="block w-full text-center bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Submit Request</button>
    </form>
  </div>

  <?php if (!empty($myRequests)): ?>
  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Your Recent Requests</h2>
    <div class="space-y-3">
      <?php foreach ($myRequests as $r): ?>
        <?php
          $statusClasses = [
              'pending' => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300',
              'approved' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
              'declined' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
          ];
        ?>
        <div class="flex items-start justify-between gap-3 border-t border-gray-200 dark:border-surface-border pt-3 first:border-t-0 first:pt-0">
          <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($r['date']); ?></p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              <?php echo htmlspecialchars($r['requested_time_in'] ?? '—'); ?> – <?php echo htmlspecialchars($r['requested_time_out'] ?? '—'); ?>
            </p>
          </div>
          <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full <?php echo $statusClasses[$r['status']]; ?>"><?php echo ucfirst($r['status']); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</main>

<script>
(function () {
  var video = document.getElementById('camera-video');
  var canvas = document.getElementById('camera-canvas');
  var preview = document.getElementById('photo-preview');
  var triggerBtn = document.getElementById('camera-trigger');
  var captureBtn = document.getElementById('camera-capture');
  var retakeBtn = document.getElementById('camera-retake');
  var photoInput = document.getElementById('photo_data');
  var stream = null;

  triggerBtn.addEventListener('click', function () {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } }).then(function (s) {
      stream = s;
      video.srcObject = s;
      video.classList.remove('hidden');
      triggerBtn.classList.add('hidden');
      captureBtn.classList.remove('hidden');
    }).catch(function () {
      alert('Could not access the camera.');
    });
  });

  captureBtn.addEventListener('click', function () {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    photoInput.value = canvas.toDataURL('image/jpeg', 0.85);
    preview.src = photoInput.value;

    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); }
    video.classList.add('hidden');
    captureBtn.classList.add('hidden');
    preview.classList.remove('hidden');
    retakeBtn.classList.remove('hidden');
  });

  retakeBtn.addEventListener('click', function () {
    photoInput.value = '';
    preview.classList.add('hidden');
    retakeBtn.classList.add('hidden');
    triggerBtn.classList.remove('hidden');
  });
})();
</script>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
