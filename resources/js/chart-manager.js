/**
 * Global Chart Manager for handling Chart.js instances across Livewire components
 *
 * This manager ensures proper creation, updating, and destruction of Chart.js instances
 * to prevent conflicts when navigating between Livewire components.
 */

class ChartManager {
    constructor() {
        this.charts = new Map();
        this.setupLivewireHooks();
    }

    /**
     * Setup Livewire lifecycle hooks for automatic cleanup
     */
    setupLivewireHooks() {
        // Clean up charts when Livewire components are being morphed/destroyed
        Livewire.hook('morph.updating', ({ el, component }) => {
            this.cleanupChartsInElement(el);
        });

        // Clean up charts when components are removed
        Livewire.hook('morph.removed', ({ el, component }) => {
            this.cleanupChartsInElement(el);
        });

        // Clean up charts when navigating away (with wire:navigate)
        document.addEventListener('livewire:navigating', () => {
            this.destroyAllCharts();
        });
    }

    /**
     * Create or update a chart instance
     * @param {string} id - Unique identifier for the chart
     * @param {HTMLCanvasElement} canvas - Canvas element
     * @param {Object} config - Chart.js configuration
     * @returns {Chart} Chart instance
     */
    createChart(id, canvas, config) {
        // Destroy existing chart with same ID
        this.destroyChart(id);

        // Ensure canvas is clean
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        try {
            const chart = new Chart(ctx, config);
            this.charts.set(id, {
                instance: chart,
                canvas: canvas,
                canvasId: canvas.id
            });

            console.log(`✅ Chart created: ${id}`);
            return chart;
        } catch (error) {
            console.error(`❌ Failed to create chart ${id}:`, error);
            throw error;
        }
    }

    /**
     * Update an existing chart's data
     * @param {string} id - Chart identifier
     * @param {Object} newData - New chart data
     * @param {string} mode - Animation mode (default: 'active')
     */
    updateChart(id, newData, mode = 'active') {
        const chartInfo = this.charts.get(id);
        if (chartInfo && chartInfo.instance) {
            chartInfo.instance.data = newData;
            chartInfo.instance.update(mode);
            console.log(`📊 Chart updated: ${id}`);
        } else {
            console.warn(`⚠️ Chart not found for update: ${id}`);
        }
    }

    /**
     * Destroy a specific chart
     * @param {string} id - Chart identifier
     */
    destroyChart(id) {
        const chartInfo = this.charts.get(id);
        if (chartInfo && chartInfo.instance) {
            try {
                chartInfo.instance.destroy();
                this.charts.delete(id);
                console.log(`🗑️ Chart destroyed: ${id}`);
            } catch (error) {
                console.error(`❌ Error destroying chart ${id}:`, error);
                // Still remove from map even if destroy failed
                this.charts.delete(id);
            }
        }
    }

    /**
     * Destroy all chart instances
     */
    destroyAllCharts() {
        console.log('🗑️ Destroying all charts...');
        for (const [id, chartInfo] of this.charts) {
            if (chartInfo.instance) {
                try {
                    chartInfo.instance.destroy();
                } catch (error) {
                    console.error(`❌ Error destroying chart ${id}:`, error);
                }
            }
        }
        this.charts.clear();
    }

    /**
     * Clean up charts within a specific DOM element
     * @param {HTMLElement} element - DOM element to search
     */
    cleanupChartsInElement(element) {
        const canvases = element.querySelectorAll('canvas');
        canvases.forEach(canvas => {
            for (const [id, chartInfo] of this.charts) {
                if (chartInfo.canvas === canvas || chartInfo.canvasId === canvas.id) {
                    this.destroyChart(id);
                }
            }
        });
    }

    /**
     * Get a chart instance by ID
     * @param {string} id - Chart identifier
     * @returns {Chart|null} Chart instance or null
     */
    getChart(id) {
        const chartInfo = this.charts.get(id);
        return chartInfo ? chartInfo.instance : null;
    }

    /**
     * Check if a chart exists
     * @param {string} id - Chart identifier
     * @returns {boolean} True if chart exists
     */
    hasChart(id) {
        return this.charts.has(id);
    }

    /**
     * Get all chart IDs
     * @returns {Array<string>} Array of chart IDs
     */
    getChartIds() {
        return Array.from(this.charts.keys());
    }
}

// Create global instance
window.chartManager = new ChartManager();

// Export for ES6 modules
export default window.chartManager;
