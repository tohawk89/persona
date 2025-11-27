<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\{User, Message, MemoryTag};
use App\Facades\GeminiBrain;

class ExtractMemoryTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $persona = $this->user->persona;

            if (!$persona) {
                Log::warning('ExtractMemoryTags: User has no persona', [
                    'user_id' => $this->user->id,
                ]);
                return;
            }

            // Get recent conversation (last 10 messages)
            $recentMessages = Message::where('user_id', $this->user->id)
                ->latest()
                ->take(10)
                ->get();

            if ($recentMessages->isEmpty()) {
                return;
            }

            // Extract memory tags with intelligent add/update/remove operations
            $changes = GeminiBrain::extractMemoryTags(
                $recentMessages,
                $persona
            );

            $addCount = 0;
            $updateCount = 0;
            $removeCount = 0;

            // Process ADD operations
            foreach ($changes['add'] ?? [] as $tagData) {
                // Validate required fields
                if (!isset($tagData['target']) || !isset($tagData['category']) || !isset($tagData['value'])) {
                    Log::warning('ExtractMemoryTags: Skipping incomplete tag', ['tag' => $tagData]);
                    continue;
                }

                // Create new tag
                MemoryTag::create([
                    'persona_id' => $persona->id,
                    'target' => $tagData['target'],
                    'category' => $tagData['category'],
                    'value' => $tagData['value'],
                    'context' => $tagData['context'] ?? "Extracted on " . now()->format('Y-m-d H:i'),
                ]);
                $addCount++;
            }

            // Process UPDATE operations
            foreach ($changes['update'] ?? [] as $updateData) {
                if (!isset($updateData['id']) || !isset($updateData['value'])) {
                    Log::warning('ExtractMemoryTags: Skipping incomplete update', ['update' => $updateData]);
                    continue;
                }

                $tag = MemoryTag::find($updateData['id']);
                if ($tag && $tag->persona_id === $persona->id) {
                    $tag->update([
                        'value' => $updateData['value'],
                        'context' => $updateData['context'] ?? "Updated on " . now()->format('Y-m-d H:i'),
                    ]);
                    $updateCount++;
                } else {
                    Log::warning('ExtractMemoryTags: Tag not found or unauthorized', ['id' => $updateData['id']]);
                }
            }

            // Process REMOVE operations
            $removeIds = $changes['remove'] ?? [];
            if (!empty($removeIds)) {
                // Verify all tags belong to this persona before deletion
                $tagsToRemove = MemoryTag::whereIn('id', $removeIds)
                    ->where('persona_id', $persona->id)
                    ->pluck('id')
                    ->toArray();

                if (!empty($tagsToRemove)) {
                    MemoryTag::destroy($tagsToRemove);
                    $removeCount = count($tagsToRemove);
                }
            }

            Log::info('ExtractMemoryTags: Memory extraction completed', [
                'user_id' => $this->user->id,
                'added' => $addCount,
                'updated' => $updateCount,
                'removed' => $removeCount,
            ]);

        } catch (\Exception $e) {
            Log::error('ExtractMemoryTags: Job failed', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't re-throw - memory extraction is non-critical
        }
    }
}
