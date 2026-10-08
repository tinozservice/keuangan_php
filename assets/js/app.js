/* Pencatat Keuangan — skrip antarmuka kecil (tanpa dependensi). */
(function () {
    'use strict';

    var toggle = document.querySelector('[data-nav-toggle]');
    var menu = document.getElementById('nav-menu');

    if (!toggle || !menu) {
        return;
    }

    var label = toggle.querySelector('.sr-only');

    function setOpen(open) {
        menu.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (label) {
            label.textContent = open ? 'Tutup menu' : 'Buka menu';
        }
    }

    toggle.addEventListener('click', function () {
        setOpen(!menu.classList.contains('is-open'));
    });

    menu.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            setOpen(false);
        }
    });
})();
