<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- TEST MODE Banner -->
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg shadow-sm">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <p class="font-bold text-lg">🧪 TEST MODE - MESSAGES NOT SAVED</p>
                    <p class="text-sm">Use this sandbox to test your persona's responses without affecting the database.</p>
                </div>
            </div>
        </div>

        <!-- Persona Info Card -->
        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ $persona->name }}</h2>
                    <p class="text-sm text-gray-500 mt-1">Testing persona responses</p>
                </div>
                <button
                    wire:click="clearChat"
                    wire:confirm="Are you sure you want to clear the chat history?"
                    class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-150 ease-in-out">
                    Clear Chat
                </button>
            </div>
        </div>

        <!-- Chat Container -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <!-- Chat Messages Area -->
            <div
                id="chat-container"
                class="h-[500px] overflow-y-auto p-6 space-y-4 bg-gray-50"
                x-data="{ scrollToBottom() { $el.scrollTop = $el.scrollHeight; } }"
                x-init="scrollToBottom()"
                @chat-message-sent.window="setTimeout(() => scrollToBottom(), 100)">

                @if(empty($chatHistory))
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center text-gray-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p class="text-lg font-medium">No messages yet</p>
                            <p class="text-sm mt-2">Start a conversation to test your persona!</p>
                        </div>
                    </div>
                @else
                    @foreach($chatHistory as $message)
                        @if($message['role'] === 'system')
                            <!-- System Message (Center - Info) -->
                            <div class="flex justify-center">
                                <div class="max-w-[90%] bg-blue-50 border-l-4 border-blue-500 rounded-lg px-4 py-3 shadow-sm">
                                    <p class="text-sm text-blue-900 whitespace-pre-wrap font-medium">{{ $message['content'] }}</p>
                                    <p class="text-xs text-blue-600 mt-1 text-center">{{ $message['timestamp'] }}</p>
                                </div>
                            </div>
                        @elseif($message['role'] === 'user')
                            <!-- User Message (Right - Blue) -->
                            <div class="flex justify-end">
                                <div class="max-w-[70%]">
                                    <div class="rounded-lg rounded-tr-none px-4 py-3 shadow-sm" style="background-color: #3b82f6;">
                                        <p class="text-sm whitespace-pre-wrap" style="color: #ffffff;">{{ $message['content'] }}</p>
                                    </div>
                                    <p class="text-xs text-gray-600 mt-1 text-right">{{ $message['timestamp'] }}</p>
                                </div>
                            </div>
                        @else
                            <!-- Bot Message (Left - Gray) -->
                            <div class="flex justify-start">
                                <div class="max-w-[70%]">
                                    <div class="bg-white border border-gray-200 rounded-lg rounded-tl-none px-4 py-3 shadow-sm">
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
                                            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ trim($textContent) }}</p>
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
                                                <div class="mt-2 bg-gray-50 border border-gray-300 rounded-lg p-3">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"></path>
                                                        </svg>
                                                        <span class="text-xs font-medium text-gray-600">Voice Note</span>
                                                    </div>
                                                    <audio controls class="w-full" style="height: 32px;">
                                                        <source src="{{ trim($audioUrl) }}" type="audio/mpeg">
                                                        Your browser does not support the audio element.
                                                    </audio>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">{{ $message['timestamp'] }}</p>
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
            <div class="border-t border-gray-200 p-4 bg-white">
                <form wire:submit.prevent="sendMessage" class="flex items-end space-x-3">
                    <div class="flex-1">
                        <textarea
                            wire:model.live="inputMessage"
                            @keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage(); }"
                            rows="2"
                            placeholder="Type your message... (Enter to send, Shift+Enter for new line)"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                            @if($loading) disabled @endif></textarea>
                        @error('inputMessage')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        style="background-color: #10b981;"
                        class="px-6 py-3 text-white rounded-lg hover:bg-emerald-600 transition duration-150 ease-in-out disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center justify-center space-x-2 min-w-[120px] font-medium">
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

        <!-- Instructions -->
        <div class="mt-6 bg-white border border-blue-300 rounded-lg p-4 shadow-sm">
            <h3 class="font-semibold text-gray-900 mb-2">💡 Testing Tips</h3>
            <ul class="text-sm text-gray-700 space-y-1">
                <li>• Test different conversation topics to see how your persona responds</li>
                <li>• Check if the system prompt is being followed correctly</li>
                <li>• Memory tags are loaded but not saved in test mode</li>
                <li>• Use "Clear Chat" to start a fresh conversation</li>
            </ul>
        </div>
    </div>
</div>
