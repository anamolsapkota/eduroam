(function () {
    if (!window.Chart || !window.analyticsCharts) return;

    const charts = window.analyticsCharts;
    const palette = {
        blue: '#0b5cab',
        navy: '#0d3b6f',
        teal: '#0e9f8a',
        amber: '#d97706',
        purple: '#7c3aed',
        red: '#dc2626',
        slate: '#64748b',
        sky: '#38bdf8'
    };

    Chart.defaults.color = '#314a63';
    Chart.defaults.font.family = 'Sora, sans-serif';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;

    function formatNumber(value, unit) {
        const number = Number(value);
        const formatted = Number.isInteger(number) ? number.toString() : number.toLocaleString(undefined, {
            maximumFractionDigits: 2
        });

        return unit ? formatted + ' ' + unit : formatted;
    }

    const visibleValueLabels = {
        id: 'visibleValueLabels',
        afterDatasetsDraw(chart, args, options) {
            if (options === false) return;

            const { ctx, chartArea } = chart;
            const chartType = chart.config.type;
            const hideZero = options.hideZero !== false;

            ctx.save();
            ctx.font = '700 11px Sora, sans-serif';
            ctx.fillStyle = options.color || '#20364c';

            chart.data.datasets.forEach((dataset, datasetIndex) => {
                const meta = chart.getDatasetMeta(datasetIndex);
                if (!meta.visible) return;

                const total = dataset.data.reduce((sum, item) => {
                    const itemValue = Number(item);
                    return Number.isFinite(itemValue) ? sum + itemValue : sum;
                }, 0);

                meta.data.forEach((element, index) => {
                    const value = Number(dataset.data[index]);
                    if (!Number.isFinite(value) || (hideZero && value === 0)) return;

                    const position = element.tooltipPosition();

                    if (chartType === 'pie' || chartType === 'doughnut') {
                        if (total > 0 && value / total < 0.08) return;
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillStyle = '#ffffff';
                        ctx.fillText(formatNumber(value), position.x, position.y);
                        ctx.fillStyle = options.color || '#20364c';
                        return;
                    }

                    if (chart.options.indexAxis === 'y') {
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        const x = Math.min(position.x + 8, chartArea.right - 26);
                        ctx.fillText(formatNumber(value, dataset.unit), x, position.y);
                        return;
                    }

                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(formatNumber(value, dataset.unit), position.x, Math.max(position.y - 7, chartArea.top + 12));
                });
            });

            ctx.restore();
        }
    };

    const emptyState = {
        id: 'emptyState',
        afterDraw(chart, args, options) {
            if (options === false) return;

            const hasVisibleData = chart.data.datasets.some((dataset) => {
                return dataset.data.some((item) => Number(item) > 0);
            });

            if (hasVisibleData) return;

            const { ctx, chartArea } = chart;
            ctx.save();
            ctx.fillStyle = '#5a6f83';
            ctx.font = '600 13px Sora, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(options.text || 'No data in this period', (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2);
            ctx.restore();
        }
    };

    if (!window.adminAnalyticsPluginsRegistered) {
        Chart.register(visibleValueLabels, emptyState);
        window.adminAnalyticsPluginsRegistered = true;
    }

    function pluginDefaults(extra) {
        return Object.assign({
            legend: {
                labels: {
                    color: '#314a63',
                    padding: 14,
                    boxWidth: 8,
                    font: {
                        size: 12,
                        weight: '600'
                    }
                }
            },
            tooltip: {
                backgroundColor: '#0f172a',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                padding: 10,
                callbacks: {
                    label(context) {
                        const parsed = context.parsed;
                        const value = typeof parsed === 'object' ? (parsed.y ?? parsed.x ?? 0) : parsed;
                        const label = context.label || context.dataset.label || '';
                        const unit = context.dataset.unit || '';
                        return (label ? label + ': ' : '') + formatNumber(value, unit);
                    }
                }
            },
            visibleValueLabels: {
                hideZero: true
            },
            emptyState: {
                text: 'No data in this period'
            }
        }, extra || {});
    }

    function axisStyle(maxTicksLimit) {
        return {
            ticks: {
                color: '#314a63',
                precision: 0,
                autoSkip: true,
                maxTicksLimit: maxTicksLimit || 8,
                font: {
                    size: 11,
                    weight: '600'
                }
            },
            grid: {
                color: 'rgba(49, 74, 99, 0.14)',
                drawBorder: false
            }
        };
    }

    function chartOptions(overrides) {
        const custom = overrides || {};
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 24,
                    right: 16,
                    bottom: 0,
                    left: 6
                }
            },
            plugins: pluginDefaults(),
            scales: {
                x: axisStyle(),
                y: Object.assign(axisStyle(), {
                    beginAtZero: true
                })
            }
        };

        if (custom.scales === false) {
            delete options.scales;
        } else if (custom.scales) {
            options.scales = custom.scales;
        }

        if (custom.plugins) {
            options.plugins = pluginDefaults(custom.plugins);
        }

        delete custom.scales;
        delete custom.plugins;

        return Object.assign(options, custom);
    }

    function renderSummary(id, data, options) {
        const container = document.getElementById(id);
        if (!container || !data) return;

        const settings = options || {};
        const labels = data.labels || [];
        const values = data.values || [];
        const hasData = values.some((item) => Number(item) > 0);

        container.textContent = '';

        if (!hasData) {
            const empty = document.createElement('span');
            empty.className = 'chart-summary-empty';
            empty.textContent = settings.emptyText || 'No data recorded';
            container.appendChild(empty);
            return;
        }

        labels.forEach((label, index) => {
            const item = document.createElement('span');
            item.className = 'chart-summary-item';

            const labelEl = document.createElement('span');
            labelEl.textContent = label;

            const valueEl = document.createElement('strong');
            valueEl.textContent = formatNumber(values[index] || 0, settings.unit || '');

            item.appendChild(labelEl);
            item.appendChild(valueEl);
            container.appendChild(item);
        });
    }

    function makeChart(id, config) {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, config);
    }

    makeChart('guestDailyChart', {
        type: 'line',
        data: {
            labels: charts.guestDaily.labels,
            datasets: [{
                label: 'Guest requests',
                data: charts.guestDaily.values,
                borderColor: palette.teal,
                backgroundColor: 'rgba(14, 159, 138, 0.16)',
                pointBackgroundColor: palette.teal,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                fill: true,
                tension: 0.32
            }]
        },
        options: chartOptions({
            plugins: {
                emptyState: {
                    text: 'No guest requests in this period'
                }
            }
        })
    });

    makeChart('guestExpiryChart', {
        type: 'bar',
        data: {
            labels: charts.guestExpiry.labels,
            datasets: [{
                label: 'Accounts expiring',
                data: charts.guestExpiry.values,
                backgroundColor: palette.amber,
                borderRadius: 8
            }]
        },
        options: chartOptions({
            plugins: {
                emptyState: {
                    text: 'No accounts expire in this period'
                }
            }
        })
    });

    makeChart('guestStatusChart', {
        type: 'doughnut',
        data: {
            labels: charts.guestStatus.labels,
            datasets: [{
                data: charts.guestStatus.values,
                backgroundColor: [palette.teal, palette.red],
                borderWidth: 0
            }]
        },
        options: chartOptions({
            scales: false,
            cutout: '62%',
            plugins: {
                visibleValueLabels: {
                    hideZero: true
                }
            }
        })
    });

    makeChart('authDailyChart', {
        type: 'bar',
        data: {
            labels: charts.authDaily.labels,
            datasets: [{
                label: 'Auth attempts',
                data: charts.authDaily.values,
                backgroundColor: palette.blue,
                borderRadius: 8
            }]
        },
        options: chartOptions({
            plugins: {
                emptyState: {
                    text: 'No radpostauth records yet'
                }
            }
        })
    });

    makeChart('sessionHourlyChart', {
        type: 'line',
        data: {
            labels: charts.sessionHourly.labels,
            datasets: [{
                label: 'Sessions',
                data: charts.sessionHourly.values,
                borderColor: palette.purple,
                backgroundColor: 'rgba(124, 58, 237, 0.14)',
                pointBackgroundColor: palette.purple,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 3,
                fill: true,
                tension: 0.3
            }]
        },
        options: chartOptions({
            scales: {
                x: axisStyle(12),
                y: Object.assign(axisStyle(), {
                    beginAtZero: true
                })
            },
            plugins: {
                emptyState: {
                    text: 'No radacct session records yet'
                }
            }
        })
    });

    makeChart('systemCapacityChart', {
        type: 'bar',
        data: {
            labels: charts.systemCapacity.labels,
            datasets: [{
                label: 'GB',
                unit: 'GB',
                data: charts.systemCapacity.values,
                backgroundColor: [palette.blue, palette.teal, palette.purple, palette.amber],
                borderRadius: 8
            }]
        },
        options: chartOptions({
            indexAxis: 'y',
            scales: {
                x: Object.assign(axisStyle(), {
                    beginAtZero: true
                }),
                y: axisStyle(4)
            }
        })
    });

    makeChart('authReplyChart', {
        type: 'pie',
        data: {
            labels: charts.authReply.labels,
            datasets: [{
                data: charts.authReply.values,
                backgroundColor: [palette.teal, palette.red, palette.amber, palette.purple, palette.slate, palette.sky],
                borderWidth: 0
            }]
        },
        options: chartOptions({
            scales: false,
            plugins: {
                emptyState: {
                    text: 'No radpostauth outcomes yet'
                }
            }
        })
    });

    makeChart('topNasChart', {
        type: 'bar',
        data: {
            labels: charts.topNas.labels,
            datasets: [{
                label: 'Sessions',
                data: charts.topNas.values,
                backgroundColor: palette.navy,
                borderRadius: 8
            }]
        },
        options: chartOptions({
            indexAxis: 'y',
            scales: {
                x: Object.assign(axisStyle(), {
                    beginAtZero: true
                }),
                y: axisStyle(5)
            },
            plugins: {
                emptyState: {
                    text: 'No radacct NAS records yet'
                }
            }
        })
    });

    renderSummary('guestDailySummary', charts.guestDaily, {
        emptyText: 'No guest requests in the last 14 days'
    });
    renderSummary('guestExpirySummary', charts.guestExpiry, {
        emptyText: 'No accounts expire in the next 14 days'
    });
    renderSummary('guestStatusSummary', charts.guestStatus, {
        emptyText: 'No guest account lifecycle data'
    });
    renderSummary('authDailySummary', charts.authDaily, {
        emptyText: 'No radpostauth records yet'
    });
    renderSummary('sessionHourlySummary', charts.sessionHourly, {
        emptyText: 'No radacct session records yet'
    });
    renderSummary('systemCapacitySummary', charts.systemCapacity, {
        unit: 'GB'
    });
    renderSummary('authReplySummary', charts.authReply, {
        emptyText: 'No radpostauth outcomes yet'
    });
    renderSummary('topNasSummary', charts.topNas, {
        emptyText: 'No radacct NAS records yet'
    });
})();
