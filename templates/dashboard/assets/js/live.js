(function () {
    'use strict';

    var INTERVAL = 1000;
    var HISTORY = 60;
    var history = { cpu: [], mem: [], rx: [], tx: [] };
    var timer = null;
    var lastAlert = { mem: 0, disk: 0 };

    function $(id) { return document.getElementById(id); }
    function setText(id, value) { var el = $(id); if (el) { el.textContent = value; } }

    function fmtBytes(n) {
        n = Number(n) || 0;
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var i = 0;
        while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
        return (i === 0 ? Math.round(n) : n.toFixed(1)) + ' ' + units[i];
    }

    function fmtRate(kbs) {
        if (kbs >= 1024) { return (kbs / 1024).toFixed(1) + ' MB/s'; }
        return (Number(kbs) || 0).toFixed(1) + ' KB/s';
    }

    function fmtUptime(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        var d = Math.floor(seconds / 86400);
        var h = Math.floor((seconds % 86400) / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        return (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm';
    }

    function drawSpark(canvas, data, color) {
        if (!canvas) { return; }
        var ctx = canvas.getContext('2d');
        var w = canvas.width;
        var h = canvas.height;
        ctx.clearRect(0, 0, w, h);
        if (data.length < 2) { return; }
        var max = Math.max.apply(null, data.concat([1]));
        var step = w / (data.length - 1);
        ctx.beginPath();
        for (var i = 0; i < data.length; i++) {
            var x = i * step;
            var y = h - (data[i] / max) * (h - 8) - 4;
            if (i === 0) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); }
        }
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = 2;
        ctx.globalAlpha = 1;
        ctx.stroke();
        ctx.lineTo(w, h);
        ctx.lineTo(0, h);
        ctx.closePath();
        ctx.globalAlpha = 0.12;
        ctx.fill();
        ctx.globalAlpha = 1;
    }

    function alertIfHigh(kind, percent, message) {
        var now = Date.now();
        if (percent > 90 && (now - lastAlert[kind]) > 60000) {
            lastAlert[kind] = now;
            if (typeof window.panelToast === 'function') {
                window.panelToast('warning', message);
            }
        }
    }

    function renderServices(services) {
        var list = $('live-services');
        if (!list || !services) { return; }
        list.innerHTML = '';
        services.forEach(function (service) {
            var li = document.createElement('li');
            li.className = 'list-row';
            var left = document.createElement('span');
            var dot = document.createElement('span');
            dot.className = 'dot ' + (service.active ? 'ok' : 'err');
            left.appendChild(dot);
            left.appendChild(document.createTextNode(service.label));
            var tag = document.createElement('span');
            tag.className = 'tag ' + (service.active ? 'tag-ok' : 'tag-err');
            tag.textContent = service.active ? 'Activo' : 'Inactivo';
            li.appendChild(left);
            li.appendChild(tag);
            list.appendChild(li);
        });
    }

    function renderTop(rows) {
        var body = $('live-top');
        if (!body) { return; }
        body.innerHTML = '';
        if (!rows || !rows.length) {
            body.innerHTML = '<tr><td colspan="4" class="muted">Sin datos</td></tr>';
            return;
        }
        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            [row.pid, row.name, row.cpu + ' %', row.mem + ' %'].forEach(function (value, index) {
                var td = document.createElement('td');
                if (index === 0) {
                    var code = document.createElement('code');
                    code.textContent = value;
                    td.appendChild(code);
                } else {
                    td.textContent = value;
                }
                tr.appendChild(td);
            });
            body.appendChild(tr);
        });
    }

    function apply(payload) {
        var fast = payload.fast || {};
        var slow = payload.slow || {};

        if (fast.cpu) {
            setText('live-cpu', fast.cpu.total);
            var bar = $('live-cpu-bar');
            if (bar) { bar.style.width = fast.cpu.total + '%'; }
            var cores = $('live-cores');
            if (cores && fast.cpu.cores) {
                cores.innerHTML = '';
                fast.cpu.cores.forEach(function (usage) {
                    var item = document.createElement('span');
                    item.className = 'core';
                    var fill = document.createElement('i');
                    fill.style.width = usage + '%';
                    item.appendChild(fill);
                    cores.appendChild(item);
                });
            }
            history.cpu.push(fast.cpu.total);
        }
        if (fast.load) { setText('live-load', fast.load.join(' · ')); }
        if (fast.mem) {
            setText('live-mem', fast.mem.percent);
            var memBar = $('live-mem-bar');
            if (memBar) { memBar.style.width = fast.mem.percent + '%'; }
            setText('live-mem-used', fmtBytes(fast.mem.used));
            setText('live-swap', fast.mem.swap_total > 0 ? fmtBytes(fast.mem.swap_used) + ' / ' + fmtBytes(fast.mem.swap_total) : 'sin swap');
            history.mem.push(fast.mem.percent);
            alertIfHigh('mem', fast.mem.percent, 'Uso de RAM alto: ' + fast.mem.percent + '%.');
        }
        if (fast.disk) {
            setText('live-disk', fast.disk.percent);
            var diskBar = $('live-disk-bar');
            if (diskBar) { diskBar.style.width = fast.disk.percent + '%'; }
            setText('live-disk-used', fmtBytes(fast.disk.used));
            setText('live-disk-io', 'R ' + fmtRate(fast.disk.read_kbs) + ' · W ' + fmtRate(fast.disk.write_kbs));
            alertIfHigh('disk', fast.disk.percent, 'Uso de disco alto: ' + fast.disk.percent + '%.');
        }
        if (fast.net) {
            setText('live-net-rx', fmtRate(fast.net.rx_kbs));
            setText('live-net-tx', fmtRate(fast.net.tx_kbs));
            history.rx.push(fast.net.rx_kbs);
            history.tx.push(fast.net.tx_kbs);
        }
        setText('live-temp', fast.temp_c != null ? fast.temp_c + ' °C' : 'N/D');
        setText('live-uptime', fmtUptime(fast.uptime));

        if (slow.services) { renderServices(slow.services); }
        if (slow.redis && slow.redis.ok) {
            setText('live-redis-mem', slow.redis.memory);
            setText('live-redis-clients', slow.redis.clients);
            setText('live-redis-ops', slow.redis.ops);
        }
        if (slow.mariadb && slow.mariadb.ok) {
            setText('live-db-threads', slow.mariadb.threads);
            setText('live-db-running', slow.mariadb.running);
            setText('live-db-qps', slow.mariadb.qps);
        }
        if (slow.top) { renderTop(slow.top); }

        ['cpu', 'mem', 'rx', 'tx'].forEach(function (key) {
            while (history[key].length > HISTORY) { history[key].shift(); }
        });
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var cpuColor = isDark ? '#38BDF8' : '#0284C7';
        var memColor = isDark ? '#4E816F' : '#5C8D7B';
        var netColor = isDark ? '#FBBF24' : '#D97706';
        drawSpark($('chart-cpu'), history.cpu, cpuColor);
        drawSpark($('chart-mem'), history.mem, memColor);
        drawSpark($('chart-net'), history.rx, netColor);

        var now = new Date();
        setText('live-updated', now.toLocaleTimeString());
        setText('live-status', 'en vivo');
    }

    function poll() {
        var token = window.SRVCTL_TOKEN || (window.localStorage && localStorage.getItem('srvctl_token')) || '';
        var url = '?action=metrics' + (token ? '&token=' + encodeURIComponent(token) : '');
        fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                return response.json();
            })
            .then(function (payload) { apply(payload); })
            .catch(function () { setText('live-status', 'sin conexión'); });
    }

    function start() {
        if (timer) { return; }
        poll();
        timer = setInterval(poll, INTERVAL);
    }
    function stop() {
        if (timer) { clearInterval(timer); timer = null; }
    }

    function refresh() {
        if (document.getElementById('chart-cpu')) { start(); } else { stop(); }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stop(); } else { refresh(); }
    });
    document.addEventListener('section:loaded', refresh);
    refresh();
})();
