<!-- Reusable Tailwind confirmation modal. Include once per page (before foot.php).
     Usage: add data-confirm="Message here" to any <form> or <button>.
     Forms: intercepted on submit, only submits after user confirms.
     Buttons: intercepted on click, only fires onclick after user confirms (set data-confirm-action to a JS function name string, or leave it to just let a normal click through after confirming if it's inside a form). -->
<div id="ts-confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
  <div id="ts-confirm-backdrop" class="absolute inset-0 bg-black/50"></div>
  <div class="relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl shadow-xl max-w-sm w-full p-6">
    <h3 class="text-gray-900 dark:text-white font-bold text-base mb-2">Confirm Action</h3>
    <p id="ts-confirm-message" class="text-sm text-gray-600 dark:text-gray-300 mb-6">Are you sure?</p>
    <div class="flex justify-end gap-3">
      <button type="button" id="ts-confirm-cancel" class="text-sm font-semibold text-gray-600 dark:text-gray-300 px-4 py-2 rounded-full hover:bg-gray-100 dark:hover:bg-white/5 transition">Cancel</button>
      <button type="button" id="ts-confirm-ok" class="text-sm font-bold text-white bg-brand-orange px-5 py-2 rounded-full hover:opacity-90 transition">Confirm</button>
    </div>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('ts-confirm-modal');
  var backdrop = document.getElementById('ts-confirm-backdrop');
  var messageEl = document.getElementById('ts-confirm-message');
  var okBtn = document.getElementById('ts-confirm-ok');
  var cancelBtn = document.getElementById('ts-confirm-cancel');
  var pendingForm = null;

  function openModal(message, form) {
    messageEl.textContent = message;
    pendingForm = form;
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
    pendingForm = null;
  }

  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);

  okBtn.addEventListener('click', function () {
    var form = pendingForm;
    closeModal();
    if (form) {
      form.submit();
    }
  });

  // Intercept any form with data-confirm
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.tagName === 'FORM' && form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
      e.preventDefault();
      openModal(form.getAttribute('data-confirm'), form);
    }
  }, true);
})();
</script>
