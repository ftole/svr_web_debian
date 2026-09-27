(function () {
    'use strict';

    var INTERVAL = 2000;
    var timer = null;

    function $(id) { return document.getElementById(id); }
    function setText(id, value) { var el = $(id); if (el) { el.textContent = value; } }

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

    function apply(payload) {
        var slow = payload.slow || {};

        if (slow.services) { renderServices(slow.services); }
        if (slow.redis) {
            if (slow.redis.ok) {
                setText('live-redis-mem', slow.redis.memory);
                setText('live-redis-clients', slow.redis.clients);
                setText('live-redis-ops', slow.redis.ops);
            } else {
                setText('live-redis-mem', '—');
                setText('live-redis-clients', '—');
                setText('live-redis-ops', '—');
            }
        }
        if (slow.mariadb) {
            if (slow.mariadb.ok) {
                setText('live-db-threads', slow.mariadb.threads);
                setText('live-db-running', slow.mariadb.running);
                setText('live-db-qps', slow.mariadb.qps);
            } else {
                setText('live-db-threads', '—');
                setText('live-db-running', '—');
                setText('live-db-qps', '—');
            }
        }
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
            .catch(function () {});
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
        if (document.getElementById('live-services')) {
            start();
        } else {
            stop();
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stop(); } else { refresh(); }
    });
    document.addEventListener('section:loaded', refresh);
    refresh();
})();
