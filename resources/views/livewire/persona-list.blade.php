<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold">My Personas</h2>
                    <button wire:click="createPersona"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Create New Persona
                    </button>
                </div>

                @if($personas->isEmpty())
                    <div class="text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400 mb-4">You haven't created any personas yet.</p>
                        <button wire:click="createPersona"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Create Your First Persona
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($personas as $persona)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow">
                                <div class="flex items-center mb-4">
                                    @if($persona->getFirstMediaUrl('avatars'))
                                        <img src="{{ $persona->getFirstMediaUrl('avatars') }}"
                                             alt="{{ $persona->name }}"
                                             class="w-16 h-16 rounded-full object-cover mr-4">
                                    @else
                                        <div class="w-16 h-16 rounded-full bg-indigo-600 flex items-center justify-center text-white text-2xl font-bold mr-4">
                                            {{ substr($persona->name, 0, 1) }}
                                        </div>
                                    @endif
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold">{{ $persona->name }}</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $persona->wake_time }} - {{ $persona->sleep_time }}
                                        </p>
                                    </div>
                                    <div>
                                        @if($persona->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                Inactive
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-3">
                                        {{ Str::limit($persona->system_prompt, 100) }}
                                    </p>
                                </div>

                                <div class="flex gap-2">
                                    <a href="{{ route('persona.dashboard', $persona) }}"
                                       wire:navigate
                                       class="flex-1 text-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                        Manage
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
