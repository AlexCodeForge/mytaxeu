<div class="p-6 bg-white min-h-screen" @if($autoRefresh) wire:poll.{{ $pollingInterval }}s @endif>
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
                <p class="mt-1 text-sm text-gray-600">
                    System Overview and Key Metrics
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <button wire:click="$refresh"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Refresh
                </button>
                <button wire:click="toggleAutoRefresh"
                        class="px-4 py-2 {{ $autoRefresh ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-600 hover:bg-gray-700' }} text-white rounded-lg transition-colors">
                    <i class="fas fa-{{ $autoRefresh ? 'pause' : 'play' }} mr-2"></i>
                    {{ $autoRefresh ? 'Pause' : 'Auto-refresh' }}
                </button>
            </div>
        </div>
    </div>

    {{-- Key Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Total Users --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                        <dd class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['total_users']) }}</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <span class="text-green-600 font-medium">+{{ $metrics['new_users_today'] }}</span>
                    <span class="text-gray-500 ml-2">today</span>
                </div>
            </div>
        </div>

        {{-- Total Uploads --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-upload text-white"></i>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Uploads</dt>
                        <dd class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['total_uploads']) }}</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <span class="text-green-600 font-medium">+{{ $metrics['uploads_today'] }}</span>
                    <span class="text-gray-500 ml-2">today</span>
                </div>
            </div>
        </div>

        {{-- Success Rate --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Success Rate</dt>
                        <dd class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['success_rate'], 1) }}%</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-4">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-yellow-500 h-2 rounded-full" style="width: {{ $metrics['success_rate'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- Active Users --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-clock text-white"></i>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Active Users</dt>
                        <dd class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['active_users']) }}</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <span class="text-purple-600 font-medium">{{ number_format($metrics['active_percentage'], 1) }}%</span>
                    <span class="text-gray-500 ml-2">of total</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts and Analytics --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Upload Trends Chart --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Upload Trends (Last 7 Days)</h3>
            <div class="h-64">
                <canvas id="uploadsChart"></canvas>
            </div>
        </div>

        {{-- System Status --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">System Status</h3>
            <div class="space-y-4">
                {{-- Queue Status --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full {{ $systemHealth['queue_status'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} mr-3"></div>
                        <span class="text-sm font-medium text-gray-900">Queue System</span>
                    </div>
                    <span class="text-sm text-gray-500">
                        {{ $systemHealth['queued_jobs'] }} jobs queued
                    </span>
                </div>

                {{-- Storage Status --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full {{ $systemHealth['storage_status'] === 'healthy' ? 'bg-green-500' : 'bg-yellow-500' }} mr-3"></div>
                        <span class="text-sm font-medium text-gray-900">Storage</span>
                    </div>
                    <span class="text-sm text-gray-500">
                        {{ $storageMetrics['total_storage_formatted'] }} used
                    </span>
                </div>

                {{-- Database Status --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full bg-green-500 mr-3"></div>
                        <span class="text-sm font-medium text-gray-900">Database</span>
                    </div>
                    <span class="text-sm text-gray-500">Operational</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Storage and Performance Metrics --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Storage Metrics --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Storage Usage</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Total Storage Used</span>
                        <span>{{ $storageMetrics['total_storage_formatted'] }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Average File Size</span>
                        <span>{{ $storageMetrics['average_file_size_formatted'] }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Files Processed Today</span>
                        <span>{{ number_format($storageMetrics['files_today']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Performance Metrics & Export --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance & Reports</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Avg Processing Time</span>
                        <span>{{ $performanceMetrics['average_processing_time_formatted'] }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Files Processed</span>
                        <span>{{ number_format($performanceMetrics['processed_count']) }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Queue Health</span>
                        <span class="
                            @if($systemHealth['queued_jobs'] < 10) text-green-600
                            @elseif($systemHealth['queued_jobs'] < 50) text-yellow-600
                            @else text-red-600
                            @endif">
                            {{ $systemHealth['queued_jobs'] }} queued
                        </span>
                    </div>
                </div>
                <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                    <div class="space-y-2">
                        <button wire:click="exportSystemMetrics"
                                class="w-full px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                            Export System Metrics
                        </button>
                        <button wire:click="exportUploadMetrics"
                                class="w-full px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors">
                            Export Processing Metrics
                        </button>
                        <button wire:click="exportUsers"
                                class="w-full px-3 py-2 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 transition-colors">
                            Export Users Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <button wire:click="navigateToUsers"
                        class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors text-left">
                    <i class="fas fa-users mr-2"></i>
                    Manage Users
                </button>
                <button wire:click="navigateToUploads"
                        class="w-full px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors text-left">
                    <i class="fas fa-file-upload mr-2"></i>
                    View Uploads
                </button>
                <button wire:click="navigateToJobs"
                        class="w-full px-4 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700 transition-colors text-left">
                    <i class="fas fa-tasks mr-2"></i>
                    Monitor Jobs
                </button>
            </div>
        </div>
    </div>

    {{-- Recent Activity and Users --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Activity --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                <span class="text-sm text-gray-500">Last 24 hours</span>
            </div>
            @if($recentActivity && count($recentActivity) > 0)
                <div class="space-y-3">
                    @foreach($recentActivity as $activity)
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                @if($activity['type'] === 'upload')
                                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                                        <i class="fas fa-file-upload text-green-600 dark:text-green-400 text-sm"></i>
                                    </div>
                                @elseif($activity['type'] === 'user')
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 dark:text-blue-400 text-sm"></i>
                                    </div>
                                @else
                                    <div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                        <i class="fas fa-info text-gray-600 dark:text-gray-400 text-sm"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $activity['description'] }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ $activity['time'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-clock text-gray-400 text-3xl mb-4"></i>
                    <p class="text-gray-500">No recent activity</p>
                </div>
            @endif
        </div>

        {{-- Recent Users --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Users</h3>
                <span class="text-sm text-gray-500">Last 7 days</span>
            </div>
            @if($recentUsers && count($recentUsers) > 0)
                <div class="space-y-3">
                    @foreach($recentUsers as $user)
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium text-sm">
                                    {{ strtoupper(substr($user['name'], 0, 1)) }}
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $user['name'] }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ $user['email'] }} • {{ $user['uploads_count'] }} uploads
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-users text-gray-400 text-3xl mb-4"></i>
                    <p>No recent users</p>
                </div>
            @endif
        </div>
    </div>


</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endassets

@script
<script>
// Initialize chart
function initializeChart() {
    const ctx = document.getElementById('uploadsChart');
    if (!ctx) return;

    const trendData = @json($trendData);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(item => item.date),
            datasets: [{
                label: 'Uploads',
                data: trendData.map(item => item.uploads),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            }
        }
    });
}

// Initialize chart when component loads
initializeChart();

// Handle export functionality
$wire.on('start-export', async (data) => {
    try {
        const response = await fetch(`/admin/exports/${data[0].type}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(data[0].filters || {})
        });

        const result = await response.json();

        if (result.success) {
            // Show success notification with download link
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg z-50';
            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <p class="font-medium">Export ready!</p>
                        <button onclick="window.open('${result.download_url}', '_blank')"
                                class="mt-2 px-3 py-1 bg-green-600 hover:bg-green-700 rounded text-sm">
                            Download CSV
                        </button>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()"
                            class="ml-4 text-green-200 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);

            // Auto-remove notification after 30 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 30000);
        } else {
            // Show error notification
            alert('Export failed: ' + result.message);
        }
    } catch (error) {
        console.error('Export error:', error);
        alert('Export failed: ' + error.message);
    }
});
</script>
@endscript
