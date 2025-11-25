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

                    <!-- System Prompt -->
                    <div>
                        <label for="system_prompt" class="block text-sm font-medium mb-2">System Prompt *</label>
                        <textarea
                            wire:model="system_prompt"
                            id="system_prompt"
                            rows="6"
                            class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Define the persona's character, behavior, and how it should interact..."
                        ></textarea>
                        @error('system_prompt') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Physical Traits -->
                    <div>
                        <label for="physical_traits" class="block text-sm font-medium mb-2">Physical Traits (for Image Generation)</label>
                        <textarea
                            wire:model="physical_traits"
                            id="physical_traits"
                            rows="4"
                            class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Describe physical appearance: hair color, eye color, style, etc..."
                        ></textarea>
                        @error('physical_traits') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
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
