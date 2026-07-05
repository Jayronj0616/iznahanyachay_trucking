<?php
require_once __DIR__ . '/config.php';

// Expects $pageTitle to be set by the including page. Falls back if not set.
if (!isset($pageTitle)) {
    $pageTitle = 'Trucking System';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> · Trucking System</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<script>
  // Applied before Tailwind loads so there's no flash of the wrong theme.
  (function () {
    var saved = localStorage.getItem('ts-theme');
    if (saved === 'dark') {
      document.documentElement.classList.add('dark');
    }
  })();

  // Theme functions defined inline (not an external file) so there is no
  // script-loading race or path issue that can silently break the toggle.
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
</script>

<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        fontFamily: {
          sans: ['Inter', 'sans-serif'],
        },
        colors: {
          brand: {
            yellow: '#FBBF24',
            orange: '#F97316',
            green: '#22C55E',
          },
          surface: {
            DEFAULT: '#0B0F1A',
            card: '#131A2C',
            border: '#1F2A3D',
          },
        },
      },
    },
  };
</script>

<style>
  /* Scrollbars are native browser UI, not Tailwind classes, so they don't
     follow dark: variants automatically. Styled explicitly here, globally,
     so any overflow-x-auto/overflow-y-auto element is visible in both themes. */
  ::-webkit-scrollbar {
    height: 8px;
    width: 8px;
  }
  ::-webkit-scrollbar-track {
    background: transparent;
  }
  ::-webkit-scrollbar-thumb {
    background-color: #D1D5DB; /* gray-300, visible on light backgrounds */
    border-radius: 9999px;
  }
  html.dark ::-webkit-scrollbar-thumb {
    background-color: #374151; /* gray-700, visible on dark backgrounds */
  }
  /* Firefox */
  * {
    scrollbar-width: thin;
    scrollbar-color: #D1D5DB transparent;
  }
  html.dark * {
    scrollbar-color: #374151 transparent;
  }
</style>
</head>
<body class="font-sans bg-white text-gray-900 dark:bg-surface dark:text-gray-100 min-h-screen transition-colors duration-200">
