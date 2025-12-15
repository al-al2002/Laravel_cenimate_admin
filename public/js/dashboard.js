// Dashboard JavaScript - Revenue Chart (Optimized)

let revenueChart = null;
let chartDataCache = null;

async function loadRevenueChart() {
    const chartLoading = document.getElementById('chartLoading');
    const canvas = document.getElementById('revenueChart');

    if (!canvas) return;

    try {
        // Use cached data if available
        let data = chartDataCache;

        if (!data) {
            const response = await fetch('/admin/dashboard/revenue-chart');
            if (!response.ok) throw new Error('Failed to fetch chart data');
            data = await response.json();
            chartDataCache = data;
        }

        // Hide loading
        if (chartLoading) chartLoading.classList.add('hidden');

        // Create gradient for the chart
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(239, 68, 68, 0.4)');
        gradient.addColorStop(0.5, 'rgba(239, 68, 68, 0.15)');
        gradient.addColorStop(1, 'rgba(239, 68, 68, 0)');

        // Destroy existing chart if any
        if (revenueChart) {
            revenueChart.destroy();
        }

        // Create the chart with reduced animation
        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Revenue',
                    data: data.revenues,
                    borderColor: '#ef4444',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#f87171',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: 'rgba(5, 5, 5, 0.95)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        borderColor: '#ef4444',
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            title: (context) => context[0].label,
                            label: (context) => {
                                const revenue = context.parsed.y;
                                const ticketCount = data.ticketCounts[context.dataIndex];
                                return [
                                    `Revenue: ₱${revenue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
                                    `Tickets: ${ticketCount}`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(248, 250, 252, 0.05)',
                            drawBorder: false,
                        },
                        ticks: {
                            color: 'rgba(248, 250, 252, 0.6)',
                            font: { size: 11 },
                            maxRotation: 45,
                            minRotation: 0,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(248, 250, 252, 0.05)',
                            drawBorder: false,
                        },
                        ticks: {
                            color: 'rgba(248, 250, 252, 0.6)',
                            font: { size: 11 },
                            callback: (value) => value >= 1000 ? '₱' + (value / 1000).toFixed(1) + 'k' : '₱' + value,
                            padding: 10,
                        },
                        border: { display: false }
                    }
                },
                animation: {
                    duration: 800,
                    easing: 'easeOutQuart',
                },
            }
        });

    } catch (error) {
        console.error('Error loading revenue chart:', error);
        if (chartLoading) {
            chartLoading.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Failed to load chart</span>';
        }
    }
}

// Simplified revenue animation
function animateRevenue() {
    const el = document.getElementById('totalRevenue');
    if (!el) return;

    const text = el.textContent;
    const match = text.match(/[\d,]+\.?\d*/);
    if (!match) return;

    const target = parseFloat(match[0].replace(/,/g, ''));
    if (target === 0) return;

    const duration = 800;
    const start = performance.now();

    function update(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = target * eased;

        el.textContent = '₱' + current.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        if (progress < 1) requestAnimationFrame(update);
    }

    requestAnimationFrame(update);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

function init() {
    // Load chart after a small delay to not block initial render
    setTimeout(loadRevenueChart, 100);
    animateRevenue();
}
