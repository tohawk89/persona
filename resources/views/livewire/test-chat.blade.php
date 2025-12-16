<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <!-- Header with Title and Actions -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-bold">Test Chat</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $persona->name }} — Sandbox Mode</p>
                    </div>
                    <button
                        wire:click="clearChat"
                        wire:confirm="Are you sure you want to clear the chat history?"
                        class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition duration-150 ease-in-out shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Clear Chat
                    </button>
                </div>

                <!-- Compact Info Banner -->
                <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Sandbox Mode Active</p>
                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">Messages in this chat are not saved to the database. Memory tags are loaded but updates won't persist.</p>
                        </div>
                    </div>
                </div>

        <!-- Chat Container -->
        <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                <!-- Chat Messages Area -->
                <div
                    id="chat-container"
                    class="h-[500px] overflow-y-auto p-6 space-y-4 bg-gray-50 dark:bg-gray-900"
                x-data="{ scrollToBottom() { $el.scrollTop = $el.scrollHeight; } }"
                x-init="scrollToBottom()"
                @chat-message-sent.window="setTimeout(() => scrollToBottom(), 100)">

                @if(empty($chatHistory))
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center text-gray-400 dark:text-gray-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p class="text-lg font-medium text-gray-900 dark:text-gray-100">No messages yet</p>
                            <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Start a conversation to test your persona!</p>
                        </div>
                    </div>
                @else
                    @foreach($chatHistory as $message)
                        @if($message['role'] === 'system')
                            <!-- System Message (Center - Info) -->
                            <div class="flex justify-center">
                                <div class="max-w-[90%] bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 rounded-lg px-4 py-3 shadow-sm">
                                    <p class="text-sm text-blue-900 dark:text-blue-100 whitespace-pre-wrap font-medium">{{ $message['content'] }}</p>
                                    <p class="text-xs text-blue-600 dark:text-blue-300 mt-1 text-center">{{ $message['timestamp'] }}</p>
                                </div>
                            </div>
                        @elseif($message['role'] === 'user')
                            <!-- User Message (Right - Indigo) -->
                            <div class="flex justify-end">
                                <div class="max-w-[70%]">
                                    <div class="bg-indigo-600 dark:bg-indigo-500 rounded-lg rounded-tr-none px-4 py-3 shadow-sm">
                                        <p class="text-sm text-white whitespace-pre-wrap">{{ $message['content'] }}</p>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 text-right">{{ $message['timestamp'] }}</p>
                                </div>
                            </div>
                        @else
                            <!-- Bot Message (Left - White/Gray) -->
                            <div class="flex justify-start">
                                <div class="max-w-[70%]">
                                    <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg rounded-tl-none px-4 py-3 shadow-sm">
                                        @php
                                            $content = $message['content'];
                                            // Check if content contains image tag
                                            preg_match_all('/\[IMAGE:\s*(.+?)\]/', $content, $imageMatches);
                                            // Check if content contains audio tag
                                            preg_match_all('/\[AUDIO:\s*(.+?)\]/', $content, $audioMatches);
                                            // Remove image and audio tags from text
                                            $textContent = preg_replace('/\[IMAGE:\s*.+?\]/', '', $content);
                                            $textContent = preg_replace('/\[AUDIO:\s*.+?\]/', '', $textContent);
                                        @endphp

                                        @if(trim($textContent))
                                            <p class="text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap">{{ trim($textContent) }}</p>
                                        @endif

                                        @if(!empty($imageMatches[1]))
                                            @foreach($imageMatches[1] as $imageUrl)
                                                <img src="{{ trim($imageUrl) }}"
                                                     alt="Generated image"
                                                     class="mt-2 rounded-lg max-w-full h-auto shadow-md"
                                                     style="max-height: 400px; object-fit: contain;">
                                            @endforeach
                                        @endif

                                        @if(!empty($audioMatches[1]))
                                            @foreach($audioMatches[1] as $audioUrl)
                                                <div class="mt-2 bg-gray-50 dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg p-3">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"></path>
                                                        </svg>
                                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-200">Voice Note</span>
                                                    </div>
                                                    <audio controls class="w-full" style="height: 32px;">
                                                        <source src="{{ trim($audioUrl) }}" type="audio/mpeg">
                                                        Your browser does not support the audio element.
                                                    </audio>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $message['timestamp'] }}</p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif

                <!-- Loading Indicator -->
                @if($loading)
                    <div class="flex justify-start">
                        <div class="max-w-[70%]">
                            <div class="bg-white border border-gray-200 rounded-lg rounded-tl-none px-4 py-3 shadow-sm">
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Input Area -->
            <div class="border-t border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
                <form wire:submit.prevent="sendMessage" class="flex items-end space-x-3">
                    <div class="flex-1">
                        <textarea
                            wire:model.live="inputMessage"
                            @keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage(); }"
                            rows="2"
                            placeholder="Type your message... (Enter to send, Shift+Enter for new line)"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-indigo-500 dark:focus:ring-indigo-400 focus:border-transparent resize-none placeholder-gray-400 dark:placeholder-gray-500"
                            @if($loading) disabled @endif></textarea>
                        @error('inputMessage')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600 text-white rounded-lg transition duration-150 ease-in-out disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center justify-center space-x-2 min-w-[120px] font-medium shadow-sm">
                        <span wire:loading.remove wire:target="sendMessage" class="flex items-center space-x-2">
                            <span>Send</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </span>
                        <span wire:loading wire:target="sendMessage" class="flex items-center space-x-2">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Sending...</span>
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Testing Tips -->
                <div class="mt-6 bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-700 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-300 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-indigo-900 dark:text-indigo-200 text-sm mb-2">💡 Testing Tips</h3>
                            <ul class="text-sm text-indigo-800 dark:text-indigo-300 space-y-1.5">
                                <li class="flex items-start">
                                    <span class="mr-2">•</span>
                                    <span>Test different conversation topics to validate persona behavior</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="mr-2">•</span>
                                    <span>Verify system prompt adherence and personality consistency</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="mr-2">•</span>
                                    <span>Memory tags load from database but changes won't persist</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="mr-2">•</span>
                                    <span>Use "Clear Chat" button to reset and start fresh</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
