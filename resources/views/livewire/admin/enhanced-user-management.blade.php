<div class="p-6 bg-white min-h-screen">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Enhanced User Management</h1>
        <p class="text-gray-600">Manage users with advanced tracking and analytics</p>
    </div>

    {{-- Filters and Search --}}
    <div class="bg-white p-6 rounded-lg shadow border mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            {{-- Search --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Users</label>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Name or email..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-900">
            </div>

            {{-- Activity Filter --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Activity Status</label>
                <select wire:model.live="activityFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-900">
                    <option value="all">All Users</option>
                    <option value="active">Active Users</option>
                    <option value="inactive">Inactive Users</option>
                </select>
            </div>

            {{-- Date From --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Registered From</label>
                <input wire:model.live="dateFrom"
                       type="date"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-900">
            </div>

            {{-- Date To --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Registered To</label>
                <input wire:model.live="dateTo"
                       type="date"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-900">
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <button wire:click="clearFilters"
                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                    Clear Filters
                </button>

                <button wire:click="toggleBulkMode"
                        class="px-3 py-2 {{ $bulkMode ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700' }} text-white rounded-md transition-colors">
                    {{ $bulkMode ? 'Exit Bulk Mode' : 'Bulk Actions' }}
                </button>
            </div>

            @if ($bulkMode && !empty($selectedUsers))
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">
                        {{ count($selectedUsers) }} selected
                    </span>
                    <button wire:click="bulkSuspendUsers"
                            wire:confirm="Are you sure you want to suspend the selected users?"
                            class="px-3 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                        Suspend Selected
                    </button>
                    <button wire:click="bulkActivateUsers"
                            wire:confirm="Are you sure you want to activate the selected users?"
                            class="px-3 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors">
                        Activate Selected
                    </button>
                    <button wire:click="clearSelection"
                            class="px-3 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition-colors">
                        Clear Selection
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Users Table --}}
    <div class="bg-white rounded-lg shadow border overflow-hidden">
        @if ($users->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @if ($bulkMode)
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox"
                                           wire:click="selectAllUsers"
                                           class="rounded border-gray-300">
                                </th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                wire:click="sortBy('name')">
                                <div class="flex items-center space-x-1">
                                    <span>User</span>
                                    @if ($sortBy === 'name')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                wire:click="sortBy('uploads_count')">
                                <div class="flex items-center space-x-1">
                                    <span>Activity</span>
                                    @if ($sortBy === 'uploads_count')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                wire:click="sortBy('created_at')">
                                <div class="flex items-center space-x-1">
                                    <span>Registered</span>
                                    @if ($sortBy === 'created_at')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50 {{ $user->is_suspended ? 'bg-red-50' : '' }}">
                                @if ($bulkMode)
                                    <td class="px-6 py-4">
                                        <input type="checkbox"
                                               wire:click="toggleUserSelection({{ $user->id }})"
                                               @checked(in_array($user->id, $selectedUsers))
                                               class="rounded border-gray-300">
                                    </td>
                                @endif
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $user->name }}
                                                @if ($user->is_admin)
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        Admin
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($user->is_suspended)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-ban mr-1"></i>
                                            Suspended
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i>
                                            Active
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        {{ $user->uploads_count }} uploads
                                    </div>
                                    @if ($user->uploads->count() > 0)
                                        @php
                                            $completedCount = $user->uploads->where('status', 'completed')->count();
                                            $successRate = $user->uploads_count > 0 ? round(($completedCount / $user->uploads_count) * 100) : 0;
                                        @endphp
                                        <div class="text-sm text-gray-500">
                                            {{ $successRate }}% success rate
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            Last: {{ $user->uploads->first()?->created_at?->diffForHumans() ?? 'Never' }}
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-500">No activity</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $user->created_at->format('M j, Y') }}
                                    <div class="text-xs">{{ $user->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button wire:click="openUserProfile({{ $user->id }})"
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        @if (!$user->is_admin)
                                            @if ($user->is_suspended)
                                                <button wire:click="activateUser({{ $user->id }})"
                                                        wire:confirm="Are you sure you want to activate this user?"
                                                        class="text-green-600 hover:text-green-900"
                                                        title="Activate User">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            @else
                                                <button wire:click="suspendUser({{ $user->id }})"
                                                        wire:confirm="Are you sure you want to suspend this user?"
                                                        class="text-red-600 hover:text-red-900"
                                                        title="Suspend User">
                                                    <i class="fas fa-user-times"></i>
                                                </button>
                                            @endif
                                        @endif

                                        <button wire:click="exportUserActivity({{ $user->id }})"
                                                class="text-purple-600 hover:text-purple-900"
                                                title="Export User Data">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $users->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                <p class="text-gray-500">Try adjusting your search criteria or filters.</p>
                @if (!empty($search) || $activityFilter !== 'all' || !empty($dateFrom) || !empty($dateTo))
                    <button wire:click="clearFilters"
                            class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Clear All Filters
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- User Profile Modal --}}
    @if ($showUserModal && $selectedUser)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ activeTab: 'overview' }">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    {{-- Modal Header --}}
                    <div class="bg-white px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-12 w-12">
                                    <div class="h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-lg">
                                        {{ strtoupper(substr($selectedUser->name, 0, 1)) }}
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900">
                                        {{ $selectedUser->name }}
                                        @if ($selectedUser->is_admin)
                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                Admin
                                            </span>
                                        @endif
                                        @if ($selectedUser->is_suspended)
                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Suspended
                                            </span>
                                        @endif
                                    </h3>
                                    <p class="text-sm text-gray-500">{{ $selectedUser->email }}</p>
                                </div>
                            </div>
                            <button wire:click="closeUserModal"
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        {{-- Tabs --}}
                        <div class="mt-4">
                            <div class="border-b border-gray-200">
                                <nav class="-mb-px flex space-x-8">
                                    <button @click="activeTab = 'overview'"
                                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'overview' }"
                                            class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                        Overview
                                    </button>
                                    <button @click="activeTab = 'activity'"
                                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'activity' }"
                                            class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                        Activity Timeline
                                    </button>
                                    <button @click="activeTab = 'login'"
                                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'login' }"
                                            class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                        Login History
                                    </button>
                                </nav>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Content --}}
                    <div class="px-6 py-4 max-h-96 overflow-y-auto">
                        {{-- Overview Tab --}}
                        <div x-show="activeTab === 'overview'" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-blue-600">{{ $userStats['total_uploads'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">Total Uploads</div>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-green-600">{{ $userStats['success_rate'] ?? 0 }}%</div>
                                    <div class="text-sm text-gray-600">Success Rate</div>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-purple-600">{{ $userStats['total_size'] ?? '0 B' }}</div>
                                    <div class="text-sm text-gray-600">Total Storage</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-lg font-medium text-gray-900 mb-3">Account Information</h4>
                                    <dl class="space-y-2">
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Registration Date</dt>
                                            <dd class="text-sm text-gray-900">{{ $selectedUser->created_at->format('M j, Y') }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Days Active</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['days_since_registration'] ?? 0 }} days</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Engagement Rate</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['engagement_rate'] ?? 0 }}%</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Credits Consumed</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['total_credits'] ?? 0 }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div>
                                    <h4 class="text-lg font-medium text-gray-900 mb-3">Processing Statistics</h4>
                                    <dl class="space-y-2">
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Completed Uploads</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['completed_uploads'] ?? 0 }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Failed Uploads</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['failed_uploads'] ?? 0 }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Avg Processing Time</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['avg_processing_time'] ?? '0s' }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Last Activity</dt>
                                            <dd class="text-sm text-gray-900">
                                                {{ $userStats['last_activity'] ? \Carbon\Carbon::parse($userStats['last_activity'])->diffForHumans() : 'Never' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        {{-- Activity Timeline Tab --}}
                        <div x-show="activeTab === 'activity'" class="space-y-4">
                            <h4 class="text-lg font-medium text-gray-900">Recent Upload Activity</h4>
                            @if (!empty($userActivity))
                                <div class="space-y-3">
                                    @foreach ($userActivity as $activity)
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex-shrink-0">
                                                        @if ($activity['status'] === 'completed')
                                                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-check text-green-600 text-sm"></i>
                                                            </div>
                                                        @elseif ($activity['status'] === 'failed')
                                                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-times text-red-600 text-sm"></i>
                                                            </div>
                                                        @else
                                                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-clock text-yellow-600 text-sm"></i>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">{{ $activity['filename'] }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ \Carbon\Carbon::parse($activity['created_at'])->format('M j, Y g:i A') }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm text-gray-900">{{ $activity['size'] }}</div>
                                                    @if ($activity['credits_consumed'])
                                                        <div class="text-xs text-gray-500">{{ $activity['credits_consumed'] }} credits</div>
                                                    @endif
                                                </div>
                                            </div>
                                            @if ($activity['failure_reason'])
                                                <div class="mt-2 text-sm text-red-600">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    {{ $activity['failure_reason'] }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <i class="fas fa-file-upload text-gray-400 text-3xl mb-4"></i>
                                    <p class="text-gray-500">No upload activity found</p>
                                </div>
                            @endif
                        </div>

                        {{-- Login History Tab --}}
                        <div x-show="activeTab === 'login'" class="space-y-4">
                            <h4 class="text-lg font-medium text-gray-900">Login History</h4>
                            @if (!empty($userLoginHistory))
                                <div class="space-y-3">
                                    @foreach ($userLoginHistory as $login)
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                            <i class="fas fa-sign-in-alt text-blue-600 text-sm"></i>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">{{ $login['ip_address'] }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ \Carbon\Carbon::parse($login['logged_in_at'])->format('M j, Y g:i A') }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-xs text-gray-500 max-w-48 truncate">
                                                        {{ $login['user_agent'] }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <i class="fas fa-history text-gray-400 text-3xl mb-4"></i>
                                    <p class="text-gray-500">No login history found</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                @if (!$selectedUser->is_admin)
                                    @if ($selectedUser->is_suspended)
                                        <button wire:click="activateUser({{ $selectedUser->id }})"
                                                wire:confirm="Are you sure you want to activate this user?"
                                                class="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors">
                                            <i class="fas fa-user-check mr-1"></i>
                                            Activate User
                                        </button>
                                    @else
                                        <button wire:click="suspendUser({{ $selectedUser->id }})"
                                                wire:confirm="Are you sure you want to suspend this user?"
                                                class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                                            <i class="fas fa-user-times mr-1"></i>
                                            Suspend User
                                        </button>
                                    @endif
                                @endif

                                <button wire:click="exportUserActivity({{ $selectedUser->id }})"
                                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-download mr-1"></i>
                                    Export Data
                                </button>
                            </div>

                            <button wire:click="closeUserModal"
                                    class="px-4 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

@script
<script>
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
