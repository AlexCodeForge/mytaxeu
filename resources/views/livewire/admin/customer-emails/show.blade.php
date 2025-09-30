<div class="p-6 bg-white min-h-screen">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center space-x-2 mb-2">
                <a href="{{ route('admin.customer-emails.index') }}"
                   class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-2"></i>Volver a emails
                </a>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $conversation->subject }}</h1>
            <p class="text-gray-600 mt-1">Conversación con {{ $conversation->customer_name ?: $conversation->customer_email }}</p>
        </div>
        <div class="flex space-x-2">
            @if(!$conversation->assigned_to)
                <button wire:click="assignToMe"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    Asignarme
                </button>
            @endif

            <button wire:click="toggleReply"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                <i class="fas fa-reply mr-2"></i>Responder
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Conversation Thread -->
        <div class="lg:col-span-2">
            <!-- Reply Form -->
            @if($isReplying)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Responder al cliente</h3>

                    <form wire:submit="sendReply">
                        <div class="mb-4">
                            <label for="replySubject" class="block text-sm font-medium text-gray-700 mb-2">Asunto</label>
                            <input type="text" id="replySubject" wire:model="replySubject"
                                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            @error('replySubject') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="replyBody" class="block text-sm font-medium text-gray-700 mb-2">Mensaje</label>
                            <textarea id="replyBody" wire:model="replyBody" rows="6"
                                      class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Escribe tu respuesta aquí..."></textarea>
                            @error('replyBody') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex space-x-3">
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors disabled:opacity-50"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove>
                                    <i class="fas fa-paper-plane mr-2"></i>Enviar Respuesta
                                </span>
                                <span wire:loading>
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Enviando...
                                </span>
                            </button>

                            <button type="button" wire:click="toggleReply"
                                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            <!-- Messages -->
            <div class="space-y-4">
                @foreach($conversation->messages as $message)
                    <div class="border rounded-lg {{ $message->sender_type === 'admin' ? 'bg-blue-50 border-blue-200' : 'bg-white border-gray-200' }}">
                        <div class="p-4">
                            <!-- Message Header -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center space-x-2">
                                        @if($message->sender_type === 'admin')
                                            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user-tie text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $message->sender_name }}</p>
                                                <p class="text-sm text-gray-500">Administrador</p>
                                            </div>
                                        @else
                                            <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-white text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $message->sender_name ?: $message->sender_email }}</p>
                                                <p class="text-sm text-gray-500">Cliente</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-sm text-gray-500">
                                    {{ $message->created_at->format('d/m/Y H:i') }}
                                </div>
                            </div>

                            <!-- Message Subject (if different from conversation) -->
                            @if($message->subject !== $conversation->subject)
                                <div class="mb-3">
                                    <p class="font-medium text-gray-700">{{ $message->subject }}</p>
                                </div>
                            @endif

                            <!-- Message Content -->
                            <div class="prose max-w-none text-gray-700">
                                @if($message->sender_type === 'customer')
                                    {{-- For customer messages, show cleaned body without quoted text --}}
                                    @if($message->body_html)
                                        {!! $message->clean_body !!}
                                    @else
                                        {!! nl2br(e($message->clean_body)) !!}
                                    @endif
                                @else
                                    {{-- For admin messages, show full body --}}
                                    @if($message->body_html)
                                        {!! $message->body_html !!}
                                    @else
                                        {!! nl2br(e($message->body_text)) !!}
                                    @endif
                                @endif
                            </div>

                            <!-- Attachments -->
                            @if($message->attachments->count() > 0)
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <p class="text-sm font-medium text-gray-700 mb-2">Adjuntos:</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($message->attachments as $attachment)
                                            <div class="flex items-center space-x-2 bg-gray-100 rounded-lg px-3 py-2">
                                                <i class="fas fa-paperclip text-gray-500"></i>
                                                <span class="text-sm text-gray-700">{{ $attachment->original_name }}</span>
                                                <span class="text-xs text-gray-500">({{ $attachment->formatted_file_size }})</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Conversation Info -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Información de la Conversación</h3>

                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Estado</label>
                        <select wire:change="updateStatus($event.target.value)"
                                class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="open" {{ $conversation->status === 'open' ? 'selected' : '' }}>Abierto</option>
                            <option value="in_progress" {{ $conversation->status === 'in_progress' ? 'selected' : '' }}>En Progreso</option>
                            <option value="resolved" {{ $conversation->status === 'resolved' ? 'selected' : '' }}>Resuelto</option>
                            <option value="closed" {{ $conversation->status === 'closed' ? 'selected' : '' }}>Cerrado</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-500">Prioridad</label>
                        <span class="block mt-1 px-2 py-1 text-xs font-medium rounded-full w-fit
                            @if($conversation->priority === 'urgent') bg-red-100 text-red-800
                            @elseif($conversation->priority === 'high') bg-orange-100 text-orange-800
                            @elseif($conversation->priority === 'low') bg-gray-100 text-gray-800
                            @else bg-blue-100 text-blue-800 @endif">
                            {{ ucfirst($conversation->priority) }}
                        </span>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-500">Cliente</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $conversation->customer_email }}</p>
                        @if($conversation->customer_name)
                            <p class="text-sm text-gray-500">{{ $conversation->customer_name }}</p>
                        @endif
                    </div>

                    @if($conversation->user)
                        <div>
                            <label class="text-sm font-medium text-gray-500">Usuario Registrado</label>
                            <p class="mt-1 text-sm text-gray-900">{{ $conversation->user->name }}</p>
                        </div>
                    @endif

                    <div>
                        <label class="text-sm font-medium text-gray-500">Asignado a</label>
                        <p class="mt-1 text-sm text-gray-900">
                            {{ $conversation->assignedAdmin?->name ?: 'Sin asignar' }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-500">Último mensaje</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $conversation->last_message_at->diffForHumans() }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-500">Creado</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $conversation->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <!-- Message Count -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas</h3>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Total de mensajes</span>
                        <span class="text-sm font-medium text-gray-900">{{ $conversation->messages->count() }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Del cliente</span>
                        <span class="text-sm font-medium text-gray-900">{{ $conversation->messages->where('sender_type', 'customer')->count() }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Del administrador</span>
                        <span class="text-sm font-medium text-gray-900">{{ $conversation->messages->where('sender_type', 'admin')->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
