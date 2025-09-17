<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                // Automatically log in the user after password reset
                Auth::login($user);
            }
        );

        // If the password was successfully reset, we will redirect the user to
        // the dashboard since they are now authenticated. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', 'Contraseña restablecida exitosamente. ¡Bienvenido de vuelta!');

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-gray-900 mb-2">
            <i class="fas fa-key text-primary mr-3"></i>Restablecer Contraseña
        </h1>
        <p class="text-gray-600">Crea una nueva contraseña para tu cuenta de MyTaxEU</p>
    </div>

    <form wire:submit.prevent="resetPassword" class="space-y-6">
        <!-- Hidden Email Field -->
        <input type="hidden" wire:model="email" />

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-lock text-primary mr-2"></i>Nueva Contraseña
            </label>
            <input wire:model="password"
                   id="password"
                   type="password"
                   name="password"
                   required
                   autofocus
                   autocomplete="new-password"
                   placeholder="••••••••"
                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-200 bg-white/90 backdrop-blur-sm text-gray-900 placeholder-gray-500" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-lock text-primary mr-2"></i>Confirmar Nueva Contraseña
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

        <!-- Reset Password Button -->
        <button type="submit"
                class="w-full bg-yellow-400 hover:bg-yellow-300 text-black font-bold py-3 px-4 rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center">
            <i class="fas fa-key mr-2"></i>
            <span wire:loading.remove wire:target="resetPassword">Restablecer Contraseña</span>
            <span wire:loading wire:target="resetPassword" class="flex items-center">
                <i class="fas fa-spinner fa-spin mr-2"></i>Restableciendo...
            </span>
        </button>

        <!-- Back to Login Link -->
        <div class="text-center pt-4 border-t border-gray-200">
            <p class="text-gray-600 text-sm mb-3">¿Recordaste tu contraseña?</p>
            <a href="{{ route('login') }}"
               wire:navigate
               class="inline-flex items-center px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-sign-in-alt mr-2"></i>Volver al Inicio de Sesión
            </a>
        </div>
    </form>
</div>
