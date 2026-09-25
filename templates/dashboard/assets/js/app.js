(function () {
    'use strict';

    function ensureContainer() {
        var container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function showToast(type, message, options) {
        options = options || {};
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        var spinner = (type === 'loading') ? '<span class="toast-spinner"></span>' : '';
        toast.innerHTML = spinner +
            '<div class="toast-body"></div>' +
            '<button type="button" class="toast-close" aria-label="Cerrar">&times;</button>';
        toast.querySelector('.toast-body').textContent = message;
        ensureContainer().appendChild(toast);
        toast.querySelector('.toast-close').addEventListener('click', function () { toast.remove(); });
        if (!options.persistent && type !== 'loading') {
            setTimeout(function () { toast.remove(); }, options.timeout || 5000);
        }
        return toast;
    }
    window.panelToast = showToast;

    function openModal(id) {
        var modal = document.getElementById(id);
        if (modal) { modal.classList.add('open'); }
    }
    function closeModal(modal) {
        if (modal) { modal.classList.remove('open'); }
    }

    function navigate(section) {
        var container = document.getElementById('section');
        if (!container || !section) {
            window.location = '/';
            return;
        }
        container.style.opacity = '0.5';
        fetch('?action=section&name=' + encodeURIComponent(section), {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }).then(function (response) {
            if (!response.ok) { throw new Error('HTTP ' + response.status); }
            return response.json();
        }).then(function (data) {
            container.innerHTML = data.html;
            container.style.opacity = '1';
            var title = document.getElementById('page-title');
            if (title) { title.textContent = data.title; }
            document.querySelectorAll('.nav-item[data-section]').forEach(function (link) {
                link.classList.toggle('active', link.getAttribute('data-section') === data.page);
            });
            panelBind();
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '/');
            }
            document.dispatchEvent(new CustomEvent('section:loaded', { detail: { page: data.page } }));
        }).catch(function () {
            container.style.opacity = '1';
            showToast('error', 'No se pudo cargar la sección.');
        });
    }

    function bindNav() {
        document.querySelectorAll('[data-section]:not([data-bound])').forEach(function (link) {
            link.setAttribute('data-bound', '1');
            link.addEventListener('click', function (event) {
                var href = link.getAttribute('href') || '/';
                if (href.charAt(0) === '/') {
                    event.preventDefault();
                    navigate(link.getAttribute('data-section'));
                }
            });
        });
    }

    function bindForms() {
        document.querySelectorAll('form:not([data-bound])').forEach(function (form) {
            form.setAttribute('data-bound', '1');
            form.addEventListener('submit', function (event) {
                var confirmMessage = form.getAttribute('data-confirm');
                if (confirmMessage && !window.confirm(confirmMessage)) {
                    event.preventDefault();
                    return;
                }
                var loadingMessage = form.getAttribute('data-loading');
                if (loadingMessage) {
                    event.preventDefault();
                    var toast = showToast('loading', loadingMessage, { persistent: true });
                    var submit = form.querySelector('button[type="submit"]');
                    if (submit) {
                        submit.disabled = true;
                        submit.style.opacity = '0.7';
                    }

                    var formData = new FormData(form);
                    fetch(form.getAttribute('action') || window.location.href, {
                        method: form.method || 'POST',
                        body: formData,
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(function(res) {
                        return res.json().catch(function() {
                            throw new Error('Respuesta inválida del servidor');
                        });
                    })
                    .then(function(data) {
                        toast.remove();
                        if (data.success) {
                            showToast('success', data.message || 'Operación completada.');
                            var modal = form.closest('.modal-overlay');
                            if (modal) { closeModal(modal); }
                        } else {
                            showToast('error', data.message || 'Ocurrió un error.');
                        }
                    })
                    .catch(function(err) {
                        toast.remove();
                        showToast('error', err.message || 'Error de conexión.');
                    })
                    .finally(function() {
                        if (submit) {
                            submit.disabled = false;
                            submit.style.opacity = '1';
                        }
                    });
                }
            });
        });
    }

    function bindModals() {
        document.querySelectorAll('[data-open-modal]:not([data-bound])').forEach(function (button) {
            button.setAttribute('data-bound', '1');
            button.addEventListener('click', function () { openModal(button.getAttribute('data-open-modal')); });
        });
        document.querySelectorAll('[data-close-modal]:not([data-bound])').forEach(function (button) {
            button.setAttribute('data-bound', '1');
            button.addEventListener('click', function () { closeModal(button.closest('.modal-overlay')); });
        });
        document.querySelectorAll('.modal-overlay:not([data-bound])').forEach(function (overlay) {
            overlay.setAttribute('data-bound', '1');
            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) { closeModal(overlay); }
            });
        });
        document.querySelectorAll('.modal-overlay[data-auto-open]').forEach(function (modal) {
            modal.classList.add('open');
        });
    }

    function bindDelete() {
        document.querySelectorAll('[data-delete-project]:not([data-bound])').forEach(function (button) {
            button.setAttribute('data-bound', '1');
            button.addEventListener('click', function () {
                var name = button.getAttribute('data-delete-project');
                var isBase = button.getAttribute('data-base') === '1';
                var nameField = document.getElementById('delete-name');
                var forceField = document.getElementById('delete-force');
                var warning = document.getElementById('delete-warning');
                if (nameField) { nameField.value = name; }
                if (forceField) { forceField.value = isBase ? '1' : '0'; }
                if (warning) {
                    warning.className = isBase ? 'danger-box' : 'warn-box';
                    warning.innerHTML = isBase
                        ? '<strong>Atención:</strong> "' + name + '" es un entorno base del sistema. Eliminarlo puede romper el flujo de trabajo y dejar de servir su subdominio. <strong>Recomendación:</strong> no eliminar entornos base; si necesitas reiniciarlo, vuelve a crearlo o usa "Crear BD".'
                        : '<strong>Aviso:</strong> se eliminará el proyecto "' + name + '" y su base de datos asociada. Esta acción es irreversible. <strong>Recomendación:</strong> crea un respaldo antes de continuar si contiene datos importantes.';
                }
                openModal('modal-delete');
            });
        });
    }

    function bindToggles() {
        var createDb = document.getElementById('create_db');
        var dbOptions = document.getElementById('db-options');
        var customDb = document.getElementById('custom_db');
        var dbNameField = document.getElementById('db-name-field');
        function refresh() {
            if (dbOptions) { dbOptions.classList.toggle('hidden', !(createDb && createDb.checked)); }
            if (dbNameField) { dbNameField.classList.toggle('hidden', !(customDb && customDb.checked)); }
        }
        if (createDb && !createDb.getAttribute('data-bound')) {
            createDb.setAttribute('data-bound', '1');
            createDb.addEventListener('change', refresh);
        }
        if (customDb && !customDb.getAttribute('data-bound')) {
            customDb.setAttribute('data-bound', '1');
            customDb.addEventListener('change', refresh);
        }
        refresh();
    }

    function bindCopy() {
        document.querySelectorAll('[data-copy]:not([data-bound])').forEach(function (button) {
            button.setAttribute('data-bound', '1');
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
    }

    function bindToasts() {
        document.querySelectorAll('[data-toast]:not([data-bound])').forEach(function (toast) {
            toast.setAttribute('data-bound', '1');
            var close = toast.querySelector('.toast-close');
            if (close) { close.addEventListener('click', function () { toast.remove(); }); }
            var ttl = parseInt(toast.getAttribute('data-ttl') || '5000', 10);
            setTimeout(function () { toast.remove(); }, ttl);
        });
    }

    function bindPasswordToggles() {
        document.querySelectorAll('[data-toggle-password]:not([data-bound])').forEach(function (button) {
            button.setAttribute('data-bound', '1');
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-toggle-password'));
                if (!input) { return; }
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
            });
        });
    }

    function panelBind() {
        bindToasts();
        bindNav();
        bindForms();
        bindModals();
        bindDelete();
        bindToggles();
        bindCopy();
        bindPasswordToggles();
        bindThemeToggle();
    }
    window.panelBind = panelBind;

    function bindThemeToggle() {
        var btn = document.getElementById('theme-toggle');
        if (btn && !btn.getAttribute('data-bound')) {
            btn.setAttribute('data-bound', '1');
            btn.addEventListener('click', function() {
                var currentTheme = document.documentElement.getAttribute('data-theme');
                var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        }
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(closeModal);
        }
    });

    panelBind();
})();
