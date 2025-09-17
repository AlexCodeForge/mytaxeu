import './bootstrap';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import Chart from 'chart.js/auto';
import chartManager from './chart-manager.js';
import '@wotz/livewire-sortablejs';

window.Alpine = Alpine;
window.Chart = Chart;
window.chartManager = chartManager;

Livewire.start();
