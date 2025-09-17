<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-gray-900 mb-2">
            <i class="fas fa-user-plus text-primary mr-3"></i>Crear Cuenta
        </h1>
        <p class="text-gray-600">Únete a MyTaxEU y automatiza tu gestión fiscal</p>
    </div>

    <form wire:submit.prevent="register" class="space-y-6">
        <!-- Name -->
        <div>
            <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-user text-primary mr-2"></i>Nombre Completo
            </label>
            <input wire:model="name"
                   id="name"
                   type="text"
                   name="name"
                   required
                   autofocus
                   autocomplete="name"
                   placeholder="Tu nombre completo"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white/90 backdrop-blur-sm text-gray-900 placeholder-gray-500" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-envelope text-primary mr-2"></i>Correo Electrónico
            </label>
            <input wire:model="email"
                   id="email"
                   type="email"
                   name="email"
                   required
                   autocomplete="username"
                   placeholder="tu@email.com"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white/90 backdrop-blur-sm text-gray-900 placeholder-gray-500" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-lock text-primary mr-2"></i>Contraseña
            </label>
            <input wire:model="password"
                   id="password"
                   type="password"
                   name="password"
                   required
                   autocomplete="new-password"
                   placeholder="••••••••"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white/90 backdrop-blur-sm text-gray-900 placeholder-gray-500" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-lock text-primary mr-2"></i>Confirmar Contraseña
            </label>
            <input wire:model="password_confirmation"
                   id="password_confirmation"
                   type="password"
                   name="password_confirmation"
                   required
                   autocomplete="new-password"
                   placeholder="••••••••"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white/90 backdrop-blur-sm text-gray-900 placeholder-gray-500" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- Register Button -->
                <button type="submit"
                class="w-full bg-yellow-400 hover:bg-yellow-300 text-black font-bold py-3 px-4 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center">
            <i class="fas fa-user-plus mr-2"></i>
            <span wire:loading.remove wire:target="register">Crear Cuenta</span>
            <span wire:loading wire:target="register" class="flex items-center">
                <i class="fas fa-spinner fa-spin mr-2"></i>Creando cuenta...
            </span>
        </button>

        <!-- Terms Notice -->
        <div class="text-center text-xs text-gray-500 mb-4">
            Al crear una cuenta, aceptas nuestros
            <a href="#" class="text-primary hover:text-blue-800 underline">Términos de Servicio</a>
            y
            <a href="#" class="text-primary hover:text-blue-800 underline">Política de Privacidad</a>
        </div>

        <!-- Login Link -->
        <div class="text-center pt-4 border-t border-gray-200">
            <p class="text-gray-600 text-sm mb-3">¿Ya tienes una cuenta?</p>
            <a href="{{ route('login') }}"
               wire:navigate
               class="inline-flex items-center px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
            </a>
        </div>
    </form>
</div>
