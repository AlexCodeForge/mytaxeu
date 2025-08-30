@section('page_title', 'Monitoreo de Trabajos')

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-gray-900">Monitoreo de Trabajos</h1>
        <p class="mt-1 text-sm text-gray-600">Supervisa el estado de todos los trabajos de procesamiento CSV en tiempo real.</p>
    </div>

    {{-- Job Monitoring Component --}}
    <livewire:admin.admin-job-monitor />
</div>
