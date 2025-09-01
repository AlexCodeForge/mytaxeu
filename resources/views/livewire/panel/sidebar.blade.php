<div class="h-full max-h-screen flex flex-col glass-white shadow-2xl overflow-hidden">
    <div class="p-6 border-b border-blue-200 flex-shrink-0">
        <div class="flex items-center">
            <div class="text-2xl font-bold text-primary">MyTaxEU</div>
            <div class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Admin</div>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto mt-6 min-h-0">
        <ul class="px-4 space-y-2 pb-20">
            @foreach ($links as $link)
                <li>
                    <a href="{{ route($link['route']) }}"
                       wire:navigate.hover
                       wire:current="sidebar-active"
                       class="flex items-center px-4 py-3 text-gray-700 rounded-lg transition-all hover:bg-blue-50">
                        <i class="fas {{ $link['icon'] }} mr-3"></i>
                        <span>{{ $link['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="absolute bottom-4 left-4 right-4 flex-shrink-0">
        <a href="{{ route('landing') }}" wire:navigate class="flex items-center justify-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-all">
            <i class="fas fa-arrow-left mr-2"></i>
            Volver al Sitio
        </a>
    </div>
</div>


