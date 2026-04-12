<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Memory Brain</h2>
                    <div class="flex gap-3">
                        <button
                            wire:click="organizeMemoryTags"
                            wire:loading.attr="disabled"
                            wire:target="organizeMemoryTags"
                            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-400 disabled:cursor-not-allowed text-white font-medium rounded-lg transition-colors duration-200 flex items-center space-x-2"
                        >
                            <span wire:loading.remove wire:target="organizeMemoryTags">🧹</span>
                            <svg wire:loading wire:target="organizeMemoryTags" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="organizeMemoryTags">Organize Tags</span>
                            <span wire:loading wire:target="organizeMemoryTags">Organizing...</span>
                        </button>
                        <button
                            wire:click="openModal"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors duration-200"
                        >
                            Add New Memory
                        </button>
                    </div>
                </div>

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

                <!-- Info Box -->
                <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-blue-900 dark:text-blue-100">🧹 Organize Tags</p>
                            <p class="text-xs text-blue-800 dark:text-blue-300 mt-1">
                                Click "Organize Tags" to let AI consolidate duplicates (e.g., "Korean", "nationality: korean" → merged), assign importance scores (1-10), and clean up your memory tags. Tags are sorted by importance.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Memory Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3">Category</th>
                                <th class="px-6 py-3">Target</th>
                                <th class="px-6 py-3">Value</th>
                                <th class="px-6 py-3">Context</th>
                                <th class="px-6 py-3">Importance</th>
                                <th class="px-6 py-3">Created At</th>
                                <th class="px-6 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($memories as $memory)
                                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 font-medium">{{ $memory->category }}</td>
                                    <td class="px-6 py-4">{{ $memory->target }}</td>
                                    <td class="px-6 py-4">{{ $memory->value }}</td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400 max-w-xs">
                                        {{ $memory->context ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($memory->importance)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $memory->importance >= 8 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                                {{ $memory->importance >= 5 && $memory->importance < 8 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                                {{ $memory->importance < 5 ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                            ">
                                                {{ $memory->importance }}/10
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">Not set</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                        {{ $memory->created_at->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 space-x-2">
                                        <button
                                            wire:click="openModal({{ $memory->id }})"
                                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            wire:click="delete({{ $memory->id }})"
                                            onclick="return confirm('Are you sure?')"
                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No memories found. Add your first memory!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }">
        <div class="flex items-center justify-center min-h-screen px-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" wire:click="closeModal"></div>

            <!-- Modal Content -->
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full p-6 z-10">
                <h3 class="text-xl font-bold mb-4">{{ $editingId ? 'Edit Memory' : 'Add New Memory' }}</h3>

                <form wire:submit.prevent="save" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Category *</label>
                            <input
                                type="text"
                                wire:model="category"
                                class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg"
                                placeholder="e.g., preference, fact, interest"
                            >
                            @error('category') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Target *</label>
                            <input
                                type="text"
                                wire:model="target"
                                class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg"
                                placeholder="user or persona"
                            >
                            @error('target') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Value *</label>
                        <input
                            type="text"
                            wire:model="value"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg"
                            placeholder="The memory value"
                        >
                        @error('value') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Context</label>
                        <textarea
                            wire:model="context"
                            rows="3"
                            class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg"
                            placeholder="Additional context or notes..."
                        ></textarea>
                        @error('context') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-gray-200 font-medium rounded-lg"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg"
                        >
                            {{ $editingId ? 'Update' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
