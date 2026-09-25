(function () {
    'use strict';

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var text = button.getAttribute('data-copy');
            if (navigator.clipboard && text) {
                navigator.clipboard.writeText(text);
                var original = button.textContent;
                button.textContent = 'Copiado';
                setTimeout(function () { button.textContent = original; }, 1500);
            }
        });
    });
})();
