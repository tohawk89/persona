<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h2 class="text-2xl font-bold mb-6">Dashboard</h2>

                @if (!$persona)
                    <div class="mb-6 p-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                        <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-2">👋 Welcome! Get Started</h3>
                        <p class="text-yellow-700 dark:text-yellow-300 mb-3">
                            You don't have a persona configured yet. Please set up your AI companion to begin.
                        </p>
                        <a href="{{ route('persona.manager') }}" class="inline-block px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg transition-colors">
                            Configure Persona →
                        </a>
                    </div>
                @endif

                @if (session()->has('success'))
                    <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded-lg">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Persona Status -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Persona Status</h3>
                        <p class="text-2xl font-bold">
                            @if($persona)
                                <span class="text-green-600 dark:text-green-400">Active</span>
                            @else
                                <span class="text-red-600 dark:text-red-400">Inactive</span>
                            @endif
                        </p>
                        @if($persona)
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                Wake: {{ $persona->wake_time }} | Sleep: {{ $persona->sleep_time }}
                            </p>
                        @endif
                    </div>

                    <!-- Next Scheduled Event -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Next Scheduled Event</h3>
                        @if($nextEvent)
                            <p class="text-xl font-bold">{{ $nextEvent->scheduled_at->format('h:i A') }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                Type: <span class="capitalize">{{ $nextEvent->type }}</span>
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $nextEvent->scheduled_at->diffForHumans() }}
                            </p>
                        @else
                            <p class="text-lg text-gray-500 dark:text-gray-400">No events scheduled</p>
                        @endif
                    </div>

                    <!-- Last User Interaction -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Last User Interaction</h3>
                        @if($lastInteraction)
                            <p class="text-xl font-bold">{{ $lastInteraction->format('h:i A') }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                {{ $lastInteraction->diffForHumans() }}
                            </p>
                        @else
                            <p class="text-lg text-gray-500 dark:text-gray-400">No interactions yet</p>
                        @endif
                    </div>
                </div>

                <!-- Trigger Wake Up Routine -->
                <div class="mt-6">
                    <button
                        wire:click="triggerWakeUpRoutine"
                        wire:loading.attr="disabled"
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        @if(!$persona) disabled @endif
                    >
                        <span wire:loading.remove wire:target="triggerWakeUpRoutine">
                            Trigger Wake-Up Routine (Generate Daily Plan)
                        </span>
                        <span wire:loading wire:target="triggerWakeUpRoutine" class="flex items-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Generating...
                        </span>
                    </button>
                    @if(!$persona)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            Please configure a persona first.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
