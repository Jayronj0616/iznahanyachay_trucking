<?php
require_once __DIR__ . '/config.php';

// Expects $pageTitle to be set by the including page. Falls back if not set.
if (!isset($pageTitle)) {
    $pageTitle = 'Iznahanyachay Trucking Services';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> · Iznahanyachay Trucking Services</title>
<link rel="icon" type="image/png" href="<?php echo BASE_PATH; ?>/assets/images/logo.png?v=<?php echo filemtime(__DIR__ . '/../assets/images/logo.png'); ?>">

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

  /* In-page links glide instead of teleporting.

     Every anchor in the app goes through this -- the landing page's "See how
     it works" and its nav, and #today on the timesheet calendar. There was no
     scroll-behavior set anywhere, so all of them jumped, which reads as the
     page having reloaded rather than as having moved down it.

     scroll-padding-top exists because the landing page header is `fixed`. Without
     it the browser scrolls the heading to y=0, which is underneath the floating
     navbar, and the section looks like it starts mid-sentence. */
  html {
    scroll-behavior: smooth;
    scroll-padding-top: 6rem;
  }

  /* Smooth scrolling is a common vestibular trigger, and a long glide is worse
     than a jump for anyone it affects. This is the one animation on the site
     that is switched off rather than softened. */
  @media (prefers-reduced-motion: reduce) {
    html {
      scroll-behavior: auto;
    }
  }

  /* ------------------------------------------------------------------
     Modal entry animation.

     Every modal in this app is built the same way -- a full-screen
     .fixed.inset-0.z-50 container holding an .absolute.inset-0 backdrop
     and a .relative panel -- and every one is opened by removing the
     `hidden` class. Because `hidden` is display:none, restoring display
     restarts a CSS animation, so no JavaScript is involved here at all.

     Targeting the structure rather than a dedicated class is deliberate:
     a modal added later inherits this without anyone remembering to opt
     in. The coupling is to that shared structure, so keep new modals
     shaped the same way.
     ------------------------------------------------------------------ */
  @keyframes ts-backdrop-in {
    from { opacity: 0; }
    to   { opacity: 1; }
  }

  @keyframes ts-panel-in {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to   { opacity: 1; transform: none; }
  }

  .fixed.inset-0.z-50 > .absolute.inset-0 {
    animation: ts-backdrop-in 160ms ease-out both;
  }

  .fixed.inset-0.z-50 > .relative {
    animation: ts-panel-in 220ms cubic-bezier(0.16, 1, 0.3, 1) both;
    transform-origin: center;
  }

  /* Reduced motion keeps the fade and drops the travel. Switching the
     animation off entirely would remove the cue that something opened,
     which is the one thing the animation is actually for. */
  @media (prefers-reduced-motion: reduce) {
    .fixed.inset-0.z-50 > .relative {
      animation: ts-backdrop-in 120ms ease-out both;
    }
  }

</style>
</head>
<body class="font-sans bg-white text-gray-900 dark:bg-surface dark:text-gray-100 min-h-screen transition-colors duration-200">
