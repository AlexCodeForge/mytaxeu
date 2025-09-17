<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-gray-900 mb-2">
            <i class="fas fa-key text-primary mr-3"></i>Recuperar Contraseña
        </h1>
        <p class="text-gray-600">Te enviaremos un enlace para restablecer tu contraseña</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form wire:submit.prevent="sendPasswordResetLink" class="space-y-6">
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
                   autofocus
                   placeholder="tu@email.com"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white/90 backdrop-blur-sm text-gray-900 placeholder-gray-500" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Send Link Button -->
                <button type="submit"
                class="w-full bg-yellow-400 hover:bg-yellow-300 text-black font-bold py-3 px-4 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center">
            <i class="fas fa-paper-plane mr-2"></i>
            <span wire:loading.remove wire:target="sendPasswordResetLink">Enviar Enlace de Recuperación</span>
            <span wire:loading wire:target="sendPasswordResetLink" class="flex items-center">
                <i class="fas fa-spinner fa-spin mr-2"></i>Enviando enlace...
            </span>
        </button>

        <!-- Back to Login -->
        <div class="text-center pt-4 border-t border-gray-200">
            <p class="text-gray-600 text-sm mb-3">¿Recordaste tu contraseña?</p>
            <a href="{{ route('login') }}"
               wire:navigate
               class="inline-flex items-center px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-sign-in-alt mr-2"></i>Volver al Login
            </a>
        </div>
    </form>
</div>
