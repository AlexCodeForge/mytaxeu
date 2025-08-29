<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

class EditCreditsModal extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;
    public ?User $user = null;
    public int $creditsChange = 0;
    public string $operation = 'add'; // add or subtract

    protected array $rules = [
        'creditsChange' => 'required|integer|min:1',
        'operation' => 'required|in:add,subtract',
    ];

    protected array $messages = [
        'creditsChange.required' => 'La cantidad de créditos es obligatoria.',
        'creditsChange.integer' => 'La cantidad debe ser un número entero.',
        'creditsChange.min' => 'La cantidad debe ser mayor a 0.',
        'operation.required' => 'La operación es obligatoria.',
        'operation.in' => 'La operación debe ser agregar o quitar.',
    ];

    #[On('open-edit-credits-modal')]
    public function openModal(int $userId): void
    {
        $this->authorize('manage-users');

        $this->user = User::findOrFail($userId);
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->creditsChange = 0;
        $this->operation = 'add';
        $this->resetErrorBag();
    }

    public function updateCredits(): void
    {
        $this->authorize('manage-users');

        $this->validate();

        if (! $this->user) {
            $this->addError('user', 'Usuario no encontrado.');
            return;
        }

        $newCredits = $this->operation === 'add'
            ? $this->user->credits + $this->creditsChange
            : $this->user->credits - $this->creditsChange;

        // Prevent negative credits
        if ($newCredits < 0) {
            $this->addError('creditsChange', 'No se pueden quitar más créditos de los disponibles.');
            return;
        }

        $this->user->update(['credits' => $newCredits]);

        // Log the credit change (could be enhanced with an audit table later)
        logger()->info('Admin credit change', [
            'admin_id' => auth()->id(),
            'user_id' => $this->user->id,
            'operation' => $this->operation,
            'amount' => $this->creditsChange,
            'old_credits' => $this->user->credits - ($this->operation === 'add' ? $this->creditsChange : -$this->creditsChange),
            'new_credits' => $newCredits,
        ]);

        $this->dispatch('credits-updated');
        $this->dispatch('flash-message', [
            'type' => 'success',
            'message' => "Créditos actualizados correctamente para {$this->user->name}."
        ]);

        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.admin.users.edit-credits-modal');
    }
}
