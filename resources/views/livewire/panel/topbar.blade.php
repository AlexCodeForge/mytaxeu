<div class="px-4 lg:px-6 py-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <button class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-md hover:bg-gray-50"
                    @click="mobileOpen = true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                    <path fill-rule="evenodd" d="M3.75 5.25a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5H4.5a.75.75 0 0 1-.75-.75Zm0 6a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5H4.5a.75.75 0 0 1-.75-.75Zm0 6a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5H4.5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd" />
                </svg>
            </button>
            <h1 class="text-lg font-semibold">@yield('page_title', 'Panel')</h1>
        </div>

        <div class="flex items-center gap-4">

            @auth
                <div class="relative">
                    <button class="flex items-center gap-2 px-3 py-1.5 rounded-md hover:bg-gray-50">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name ?? 'User') }}&background=1e40af&color=fff"
                             class="w-8 h-8 rounded-full" alt="avatar">
                        <span class="hidden sm:inline text-sm">{{ $user->name }}</span>
                    </button>
                </div>
            @endauth
        </div>
    </div>
</div>


