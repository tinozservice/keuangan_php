/* Pencatat Keuangan — skrip antarmuka kecil (tanpa dependensi). */
(function () {
    'use strict';

    /* --- Toggle menu navigasi seluler --- */
    var toggle = document.querySelector('[data-nav-toggle]');
    var menu = document.getElementById('nav-menu');

    if (toggle && menu) {
        var label = toggle.querySelector('.sr-only');

        var setOpen = function (open) {
            menu.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (label) {
                label.textContent = open ? 'Tutup menu' : 'Buka menu';
            }
        };

        toggle.addEventListener('click', function () {
            setOpen(!menu.classList.contains('is-open'));
        });

        menu.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                setOpen(false);
            }
        });
    }

    /* --- Dropdown avatar (Pengaturan Akun / Keluar) --- */
    var avatarToggle = document.querySelector('[data-avatar-toggle]');
    var avatarMenu = avatarToggle ? avatarToggle.closest('.avatar-menu') : null;

    if (avatarToggle && avatarMenu) {
        var setAvatarOpen = function (open) {
            avatarMenu.classList.toggle('is-open', open);
            avatarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        avatarToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            setAvatarOpen(!avatarMenu.classList.contains('is-open'));
        });

        document.addEventListener('click', function (event) {
            if (avatarMenu.classList.contains('is-open') && !avatarMenu.contains(event.target)) {
                setAvatarOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setAvatarOpen(false);
            }
        });
    }
})();
