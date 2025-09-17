<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-gray-900 mb-2">
            <i class="fas fa-envelope-open-text text-primary mr-3"></i>Verificar Email
        </h1>
        <p class="text-gray-600">Confirma tu dirección de correo para activar tu cuenta</p>
    </div>

    <!-- Main Content -->
    <div class="space-y-6">
        <!-- Verification Message -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 text-center">
            <div class="mb-4">
                <i class="fas fa-paper-plane text-blue-500 text-4xl mb-3"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">¡Gracias por registrarte!</h3>
            <p class="text-gray-700 text-sm leading-relaxed">
                Antes de comenzar, por favor verifica tu dirección de correo electrónico haciendo clic en el enlace que te acabamos de enviar.
                Si no recibiste el email, con gusto te enviaremos otro.
            </p>
        </div>

        <!-- Success Message -->
        @if (session('status') == 'verification-link-sent')
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
                <div class="flex items-center justify-center mb-2">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-2"></i>
                    <span class="font-semibold text-green-800">¡Email Enviado!</span>
                </div>
                <p class="text-green-700 text-sm">
                    Se ha enviado un nuevo enlace de verificación a tu dirección de correo electrónico.
                </p>
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="space-y-4">
            <!-- Resend Verification Button -->
            <button wire:click="sendVerification"
                    class="w-full bg-yellow-400 hover:bg-yellow-300 text-black font-bold py-3 px-4 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center">
                <i class="fas fa-paper-plane mr-2"></i>
                <span wire:loading.remove wire:target="sendVerification">Reenviar Email de Verificación</span>
                <span wire:loading wire:target="sendVerification" class="flex items-center">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                </span>
            </button>

            <!-- Logout Button -->
            <button wire:click="logout"
                    class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-4 rounded-xl transition-all duration-200 flex items-center justify-center">
                <i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión
            </button>
        </div>

        <!-- Help Text -->
        <div class="text-center pt-4 border-t border-gray-200">
            <p class="text-gray-500 text-xs">
                ¿No recibes el email? Revisa tu carpeta de spam o contacta con nuestro soporte.
            </p>
        </div>
    </div>
</div>
