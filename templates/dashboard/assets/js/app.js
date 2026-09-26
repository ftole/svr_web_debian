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
        var token = window.SRVCTL_TOKEN || (window.localStorage && localStorage.getItem('srvctl_token')) || '';
        var url = '?action=section&name=' + encodeURIComponent(section) + (token ? '&token=' + encodeURIComponent(token) : '');
        fetch(url, {
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
                if (form.getAttribute('data-submitting') === '1') {
                    event.preventDefault();
                    return;
                }
                var confirmMessage = form.getAttribute('data-confirm');
                if (confirmMessage && !window.confirm(confirmMessage)) {
                    event.preventDefault();
                    return;
                }
                if (form.classList.contains('logout-form') || form.querySelector('input[name="action"][value="logout"]')) {
                    if (window.localStorage) {
                        localStorage.removeItem('srvctl_token');
                    }
                }
                var loadingMessage = form.getAttribute('data-loading');
                if (loadingMessage) {
                    event.preventDefault();
                    form.setAttribute('data-submitting', '1');
                    var toast = showToast('loading', loadingMessage, { persistent: true });
                    var submit = form.querySelector('button[type="submit"]');
                    if (submit) {
                        submit.disabled = true;
                        submit.style.opacity = '0.7';
                    }

                    var formData = new FormData(form);
                    var token = window.SRVCTL_TOKEN || (window.localStorage && localStorage.getItem('srvctl_token')) || '';
                    if (token && !formData.get('token')) {
                        formData.append('token', token);
                    }
                    var actionUrl = form.getAttribute('action') || window.location.href;
                    if (token && !actionUrl.includes('token=')) {
                        actionUrl += (actionUrl.includes('?') ? '&' : '?') + 'token=' + encodeURIComponent(token);
                    }
                    fetch(actionUrl, {
                        method: form.method || 'POST',
                        body: formData,
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(function(res) {
                        return res.json().catch(function() {
                            throw new Error('Respuesta inválida del servidor (HTTP ' + res.status + ')');
                        });
                    })
                    .then(function(data) {
                        toast.remove();
                        if (data.success) {
                            showToast('success', data.message || 'Operación completada.');
                            var modal = form.closest('.modal-overlay');
                            if (modal) { closeModal(modal); }
                            var activeNav = document.querySelector('.nav-item.active[data-section]');
                            if (activeNav) {
                                setTimeout(function () { navigate(activeNav.getAttribute('data-section')); }, 600);
                            }
                        } else {
                            showToast('error', data.message || data.error || 'Ocurrió un error al procesar la solicitud.');
                        }
                    })
                    .catch(function(err) {
                        toast.remove();
                        showToast('error', err.message || 'Error de conexión con el servidor.');
                    })
                    .finally(function() {
                        form.removeAttribute('data-submitting');
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

    var diagLogTimer = null;

    function bindQuickDownloads() {
        var btn = document.getElementById('btn-quick-download-db');
        var select = document.getElementById('quick-db-select');
        if (btn && select && !btn.getAttribute('data-bound')) {
            btn.setAttribute('data-bound', '1');
            btn.addEventListener('click', function() {
                var proj = select.value;
                if (proj) {
                    var a = document.createElement('a');
                    var token = window.SRVCTL_TOKEN || (window.localStorage && localStorage.getItem('srvctl_token')) || '';
                    a.href = '/?action=download&type=db&project=' + encodeURIComponent(proj) + (token ? '&token=' + encodeURIComponent(token) : '');
                    a.download = proj + '_db.sql';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                }
            });
        }
    }

    function bindDiagnosticsLogs() {
        var consoleBox = document.getElementById('live-log-console-box');
        if (!consoleBox) {
            if (diagLogTimer) {
                clearInterval(diagLogTimer);
                diagLogTimer = null;
            }
            return;
        }
        if (consoleBox.getAttribute('data-bound') === '1') {
            return;
        }
        consoleBox.setAttribute('data-bound', '1');

        var badge = document.getElementById('live-stream-badge');
        var btnPause = document.getElementById('btn-toggle-pause');
        var txtPause = document.getElementById('txt-pause');
        var btnScroll = document.getElementById('btn-toggle-autoscroll');
        var txtScroll = document.getElementById('txt-autoscroll');
        var selSource = document.getElementById('filter-log-source');
        var selLevel = document.getElementById('filter-log-level');
        var inputSearch = document.getElementById('filter-log-search');

        var isPaused = false;
        var autoScroll = true;
        var lastSeenId = 0;

        var lines = consoleBox.querySelectorAll('.live-log-line[data-id]');
        if (lines.length > 0) {
            lastSeenId = parseInt(lines[lines.length - 1].getAttribute('data-id'), 10) || 0;
        }

        if (btnPause && !btnPause.getAttribute('data-bound')) {
            btnPause.setAttribute('data-bound', '1');
            btnPause.addEventListener('click', function() {
                isPaused = !isPaused;
                if (isPaused) {
                    if (txtPause) txtPause.textContent = 'Reanudar';
                    if (badge) {
                        badge.style.background = 'var(--warning-soft)';
                        badge.style.color = 'var(--warning)';
                        badge.textContent = 'PAUSADO';
                    }
                } else {
                    if (txtPause) txtPause.textContent = 'Pausar';
                    if (badge) {
                        badge.style.background = 'var(--success-soft)';
                        badge.style.color = 'var(--success)';
                        badge.innerHTML = '<span class="md-pulse-core" style="width: 6px; height: 6px;"></span> <span>EN VIVO</span>';
                    }
                }
            });
        }

        if (btnScroll && !btnScroll.getAttribute('data-bound')) {
            btnScroll.setAttribute('data-bound', '1');
            btnScroll.addEventListener('click', function() {
                autoScroll = !autoScroll;
                if (txtScroll) txtScroll.textContent = autoScroll ? 'ON' : 'OFF';
                if (autoScroll) {
                    consoleBox.scrollTop = consoleBox.scrollHeight;
                }
            });
        }

        function applyLocalFilters() {
            var src = selSource ? selSource.value : 'all';
            var lvl = selLevel ? selLevel.value : 'all';
            var q = inputSearch ? inputSearch.value.toLowerCase().trim() : '';

            var allLines = consoleBox.querySelectorAll('.live-log-line');
            allLines.forEach(function(line) {
                var lineSrc = line.getAttribute('data-source') || '';
                var lineLvl = line.getAttribute('data-level') || '';
                var lineText = (line.textContent || '').toLowerCase();

                var matchSrc = (src === 'all' || lineSrc === src);
                var matchLvl = (lvl === 'all' || lineLvl === lvl);
                var matchQ = (!q || lineText.indexOf(q) !== -1);

                line.style.display = (matchSrc && matchLvl && matchQ) ? 'flex' : 'none';
            });

            if (autoScroll) {
                consoleBox.scrollTop = consoleBox.scrollHeight;
            }
        }

        if (selSource && !selSource.getAttribute('data-bound')) {
            selSource.setAttribute('data-bound', '1');
            selSource.addEventListener('change', applyLocalFilters);
        }
        if (selLevel && !selLevel.getAttribute('data-bound')) {
            selLevel.setAttribute('data-bound', '1');
            selLevel.addEventListener('change', applyLocalFilters);
        }
        if (inputSearch && !inputSearch.getAttribute('data-bound')) {
            inputSearch.setAttribute('data-bound', '1');
            inputSearch.addEventListener('input', applyLocalFilters);
        }

        if (diagLogTimer) {
            clearInterval(diagLogTimer);
        }
        diagLogTimer = setInterval(function() {
            if (!document.getElementById('live-log-console-box')) {
                clearInterval(diagLogTimer);
                diagLogTimer = null;
                return;
            }
            if (isPaused) return;

            var token = window.SRVCTL_TOKEN || (window.localStorage && localStorage.getItem('srvctl_token')) || '';
            var url = '/?action=live_logs&since=' + lastSeenId + (token ? '&token=' + encodeURIComponent(token) : '');

            fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function(data) {
                    if (data.logs && data.logs.length > 0) {
                        data.logs.forEach(function(l) {
                            lastSeenId = Math.max(lastSeenId, l.id);

                            var div = document.createElement('div');
                            div.className = 'live-log-line';
                            div.setAttribute('data-id', l.id);
                            div.setAttribute('data-source', l.source);
                            div.setAttribute('data-level', l.level);

                            var sTime = document.createElement('span');
                            sTime.className = 'log-time';
                            sTime.textContent = '[' + (l.timeOnly || l.timestamp || '') + ']';

                            var sSource = document.createElement('span');
                            sSource.className = 'log-source';
                            sSource.textContent = '[' + (l.source || '') + ']';

                            var sBadge = document.createElement('span');
                            sBadge.className = 'log-badge ' + (l.level || 'INFO');
                            sBadge.textContent = l.level || 'INFO';

                            var sMsg = document.createElement('span');
                            sMsg.className = 'log-msg';
                            sMsg.textContent = l.message || '';

                            div.appendChild(sTime);
                            div.appendChild(document.createTextNode(' '));
                            div.appendChild(sSource);
                            div.appendChild(document.createTextNode(' '));
                            div.appendChild(sBadge);
                            div.appendChild(document.createTextNode(' '));
                            div.appendChild(sMsg);

                            consoleBox.appendChild(div);
                        });

                        var currentLines = consoleBox.querySelectorAll('.live-log-line');
                        if (currentLines.length > 200) {
                            for (var i = 0; i < currentLines.length - 200; i++) {
                                consoleBox.removeChild(currentLines[i]);
                            }
                        }

                        applyLocalFilters();
                    }
                })
                .catch(function() {});
        }, 2000);
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
        bindDevNotesToggle();
        bindQuickDownloads();
        bindDiagnosticsLogs();
    }
    window.panelBind = panelBind;

    document.addEventListener('section:loaded', function(e) {
        if (e.detail && e.detail.page !== 'diagnostics') {
            if (diagLogTimer) {
                clearInterval(diagLogTimer);
                diagLogTimer = null;
            }
        }
    });

    function bindDevNotesToggle() {
        var btn = document.getElementById('dev-notes-toggle');
        var savedState = localStorage.getItem('srvctl_dev_notes');
        
        if (savedState === 'hidden') {
            document.body.classList.add('hide-dev-notes');
        } else {
            document.body.classList.remove('hide-dev-notes');
        }

        function updateBtnState() {
            if (!btn) { return; }
            var isHidden = document.body.classList.contains('hide-dev-notes');
            btn.classList.toggle('active', !isHidden);
            var label = btn.querySelector('.dev-notes-btn-text');
            if (label) {
                label.textContent = isHidden ? 'Ver Notas Backend' : 'Notas Backend';
            }
        }
        updateBtnState();

        if (btn && !btn.getAttribute('data-bound')) {
            btn.setAttribute('data-bound', '1');
            btn.addEventListener('click', function() {
                var isHidden = document.body.classList.toggle('hide-dev-notes');
                localStorage.setItem('srvctl_dev_notes', isHidden ? 'hidden' : 'visible');
                updateBtnState();
                showToast('info', isHidden ? 'Notas de especificación ocultadas (Vista Maqueta).' : 'Notas de especificación técnica activadas para el desarrollador.');
            });
        }
    }

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
