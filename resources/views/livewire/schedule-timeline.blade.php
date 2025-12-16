<div class="py-12" wire:poll.3s>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold">Schedule Timeline</h2>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        🔄 Auto-refreshing every 3s
                    </span>
                </div>

                @if (session()->has('success'))
                    <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                <!-- Events List -->
                <div class="space-y-3">
                    @forelse($events as $event)
                        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-sm overflow-hidden border
                            {{ $event->status === 'sent' ? 'border-green-300 dark:border-green-600' :
                               ($event->status === 'pending' ? 'border-blue-300 dark:border-blue-600' : 'border-gray-300 dark:border-gray-600') }}">

                            <!-- Header Row -->
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-600">
                                <div class="flex items-center gap-3">
                                    <!-- Time -->
                                    <div class="flex flex-col">
                                        <span class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                            {{ $event->scheduled_at->format('h:i A') }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $event->scheduled_at->format('D, M j') }}
                                        </span>
                                    </div>

                                    <!-- Badges -->
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-1 text-xs font-medium rounded-md
                                            {{ $event->type === 'text' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300' }}">
                                            {{ $event->type === 'image_generation' ? '🖼️ Image' : '💬 Text' }}
                                        </span>
                                        <span class="px-2.5 py-1 text-xs font-medium rounded-md
                                            {{ $event->status === 'sent' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' :
                                               ($event->status === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300' :
                                               ($event->status === 'cancelled' ? 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300')) }}">
                                            {{ ucfirst($event->status) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Relative Time & Action -->
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $event->scheduled_at->diffForHumans() }}
                                    </span>
                                    @if($event->status === 'pending')
                                        <button
                                            wire:click="sendNow({{ $event->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="sendNow({{ $event->id }})"
                                            class="px-3 py-1.5 text-xs font-medium bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md transition-colors"
                                        >
                                            <span wire:loading.remove wire:target="sendNow({{ $event->id }})">📤 Send Now</span>
                                            <span wire:loading wire:target="sendNow({{ $event->id }})">⏳ Sending...</span>
                                        </button>
                                        <button
                                            wire:click="cancelEvent({{ $event->id }})"
                                            onclick="return confirm('Cancel this event?')"
                                            class="px-3 py-1.5 text-xs font-medium bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors"
                                        >
                                            Cancel
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-4">
                                <p class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed">
                                    {{ $event->context_prompt }}
                                </p>
                            </div>

                            <!-- Footer -->
                            <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                        <span>📅 Created: {{ $event->created_at->format('M j, h:i A') }}</span>
                                        @if($event->updated_at != $event->created_at)
                                            <span>•</span>
                                            <span>✏️ Updated: {{ $event->updated_at->format('M j, h:i A') }}</span>
                                        @endif
                                    </div>
                                    <button
                                        wire:click="testInChat({{ $event->id }})"
                                        class="px-3 py-1.5 text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors"
                                    >
                                        🧪 Test in Chat
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-16 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="text-4xl mb-3">📅</div>
                            <p class="text-lg font-medium text-gray-700 dark:text-gray-300">No events scheduled</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                Use the "Trigger Wake-Up Routine" from the Dashboard to generate events.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
