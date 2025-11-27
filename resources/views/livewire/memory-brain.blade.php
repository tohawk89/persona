<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                @if (!Auth::user()->persona)
                    <div class="mb-6 p-6 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg text-center">
                        <p class="text-purple-700 dark:text-purple-300 mb-3">
                            No persona configured. Please set up your persona first to manage memories.
                        </p>
                        <a href="{{ route('persona.manager') }}" class="inline-block px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                            Configure Persona →
                        </a>
                    </div>
                @endif

                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Memory Brain</h2>
                    <button
                        wire:click="openModal"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors duration-200"
                    >
                        Add New Memory
                    </button>
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

                <!-- Memory Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3">Category</th>
                                <th class="px-6 py-3">Target</th>
                                <th class="px-6 py-3">Value</th>
                                <th class="px-6 py-3">Context</th>
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
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
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
