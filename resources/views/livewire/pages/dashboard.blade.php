@php($title = 'Dashboard')
@section('page_title', $title)

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Usuarios Activos</p>
                    <p class="text-3xl font-bold text-primary">1,247</p>
                    <p class="text-xs text-green-600 flex items-center mt-1">
                        <i class="fas fa-arrow-up mr-1"></i>
                        +12.5% este mes
                    </p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-primary text-xl"></i>
                </div>
            </div>
        </div>

        <div class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Ingresos Mensuales</p>
                    <p class="text-3xl font-bold text-primary">€89,340</p>
                    <p class="text-xs text-green-600 flex items-center mt-1">
                        <i class="fas fa-arrow-up mr-1"></i>
                        +28.4% este mes
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-euro-sign text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Transacciones</p>
                    <p class="text-3xl font-bold text-primary">45,230</p>
                    <p class="text-xs text-green-600 flex items-center mt-1">
                        <i class="fas fa-arrow-up mr-1"></i>
                        +15.2% este mes
                    </p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="glass-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Tasa de Éxito</p>
                    <p class="text-3xl font-bold text-primary">99.8%</p>
                    <p class="text-xs text-green-600 flex items-center mt-1">
                        <i class="fas fa-arrow-up mr-1"></i>
                        +0.3% este mes
                    </p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-8">
        <div class="glass-white p-6 rounded-xl shadow-lg min-h-72">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ingresos vs Gastos</h3>
            <div class="relative h-72">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="glass-white p-6 rounded-xl shadow-lg min-h-72">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Usuarios por Plan</h3>
            <div class="relative h-72">
                <canvas id="userPlanChart"></canvas>
            </div>
        </div>
    </div>
</div>


