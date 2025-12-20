<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <!-- Header -->
                <div class="mb-6">
                    <h2 class="text-2xl font-semibold">Wardrobe Manager</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage your persona's outfit collection. Set primary outfits and rotation pool for variety.</p>
                </div>

                <!-- Tab Navigation -->
                <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            wire:click="switchTab('wardrobe')"
                            class="@if($activeTab === 'wardrobe') border-indigo-500 text-indigo-600 dark:text-indigo-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            👔 Wardrobe
                        </button>
                        <button
                            wire:click="switchTab('history')"
                            class="@if($activeTab === 'history') border-indigo-500 text-indigo-600 dark:text-indigo-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            📅 History
                        </button>
                        <button
                            wire:click="switchTab('analytics')"
                            class="@if($activeTab === 'analytics') border-indigo-500 text-indigo-600 dark:text-indigo-400 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            📊 Analytics
                        </button>
                    </nav>
                </div>

                <!-- Flash Messages -->
                @if (session()->has('message'))
                    <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 rounded">
                        {{ session('message') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 rounded">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- Wardrobe Tab Content -->
                @if($activeTab === 'wardrobe')
                <!-- Outfit Slots Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($slots as $slotName => $slotInfo)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Slot Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <span class="text-2xl mr-2">{{ $slotInfo['icon'] }}</span>
                                {{ $slotInfo['label'] }}
                            </h3>
                        </div>

                        <!-- Outfits List -->
                        @php
                            $outfits = $wardrobeBySlot[$slotName] ?? collect();
                            $primary = $outfits->where('is_primary', true)->first();
                            $rotation = $outfits->where('is_primary', false);
                        @endphp

                        @if($primary)
                            <!-- Primary Outfit -->
                            <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                                <div class="flex items-start justify-between mb-2">
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-yellow-800 bg-yellow-100 rounded">
                                        ⭐ Primary
                                    </span>
                                    <span class="text-xs text-gray-500">Worn {{ $primary->wear_count }} times</span>
                                </div>
                                <p class="text-sm text-gray-700 mb-2">{{ $primary->description }}</p>
                                @if($primary->last_worn_at)
                                    <p class="text-xs text-gray-500">Last worn: {{ $primary->last_worn_at->diffForHumans() }}</p>
                                @endif
                                @if(!empty($primary->tags))
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        @foreach($primary->tags as $tag)
                                            <span class="px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="openEditModal({{ $primary->id }})"
                                            class="text-xs text-blue-600 hover:text-blue-800">
                                        Edit
                                    </button>
                                    <button wire:click="generateSimilar({{ $primary->id }})"
                                            class="text-xs text-purple-600 hover:text-purple-800 flex items-center gap-1">
                                        <span>🔄</span> Generate Similar
                                    </button>
                                </div>
                            </div>
                        @endif

                        <!-- Rotation Pool -->
                        @if($rotation->count() > 0)
                            <div class="mb-4">
                                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Rotation Pool ({{ $rotation->count() }})</p>
                                <div class="space-y-2">
                                    @foreach($rotation as $item)
                                        <div class="p-2 bg-gray-50 border border-gray-200 rounded">
                                            <p class="text-sm text-gray-700 mb-1">{{ $item->description }}</p>
                                            @if(!empty($item->tags))
                                                <div class="flex flex-wrap gap-1 mb-2">
                                                    @foreach($item->tags as $tag)
                                                        <span class="px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded">{{ $tag }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div class="mt-1 flex items-center justify-between">
                                                @if($item->last_worn_at)
                                                    <span class="text-xs text-gray-500">Last: {{ $item->last_worn_at->diffForHumans() }}</span>
                                                @else
                                                    <span class="text-xs text-gray-500">Never worn</span>
                                                @endif
                                                <div class="flex gap-2">
                                                    <button wire:click="setPrimary({{ $item->id }})"
                                                            class="text-xs text-yellow-600 hover:text-yellow-800">
                                                        Set Primary
                                                    </button>
                                                    <button wire:click="openEditModal({{ $item->id }})"
                                                            class="text-xs text-blue-600 hover:text-blue-800">
                                                        Edit
                                                    </button>
                                                    <button wire:click="generateSimilar({{ $item->id }})"
                                                            class="text-xs text-purple-600 hover:text-purple-800">
                                                        🔄
                                                    </button>
                                                    <button wire:click="deleteOutfit({{ $item->id }})"
                                                            wire:confirm="Are you sure you want to delete this outfit?"
                                                            class="text-xs text-red-600 hover:text-red-800">
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($outfits->isEmpty())
                            <p class="text-sm text-gray-500 italic mb-4">No outfits yet</p>
                        @endif

                        <!-- Action Buttons -->
                        <div class="space-y-2">
                            <button wire:click="openGenerateModal('{{ $slotName }}')"
                                    class="w-full px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded hover:bg-purple-700 flex items-center justify-center gap-2">
                                <span>✨</span> Generate with AI
                            </button>
                            <button wire:click="openAddModal('{{ $slotName }}')"
                                    class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">
                                + Add Manually
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit.prevent="saveOutfit">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                {{ $editingId ? 'Edit Outfit' : 'Add Outfit' }}
                            </h3>

                            <!-- Full Description -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Description <span class="text-red-500">*</span>
                                </label>
                                <textarea wire:model="form.description"
                                          rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="E.g., Blue jeans with white floral sundress and sandals"></textarea>
                                @error('form.description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Upper Body -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Upper Body</label>
                                <input type="text"
                                       wire:model="form.upper_body"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="E.g., white floral sundress">
                                @error('form.upper_body') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Lower Body -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lower Body</label>
                                <input type="text"
                                       wire:model="form.lower_body"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="E.g., blue jeans (leave empty for dresses)">
                                @error('form.lower_body') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Footwear -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Footwear</label>
                                <input type="text"
                                       wire:model="form.footwear"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="E.g., sandals">
                                @error('form.footwear') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Accessories -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Accessories</label>
                                <input type="text"
                                       wire:model="form.accessories"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="E.g., sunglasses, small handbag">
                                @error('form.accessories') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <!-- Tags -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tags (Max 10)</label>
                                
                                <!-- Predefined Tags -->
                                <div class="mb-3">
                                    <div class="text-xs text-gray-600 mb-2">Predefined Tags:</div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach(\App\Services\WardrobeService::PREDEFINED_TAGS as $tag)
                                            <button type="button"
                                                    wire:click="toggleModalTag('{{ $tag }}')"
                                                    class="px-3 py-1 text-sm rounded-full border transition-colors
                                                           {{ in_array($tag, $modalTags) ? 'bg-purple-100 border-purple-500 text-purple-700' : 'bg-gray-100 border-gray-300 text-gray-700 hover:bg-gray-200' }}">
                                                {{ $tag }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Custom Tags -->
                                <div class="mb-3">
                                    <div class="text-xs text-gray-600 mb-2">Custom Tags:</div>
                                    <div class="flex gap-2 mb-2">
                                        <input type="text"
                                               wire:model="newModalCustomTag"
                                               wire:keydown.enter.prevent="addModalCustomTag"
                                               class="flex-1 px-3 py-1 text-sm border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500"
                                               placeholder="Add custom tag...">
                                        <button type="button"
                                                wire:click="addModalCustomTag"
                                                class="px-4 py-1 text-sm bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                                            + Add
                                        </button>
                                    </div>
                                    @if(count($modalCustomTags) > 0)
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($modalCustomTags as $customTag)
                                                <span class="inline-flex items-center px-3 py-1 text-sm rounded-full bg-blue-100 border border-blue-500 text-blue-700">
                                                    {{ $customTag }}
                                                    <button type="button"
                                                            wire:click="removeModalCustomTag('{{ $customTag }}')"
                                                            class="ml-2 text-blue-600 hover:text-blue-800">
                                                        ×
                                                    </button>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <!-- Total Tag Count -->
                                @php
                                    $totalTags = count($modalTags) + count($modalCustomTags);
                                @endphp
                                <div class="text-xs {{ $totalTags > 10 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    Selected: {{ $totalTags }}/10 tags
                                </div>
                                @error('modalTags') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Set as Primary -->
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           wire:model="form.is_primary"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Set as Primary Outfit</span>
                                </label>
                                <p class="mt-1 text-xs text-gray-500">Primary outfit is worn ~70% of the time</p>
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                                Save Outfit
                            </button>
                            <button type="button"
                                    wire:click="closeModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- History Tab Content -->
    @elseif($activeTab === 'history')
        <div class="space-y-4">
            <!-- Date Range Filter -->
            <div class="flex justify-between items-center">
                <div class="flex space-x-2">
                    <button wire:click="updateDateRange(7)" class="@if($historyDateRange === 7) bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300 @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif px-3 py-2 rounded text-sm">Last 7 days</button>
                    <button wire:click="updateDateRange(30)" class="@if($historyDateRange === 30) bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300 @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif px-3 py-2 rounded text-sm">Last 30 days</button>
                    <button wire:click="updateDateRange(365)" class="@if($historyDateRange === 365) bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300 @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 @endif px-3 py-2 rounded text-sm">All time</button>
                </div>
                <button wire:click="exportHistory" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm flex items-center">
                    📥 Export CSV
                </button>
            </div>

            <!-- History Timeline -->
            @if($outfitHistory->isEmpty())
                <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <p class="text-lg">No outfit history yet</p>
                    <p class="text-sm mt-2">Outfit selections will appear here once your persona starts wearing them</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($outfitHistory as $date => $selections)
                        <div class="bg-white dark:bg-gray-700 rounded-lg shadow p-4">
                            <div class="font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                📅 {{ \Carbon\Carbon::parse($date)->format('l, M d, Y') }}
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($selections as $slotName => $selection)
                                    <div class="flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-600 rounded">
                                        <div class="text-2xl">{{ $slots[$slotName]['icon'] ?? '👔' }}</div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ $slots[$slotName]['label'] ?? $slotName }}</div>
                                            <div class="text-sm text-gray-900 dark:text-gray-100 mt-1">{{ $selection->wardrobeItem->description ?? 'N/A' }}</div>
                                            @if($selection->wardrobeItem && $selection->wardrobeItem->is_primary)
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-yellow-800 bg-yellow-100 rounded mt-1">⭐ Primary</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    <!-- Analytics Tab Content -->
    @elseif($activeTab === 'analytics')
        <div class="space-y-6">
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Outfits</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $analytics['total_outfits'] ?? 0 }}</div>
                </div>
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Unworn Items</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ count($analytics['unworn'] ?? []) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Primary Wears</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $analytics['primary_wears'] ?? 0 }}</div>
                </div>
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Rotation Rate</div>
                    <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $analytics['rotation_effectiveness'] ?? 0 }}%</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Non-primary usage</div>
                </div>
            </div>

            <!-- Most Worn Outfits -->
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow">
                <div class="p-4 border-b border-gray-200 dark:border-gray-600">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">🏆 Most Worn Outfits</h3>
                </div>
                <div class="p-4">
                    @if(empty($analytics['most_worn']) || $analytics['most_worn']->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">No outfit history yet</p>
                    @else
                        <div class="space-y-3">
                            @foreach($analytics['most_worn'] as $outfit)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-600 rounded">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xl">{{ $slots[$outfit->slot_name]['icon'] ?? '👔' }}</span>
                                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ $outfit->description }}</span>
                                            @if($outfit->is_primary)
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-yellow-800 bg-yellow-100 rounded">⭐</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $slots[$outfit->slot_name]['label'] ?? $outfit->slot_name }}
                                            @if($outfit->last_worn_at)
                                                • Last worn {{ $outfit->last_worn_at->diffForHumans() }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ $outfit->wear_count }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">times</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Least Worn Outfits -->
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow">
                <div class="p-4 border-b border-gray-200 dark:border-gray-600">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">💤 Least Worn Outfits</h3>
                </div>
                <div class="p-4">
                    @if(empty($analytics['least_worn']) || $analytics['least_worn']->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">All outfits equally worn or no data available</p>
                    @else
                        <div class="space-y-3">
                            @foreach($analytics['least_worn'] as $outfit)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-600 rounded">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xl">{{ $slots[$outfit->slot_name]['icon'] ?? '👔' }}</span>
                                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ $outfit->description }}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $slots[$outfit->slot_name]['label'] ?? $outfit->slot_name }}
                                            @if($outfit->last_worn_at)
                                                • Last worn {{ $outfit->last_worn_at->diffForHumans() }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-gray-600 dark:text-gray-400">{{ $outfit->wear_count }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">times</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Unworn Outfits -->
            @if(!empty($analytics['unworn']) && $analytics['unworn']->isNotEmpty())
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">🆕 Never Worn</h3>
                    </div>
                    <div class="p-4">
                        <div class="space-y-3">
                            @foreach($analytics['unworn'] as $outfit)
                                <div class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-600 rounded">
                                    <span class="text-xl">{{ $slots[$outfit->slot_name]['icon'] ?? '👔' }}</span>
                                    <div class="flex-1">
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ $outfit->description }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $slots[$outfit->slot_name]['label'] ?? $outfit->slot_name }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
    <!-- Generate Modal -->
    @if($showGenerateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="generate-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeGenerateModals"></div>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <span>✨</span> Generate Outfits with AI
                        </h3>

                        <!-- Count Selector -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                How many outfits?
                            </label>
                            <select wire:model="generateCount"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}">{{ $i }} outfit{{ $i > 1 ? 's' : '' }}</option>
                                @endfor
                            </select>
                        </div>

                        <!-- Style Tags -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Select Style Tags:
                            </label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($predefinedTags as $tag)
                                    <button type="button"
                                            wire:click="toggleTag('{{ $tag }}')"
                                            class="px-3 py-1 text-sm rounded-full transition-colors
                                                   {{ in_array($tag, $selectedTags)
                                                      ? 'bg-purple-600 text-white'
                                                      : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                                        {{ $tag }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Custom Tags -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Custom Tags:
                            </label>
                            <div class="flex gap-2 mb-2">
                                <input type="text"
                                       wire:model="newCustomTag"
                                       wire:keydown.enter.prevent="addCustomTag"
                                       placeholder="Enter custom tag..."
                                       class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                <button type="button"
                                        wire:click="addCustomTag"
                                        class="px-4 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">
                                    + Add
                                </button>
                            </div>
                            @if(!empty($customTags))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($customTags as $tag)
                                        <span class="inline-flex items-center gap-1 px-3 py-1 text-sm bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 rounded-full">
                                            {{ $tag }}
                                            <button type="button"
                                                    wire:click="removeCustomTag('{{ $tag }}')"
                                                    class="hover:text-indigo-600 dark:hover:text-indigo-300">
                                                ×
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if($isGenerating)
                            <div class="text-center py-4">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-purple-500 border-t-transparent"></div>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Generating with AI... (5-10 seconds)</p>
                            </div>
                        @endif
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button"
                                wire:click="generateWithAI"
                                :disabled="$isGenerating"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            Generate →
                        </button>
                        <button type="button"
                                wire:click="closeGenerateModals"
                                :disabled="$isGenerating"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Review Modal -->
    @if($showReviewModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="review-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 max-h-[80vh] overflow-y-auto">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Review Generated Outfits ({{ count($generatedOutfits) }})
                        </h3>

                        <div class="space-y-3">
                            @foreach($generatedOutfits as $index => $outfit)
                                @if($editingGenerated === $index)
                                    <!-- Edit Mode -->
                                    <div class="p-4 border-2 border-purple-500 dark:border-purple-400 rounded-lg bg-purple-50 dark:bg-purple-900/20">
                                        <div class="mb-3">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                            <textarea wire:model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md focus:ring-purple-500 focus:border-purple-500"></textarea>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3 mb-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upper Body</label>
                                                <input type="text" wire:model="form.upper_body" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lower Body</label>
                                                <input type="text" wire:model="form.lower_body" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md">
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="saveEditedGenerated" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">Save</button>
                                            <button wire:click="cancelEditGenerated" class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">Cancel</button>
                                        </div>
                                    </div>
                                @else
                                    <!-- View Mode -->
                                    <div class="flex items-start gap-3 p-4 border rounded-lg
                                                {{ in_array($index, $selectedForSave)
                                                   ? 'border-purple-500 dark:border-purple-400 bg-purple-50 dark:bg-purple-900/20'
                                                   : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700' }}">
                                        <input type="checkbox"
                                               wire:click="toggleOutfitForSave({{ $index }})"
                                               {{ in_array($index, $selectedForSave) ? 'checked' : '' }}
                                               class="mt-1 h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">

                                        <div class="flex-1">
                                            <div class="flex items-start justify-between mb-2">
                                                <p class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $outfit['description'] }}</p>
                                                <label class="flex items-center ml-2">
                                                    <input type="radio"
                                                           name="primary_outfit"
                                                           wire:click="setPrimaryGenerated({{ $index }})"
                                                           {{ $primaryIndex === $index ? 'checked' : '' }}
                                                           class="h-4 w-4 text-yellow-600 focus:ring-yellow-500">
                                                    <span class="ml-1 text-xs text-gray-600 dark:text-gray-400">Primary</span>
                                                </label>
                                            </div>
                                            <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                                @if($outfit['upper_body'])
                                                    <div><strong>Upper:</strong> {{ $outfit['upper_body'] }}</div>
                                                @endif
                                                @if($outfit['lower_body'])
                                                    <div><strong>Lower:</strong> {{ $outfit['lower_body'] }}</div>
                                                @endif
                                                @if($outfit['footwear'])
                                                    <div><strong>Footwear:</strong> {{ $outfit['footwear'] }}</div>
                                                @endif
                                                @if($outfit['accessories'])
                                                    <div><strong>Accessories:</strong> {{ $outfit['accessories'] }}</div>
                                                @endif
                                            </div>
                                            @if(!empty($outfit['tags']))
                                                <div class="flex flex-wrap gap-1 mt-2">
                                                    @foreach($outfit['tags'] as $tag)
                                                        <span class="px-2 py-0.5 text-xs bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 rounded">{{ $tag }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div class="mt-2">
                                                <button wire:click="editGeneratedOutfit({{ $index }})" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">Edit</button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button"
                                wire:click="saveGeneratedOutfits"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Save Selected ({{ count($selectedForSave) }})
                        </button>
                        <button type="button"
                                wire:click="closeGenerateModals"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                            ← Back
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
