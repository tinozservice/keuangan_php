<?php defined('APP_ROOT') || exit('Akses langsung tidak diizinkan.'); ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#FFA600">
<title><?= e($page_title ?? 'Pencatat Keuangan') ?></title>
<meta name="description" content="<?= e($page_desc ?? '') ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='5' fill='%23FFA600'/%3E%3Ctext x='16' y='23' font-family='Arial' font-size='17' font-weight='800' fill='%2333210F' text-anchor='middle'%3EK%3C/text%3E%3C/svg%3E">

<!-- Font & ikon via CDN (mengikuti referensi desain) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&amp;family=Inter:wght@400;500;600&amp;family=IBM+Plex+Mono:wght@500;600&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

<!-- Tailwind CDN + token tema (selaras dengan :root di app.css) -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    canvas: '#FFF4E9',
                    card: '#FFFFFF',
                    line: '#F0DCC8',
                    'line-strong': '#E5C7A8',
                    ink: '#33210F',
                    'ink-2': '#6B4F33',
                    muted: '#7E5F44',
                    orange: { DEFAULT: '#FFA600', hover: '#F09A00', ink: '#9E4E00', bright: '#F28123', soft: '#FFE7D2' },
                    yellow: { DEFAULT: '#FFC65C', hover: '#F0B84E', soft: '#FFF3CC' },
                    footer: '#2A1808'
                },
                fontFamily: {
                    display: ['Poppins', 'Inter', 'system-ui', 'sans-serif'],
                    body: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
                    mono: ['IBM Plex Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace']
                },
                borderRadius: { DEFAULT: '6px', sm: '4px' }
            }
        }
    };
</script>

<!-- Gaya aplikasi: token & komponen khas -->
<link rel="stylesheet" href="<?= e(APP_BASE) ?>/assets/css/app.css">
