<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Contribution Brackets';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;

$types = ['sss' => 'SSS', 'philhealth' => 'PhilHealth', 'pagibig' => 'Pag-IBIG'];

// A row is either a fixed monthly peso amount (employee_share, SSS's real-world
// table) or a rate applied to a base clamped between base_min/base_max (PhilHealth,
// Pag-IBIG). Exactly one of employee_share / rate must be set -- includes/payroll.php
// checks employee_share first, so leaving both blank would silently withhold 0 and
// leaving both set would silently ignore rate.
function validateBracketInput(array $post, array $types): array {
    $type = $post['type'] ?? '';
    $minMonthly = $post['min_monthly'] ?? '';
    $maxMonthly = trim($post['max_monthly'] ?? '');
    $mode = $post['mode'] ?? 'fixed';
    $employeeShare = trim($post['employee_share'] ?? '');
    $rate = trim($post['rate'] ?? '');
    $baseMin = trim($post['base_min'] ?? '');
    $baseMax = trim($post['base_max'] ?? '');
    $notes = trim($post['notes'] ?? '');

    if (!isset($types[$type])) {
        return [null, 'Invalid contribution type.'];
    }
    if (!is_numeric($minMonthly) || (float) $minMonthly < 0) {
        return [null, 'Minimum monthly salary must be zero or a positive number.'];
    }
    if ($maxMonthly !== '' && (!is_numeric($maxMonthly) || (float) $maxMonthly <= (float) $minMonthly)) {
        return [null, 'Maximum monthly salary must be greater than the minimum, or left blank for "and above".'];
    }

    if ($mode === 'fixed') {
        if (!is_numeric($employeeShare) || (float) $employeeShare < 0) {
            return [null, 'Fixed monthly contribution must be zero or a positive number.'];
        }
        $data = [
            'type' => $type,
            'min_monthly' => (float) $minMonthly,
            'max_monthly' => $maxMonthly !== '' ? (float) $maxMonthly : null,
            'employee_share' => (float) $employeeShare,
            'rate' => null,
            'base_min' => null,
            'base_max' => null,
            'notes' => $notes ?: null,
        ];
    } else {
        if (!is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 1) {
            return [null, 'Rate must be a number between 0 and 1 (e.g. 0.025 for 2.5%).'];
        }
        if ($baseMin !== '' && !is_numeric($baseMin)) {
            return [null, 'Base minimum must be a number.'];
        }
        if ($baseMax !== '' && !is_numeric($baseMax)) {
            return [null, 'Base maximum must be a number.'];
        }
        $data = [
            'type' => $type,
            'min_monthly' => (float) $minMonthly,
            'max_monthly' => $maxMonthly !== '' ? (float) $maxMonthly : null,
            'employee_share' => null,
            'rate' => (float) $rate,
            'base_min' => $baseMin !== '' ? (float) $baseMin : null,
            'base_max' => $baseMax !== '' ? (float) $baseMax : null,
            'notes' => $notes ?: null,
        ];
    }

    return [$data, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        [$data, $validationError] = validateBracketInput($_POST, $types);
        if ($validationError) {
            $error = $validationError;
        } else {
            try {
                $stmt = $db->prepare(
                    'INSERT INTO contribution_brackets (type, min_monthly, max_monthly, employee_share, rate, base_min, base_max, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $data['type'], $data['min_monthly'], $data['max_monthly'], $data['employee_share'],
                    $data['rate'], $data['base_min'], $data['base_max'], $data['notes'],
                ]);
                $success = 'Bracket added.';
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'uniq_type_min')
                    ? 'A bracket for this type already starts at that minimum salary.'
                    : 'Could not add bracket: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $bracketId = (int) ($_POST['bracket_id'] ?? 0);
        [$data, $validationError] = validateBracketInput($_POST, $types);
        if ($validationError) {
            $error = $validationError;
        } else {
            try {
                $stmt = $db->prepare(
                    'UPDATE contribution_brackets
                     SET type = ?, min_monthly = ?, max_monthly = ?, employee_share = ?, rate = ?, base_min = ?, base_max = ?, notes = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $data['type'], $data['min_monthly'], $data['max_monthly'], $data['employee_share'],
                    $data['rate'], $data['base_min'], $data['base_max'], $data['notes'], $bracketId,
                ]);
                $success = 'Bracket updated.';
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'uniq_type_min')
                    ? 'A bracket for this type already starts at that minimum salary.'
                    : 'Could not update bracket: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $bracketId = (int) ($_POST['bracket_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM contribution_brackets WHERE id = ?');
        $stmt->execute([$bracketId]);
        $success = 'Bracket removed.';
    }
}

$brackets = $db->query('SELECT * FROM contribution_brackets ORDER BY type ASC, min_monthly ASC')->fetchAll(PDO::FETCH_ASSOC);
$bracketsByType = ['sss' => [], 'philhealth' => [], 'pagibig' => []];
foreach ($brackets as $b) {
    $bracketsByType[$b['type']][] = $b;
}

function formatMonthly(?string $value): string {
    return $value === null ? '∞' : '₱' . number_format((float) $value, 2);
}

function formatBracketAmount(array $b): string {
    if ($b['employee_share'] !== null) {
        return '₱' . number_format((float) $b['employee_share'], 2) . ' fixed';
    }
    $rate = number_format((float) $b['rate'] * 100, 2) . '%';
    $base = 'clamp(' . formatMonthly($b['base_min']) . '–' . formatMonthly($b['base_max']) . ')';
    return $rate . ' of ' . $base;
}
?>

<main class="max-w-5xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <div>
    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Contribution Brackets</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
      SSS, PhilHealth and Pag-IBIG employee-share deductions, by monthly-equivalent salary range.
      Payroll reads these rows directly — nothing here is hardcoded in the app.
    </p>
  </div>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Add Bracket</h2>
    <form method="POST" class="space-y-4" id="ts-add-bracket-form">
      <input type="hidden" name="action" value="create">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
          <select name="type" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <?php foreach ($types as $val => $label): ?>
              <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Monthly (₱)</label>
          <input type="number" name="min_monthly" step="0.01" min="0" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Monthly (₱, blank = and above)</label>
          <input type="number" name="max_monthly" step="0.01" min="0" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">How this bracket's contribution is computed</label>
        <div class="flex gap-4 text-sm text-gray-700 dark:text-gray-300 mb-3">
          <label class="flex items-center gap-1.5"><input type="radio" name="mode" value="fixed" checked class="ts-bracket-mode"> Fixed peso amount</label>
          <label class="flex items-center gap-1.5"><input type="radio" name="mode" value="rate" class="ts-bracket-mode"> Rate × clamped base</label>
        </div>
      </div>

      <div class="ts-bracket-fixed-fields">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fixed Monthly Contribution (₱)</label>
        <input type="number" name="employee_share" step="0.01" min="0" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>

      <div class="ts-bracket-rate-fields hidden grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rate (0–1, e.g. 0.025)</label>
          <input type="number" name="rate" step="0.0001" min="0" max="1" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base Min (₱, blank = 0)</label>
          <input type="number" name="base_min" step="0.01" min="0" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base Max (₱, blank = uncapped)</label>
          <input type="number" name="base_max" step="0.01" min="0" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
        <input type="text" name="notes" maxlength="255" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>

      <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Add Bracket</button>
    </form>
  </div>

  <?php foreach ($types as $typeVal => $typeLabel): ?>
  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4"><?php echo htmlspecialchars($typeLabel); ?> (<?php echo count($bracketsByType[$typeVal]); ?> bracket<?php echo count($bracketsByType[$typeVal]) === 1 ? '' : 's'; ?>)</h2>
    <?php if (empty($bracketsByType[$typeVal])): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No brackets configured — nothing will be withheld for <?php echo htmlspecialchars($typeLabel); ?>.</p>
    <?php else: ?>
      <div class="overflow-x-auto max-h-96 overflow-y-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead class="sticky top-0 bg-gray-50 dark:bg-surface-card">
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Monthly Range</th>
              <th class="pr-6 pb-2">Contribution</th>
              <th class="pr-6 pb-2">Notes</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bracketsByType[$typeVal] as $b): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo formatMonthly($b['min_monthly']) . ' – ' . formatMonthly($b['max_monthly']); ?></td>
                <td class="pr-6 py-2 text-right"><?php echo htmlspecialchars(formatBracketAmount($b)); ?></td>
                <td class="pr-6 py-2 text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($b['notes'] ?? '—'); ?></td>
                <td class="py-2">
                  <div class="flex gap-2">
                    <button
                      type="button"
                      class="ts-edit-bracket-btn bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition"
                      data-id="<?php echo (int) $b['id']; ?>"
                      data-type="<?php echo htmlspecialchars($b['type'], ENT_QUOTES); ?>"
                      data-min-monthly="<?php echo htmlspecialchars($b['min_monthly'], ENT_QUOTES); ?>"
                      data-max-monthly="<?php echo htmlspecialchars($b['max_monthly'] ?? '', ENT_QUOTES); ?>"
                      data-mode="<?php echo $b['employee_share'] !== null ? 'fixed' : 'rate'; ?>"
                      data-employee-share="<?php echo htmlspecialchars($b['employee_share'] ?? '', ENT_QUOTES); ?>"
                      data-rate="<?php echo htmlspecialchars($b['rate'] ?? '', ENT_QUOTES); ?>"
                      data-base-min="<?php echo htmlspecialchars($b['base_min'] ?? '', ENT_QUOTES); ?>"
                      data-base-max="<?php echo htmlspecialchars($b['base_max'] ?? '', ENT_QUOTES); ?>"
                      data-notes="<?php echo htmlspecialchars($b['notes'] ?? '', ENT_QUOTES); ?>"
                    >Edit</button>
                    <form method="POST" data-confirm="Delete this bracket? Salaries in this range will withhold nothing for <?php echo htmlspecialchars($typeLabel, ENT_QUOTES); ?> until another bracket covers them.">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="bracket_id" value="<?php echo (int) $b['id']; ?>">
                      <button type="submit" class="bg-gray-400 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</main>

<!-- Edit Bracket modal -->
<div id="ts-edit-bracket-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
  <div id="ts-edit-bracket-backdrop" class="absolute inset-0 bg-black/50"></div>
  <div class="relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Edit Bracket</h2>
    <form method="POST" data-confirm="Save changes to this bracket? This affects every future payroll run in this salary range." class="space-y-4">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="bracket_id" id="ts-edit-bracket-id">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
          <select name="type" id="ts-edit-bracket-type" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <?php foreach ($types as $val => $label): ?>
              <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Min Monthly (₱)</label>
          <input type="number" name="min_monthly" id="ts-edit-bracket-min" step="0.01" min="0" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Monthly (₱, blank = and above)</label>
          <input type="number" name="max_monthly" id="ts-edit-bracket-max" step="0.01" min="0" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
      </div>

      <div>
        <div class="flex gap-4 text-sm text-gray-700 dark:text-gray-300 mb-3">
          <label class="flex items-center gap-1.5"><input type="radio" name="mode" value="fixed" id="ts-edit-bracket-mode-fixed" class="ts-edit-bracket-mode"> Fixed peso amount</label>
          <label class="flex items-center gap-1.5"><input type="radio" name="mode" value="rate" id="ts-edit-bracket-mode-rate" class="ts-edit-bracket-mode"> Rate × clamped base</label>
        </div>
      </div>

      <div class="ts-edit-bracket-fixed-fields">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fixed Monthly Contribution (₱)</label>
        <input type="number" name="employee_share" id="ts-edit-bracket-share" step="0.01" min="0" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>

      <div class="ts-edit-bracket-rate-fields hidden grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rate (0–1)</label>
          <input type="number" name="rate" id="ts-edit-bracket-rate" step="0.0001" min="0" max="1" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base Min (₱)</label>
          <input type="number" name="base_min" id="ts-edit-bracket-base-min" step="0.01" min="0" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Base Max (₱)</label>
          <input type="number" name="base_max" id="ts-edit-bracket-base-max" step="0.01" min="0" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
        <input type="text" name="notes" id="ts-edit-bracket-notes" maxlength="255" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>

      <div class="flex gap-3">
        <button type="submit" class="flex-1 bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save Changes</button>
        <button type="button" id="ts-edit-bracket-cancel" class="flex-1 border border-gray-300 dark:border-surface-border text-gray-700 dark:text-gray-300 font-bold rounded-lg px-5 py-3 hover:bg-gray-100 dark:hover:bg-white/5 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  function wireModeToggle(radios, fixedWrap, rateWrap) {
    function apply() {
      var isFixed = Array.prototype.some.call(radios, function (r) { return r.checked && r.value === 'fixed'; });
      fixedWrap.classList.toggle('hidden', !isFixed);
      rateWrap.classList.toggle('hidden', isFixed);
    }
    Array.prototype.forEach.call(radios, function (r) { r.addEventListener('change', apply); });
    apply();
  }

  wireModeToggle(
    document.querySelectorAll('.ts-bracket-mode'),
    document.querySelector('.ts-bracket-fixed-fields'),
    document.querySelector('.ts-bracket-rate-fields')
  );

  var editRadios = document.querySelectorAll('.ts-edit-bracket-mode');
  wireModeToggle(
    editRadios,
    document.querySelector('.ts-edit-bracket-fixed-fields'),
    document.querySelector('.ts-edit-bracket-rate-fields')
  );

  var modal = document.getElementById('ts-edit-bracket-modal');
  var backdrop = document.getElementById('ts-edit-bracket-backdrop');
  var cancelBtn = document.getElementById('ts-edit-bracket-cancel');

  var fields = {
    id: document.getElementById('ts-edit-bracket-id'),
    type: document.getElementById('ts-edit-bracket-type'),
    min: document.getElementById('ts-edit-bracket-min'),
    max: document.getElementById('ts-edit-bracket-max'),
    share: document.getElementById('ts-edit-bracket-share'),
    rate: document.getElementById('ts-edit-bracket-rate'),
    baseMin: document.getElementById('ts-edit-bracket-base-min'),
    baseMax: document.getElementById('ts-edit-bracket-base-max'),
    notes: document.getElementById('ts-edit-bracket-notes')
  };

  function openModal(btn) {
    fields.id.value = btn.dataset.id;
    fields.type.value = btn.dataset.type;
    fields.min.value = btn.dataset.minMonthly;
    fields.max.value = btn.dataset.maxMonthly;
    fields.share.value = btn.dataset.employeeShare;
    fields.rate.value = btn.dataset.rate;
    fields.baseMin.value = btn.dataset.baseMin;
    fields.baseMax.value = btn.dataset.baseMax;
    fields.notes.value = btn.dataset.notes;

    var mode = btn.dataset.mode === 'rate' ? 'rate' : 'fixed';
    document.getElementById('ts-edit-bracket-mode-' + mode).checked = true;
    editRadios.forEach(function (r) { r.dispatchEvent(new Event('change')); });

    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  document.querySelectorAll('.ts-edit-bracket-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { openModal(btn); });
  });

  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
})();
</script>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
