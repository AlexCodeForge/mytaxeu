import './bootstrap';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import Chart from 'chart.js/auto';
import chartManager from './chart-manager.js';

window.Alpine = Alpine;
window.Chart = Chart;
window.chartManager = chartManager;

Livewire.start();
