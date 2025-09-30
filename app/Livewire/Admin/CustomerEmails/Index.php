<?php

declare(strict_types=1);

namespace App\Livewire\Admin\CustomerEmails;

use App\Models\CustomerConversation;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.panel')]
#[Title('Gestión de Emails de Clientes')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $priorityFilter = 'all';
    public bool $showAssignedOnly = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'priorityFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        Log::info('CustomerEmails Index component mounted', [
            'admin_user' => auth()->id()
        ]);
    }

    public function render()
    {
        $conversations = CustomerConversation::query()
            ->with(['latestMessage', 'assignedAdmin'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('customer_email', 'like', '%' . $this->search . '%')
                      ->orWhere('subject', 'like', '%' . $this->search . '%')
                      ->orWhere('customer_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->priorityFilter !== 'all', function ($query) {
                $query->where('priority', $this->priorityFilter);
            })
            ->when($this->showAssignedOnly, function ($query) {
                $query->where('assigned_to', auth()->id());
            })
            ->orderBy('last_message_at', 'desc')
            ->paginate(15);

        return view('livewire.admin.customer-emails.index', [
            'conversations' => $conversations
        ]);
    }

    public function assignToMe(int $conversationId): void
    {
        Log::info('Admin assigning conversation to self', [
            'conversation_id' => $conversationId,
            'admin_user' => auth()->id()
        ]);

        CustomerConversation::findOrFail($conversationId)->update([
            'assigned_to' => auth()->id(),
            'status' => 'in_progress'
        ]);

        $this->dispatch('conversation-assigned');
        session()->flash('success', 'Conversación asignada correctamente');
    }

    public function updateStatus(int $conversationId, string $status): void
    {
        Log::info('Admin updating conversation status', [
            'conversation_id' => $conversationId,
            'new_status' => $status,
            'admin_user' => auth()->id()
        ]);

        CustomerConversation::findOrFail($conversationId)->updateStatus($status);
        $this->dispatch('status-updated');
        session()->flash('success', 'Estado actualizado correctamente');
    }

    // Reset pagination when search changes
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPriorityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedShowAssignedOnly(): void
    {
        $this->resetPage();
    }
}
