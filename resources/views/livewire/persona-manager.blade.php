<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h2 class="text-2xl font-bold mb-6">Persona Manager</h2>

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

                <form wire:submit.prevent="save" class="space-y-6">
                    <!-- Persona Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium mb-2">Persona Name *</label>
                        <input
                            type="text"
                            wire:model="name"
                            id="name"
                            class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="e.g., Sarah, Alex, Luna..."
                        >
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Personality Section (2-Stage) -->
                    <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-semibold mb-4">🧠 Personality Configuration</h3>

                        <!-- Stage 1: Raw Concept -->
                        <div>
                            <label for="about_description" class="block text-sm font-medium mb-2">
                                💭 Concept (Your Idea)
                            </label>
                            <textarea
                                wire:model="about_description"
                                id="about_description"
                                rows="4"
                                class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Rough idea: 'Friendly Malaysian girl, loves anime, uses Manglish...'"
                            ></textarea>
                            @error('about_description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Optimize Button -->
                        <div class="flex justify-center">
                            <button
                                type="button"
                                wire:click="optimizeSystemPrompt"
                                wire:loading.attr="disabled"
                                wire:target="optimizeSystemPrompt"
                                class="px-6 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-400 disabled:cursor-not-allowed text-white font-medium rounded-lg transition-colors duration-200 flex items-center space-x-2"
                            >
                                <span wire:loading.remove wire:target="optimizeSystemPrompt">✨</span>
                                <svg wire:loading wire:target="optimizeSystemPrompt" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="optimizeSystemPrompt">Generate System Prompt</span>
                                <span wire:loading wire:target="optimizeSystemPrompt">Optimizing...</span>
                            </button>
                        </div>

                        <!-- Stage 2: Optimized Output -->
                        <div>
                            <label for="system_prompt" class="block text-sm font-medium mb-2">
                                📋 Final Instruction (Optimized) *
                            </label>
                            <textarea
                                wire:model="system_prompt"
                                id="system_prompt"
                                rows="8"
                                class="w-full px-4 py-2 bg-white dark:bg-gray-800 border-2 border-purple-300 dark:border-purple-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent font-mono text-sm"
                                placeholder="AI-optimized system prompt will appear here..."
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-1">You can manually edit this if needed</p>
                            @error('system_prompt') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Migrate Bio Button -->
                        @if($persona)
                        <div class="border-t border-gray-300 dark:border-gray-600 pt-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-1">🧠 Bio Migration</h4>
                                        <p class="text-xs text-blue-800 dark:text-blue-300 mb-3">
                                            Extract identity details (name, personality, backstory) from the System Prompt and move them to Memory Tags with high importance. This leaves only behavioral rules in the prompt.
                                        </p>
                                        <button
                                            type="button"
                                            wire:click="migrateBio"
                                            wire:loading.attr="disabled"
                                            wire:target="migrateBio"
                                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors duration-200 flex items-center space-x-2"
                                        >
                                            <span wire:loading.remove wire:target="migrateBio">🔄</span>
                                            <svg wire:loading wire:target="migrateBio" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span wire:loading.remove wire:target="migrateBio">Migrate to Memory Tags</span>
                                            <span wire:loading wire:target="migrateBio">Migrating...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Appearance Section (2-Stage) -->
                    <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-semibold mb-4">👤 Appearance Configuration</h3>

                        <!-- Stage 1: Raw Concept -->
                        <div>
                            <label for="appearance_description" class="block text-sm font-medium mb-2">
                                💭 Visual Concept (Your Idea)
                            </label>
                            <textarea
                                wire:model="appearance_description"
                                id="appearance_description"
                                rows="3"
                                class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Rough idea: 'Short black hair, brown eyes, casual style...'"
                            ></textarea>
                            @error('appearance_description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Optimize Button -->
                        <div class="flex justify-center">
                            <button
                                type="button"
                                wire:click="optimizePhysicalTraits"
                                wire:loading.attr="disabled"
                                wire:target="optimizePhysicalTraits"
                                class="px-6 py-2 bg-pink-600 hover:bg-pink-700 disabled:bg-pink-400 disabled:cursor-not-allowed text-white font-medium rounded-lg transition-colors duration-200 flex items-center space-x-2"
                            >
                                <span wire:loading.remove wire:target="optimizePhysicalTraits">✨</span>
                                <svg wire:loading wire:target="optimizePhysicalTraits" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="optimizePhysicalTraits">Generate Traits</span>
                                <span wire:loading wire:target="optimizePhysicalTraits">Optimizing...</span>
                            </button>
                        </div>

                        <!-- Stage 2: Optimized Output -->
                        <div>
                            <label for="physical_traits" class="block text-sm font-medium mb-2">
                                🎨 Final Image Prompt (Optimized)
                            </label>
                            <textarea
                                wire:model="physical_traits"
                                id="physical_traits"
                                rows="5"
                                class="w-full px-4 py-2 bg-white dark:bg-gray-800 border-2 border-indigo-300 dark:border-indigo-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent font-mono text-sm"
                                placeholder="AI-optimized physical description will appear here..."
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-1">You can manually edit this if needed</p>
                            @error('physical_traits') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Gender Selection -->
                    <div>
                        <label for="gender" class="block text-sm font-medium mb-2">Gender *</label>
                        <select
                            wire:model="gender"
                            id="gender"
                            class="w-full px-4 py-2 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                            <option value="female">Female</option>
                            <option value="male">Male</option>
                            <option value="non-binary">Non-Binary</option>
                            <option value="other">Other</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Used for image generation and pronouns</p>
                        @error('gender') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Wake and Sleep Times -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="wake_time" class="block text-sm font-medium mb-2">Wake Time *</label>
                            <input
                                type="time"
                                wire:model="wake_time"
                                id="wake_time"
                                class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                            @error('wake_time') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="sleep_time" class="block text-sm font-medium mb-2">Sleep Time *</label>
                            <input
                                type="time"
                                wire:model="sleep_time"
                                id="sleep_time"
                                class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                            @error('sleep_time') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Media Preferences -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="voice_frequency" class="block text-sm font-medium mb-2">🎤 Voice Note Frequency *</label>
                            <select
                                wire:model="voice_frequency"
                                id="voice_frequency"
                                class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="never">Never - Text only</option>
                                <option value="rare">Rare - Special moments only</option>
                                <option value="moderate">Moderate - Occasionally (Recommended)</option>
                                <option value="frequent">Frequent - Often</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">How often the AI should use voice notes</p>
                            @error('voice_frequency') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="image_frequency" class="block text-sm font-medium mb-2">📸 Image Generation Frequency *</label>
                            <select
                                wire:model="image_frequency"
                                id="image_frequency"
                                class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="never">Never - No images</option>
                                <option value="rare">Rare - Only when asked</option>
                                <option value="moderate">Moderate - Occasionally (Recommended)</option>
                                <option value="frequent">Frequent - Often</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">How often the AI should generate images</p>
                            @error('image_frequency') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Reference Images Gallery -->
                    <div>
                        <label class="block text-sm font-medium mb-3">Reference Images</label>

                        <!-- Existing Images Gallery -->
                        @if($persona && $persona->hasMedia('reference_images'))
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
                                @foreach($persona->getMedia('reference_images') as $media)
                                    <div class="relative group">
                                        <img
                                            src="{{ $media->getUrl() }}"
                                            alt="Reference Image"
                                            class="w-full h-40 object-cover rounded-lg border-2 border-gray-200 dark:border-gray-600"
                                            onerror="this.onerror=null; this.style.border='2px solid red'; console.error('Failed to load:', '{{ $media->getUrl() }}');"
                                        >
                                        <!-- Delete Button Overlay -->
                                        <button
                                            type="button"
                                            wire:click="deleteMedia({{ $media->id }})"
                                            onclick="return confirm('Delete this image?')"
                                            class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                            title="Delete image"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Accumulated Photos Preview (Before Upload) -->
                        @if(!empty($accumulated_photos))
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
                                @foreach($accumulated_photos as $index => $photo)
                                    <div class="relative group">
                                        <img
                                            src="{{ $photo->temporaryUrl() }}"
                                            alt="New Photo Preview"
                                            class="w-full h-40 object-cover rounded-lg border-2 border-blue-400 dark:border-blue-600"
                                        >
                                        <div class="absolute top-2 left-2 bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                            New
                                        </div>
                                        <!-- Remove Button -->
                                        <button
                                            type="button"
                                            wire:click="removeAccumulatedPhoto({{ $index }})"
                                            class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                                            title="Remove image"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Upload Drop Zone -->
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-blue-500 dark:hover:border-blue-400 transition-colors">
                            <input
                                type="file"
                                wire:model="new_photos"
                                multiple
                                accept="image/*"
                                id="photo-upload"
                                class="hidden"
                            >
                            <label for="photo-upload" class="cursor-pointer">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-semibold text-blue-600 dark:text-blue-400">Click to upload</span> or drag and drop
                                </span>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">PNG, JPG up to 10MB each</p>
                            </label>
                        </div>

                        <div wire:loading wire:target="new_photos" class="text-sm text-blue-600 dark:text-blue-400 mt-2">
                            Processing images...
                        </div>

                        @error('new_photos.*') <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Is Active Toggle -->
                    <div class="flex items-center space-x-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="is_active"
                                class="sr-only peer"
                            >
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        </label>
                        <span class="text-sm font-medium">
                            Persona is {{ $is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200"
                        >
                            Save Persona
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
