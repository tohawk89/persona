<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Mission Control</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Manage your AI companions</p>
            </div>
            <button wire:click="createPersona"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Create New Persona
                    </button>
        </div>

        @if (session()->has('success'))
            <div class="p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="p-4 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        {{-- Usage Stats Today --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-800 dark:bg-gray-700 rounded-lg p-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-sm text-gray-400">Messages</p>
                    <p class="text-2xl font-semibold text-white">{{ $stats['messages_count'] }}</p>
                </div>
                <div class="text-gray-300 text-2xl">💬</div>
            </div>

            <div class="bg-gray-800 dark:bg-gray-700 rounded-lg p-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-sm text-gray-400">Photos</p>
                    <p class="text-2xl font-semibold text-white">{{ $stats['photos_count'] }}</p>
                </div>
                <div class="text-gray-300 text-2xl">📸</div>
            </div>

            <div class="bg-gray-800 dark:bg-gray-700 rounded-lg p-4 flex items-center justify-between shadow-sm">
                <div>
                    <p class="text-sm text-gray-400">Voice</p>
                    <p class="text-2xl font-semibold text-white">{{ $stats['voice_count'] }}</p>
                </div>
                <div class="text-gray-300 text-2xl">🎙️</div>
            </div>
        </div>

        {{-- Main Content Grid: 3 Columns --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
            {{-- My Companions --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
                <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center mb-0">
                        <span class="text-xl mr-2">👥</span>
                        My Companions
                    </h2>
                </div>

                @if($personas->isEmpty())
                    <div class="p-6 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400 py-4">
                            No companions yet
                        </p>
                    </div>
                @else
                    <div class="p-4 space-y-3 max-h-96 overflow-y-auto">
                        @foreach($personas as $persona)
                            <a href="{{ route('persona.dashboard', $persona) }}"
                               class="group block bg-gray-100 dark:bg-gray-700 rounded-lg p-3 hover:bg-gray-60 dark:hover:bg-gray-500 transition-all duration-200 border border-gray-200 dark:border-gray-600">

                                <div class="flex items-center gap-3">
                                    @if($persona->getFirstMediaUrl('avatars'))
                                        <img src="{{ $persona->getFirstMediaUrl('avatars') }}"
                                             alt="{{ $persona->name }}"
                                             class="w-10 h-10 rounded-full object-cover">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                            <span class="text-white font-bold text-sm">{{ substr($persona->name, 0, 1) }}</span>
                                        </div>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ $persona->name }}</h3>
                                        <div class="flex items-center mt-1">
                                            <span class="relative flex size-2 mr-5">
                                                @if($persona->is_awake)
                                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full size-2 bg-green-600"></span>
                                                @else
                                                    <span class="relative inline-flex rounded-full size-2 bg-gray-400"></span>
                                                @endif
                                            </span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ $persona->is_awake ? 'Awake' : 'Asleep' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Social Calendar --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
                <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center mb-0">
                        <span class="text-xl mr-2">📅</span>
                        Social Calendar
                    </h2>
                </div>

                <div class="p-4 max-h-96 overflow-y-auto">
                    @if($upcomingEvents->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            No upcoming events
                        </p>
                    @else
                        <div class="space-y-2">
                            @foreach($upcomingEvents as $event)
                                <div class="flex items-start space-x-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <div class="flex-shrink-0 w-12 text-center">
                                        <p class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ $event->scheduled_at->format('H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $event->persona->name }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 truncate">
                                            {{ Str::limit($event->context_prompt, 40) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Life Updates --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700">
                <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center mb-0">
                        <span class="text-xl mr-2">💭</span>
                        Life Updates
                    </h2>
                </div>

                <div class="p-4 max-h-96 overflow-y-auto">
                    @if($lifeUpdates->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            No recent updates
                        </p>
                    @else
                        <div class="space-y-2">
                            @foreach($lifeUpdates as $update)
                                <div class="p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        <span class="font-semibold">{{ $update->persona->name }}</span>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            {{ $update->target === 'user' ? 'learned' : 'updated' }}:
                                        </span>
                                        <span class="text-gray-700 dark:text-gray-300">
                                            {{ $update->value }}
                                        </span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $update->updated_at->diffForHumans() }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
