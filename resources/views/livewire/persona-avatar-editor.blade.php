<div>
    <!-- Mode Toggle -->
    <div class="mb-6">
                    <div class="flex space-x-4 border-b border-gray-200 dark:border-gray-700">
                        <button
                            wire:click="$set('mode', 'upload')"
                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors
                                {{ $mode === 'upload'
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                                }}"
                        >
                            📤 Upload Photo
                        </button>
                        <button
                            wire:click="$set('mode', 'generate')"
                            class="px-6 py-3 text-sm font-medium border-b-2 transition-colors
                                {{ $mode === 'generate'
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                                }}"
                        >
                            ✨ Generate New
                        </button>
            </div>
        </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <!-- Left Column: Input Section -->
        <div>
                        @if($mode === 'upload')
                            <!-- Upload Mode -->
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Select Reference Photo
                                    </label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg hover:border-gray-400 dark:hover:border-gray-500 transition-colors">
                                        <div class="space-y-1 text-center">
                                            @if($photo)
                                                <div class="mb-4">
                                                    <img src="{{ $photo->temporaryUrl() }}"
                                                         alt="Preview"
                                                         class="mx-auto h-64 w-auto rounded-lg shadow-md">
                                                </div>
                                                <button
                                                    wire:click="$set('photo', null)"
                                                    type="button"
                                                    class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300"
                                                >
                                                    Remove
                                                </button>
                                            @else
                                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                                <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                                    <label for="file-upload" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                                        <span>Upload a file</span>
                                                        <input id="file-upload" wire:model="photo" type="file" class="sr-only" accept="image/*">
                                                    </label>
                                                    <p class="pl-1">or drag and drop</p>
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    PNG, JPG, GIF up to 10MB
                                                </p>
                                            @endif

                                            @error('photo')
                                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Current Reference Image -->
                                @if($persona->hasMedia('reference_image'))
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Current Reference Image
                                        </label>
                                        <img src="{{ $persona->getFirstMediaUrl('reference_image') }}"
                                             alt="Current Reference"
                                             class="w-full rounded-lg shadow-md">
                                    </div>
                                @endif

                                <button
                                    wire:click="uploadReference"
                                    wire:loading.attr="disabled"
                                    wire:target="photo,uploadReference"
                                    :disabled="!$photo || $isProcessing"
                                    class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span wire:loading.remove wire:target="uploadReference">
                                        📸 Create Passport Photo
                                    </span>
                                    <span wire:loading wire:target="uploadReference" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                </button>
                            </div>
                        @else
                            <!-- Generate Mode -->
                            <div class="space-y-6">
                                <div>
                                    <label for="gender" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Gender
                                    </label>
                                    <select
                                        wire:model="gender"
                                        id="gender"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-gray-900 dark:text-gray-100"
                                    >
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="non-binary">Non-binary</option>
                                    </select>
                                    @error('gender')
                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Specific Features (Optional)
                                    </label>
                                    <textarea
                                        wire:model="description"
                                        id="description"
                                        rows="4"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-gray-900 dark:text-gray-100"
                                        placeholder="e.g., with short black hair, brown eyes, wearing glasses..."
                                    ></textarea>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Describe specific physical features, hairstyle, or accessories
                                    </p>
                                    @error('description')
                                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <button
                                    wire:click="generateAvatar"
                                    wire:loading.attr="disabled"
                                    wire:target="generateAvatar"
                                    class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span wire:loading.remove wire:target="generateAvatar">
                                        ✨ Generate Passport Photo
                                    </span>
                                    <span wire:loading wire:target="generateAvatar" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Generating...
                                    </span>
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Right Column: Preview Area -->
                    <div>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Current Passport Photo
                            </h3>

                            @if($persona->hasMedia('avatar'))
                                <div class="relative">
                                    <img
                                        src="{{ $persona->getFirstMediaUrl('avatar') }}"
                                        alt="{{ $persona->name }} Avatar"
                                        class="w-full rounded-lg shadow-lg"
                                        wire:key="avatar-{{ $persona->getFirstMedia('avatar')->id ?? 'none' }}"
                                    >
                                    <div class="mt-4 text-center">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Last updated: {{ $persona->getFirstMedia('avatar')->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center h-96 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                    <svg class="h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                                        No passport photo yet<br>
                                        Upload or generate one to get started
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Info Box -->
                        <div class="mt-6 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                        Passport Photo Guidelines
                                    </h3>
                                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                        <ul class="list-disc list-inside space-y-1">
                                            <li>Neutral expression, facing camera</li>
                                            <li>Plain off-white background</li>
                                            <li>Even lighting, no shadows</li>
                                            <li>Professional headshot style</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <script>
        window.addEventListener('success', event => {
            alert(event.detail.message);
        });

        window.addEventListener('error', event => {
            alert(event.detail.message);
        });

        window.addEventListener('avatar-updated', event => {
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    </script>
</div>
