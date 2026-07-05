function tsSetTheme(theme) {
  var html = document.documentElement;
  if (theme === 'dark') {
    html.classList.add('dark');
  } else {
    html.classList.remove('dark');
  }
  localStorage.setItem('ts-theme', theme);
}

function tsToggleTheme() {
  var isDark = document.documentElement.classList.contains('dark');
  tsSetTheme(isDark ? 'light' : 'dark');
}

document.addEventListener('DOMContentLoaded', function () {
  var toggles = document.querySelectorAll('[data-theme-toggle]');
  toggles.forEach(function (btn) {
    btn.addEventListener('click', tsToggleTheme);
  });
});
