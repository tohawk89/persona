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

            // Extract memory tags from conversation
            $extractedTags = GeminiBrain::extractMemoryTags(
                $recentMessages,
                $persona->system_prompt
            );

            // Save new memory tags
            $savedCount = 0;
            foreach ($extractedTags as $tagData) {
                // Validate required fields
                if (!isset($tagData['target']) || !isset($tagData['category']) || !isset($tagData['value'])) {
                    Log::warning('ExtractMemoryTags: Skipping incomplete tag', ['tag' => $tagData]);
                    continue;
                }

                // Check if similar tag already exists (update instead of duplicate)
                $existing = MemoryTag::where('persona_id', $persona->id)
                    ->where('category', $tagData['category'])
                    ->where('target', $tagData['target'])
                    ->first();

                if ($existing) {
                    // Update existing tag
                    $existing->update([
                        'value' => $tagData['value'],
                        'context' => $tagData['context'] ?? "Updated on " . now()->format('Y-m-d H:i'),
                    ]);
                } else {
                    // Create new tag
                    MemoryTag::create([
                        'persona_id' => $persona->id,
                        'target' => $tagData['target'],
                        'category' => $tagData['category'],
                        'value' => $tagData['value'],
                        'context' => $tagData['context'] ?? "Extracted on " . now()->format('Y-m-d H:i'),
                    ]);
                    $savedCount++;
                }
            }

            Log::info('ExtractMemoryTags: Memory extraction completed', [
                'user_id' => $this->user->id,
                'extracted_count' => count($extractedTags),
                'new_tags_saved' => $savedCount,
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
