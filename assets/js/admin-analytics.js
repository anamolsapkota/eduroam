(function () {
    if (!window.Chart || !window.analyticsCharts) return;

    var data = window.analyticsCharts;

    Chart.defaults.color = '#314a63';
    Chart.defaults.font.family = 'Sora, sans-serif';
    Chart.defaults.font.weight = '500';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding = 16;

    var palette = {
        blue: '#3b82f6',
        blueBg: 'rgba(59, 130, 246, 0.12)',
        green: '#10b981',
        greenBg: 'rgba(16, 185, 129, 0.12)',
        red: '#ef4444',
        amber: '#f59e0b',
        purple: '#8b5cf6',
        navy: '#0d3b6f',
        navyBg: 'rgba(13, 59, 111, 0.10)',
        teal: '#0e9f8a',
        tealBg: 'rgba(14, 159, 138, 0.12)',
        slate: '#64748b',
    };

    function professionalTooltip(extra) {
        return {
            backgroundColor: 'rgba(15, 23, 42, 0.95)',
            titleColor: '#e2e8f0',
            bodyColor: '#cbd5e1',
            borderColor: 'rgba(148, 163, 184, 0.3)',
            borderWidth: 1,
            padding: { top: 12, right: 16, bottom: 12, left: 16 },
            cornerRadius: 8,
            titleFont: { size: 13, weight: '700', family: 'Sora, sans-serif' },
            bodyFont: { size: 12, weight: '500', family: 'Sora, sans-serif' },
            bodySpacing: 6,
            boxPadding: 6,
            usePointStyle: true,
            callbacks: Object.assign({ title: function (items) { return items[0].label || ''; } }, extra || {}),
        };
    }

    function gridStyle() {
        return { color: 'rgba(49, 74, 99, 0.08)', drawBorder: false, tickLength: 0 };
    }

    function tickStyle(limit) {
        return { color: '#64748b', font: { size: 11, weight: '600' }, padding: 8, maxTicksLimit: limit || 10, autoSkip: true };
    }

    function summaryHtml(el, items) {
        if (!el) return;
        el.innerHTML = items.map(function (item) {
            return '<span class="chart-summary-item"><span>' + item[0] + '</span><strong>' + item[1] + '</strong></span>';
        }).join('');
    }

    // User Registrations (14d line)
    var userDailyCanvas = document.getElementById('userDailyChart');
    if (userDailyCanvas && data.userDaily) {
        var ud = data.userDaily;
        new Chart(userDailyCanvas, {
            type: 'line',
            data: {
                labels: ud.labels,
                datasets: [{
                    label: 'New Users',
                    data: ud.values,
                    borderColor: palette.blue,
                    backgroundColor: palette.blueBg,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: palette.blue,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    tension: 0.35,
                    fill: true,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { tooltip: professionalTooltip() },
                scales: { x: { grid: gridStyle(), ticks: tickStyle(7) }, y: { beginAtZero: true, grid: gridStyle(), ticks: Object.assign(tickStyle(), { precision: 0 }) } },
            },
        });
        var total = ud.values.reduce(function (a, b) { return a + b; }, 0);
        summaryHtml(document.getElementById('userDailySummary'), [['Period', 'Last 14 days'], ['Total', total.toLocaleString()], ['Avg/Day', (total / 14).toFixed(1)]]);
    }

    // Auth Attempts (14d bar)
    var authDailyCanvas = document.getElementById('authDailyChart');
    if (authDailyCanvas && data.authDaily) {
        var ad = data.authDaily;
        new Chart(authDailyCanvas, {
            type: 'bar',
            data: {
                labels: ad.labels,
                datasets: [{
                    label: 'Attempts',
                    data: ad.values,
                    backgroundColor: palette.blue,
                    borderRadius: 4,
                    barPercentage: 0.7,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { tooltip: professionalTooltip() },
                scales: { x: { grid: gridStyle(), ticks: tickStyle(7) }, y: { beginAtZero: true, grid: gridStyle(), ticks: Object.assign(tickStyle(), { precision: 0 }) } },
            },
        });
        var total = ad.values.reduce(function (a, b) { return a + b; }, 0);
        summaryHtml(document.getElementById('authDailySummary'), [['Period', 'Last 14 days'], ['Total', total.toLocaleString()], ['Avg/Day', (total / 14).toFixed(1)]]);
    }

    // Sessions by Hour (line)
    var sessionCanvas = document.getElementById('sessionHourlyChart');
    if (sessionCanvas && data.sessionHourly) {
        var sh = data.sessionHourly;
        new Chart(sessionCanvas, {
            type: 'line',
            data: {
                labels: sh.labels,
                datasets: [{
                    label: 'Sessions',
                    data: sh.values,
                    borderColor: palette.teal,
                    backgroundColor: palette.tealBg,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: palette.teal,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    tension: 0.35,
                    fill: true,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { tooltip: professionalTooltip() },
                scales: { x: { grid: gridStyle(), ticks: tickStyle(12) }, y: { beginAtZero: true, grid: gridStyle(), ticks: Object.assign(tickStyle(), { precision: 0 }) } },
            },
        });
        var peak = Math.max.apply(null, sh.values);
        var peakHour = sh.labels[sh.values.indexOf(peak)] || '-';
        summaryHtml(document.getElementById('sessionHourlySummary'), [['Period', 'Last 14 days (by hour)'], ['Peak Hour', peakHour], ['Peak Count', peak.toLocaleString()]]);
    }

    // System Capacity (horizontal bar)
    var sysCanvas = document.getElementById('systemCapacityChart');
    if (sysCanvas && data.systemCapacity) {
        var sc = data.systemCapacity;
        new Chart(sysCanvas, {
            type: 'bar',
            data: {
                labels: sc.labels,
                datasets: [{
                    data: sc.values,
                    backgroundColor: [palette.blue, palette.green, palette.amber, palette.green],
                    borderRadius: 4,
                    barPercentage: 0.6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: professionalTooltip({ label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed.x.toFixed(2) + ' GB'; } }),
                },
                scales: {
                    x: { beginAtZero: true, grid: gridStyle(), ticks: Object.assign(tickStyle(), { callback: function (val) { return val + ' GB'; } }) },
                    y: { grid: { display: false }, ticks: tickStyle() },
                },
            },
        });
    }

    // Auth Outcomes (doughnut)
    var replyCanvas = document.getElementById('authReplyChart');
    if (replyCanvas && data.authReply) {
        var ar = data.authReply;
        var arTotal = ar.values.reduce(function (a, b) { return a + b; }, 0);
        var colors = [palette.green, palette.red, palette.amber, palette.purple, palette.slate];
        new Chart(replyCanvas, {
            type: 'doughnut',
            data: {
                labels: ar.labels,
                datasets: [{ data: ar.values, backgroundColor: colors.slice(0, ar.labels.length), borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '58%',
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, padding: 16, font: { size: 12, weight: '600' } } },
                    tooltip: professionalTooltip({
                        label: function (ctx) {
                            var pct = arTotal > 0 ? ((ctx.parsed / arTotal) * 100).toFixed(1) : '0.0';
                            return ' ' + ctx.label + ': ' + ctx.parsed.toLocaleString() + ' (' + pct + '%)';
                        },
                    }),
                },
            },
        });
    }

    // Top NAS (horizontal bar)
    var nasCanvas = document.getElementById('topNasChart');
    if (nasCanvas && data.topNas) {
        var tn = data.topNas;
        new Chart(nasCanvas, {
            type: 'bar',
            data: {
                labels: tn.labels,
                datasets: [{ label: 'Sessions', data: tn.values, backgroundColor: palette.navy, borderRadius: 4, barPercentage: 0.6 }],
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: professionalTooltip() },
                scales: {
                    x: { beginAtZero: true, grid: gridStyle(), ticks: Object.assign(tickStyle(), { precision: 0 }) },
                    y: { grid: { display: false }, ticks: tickStyle() },
                },
            },
        });
    }
})();
