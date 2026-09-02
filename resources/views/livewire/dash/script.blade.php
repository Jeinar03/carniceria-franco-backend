@push('scripts')
<script src="{{ asset('plugins/apex/apexcharts.min.js') }}"></script>
<script>
    (function() {
        if (window.__dashboardChartsReady) {
            return;
        }

        window.__dashboardChartsReady = true;

        let salesByMonthData = @json($salesByMonthData ?? []);
        let top5Labels = @json($top5Labels ?? []);
        let top5Data = @json($top5Data ?? []);
        let weekSalesData = @json($weekSalesData ?? []);
        let dailyOrdersLabels = @json($dailyOrdersLabels ?? []);
        let dailyOrdersData = @json($dailyOrdersData ?? []);
        let satisfactionDistributionData = @json($satisfactionDistributionData ?? []);
        let questionSatisfactionLabels = @json($questionSatisfactionLabels ?? []);
        let questionSatisfactionData = @json($questionSatisfactionData ?? []);
        const dashboardLogoUrl = "{{ asset('images/logo.jpeg') }}";
        let dashboardPeriod = @json($kpis['periodo'] ?? '');

        window.dashboardCharts = window.dashboardCharts || {};
        window.dashboardChartTitles = {
            dailyOrders: 'Volumen diario de pedidos',
            satisfactionDistribution: 'Distribucion de satisfaccion',
            questionSatisfaction: 'Satisfaccion por pregunta',
            weekSales: 'Ventas de la semana',
            top5: 'Top 5 mas vendidos',
            monthSales: 'Ventas anuales'
        };
        window.dashboardChartSelectors = {
            dailyOrders: '#chartDailyOrders',
            satisfactionDistribution: '#chartSatisfactionDistribution',
            questionSatisfaction: '#chartQuestionSatisfaction',
            weekSales: '#areaChart',
            top5: '#chartTop5',
            monthSales: '#chartMonth'
        };

        function money(value) {
            return '$' + Number(value || 0).toFixed(2);
        }

        function renderChart(selector, options) {
            const element = document.querySelector(selector);
            if (!element || typeof ApexCharts === 'undefined') return null;
            const chart = new ApexCharts(element, options);
            chart.__container = element;
            chart.__renderPromise = chart.render();
            return chart;
        }

        function downloadDataUri(dataUri, filename) {
            if (!dataUri || dataUri === 'undefined') {
                if (typeof noty === 'function') noty('No se pudo generar la imagen', 2);
                return;
            }

            const link = document.createElement('a');
            link.href = dataUri;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function slug(value) {
            return String(value || 'grafica')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
        }

        function loadImage(src) {
            return new Promise((resolve, reject) => {
                const image = new Image();
                image.crossOrigin = 'anonymous';
                image.onload = () => resolve(image);
                image.onerror = reject;
                image.src = src;
            });
        }

        async function svgToPngDataUri(chartKey) {
            const selector = window.dashboardChartSelectors[chartKey];
            const container = selector ? document.querySelector(selector) : null;
            const svg = container ? container.querySelector('svg') : null;

            if (!svg) {
                return null;
            }

            const clone = svg.cloneNode(true);
            const box = svg.getBoundingClientRect();
            const width = Math.max(Math.ceil(box.width || svg.clientWidth || 900), 1);
            const height = Math.max(Math.ceil(box.height || svg.clientHeight || 360), 1);

            clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            clone.setAttribute('width', width);
            clone.setAttribute('height', height);
            clone.setAttribute('viewBox', clone.getAttribute('viewBox') || '0 0 ' + width + ' ' + height);

            const serialized = new XMLSerializer().serializeToString(clone);
            const blob = new Blob([serialized], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);

            try {
                const image = await loadImage(url);
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = width * 2;
                canvas.height = height * 2;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
                return canvas.toDataURL('image/png');
            } finally {
                URL.revokeObjectURL(url);
            }
        }

        async function getChartImageUri(chartKey) {
            const chart = window.dashboardCharts[chartKey];

            if (!chart) {
                return null;
            }

            if (chart.__renderPromise) {
                await chart.__renderPromise;
            }

            try {
                const result = await chart.dataURI();

                if (typeof result === 'string' && result.indexOf('data:image') === 0) {
                    return result;
                }

                if (result && typeof result.imgURI === 'string' && result.imgURI.indexOf('data:image') === 0) {
                    return result.imgURI;
                }

                if (result && result.blob) {
                    return URL.createObjectURL(result.blob);
                }
            } catch (e) {
                // Si Apex no puede exportar, intentamos con el SVG renderizado.
            }

            return svgToPngDataUri(chartKey);
        }

        function annualTitle() {
            const totalYear = salesByMonthData.reduce((acc, item) => acc + Number(item || 0), 0);
            return 'Total periodo: ' + money(totalYear);
        }

        function dailyXAxisOptions() {
            return {
                categories: dailyOrdersLabels,
                labels: {
                    rotate: dailyOrdersLabels.length > 18 ? -45 : 0,
                    trim: true,
                    hideOverlappingLabels: true,
                    style: {
                        fontSize: '11px'
                    }
                },
                tickAmount: dailyOrdersLabels.length > 24 ? 12 : undefined
            };
        }

        function initDashboardCharts() {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const chartDailyOrders = renderChart('#chartDailyOrders', {
            series: [{
                name: 'Pedidos',
                data: dailyOrdersData
            }],
            chart: {
                height: 320,
                type: 'bar',
                toolbar: { show: false }
            },
            colors: ['#3B3F5C'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '52%'
                }
            },
            dataLabels: { enabled: true },
            xaxis: dailyXAxisOptions(),
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return Math.round(val);
                    }
                }
            }
        });
            window.dashboardCharts.dailyOrders = chartDailyOrders;

            const chartSatisfactionDistribution = renderChart('#chartSatisfactionDistribution', {
            series: [{
                name: 'Respuestas',
                data: satisfactionDistributionData
            }],
            chart: {
                height: 320,
                type: 'bar',
                toolbar: { show: false }
            },
            colors: ['#1abc9c'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '45%'
                }
            },
            dataLabels: { enabled: false },
            xaxis: { categories: ['1','2','3','4','5','6','7','8','9','10'] },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return Math.round(val);
                    }
                }
            }
        });
            window.dashboardCharts.satisfactionDistribution = chartSatisfactionDistribution;

            const chartQuestionSatisfaction = renderChart('#chartQuestionSatisfaction', {
            series: [{
                name: 'Promedio',
                data: questionSatisfactionData
            }],
            chart: {
                height: 320,
                type: 'radar',
                toolbar: { show: false }
            },
            colors: ['#e2a03f'],
            xaxis: {
                categories: questionSatisfactionLabels
            },
            yaxis: {
                min: 0,
                max: 10,
                tickAmount: 5
            },
            markers: {
                size: 4
            }
        });
            window.dashboardCharts.questionSatisfaction = chartQuestionSatisfaction;

            const chartMonth = renderChart('#chartMonth', {
            series: [{
                name: 'Ventas del mes',
                data: salesByMonthData
            }],
            chart: {
                height: 320,
                type: 'bar',
                toolbar: { show: false }
            },
            colors: ['#4361ee'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '45%',
                    dataLabels: { position: 'top' }
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return money(val);
                    }
                }
            },
            title: {
                text: annualTitle(),
                align: 'center',
                style: { fontSize: '14px' }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return money(val);
                    }
                }
            }
        });
            window.dashboardCharts.monthSales = chartMonth;

            const chartTop5 = renderChart('#chartTop5', {
            series: top5Data,
            chart: {
                height: 320,
                type: 'donut'
            },
            labels: top5Labels,
            legend: { position: 'bottom' },
            dataLabels: {
                formatter: function(val) {
                    return val.toFixed(1) + '%';
                }
            }
        });
            window.dashboardCharts.top5 = chartTop5;

            const chartWeek = renderChart('#areaChart', {
            chart: {
                height: 320,
                type: 'area',
                stacked: false,
                toolbar: { show: false }
            },
            colors: ['#00ab55'],
            stroke: { curve: 'smooth' },
            dataLabels: {
                enabled: false
            },
            series: [{
                name: 'Ventas',
                data: weekSalesData
            }],
            xaxis: {
                categories: ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo']
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return money(val);
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return money(val);
                    }
                }
            }
        });
            window.dashboardCharts.weekSales = chartWeek;

            window.addEventListener('dashboard-charts-updated', function(event) {
            const data = event.detail || {};

            salesByMonthData = data.salesByMonthData || [];
            top5Labels = data.top5Labels || [];
            top5Data = data.top5Data || [];
            weekSalesData = data.weekSalesData || [];
            dailyOrdersLabels = data.dailyOrdersLabels || [];
            dailyOrdersData = data.dailyOrdersData || [];
            satisfactionDistributionData = data.satisfactionDistributionData || [];
            questionSatisfactionLabels = data.questionSatisfactionLabels || [];
            questionSatisfactionData = data.questionSatisfactionData || [];
            dashboardPeriod = data.periodo || dashboardPeriod;

            if (chartDailyOrders) {
                chartDailyOrders.updateOptions({
                    xaxis: dailyXAxisOptions()
                });
                chartDailyOrders.updateSeries([{ name: 'Pedidos', data: dailyOrdersData }]);
            }

            if (chartSatisfactionDistribution) {
                chartSatisfactionDistribution.updateSeries([{ name: 'Respuestas', data: satisfactionDistributionData }]);
            }

            if (chartQuestionSatisfaction) {
                chartQuestionSatisfaction.updateOptions({
                    xaxis: { categories: questionSatisfactionLabels }
                });
                chartQuestionSatisfaction.updateSeries([{ name: 'Promedio', data: questionSatisfactionData }]);
            }

            if (chartMonth) {
                chartMonth.updateOptions({
                    title: { text: annualTitle() }
                });
                chartMonth.updateSeries([{ name: 'Ventas del mes', data: salesByMonthData }]);
            }

            if (chartTop5) {
                chartTop5.updateOptions({
                    labels: top5Labels
                });
                chartTop5.updateSeries(top5Data);
            }

            if (chartWeek) {
                chartWeek.updateSeries([{ name: 'Ventas', data: weekSalesData }]);
            }
            });
        }

        window.downloadDashboardChart = async function(chartKey) {
            const chart = window.dashboardCharts[chartKey];
            const title = window.dashboardChartTitles[chartKey] || 'Grafica';

            if (!chart) {
                if (typeof noty === 'function') noty('Grafica no disponible', 2);
                return;
            }

            const imgURI = await getChartImageUri(chartKey);
            downloadDataUri(imgURI, slug(title) + '.png');
        };

        window.downloadAllDashboardCharts = async function() {
            const chartKeys = ['dailyOrders', 'satisfactionDistribution', 'questionSatisfaction', 'weekSales', 'top5', 'monthSales'];
            const charts = chartKeys
                .map(key => ({ key, chart: window.dashboardCharts[key], title: window.dashboardChartTitles[key] }))
                .filter(item => item.chart);

            if (charts.length === 0) {
                if (typeof noty === 'function') noty('No hay graficas disponibles', 2);
                return;
            }

            try {
                const images = [];
                for (const item of charts) {
                    const imgURI = await getChartImageUri(item.key);
                    if (!imgURI) {
                        continue;
                    }

                    images.push({
                        title: item.title,
                        image: await loadImage(imgURI)
                    });
                }

                if (images.length === 0) {
                    if (typeof noty === 'function') noty('No se pudo generar la imagen', 2);
                    return;
                }

                let logo = null;
                try {
                    logo = await loadImage(dashboardLogoUrl);
                } catch (e) {
                    logo = null;
                }

                const width = 1600;
                const padding = 48;
                const gap = 32;
                const headerHeight = 150;
                const cardWidth = (width - padding * 2 - gap) / 2;
                const cardHeight = 390;
                const rows = Math.ceil(images.length / 2);
                const height = headerHeight + padding + rows * cardHeight + (rows - 1) * gap + padding;
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                canvas.width = width;
                canvas.height = height;

                ctx.fillStyle = '#f4f6f9';
                ctx.fillRect(0, 0, width, height);

                ctx.fillStyle = '#3B3F5C';
                ctx.fillRect(0, 0, width, headerHeight);

                if (logo) {
                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(padding + 38, 75, 38, 0, Math.PI * 2);
                    ctx.closePath();
                    ctx.clip();
                    ctx.drawImage(logo, padding, 37, 76, 76);
                    ctx.restore();
                }

                ctx.fillStyle = '#ffffff';
                ctx.font = '700 34px Arial';
                ctx.fillText('Dashboard de indicadores', padding + 100, 68);

                ctx.font = '400 18px Arial';
                ctx.fillStyle = 'rgba(255,255,255,.82)';
                ctx.fillText(dashboardPeriod || 'Periodo seleccionado', padding + 100, 100);

                images.forEach((item, index) => {
                    const col = index % 2;
                    const row = Math.floor(index / 2);
                    const x = padding + col * (cardWidth + gap);
                    const y = headerHeight + padding + row * (cardHeight + gap);

                    ctx.fillStyle = '#ffffff';
                    ctx.shadowColor = 'rgba(31,45,61,.16)';
                    ctx.shadowBlur = 18;
                    ctx.shadowOffsetY = 8;
                    ctx.fillRect(x, y, cardWidth, cardHeight);
                    ctx.shadowColor = 'transparent';

                    ctx.fillStyle = '#3B3F5C';
                    ctx.font = '700 22px Arial';
                    ctx.fillText(item.title, x + 24, y + 36);

                    ctx.drawImage(item.image, x + 18, y + 58, cardWidth - 36, cardHeight - 76);
                });

                downloadDataUri(canvas.toDataURL('image/png'), 'dashboard-indicadores.png');
            } catch (error) {
                if (typeof noty === 'function') noty('No se pudo generar la imagen', 2);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDashboardCharts);
        } else {
            initDashboardCharts();
        }
    })();
</script>
@endpush
