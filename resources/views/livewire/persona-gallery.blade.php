<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <header class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold">{{ $persona->name }} — Media Gallery</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ $mediaItems->total() }} {{ Str::plural('item', $mediaItems->total()) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <select wire:model.live="collection" class="border dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="all">All Collections</option>
                            <option value="avatar">Avatar</option>
                            <option value="reference_image">Reference</option>
                            <option value="generated_images">Generated Images</option>
                            <option value="voice_notes">Voice Notes</option>
                        </select>
                    </div>
                </header>

                @if($mediaItems->isEmpty())
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No media found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This persona doesn't have any media in this collection yet.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($mediaItems as $media)
                            <div wire:key="media-{{ $media->id }}" class="relative bg-gray-50 dark:bg-gray-700 rounded-lg shadow overflow-hidden group hover:shadow-lg transition-shadow">
                                @if(str_starts_with($media->mime_type, 'image'))
                                    <img src="{{ route('persona.media.view', ['persona' => $persona, 'media' => $media, 'conversion' => 'thumb']) }}" alt="{{ $media->name }}" loading="lazy" class="w-full h-48 object-cover">
                                @elseif(str_starts_with($media->mime_type, 'audio'))
                                    <div class="w-full h-48 flex items-center justify-center bg-gradient-to-br from-purple-100 to-indigo-100 dark:from-purple-900 dark:to-indigo-900">
                                        <svg class="w-16 h-16 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-full h-48 flex items-center justify-center bg-gray-100 dark:bg-gray-600">
                                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                @endif

                                <div class="absolute top-2 right-2">
                                    <span class="bg-black/70 text-white rounded-full px-2 py-1 text-xs font-medium">
                                        {{ ucfirst(str_replace('_', ' ', $media->collection_name)) }}
                                    </span>
                                </div>

                                <div class="p-3">
                                    <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-2">
                                        <span>{{ $media->created_at->format('M d, Y') }}</span>
                                        <span>{{ $media->human_readable_size }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button wire:click="previewMedia({{ $media->id }})" class="flex-1 text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">
                                            Preview
                                        </button>
                                        <a href="{{ route('persona.media.download', [$persona, $media->id]) }}" class="flex-1 text-center text-sm text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 font-medium">
                                            Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $mediaItems->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    @if($previewMedia)
        <div x-data="{ open: @entangle('previewMediaId').live }" x-show="open !== null" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="open !== null" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black bg-opacity-75 transition-opacity" aria-hidden="true" @click="$wire.closePreview()"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="open !== null" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $previewMedia->name }}</h3>
                            <button @click="$wire.closePreview()" class="text-gray-400 hover:text-gray-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-2">
                            @if(str_starts_with($previewMedia->mime_type, 'image'))
                                <img src="{{ route('persona.media.view', ['persona' => $persona, 'media' => $previewMedia, 'conversion' => 'large']) }}" alt="{{ $previewMedia->name }}" class="w-full rounded-lg">
                            @elseif(str_starts_with($previewMedia->mime_type, 'audio'))
                                <div class="bg-gradient-to-br from-purple-100 to-indigo-100 dark:from-purple-900 dark:to-indigo-900 rounded-lg p-8">
                                    <audio controls class="w-full">
                                        <source src="{{ route('persona.media.view', ['persona' => $persona, 'media' => $previewMedia]) }}" type="{{ $previewMedia->mime_type }}>
                                        Your browser does not support the audio element.
                                    </audio>
                                </div>
                            @endif

                            <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Collection:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $previewMedia->collection_name)) }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Size:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $previewMedia->human_readable_size }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Type:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $previewMedia->mime_type }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Created:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $previewMedia->created_at->format('M d, Y H:i') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <a href="{{ route('persona.media.download', [$persona, $previewMedia->id]) }}" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Download
                        </a>
                        <button @click="$wire.closePreview()" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
