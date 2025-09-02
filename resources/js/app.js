import './bootstrap';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

Livewire.start();
