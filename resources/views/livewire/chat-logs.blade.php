<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h2 class="text-2xl font-bold mb-6">Chat Logs</h2>

                @if (!$messages->count() && !Auth::user()->persona)
                    <div class="mb-6 p-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-center">
                        <p class="text-green-700 dark:text-green-300 mb-3">
                            No persona configured. Please set up your persona first to view chat logs.
                        </p>
                        <a href="{{ route('persona.manager') }}" class="inline-block px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                            Configure Persona →
                        </a>
                    </div>
                @endif

                <!-- Chat Messages -->
                <div class="space-y-4 max-h-[600px] overflow-y-auto">
                    @forelse($messages as $message)
                        <div class="flex {{ $message->sender_type === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[70%] {{ $message->sender_type === 'user' ? 'order-2' : 'order-1' }}">
                                <!-- Message Bubble -->
                                <div class="rounded-lg px-4 py-3 {{ $message->sender_type === 'user'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">
                                    <p class="text-sm whitespace-pre-wrap">{{ $message->content }}</p>
                                </div>

                                <!-- Timestamp -->
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 {{ $message->sender_type === 'user' ? 'text-right' : 'text-left' }}">
                                    {{ $message->created_at->format('M d, Y h:i A') }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <p class="text-lg">No chat history yet.</p>
                            <p class="text-sm mt-2">Messages will appear here once your persona starts interacting via Telegram.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
