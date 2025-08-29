@section('page_title', 'Dashboard')

<div class="space-y-6">
    <!-- Welcome Section -->
    <div class="glass-white p-6 rounded-xl shadow-lg">
        <h1 class="text-2xl font-bold text-gray-900">¡Bienvenido, {{ auth()->user()->name }}!</h1>
        <p class="text-gray-600 mt-1">Gestiona tus archivos CSV y consulta el estado de tus procesamientos.</p>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <a href="{{ route('uploads.create') }}" class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all group">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                    <i class="fas fa-upload text-primary text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900">Subir CSV</h3>
                    <p class="text-sm text-gray-600">Sube un nuevo archivo para procesar</p>
                </div>
            </div>
        </a>

        <a href="{{ route('uploads.index') }}" class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all group">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                    <i class="fas fa-file-csv text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900">Mis Archivos</h3>
                    <p class="text-sm text-gray-600">Ver estado de tus archivos</p>
                </div>
            </div>
        </a>
    </div>

    <!-- Stats Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Archivos Subidos</p>
                    <p class="text-3xl font-bold text-primary">{{ number_format($stats['totalUploads']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Total de archivos</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-upload text-primary text-xl"></i>
                </div>
            </div>
        </div>

        <div class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Completados</p>
                    <p class="text-3xl font-bold text-primary">{{ number_format($stats['completedUploads']) }}</p>
                    <p class="text-xs text-green-600 flex items-center mt-1">
                        <i class="fas fa-check-circle mr-1"></i>
                        Procesados con éxito
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">En Proceso</p>
                    <p class="text-3xl font-bold text-primary">{{ number_format($stats['processingUploads']) }}</p>
                    <p class="text-xs text-orange-600 flex items-center mt-1">
                        <i class="fas fa-clock mr-1"></i>
                        Procesando ahora
                    </p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cog fa-spin text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Créditos</p>
                    <p class="text-3xl font-bold text-primary">{{ number_format($stats['credits']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Disponibles</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-coins text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Uploads Section -->
    <div class="glass-white p-6 rounded-xl shadow-lg">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Archivos Recientes</h3>
            <a href="{{ route('uploads.index') }}" class="text-primary hover:text-blue-700 text-sm font-medium">
                Ver todos <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        @if($recentUploads->count() > 0)
            <div class="space-y-3">
                @foreach($recentUploads as $upload)
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-csv text-blue-600"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">{{ $upload->original_name }}</p>
                                <p class="text-xs text-gray-500">{{ $upload->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $upload->status_color }}-100 text-{{ $upload->status_color }}-800">
                                {{ $upload->status_label }}
                            </span>
                            @if($upload->rows_count)
                                <span class="text-xs text-gray-500">{{ number_format($upload->rows_count) }} filas</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-csv text-gray-400 text-2xl"></i>
                </div>
                <h4 class="text-lg font-medium text-gray-900 mb-2">No hay archivos aún</h4>
                <p class="text-gray-600 mb-4">Comienza subiendo tu primer archivo CSV</p>
                <a href="{{ route('uploads.create') }}" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-upload mr-2"></i>
                    Subir CSV
                </a>
            </div>
        @endif
    </div>
</div>


