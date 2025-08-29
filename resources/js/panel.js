import './app.js';
import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
  const revenueCtx = document.getElementById('revenueChart');
  if (revenueCtx) {
    new Chart(revenueCtx, {
      type: 'line',
      data: {
        labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
        datasets: [
          { label: 'Ingresos', data: [65000, 72000, 68000, 89000, 85000, 89340], borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', tension: 0.4, fill: true },
          { label: 'Gastos', data: [25000, 28000, 26000, 23000, 22000, 23450], borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.1)', tension: 0.4, fill: true },
        ],
      },
      options: { responsive: true, maintainAspectRatio: false },
    });
  }

  const userPlanCtx = document.getElementById('userPlanChart');
  if (userPlanCtx) {
    new Chart(userPlanCtx, {
      type: 'doughnut',
      data: {
        labels: ['Individual', 'Business', 'Enterprise'],
        datasets: [{ data: [750, 350, 147], backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6'], borderWidth: 0 }],
      },
      options: { responsive: true, maintainAspectRatio: false },
    });
  }
});


