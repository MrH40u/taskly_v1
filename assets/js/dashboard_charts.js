/**
 * dashboard_charts.js
 * Renders charts using Chart.js based on data from api/stats.php
 */

document.addEventListener('DOMContentLoaded', function () {
    fetch('api/stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderStatusChart(data.statusData);
                renderProjectChart(data.projectData);
                renderPriorityChart(data.priorityData);
            } else {
                console.error('Failed to load stats:', data.message);
            }
        })
        .catch(err => console.error('Error fetching stats:', err));
});

function renderStatusChart(data) {
    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['À faire', 'En cours', 'En revue', 'Terminé'],
            datasets: [{
                data: [data.todo, data.in_progress, data.review, data.done],
                backgroundColor: ['#f59e0b', '#3b82f6', '#8b5cf6', '#10b981'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, boxWidth: 8 }
                }
            }
        }
    });
}

function renderProjectChart(data) {
    const ctx = document.getElementById('projectChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.name),
            datasets: [{
                label: 'Tâches',
                data: data.map(item => item.count),
                backgroundColor: '#6366f1',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function renderPriorityChart(data) {
    const ctx = document.getElementById('priorityChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Basse', 'Moyenne', 'Haute'],
            datasets: [{
                data: [data.low, data.medium, data.high],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { usePointStyle: true, boxWidth: 8 }
                }
            }
        }
    });
}
