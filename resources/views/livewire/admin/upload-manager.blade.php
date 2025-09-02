<div class="p-6 bg-white min-h-screen">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Upload Management</h1>
        <p class="mt-1 text-sm text-gray-600">
            Manage and monitor all file uploads across the platform
        </p>
    </div>

    {{-- Filters and Search --}}
    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            {{-- Search --}}
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">
                    Search
                </label>
                <input type="text"
                       id="search"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search by filename or user..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Status Filter --}}
            <div>
                <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">
                    Status Filter
                </label>
                <select id="statusFilter"
                        wire:model.live="statusFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Statuses</option>
                    <option value="received">Received</option>
                    <option value="queued">Queued</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select>
            </div>

            {{-- User Filter --}}
            <div>
                <label for="userFilter" class="block text-sm font-medium text-gray-700 mb-1">
                    User Filter
                </label>
                <select id="userFilter"
                        wire:model.live="userFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Quick Actions --}}
            <div class="flex flex-col space-y-2">
                <button wire:click="clearFilters"
                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                    Clear Filters
                </button>
                                <div class="flex space-x-2">
                    <button wire:click="exportToInstantCsv"
                            class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                        Quick Export
                    </button>
                    <button wire:click="exportToCsv"
                            class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Full Export
                    </button>
                </div>
            </div>
        </div>

        {{-- Date Range and File Size Filters --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Date From --}}
            <div>
                <label for="dateFrom" class="block text-sm font-medium text-gray-700 mb-1">
                    Date From
                </label>
                <input type="date"
                       id="dateFrom"
                       wire:model.live="dateFrom"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Date To --}}
            <div>
                <label for="dateTo" class="block text-sm font-medium text-gray-700 mb-1">
                    Date To
                </label>
                <input type="date"
                       id="dateTo"
                       wire:model.live="dateTo"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Min File Size --}}
            <div>
                <label for="minFileSize" class="block text-sm font-medium text-gray-700 mb-1">
                    Min Size (MB)
                </label>
                <input type="number"
                       id="minFileSize"
                       wire:model.live.debounce.500ms="minFileSize"
                       placeholder="0"
                       min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Max File Size --}}
            <div>
                <label for="maxFileSize" class="block text-sm font-medium text-gray-700 mb-1">
                    Max Size (MB)
                </label>
                <input type="number"
                       id="maxFileSize"
                       wire:model.live.debounce.500ms="maxFileSize"
                       placeholder="100"
                       min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    </div>

    {{-- Status Counts --}}
    <div class="mb-6 grid grid-cols-2 md:grid-cols-6 gap-4">
        @foreach($statusCounts as $status => $count)
            <div class="bg-white p-3 rounded-lg shadow border">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($count) }}</div>
                <div class="text-sm text-gray-600 capitalize">{{ $status }}</div>
            </div>
        @endforeach
    </div>

    {{-- Bulk Actions --}}
    @if(count($selectedUploads) > 0)
        <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="text-sm text-blue-800">
                    {{ count($selectedUploads) }} upload(s) selected
                </div>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="bulkRetry"
                            wire:confirm="Are you sure you want to retry the selected uploads?"
                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                        <i class="fas fa-redo mr-1"></i>
                        Retry Selected
                    </button>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors">
                            <i class="fas fa-edit mr-1"></i>
                            Change Status <i class="fas fa-chevron-down ml-1"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                            <button wire:click="bulkUpdateStatus('cancelled')"
                                    wire:confirm="Mark selected uploads as cancelled?"
                                    @click="open = false"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-b border-gray-200">
                                <i class="fas fa-ban mr-2"></i>
                                Mark as Cancelled
                            </button>
                            <button wire:click="bulkUpdateStatus('queued')"
                                    wire:confirm="Mark selected uploads as queued?"
                                    @click="open = false"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-b border-gray-200">
                                <i class="fas fa-clock mr-2"></i>
                                Mark as Queued
                            </button>
                            <button wire:click="bulkUpdateStatus('failed')"
                                    wire:confirm="Mark selected uploads as failed?"
                                    @click="open = false"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-times mr-2"></i>
                                Mark as Failed
                            </button>
                        </div>
                    </div>

                    <button wire:click="bulkDelete"
                            wire:confirm="Are you sure you want to delete the selected uploads? This action cannot be undone."
                            class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                        <i class="fas fa-trash mr-1"></i>
                        Delete Selected
                    </button>
                    <button wire:click="clearSelection"
                            class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition-colors">
                        Clear Selection
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Upload Table --}}
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left">
                            <input type="checkbox"
                                   wire:model.live="selectAll"
                                   wire:click="toggleSelectAll"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th wire:click="sortBy('id')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            ID
                            @if($sortField === 'id')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th wire:click="sortBy('original_name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            Filename
                            @if($sortField === 'original_name')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th wire:click="sortBy('size_bytes')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            Size
                            @if($sortField === 'size_bytes')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            Created
                            @if($sortField === 'created_at')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($uploads as $upload)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <input type="checkbox"
                                       value="{{ $upload->id }}"
                                       wire:click="toggleUploadSelection({{ $upload->id }})"
                                       @if(in_array($upload->id, $selectedUploads)) checked @endif
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $upload->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $upload->user->name }}</div>
                                <div class="text-sm text-gray-500">{{ $upload->user->email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ Str::limit($upload->original_name, 30) }}</div>
                                @if($upload->rows_count)
                                    <div class="text-sm text-gray-500">{{ number_format($upload->rows_count) }} rows</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $upload->formatted_size }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    @if($upload->status === 'completed') bg-green-100 text-green-800
                                    @elseif($upload->status === 'failed') bg-red-100 text-red-800
                                    @elseif($upload->status === 'processing') bg-blue-100 text-blue-800
                                    @elseif($upload->status === 'queued') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($upload->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{{ $upload->created_at->format('M j, Y') }}</div>
                                <div class="text-xs">{{ $upload->created_at->format('H:i') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <button wire:click="showUploadDetails({{ $upload->id }})"
                                            class="text-blue-600 hover:text-blue-800">
                                        View
                                    </button>
                                    @if($upload->status === 'failed')
                                        <button wire:click="retryUpload({{ $upload->id }})"
                                                class="text-yellow-600 hover:text-yellow-800">
                                            Retry
                                        </button>
                                    @endif
                                    <button wire:click="downloadOriginalFile({{ $upload->id }})"
                                            class="text-green-600 hover:text-green-800">
                                        Download
                                    </button>
                                    @if($upload->transformed_path && $upload->status === 'completed')
                                        <button wire:click="downloadTransformedFile({{ $upload->id }})"
                                                class="text-purple-600 hover:text-purple-800">
                                            Result
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <div class="text-lg mb-2">No uploads found</div>
                                <div class="text-sm">Try adjusting your filters or search criteria</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($uploads->hasPages())
            <div class="bg-white px-6 py-3 border-t border-gray-200">
                {{ $uploads->links() }}
            </div>
        @endif
    </div>

    {{-- Upload Details Modal --}}
    @if($showDetailsModal && $selectedUpload)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeDetailsModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white px-6 pt-6 pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">
                                Upload Details
                            </h3>
                            <button wire:click="closeDetailsModal"
                                    class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Upload ID</label>
                                    <div class="text-sm text-gray-900">{{ $selectedUpload->id }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        @if($selectedUpload->status === 'completed') bg-green-100 text-green-800
                                        @elseif($selectedUpload->status === 'failed') bg-red-100 text-red-800
                                        @elseif($selectedUpload->status === 'processing') bg-blue-100 text-blue-800
                                        @elseif($selectedUpload->status === 'queued') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($selectedUpload->status) }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">User</label>
                                    <div class="text-sm text-gray-900">{{ $selectedUpload->user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $selectedUpload->user->email }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">File Size</label>
                                    <div class="text-sm text-gray-900">{{ $selectedUpload->formatted_size }}</div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Filename</label>
                                <div class="text-sm text-gray-900 break-words">{{ $selectedUpload->original_name }}</div>
                            </div>

                            @if($selectedUpload->rows_count)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Row Count</label>
                                    <div class="text-sm text-gray-900">{{ number_format($selectedUpload->rows_count) }}</div>
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Created At</label>
                                    <div class="text-sm text-gray-900">{{ $selectedUpload->created_at->format('Y-m-d H:i:s') }}</div>
                                </div>
                                @if($selectedUpload->processed_at)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Processed At</label>
                                        <div class="text-sm text-gray-900">{{ $selectedUpload->processed_at->format('Y-m-d H:i:s') }}</div>
                                    </div>
                                @endif
                            </div>

                            @if($selectedUpload->failure_reason)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Failure Reason</label>
                                    <div class="text-sm text-red-600 bg-red-50 p-2 rounded">
                                        {{ $selectedUpload->failure_reason }}
                                    </div>
                                </div>
                            @endif

                            {{-- Upload Metrics if available --}}
                            @if($selectedUpload->uploadMetric)
                                <div class="border-t border-gray-200 pt-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Processing Metrics</h4>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        @if($selectedUpload->uploadMetric->processing_duration_seconds)
                                            <div>
                                                <span class="text-gray-600">Duration:</span>
                                                <span class="text-gray-900">{{ $selectedUpload->uploadMetric->processing_duration_seconds }}s</span>
                                            </div>
                                        @endif
                                        @if($selectedUpload->uploadMetric->credits_consumed)
                                            <div>
                                                <span class="text-gray-600">Credits:</span>
                                                <span class="text-gray-900">{{ $selectedUpload->uploadMetric->credits_consumed }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3">
                        @if($selectedUpload->status === 'failed')
                            <button wire:click="retryUpload({{ $selectedUpload->id }})"
                                    class="px-4 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700 transition-colors">
                                Retry Upload
                            </button>
                        @endif
                        <button wire:click="downloadOriginalFile({{ $selectedUpload->id }})"
                                class="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors">
                            Download Original
                        </button>
                        @if($selectedUpload->transformed_path && $selectedUpload->status === 'completed')
                            <button wire:click="downloadTransformedFile({{ $selectedUpload->id }})"
                                    class="px-4 py-2 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 transition-colors">
                                Download Result
                            </button>
                        @endif
                        <button wire:click="closeDetailsModal"
                                class="px-4 py-2 bg-gray-300 text-gray-700 text-sm rounded hover:bg-gray-400 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

@script
<script>
// Handle bulk operation completion
$wire.on('bulk-operation-complete', (data) => {
    const operation = data[0].operation;
    const result = data[0].result;

    // Show detailed result notification
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-blue-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
    notification.innerHTML = `
        <div class="flex items-start space-x-2">
            <i class="fas fa-info-circle mt-1"></i>
            <div class="flex-1">
                <h4 class="font-medium">Bulk ${operation} completed</h4>
                <div class="text-sm mt-1">
                    <div>✓ Processed: ${result.processed_count}</div>
                    ${result.skipped_count > 0 ? `<div>⊝ Skipped: ${result.skipped_count}</div>` : ''}
                    ${result.failed_count > 0 ? `<div>✗ Failed: ${result.failed_count}</div>` : ''}
                </div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()"
                    class="text-blue-200 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.body.appendChild(notification);

    // Auto-remove notification after 10 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 10000);
});

// Handle instant downloads
$wire.on('instant-download', (data) => {
    window.open(data[0].url, '_blank');
});

// Handle export generation with signed URLs
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
