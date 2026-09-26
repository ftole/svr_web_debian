/**
 * material-monitor.js
 * 100% Native Vanilla JavaScript & Pure SVG Real-Time Telemetry Monitor
 * Zero external libraries or dependencies.
 */
(function () {
    'use strict';

    function initMaterialMonitor() {
        var container = document.getElementById('material-status-monitor');
        if (!container) return;

        // Limpiar contenido previo si re-montamos
        container.innerHTML = '';

        var state = {
            server_ip: '10.1.0.4',
            base_domain: 'empresa.local',
            hostname: 'debian13-server',
            os: 'Debian GNU/Linux 13 (trixie)',
            kernel: 'Linux 6.12.0',
            uptime_formatted: '3d 5h 0m',
            cpu_percent: 0,
            cpu_cores: [0, 0],
            cpu_load: [0.12, 0.15, 0.12],
            ram_percent: 24,
            ram_used: '1.0 GB',
            ram_total: '4.0 GB',
            ram_free: '3.0 GB',
            ram_swap: '0 B / 1.0 GB',
            disk_percent: 18,
            disk_used: '18.0 GB',
            disk_total: '100.0 GB',
            disk_free: '82.0 GB',
            net_rx: '3.1 KB/s',
            net_tx: '4.5 KB/s',
            active_connections: 2,
            latency_ms: 12,
            is_polling: true,
            interval_ms: 1000,
            timer: null,
            history: [] // { time, cpu, ram }
        };

        // Generar estructura DOM limpia estilo Material Design
        container.className = 'md-monitor-wrapper';
        container.innerHTML = [
            '<div class="md-monitor-card md-elevation-1">',
            '  <div class="md-monitor-header">',
            '    <div class="md-header-title-box">',
            '      <div class="md-pulse-indicator" title="Sondeo activo en tiempo real">',
            '        <span class="md-pulse-core"></span>',
            '      </div>',
            '      <div>',
            '        <h2 class="md-header-title">Telemetría del Servidor</h2>',
            '        <div class="md-header-subtitle" id="md-host-info">debian13-server · Linux 6.12</div>',
            '      </div>',
            '    </div>',
            '    <div class="md-header-actions">',
            '      <div class="md-chip md-chip-primary" id="md-ip-chip">',
            '        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
            '        <span>IP: <strong id="md-val-ip">10.1.0.4</strong></span>',
            '      </div>',
            '      <div class="md-chip" id="md-ping-chip">',
            '        <span>Ping: <strong id="md-val-ping">12 ms</strong></span>',
            '      </div>',
            '      <div class="md-btn-toggle-group">',
            '        <button type="button" class="md-btn-chip active" data-interval="1000">1s</button>',
            '        <button type="button" class="md-btn-chip" data-interval="2000">2s</button>',
            '        <button type="button" class="md-btn-chip" data-interval="5000">5s</button>',
            '      </div>',
            '      <button type="button" class="md-btn md-btn-outlined" id="md-btn-pause">',
            '        <span id="md-pause-text">Pausar</span>',
            '      </button>',
            '    </div>',
            '  </div>',

            '  <!-- 4 Tarjetas KPI Material Design -->',
            '  <div class="md-kpi-grid">',
            '    <!-- KPI 1: CPU -->',
            '    <div class="md-kpi-card">',
            '      <div class="md-kpi-label">Uso de CPU</div>',
            '      <div class="md-kpi-main">',
            '        <span class="md-kpi-value" id="md-kpi-cpu">0.0</span>',
            '        <span class="md-kpi-unit">%</span>',
            '      </div>',
            '      <div class="md-progress-track">',
            '        <div class="md-progress-bar" id="md-bar-cpu" style="width: 0%;"></div>',
            '      </div>',
            '      <div class="md-kpi-caption" id="md-caption-cpu">Core 0: 0% · Core 1: 0% · Load: 0.12</div>',
            '    </div>',

            '    <!-- KPI 2: RAM -->',
            '    <div class="md-kpi-card">',
            '      <div class="md-kpi-label">Memoria RAM</div>',
            '      <div class="md-kpi-main">',
            '        <span class="md-kpi-value" id="md-kpi-ram">24.0</span>',
            '        <span class="md-kpi-unit">%</span>',
            '      </div>',
            '      <div class="md-progress-track">',
            '        <div class="md-progress-bar md-bar-success" id="md-bar-ram" style="width: 24%;"></div>',
            '      </div>',
            '      <div class="md-kpi-caption" id="md-caption-ram">1.0 GB de 4.0 GB · Libre: 3.0 GB</div>',
            '    </div>',

            '    <!-- KPI 3: Disco -->',
            '    <div class="md-kpi-card">',
            '      <div class="md-kpi-label">Almacenamiento (/)</div>',
            '      <div class="md-kpi-main">',
            '        <span class="md-kpi-value" id="md-kpi-disk">18.0</span>',
            '        <span class="md-kpi-unit">%</span>',
            '      </div>',
            '      <div class="md-progress-track">',
            '        <div class="md-progress-bar md-bar-teal" id="md-bar-disk" style="width: 18%;"></div>',
            '      </div>',
            '      <div class="md-kpi-caption" id="md-caption-disk">18.0 GB de 100.0 GB · Libre: 82.0 GB</div>',
            '    </div>',

            '    <!-- KPI 4: Uptime -->',
            '    <div class="md-kpi-card">',
            '      <div class="md-kpi-label">Tiempo de Actividad</div>',
            '      <div class="md-kpi-main">',
            '        <span class="md-kpi-value md-val-text" id="md-kpi-uptime">3d 5h 0m</span>',
            '      </div>',
            '      <div class="md-progress-track">',
            '        <div class="md-progress-bar md-bar-success" style="width: 100%;"></div>',
            '      </div>',
            '      <div class="md-kpi-caption" id="md-caption-uptime">Conexiones Web: 2 activas (80/443)</div>',
            '    </div>',
            '  </div>',

            '  <!-- Gráficos Nativos SVG Material Design -->',
            '  <div class="md-charts-grid">',
            '    <!-- Gráfico CPU SVG -->',
            '    <div class="md-chart-panel">',
            '      <div class="md-chart-header">',
            '        <div class="md-chart-title">',
            '          <span class="md-dot md-dot-blue"></span> Historial de CPU (%)',
            '        </div>',
            '        <div class="md-chart-meta">Alerta &gt; 80%</div>',
            '      </div>',
            '      <div class="md-svg-container" id="md-svg-cpu-container">',
            '        <svg id="md-svg-cpu" viewBox="0 0 500 150" preserveAspectRatio="none"></svg>',
            '        <div class="md-chart-tooltip" id="md-tooltip-cpu" style="display:none;"></div>',
            '      </div>',
            '    </div>',

            '    <!-- Gráfico RAM SVG -->',
            '    <div class="md-chart-panel">',
            '      <div class="md-chart-header">',
            '        <div class="md-chart-title">',
            '          <span class="md-dot md-dot-green"></span> Historial de Memoria RAM (%)',
            '        </div>',
            '        <div class="md-chart-meta">Límite &gt; 90%</div>',
            '      </div>',
            '      <div class="md-svg-container" id="md-svg-ram-container">',
            '        <svg id="md-svg-ram" viewBox="0 0 500 150" preserveAspectRatio="none"></svg>',
            '        <div class="md-chart-tooltip" id="md-tooltip-ram" style="display:none;"></div>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('\n');

        // Referencias DOM
        var dom = {
            ip: document.getElementById('md-val-ip'),
            ping: document.getElementById('md-val-ping'),
            hostInfo: document.getElementById('md-host-info'),
            kpiCpu: document.getElementById('md-kpi-cpu'),
            barCpu: document.getElementById('md-bar-cpu'),
            captionCpu: document.getElementById('md-caption-cpu'),
            kpiRam: document.getElementById('md-kpi-ram'),
            barRam: document.getElementById('md-bar-ram'),
            captionRam: document.getElementById('md-caption-ram'),
            kpiDisk: document.getElementById('md-kpi-disk'),
            barDisk: document.getElementById('md-bar-disk'),
            captionDisk: document.getElementById('md-caption-disk'),
            kpiUptime: document.getElementById('md-kpi-uptime'),
            svgCpu: document.getElementById('md-svg-cpu'),
            tooltipCpu: document.getElementById('md-tooltip-cpu'),
            svgRam: document.getElementById('md-svg-ram'),
            tooltipRam: document.getElementById('md-tooltip-ram'),
            btnPause: document.getElementById('md-btn-pause'),
            pauseText: document.getElementById('md-pause-text')
        };

        // Generar trazado SVG suave (Curvas Bézier nativas)
        function renderNativeSvgChart(svgEl, data, key, colorHex, alertY, tooltipEl) {
            if (!svgEl) return;
            var w = 500;
            var h = 150;
            var padBottom = 22;
            var chartH = h - padBottom;
            var maxPoints = 30;

            var points = data.slice(-maxPoints);
            if (points.length < 2) {
                points = [{ time: '--:--', cpu: 0, ram: 0 }].concat(points);
            }

            var gradId = 'grad-' + key;
            var svgContent = [
                '<defs>',
                '  <linearGradient id="' + gradId + '" x1="0" y1="0" x2="0" y2="1">',
                '    <stop offset="0%" stop-color="' + colorHex + '" stop-opacity="0.35"/>',
                '    <stop offset="100%" stop-color="' + colorHex + '" stop-opacity="0.0"/>',
                '  </linearGradient>',
                '</defs>',
                '<!-- Grid Lines -->',
                '<line x1="30" y1="10" x2="495" y2="10" stroke="var(--border)" stroke-dasharray="3,3" stroke-width="1"/>',
                '<line x1="30" y1="' + (chartH * 0.5) + '" x2="495" y2="' + (chartH * 0.5) + '" stroke="var(--border)" stroke-dasharray="3,3" stroke-width="1"/>',
                '<line x1="30" y1="' + chartH + '" x2="495" y2="' + chartH + '" stroke="var(--border)" stroke-width="1"/>',
                '<!-- Y Axis Labels -->',
                '<text x="5" y="14" fill="var(--text-secondary)" font-size="10" font-family="system-ui">100%</text>',
                '<text x="12" y="' + (chartH * 0.5 + 3) + '" fill="var(--text-secondary)" font-size="10" font-family="system-ui">50%</text>',
                '<text x="18" y="' + (chartH + 3) + '" fill="var(--text-secondary)" font-size="10" font-family="system-ui">0%</text>'
            ];

            // Línea de alerta
            if (alertY) {
                var yAlertPx = chartH - (alertY / 100) * (chartH - 10);
                svgContent.push(
                    '<line x1="30" y1="' + yAlertPx + '" x2="495" y2="' + yAlertPx + '" stroke="#d32f2f" stroke-dasharray="4,4" stroke-width="1"/>',
                    '<text x="465" y="' + (yAlertPx - 3) + '" fill="#d32f2f" font-size="9" font-family="system-ui">' + alertY + '%</text>'
                );
            }

            var startX = 35;
            var endX = 490;
            var stepX = (endX - startX) / (points.length - 1 || 1);

            var coords = [];
            for (var i = 0; i < points.length; i++) {
                var val = Math.max(0, Math.min(100, Number(points[i][key]) || 0));
                var x = startX + i * stepX;
                var y = chartH - (val / 100) * (chartH - 15);
                coords.push({ x: x, y: y, val: val, time: points[i].time });
            }

            // Construir curvas Bézier suaves
            var pathD = 'M ' + coords[0].x + ' ' + coords[0].y;
            for (var j = 0; j < coords.length - 1; j++) {
                var p0 = coords[j];
                var p1 = coords[j + 1];
                var cpx1 = p0.x + (p1.x - p0.x) / 2;
                var cpy1 = p0.y;
                var cpx2 = p0.x + (p1.x - p0.x) / 2;
                var cpy2 = p1.y;
                pathD += ' C ' + cpx1 + ' ' + cpy1 + ', ' + cpx2 + ' ' + cpy2 + ', ' + p1.x + ' ' + p1.y;
            }

            var areaD = pathD + ' L ' + coords[coords.length - 1].x + ' ' + chartH + ' L ' + coords[0].x + ' ' + chartH + ' Z';

            svgContent.push('<path d="' + areaD + '" fill="url(#' + gradId + ')"/>');
            svgContent.push('<path d="' + pathD + '" fill="none" stroke="' + colorHex + '" stroke-width="2.5" stroke-linecap="round"/>');

            // Puntos y etiquetas temporales en el eje X
            var firstTime = points[0].time || '';
            var lastTime = points[points.length - 1].time || '';
            svgContent.push(
                '<text x="' + startX + '" y="' + (h - 4) + '" fill="var(--text-secondary)" font-size="10" font-family="system-ui">' + firstTime + '</text>',
                '<text x="' + (endX - 35) + '" y="' + (h - 4) + '" fill="var(--text-secondary)" font-size="10" font-family="system-ui">' + lastTime + '</text>'
            );

            // Círculo animado en el último punto
            var lastPt = coords[coords.length - 1];
            svgContent.push(
                '<circle cx="' + lastPt.x + '" cy="' + lastPt.y + '" r="4" fill="' + colorHex + '" stroke="var(--surface)" stroke-width="2"/>'
            );

            svgEl.innerHTML = svgContent.join('\n');

            // Interacción de Tooltip al pasar el ratón
            svgEl.onmousemove = function (e) {
                var rect = svgEl.getBoundingClientRect();
                var relX = (e.clientX - rect.left) / rect.width * w;
                // Buscar punto más cercano
                var closest = null;
                var minDist = 9999;
                for (var k = 0; k < coords.length; k++) {
                    var d = Math.abs(coords[k].x - relX);
                    if (d < minDist) {
                        minDist = d;
                        closest = coords[k];
                    }
                }
                if (closest && tooltipEl) {
                    tooltipEl.style.display = 'block';
                    tooltipEl.style.left = (closest.x / w * 100) + '%';
                    tooltipEl.innerHTML = '<strong>' + closest.time + '</strong>: ' + closest.val.toFixed(1) + '%';
                }
            };
            svgEl.onmouseleave = function () {
                if (tooltipEl) tooltipEl.style.display = 'none';
            };
        }

        // Sondeo del endpoint /api/server-status
        function pollServer() {
            var token = window.SRVCTL_TOKEN || (window.localStorage && localStorage.getItem('srvctl_token')) || '';
            var start = performance.now();
            var url = '?action=server_status' + (token ? '&token=' + encodeURIComponent(token) : '');

            fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    var latency = Math.round(performance.now() - start);
                    state.latency_ms = latency || data.latency_ms || 10;
                    state.server_ip = data.server_ip || state.server_ip;
                    state.base_domain = data.base_domain || state.base_domain;
                    state.hostname = data.hostname || state.hostname;
                    state.kernel = data.kernel || state.kernel;
                    state.uptime_formatted = data.uptime_formatted || state.uptime_formatted;

                    if (data.cpu) {
                        state.cpu_percent = Number(data.cpu.usage_percent) || 0;
                        state.cpu_cores = data.cpu.cores || [0, 0];
                        state.cpu_load = data.cpu.load_avg || [0, 0, 0];
                    }
                    if (data.ram) {
                        state.ram_percent = Number(data.ram.usage_percent) || 0;
                        state.ram_used = data.ram.used_formatted;
                        state.ram_total = data.ram.total_formatted;
                        state.ram_free = data.ram.free_formatted;
                    }
                    if (data.disk) {
                        state.disk_percent = Number(data.disk.usage_percent) || 18;
                        state.disk_used = data.disk.used_formatted;
                        state.disk_total = data.disk.total_formatted;
                        state.disk_free = data.disk.free_formatted;
                    }

                    // Actualizar histórico
                    var point = {
                        time: data.timestamp || new Date().toTimeString().split(' ')[0],
                        cpu: state.cpu_percent,
                        ram: state.ram_percent
                    };
                    state.history.push(point);
                    if (state.history.length > 30) state.history.shift();

                    // Renderizar DOM
                    updateDom();
                })
                .catch(function (err) {
                    // Mantener datos anteriores y reportar en ping
                    if (dom.ping) dom.ping.textContent = 'Reconectando…';
                });
        }

        function updateDom() {
            if (dom.ip) dom.ip.textContent = state.server_ip;
            if (dom.ping) dom.ping.textContent = state.latency_ms + ' ms';
            if (dom.hostInfo) dom.hostInfo.textContent = state.hostname + ' · ' + state.kernel.split(' ')[0] + ' · ' + state.base_domain;

            // CPU
            if (dom.kpiCpu) dom.kpiCpu.textContent = state.cpu_percent.toFixed(1);
            if (dom.barCpu) {
                dom.barCpu.style.width = Math.min(100, Math.max(0, state.cpu_percent)) + '%';
                dom.barCpu.className = 'md-progress-bar ' + (state.cpu_percent > 85 ? 'md-bar-danger' : (state.cpu_percent > 60 ? 'md-bar-warning' : ''));
            }
            if (dom.captionCpu) {
                var c0 = state.cpu_cores[0] !== undefined ? state.cpu_cores[0] : 0;
                var c1 = state.cpu_cores[1] !== undefined ? state.cpu_cores[1] : 0;
                dom.captionCpu.textContent = 'Core 0: ' + c0 + '% · Core 1: ' + c1 + '% · Load: ' + state.cpu_load.join(' · ');
            }

            // RAM
            if (dom.kpiRam) dom.kpiRam.textContent = state.ram_percent.toFixed(1);
            if (dom.barRam) {
                dom.barRam.style.width = Math.min(100, Math.max(0, state.ram_percent)) + '%';
                dom.barRam.className = 'md-progress-bar ' + (state.ram_percent > 90 ? 'md-bar-danger' : (state.ram_percent > 75 ? 'md-bar-warning' : 'md-bar-success'));
            }
            if (dom.captionRam) {
                dom.captionRam.textContent = state.ram_used + ' de ' + state.ram_total + ' · Libre: ' + state.ram_free;
            }

            // Disco
            if (dom.kpiDisk) dom.kpiDisk.textContent = state.disk_percent.toFixed(1);
            if (dom.barDisk) dom.barDisk.style.width = state.disk_percent + '%';
            if (dom.captionDisk) {
                dom.captionDisk.textContent = state.disk_used + ' de ' + state.disk_total + ' · Libre: ' + state.disk_free;
            }

            // Uptime
            if (dom.kpiUptime) dom.kpiUptime.textContent = state.uptime_formatted;

            // Gráficos SVG Nativos
            renderNativeSvgChart(dom.svgCpu, state.history, 'cpu', '#1976d2', 80, dom.tooltipCpu);
            renderNativeSvgChart(dom.svgRam, state.history, 'ram', '#2e7d32', 90, dom.tooltipRam);
        }

        function startPolling() {
            stopPolling();
            pollServer();
            state.timer = setInterval(pollServer, state.interval_ms);
        }

        function stopPolling() {
            if (state.timer) {
                clearInterval(state.timer);
                state.timer = null;
            }
        }

        // Configurar botones de intervalo
        var intervalBtns = container.querySelectorAll('.md-btn-chip[data-interval]');
        intervalBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                intervalBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                state.interval_ms = parseInt(btn.getAttribute('data-interval'), 10) || 1000;
                if (state.is_polling) {
                    startPolling();
                }
            });
        });

        // Botón pausar / reanudar
        if (dom.btnPause) {
            dom.btnPause.addEventListener('click', function () {
                state.is_polling = !state.is_polling;
                if (state.is_polling) {
                    startPolling();
                    dom.pauseText.textContent = 'Pausar';
                    dom.btnPause.classList.remove('active');
                } else {
                    stopPolling();
                    dom.pauseText.textContent = 'Reanudar';
                    dom.btnPause.classList.add('active');
                }
            });
        }

        // Iniciar
        startPolling();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMaterialMonitor);
    } else {
        initMaterialMonitor();
    }

    document.addEventListener('section:loaded', function (e) {
        if (e.detail && e.detail.page === 'overview') {
            setTimeout(initMaterialMonitor, 40);
        }
    });
})();
