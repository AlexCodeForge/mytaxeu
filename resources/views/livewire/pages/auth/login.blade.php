<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-gray-900 mb-2">
            <i class="fas fa-sign-in-alt text-primary mr-3"></i>Iniciar Sesión
        </h1>
        <p class="text-gray-600">Accede a tu panel de gestión fiscal</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form wire:submit.prevent="login" class="space-y-6">

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-envelope text-primary mr-2"></i>Correo Electrónico
            </label>
            <input wire:model="form.email"
                   id="email"
                   type="email"
                   name="email"
                   required
                   autofocus
                   autocomplete="username"
                   placeholder="tu@email.com"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white/90 backdrop-blur-sm text-gray-900 placeholder-gray-500" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-lock text-primary mr-2"></i>Contraseña
            </label>
            <input wire:model="form.password"
                   id="password"
                   type="password"
                   name="password"
                   required
                   autocomplete="current-password"
                   placeholder="••••••••"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white/90 backdrop-blur-sm text-gray-900 placeholder-gray-500" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember"
                       id="remember"
                       type="checkbox"
                       class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary focus:ring-2"
                       name="remember">
                <span class="ml-2 text-sm text-gray-600">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   wire:navigate
                   class="text-sm text-primary hover:text-blue-800 font-medium transition-colors">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <!-- Login Button -->
                <button type="submit"
                class="w-full bg-yellow-400 hover:bg-yellow-300 text-black font-bold py-3 px-4 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center">
            <i class="fas fa-sign-in-alt mr-2"></i>
            <span wire:loading.remove wire:target="login">Iniciar Sesión</span>
            <span wire:loading wire:target="login" class="flex items-center">
                <i class="fas fa-spinner fa-spin mr-2"></i>Iniciando sesión...
            </span>
        </button>

        <!-- Register Link -->
        <div class="text-center pt-4 border-t border-gray-200">
            <p class="text-gray-600 text-sm mb-3">¿No tienes una cuenta?</p>
            <a href="{{ route('register') }}"
               wire:navigate
               class="inline-flex items-center px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-user-plus mr-2"></i>Crear Cuenta
            </a>
        </div>
    </form>
</div>
