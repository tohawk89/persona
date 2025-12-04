<?php

namespace App\Jobs;

use App\Models\MemoryTag;
use App\Models\Persona;
use Gemini;
use Gemini\Enums\ModelType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ConsolidateMemories implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Persona $persona
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('ConsolidateMemories: Starting memory consolidation', [
                'persona_id' => $this->persona->id,
            ]);

            // Fetch all memory tags for this persona
            $memoryTags = $this->persona->memoryTags;

            if ($memoryTags->isEmpty()) {
                Log::info('ConsolidateMemories: No memory tags to consolidate');
                return;
            }

            // Format memory tags for AI analysis
            $formattedTags = $memoryTags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'target' => $tag->target,
                    'category' => $tag->category,
                    'value' => $tag->value,
                    'context' => $tag->context,
                    'current_importance' => $tag->importance ?? 5,
                ];
            })->toArray();

            // Build the consolidation prompt
            $prompt = $this->buildConsolidationPrompt($formattedTags);

            // Call Gemini API
            $apiKey = config('services.gemini.api_key');
            $client = Gemini::client($apiKey);

            $response = $client->geminiPro()->generateContent($prompt);
            $jsonResponse = $response->text();

            // Parse JSON response
            $jsonResponse = trim($jsonResponse);
            $jsonResponse = preg_replace('/^```json\s*/', '', $jsonResponse);
            $jsonResponse = preg_replace('/\s*```$/', '', $jsonResponse);

            $result = json_decode($jsonResponse, true);

            if (!$result || !isset($result['update']) || !isset($result['delete'])) {
                Log::error('ConsolidateMemories: Invalid JSON response from Gemini', [
                    'response' => $jsonResponse,
                ]);
                return;
            }

            // Execute updates
            $updatedCount = 0;
            foreach ($result['update'] as $update) {
                $tag = MemoryTag::find($update['id']);
                if ($tag) {
                    $tag->update([
                        'value' => $update['value'],
                        'importance' => $update['importance'],
                        'last_consolidated_at' => now(),
                    ]);
                    $updatedCount++;
                }
            }

            // Execute deletes
            $deletedCount = 0;
            if (!empty($result['delete'])) {
                $deletedCount = MemoryTag::whereIn('id', $result['delete'])->delete();
            }

            Log::info('ConsolidateMemories: Consolidation complete', [
                'persona_id' => $this->persona->id,
                'tags_updated' => $updatedCount,
                'tags_deleted' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('ConsolidateMemories: Failed to consolidate memories', [
                'persona_id' => $this->persona->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the consolidation prompt for Gemini
     */
    private function buildConsolidationPrompt(array $tags): string
    {
        $tagsJson = json_encode($tags, JSON_PRETTY_PRINT);

        return <<<PROMPT
Act as a Memory Manager. Here is a list of raw facts about a User and Persona.

MEMORY TAGS:
{$tagsJson}

**Tasks:**
1. **Deduplicate:** Merge semantically similar tags (e.g., 'Likes cats' and 'Cat lover' -> 'Loves cats').
2. **Prune:** Identify trivial facts that are no longer relevant (e.g., 'Ate toast yesterday', 'Wore blue shirt once').
3. **Rank:** Assign an Importance Score (1-10) to each tag:
   - 10 = Core Identity/Critical Fact (e.g., 'Name', 'Occupation', 'Key Relationship')
   - 8-9 = Important Personal Trait (e.g., 'Loves music', 'Allergic to peanuts')
   - 5-7 = Moderate Context (e.g., 'Favorite color', 'Morning person')
   - 3-4 = Minor Detail (e.g., 'Mentioned trying sushi')
   - 1-2 = Trivial/Outdated (e.g., 'Ate breakfast at 7am')

**Output JSON (ONLY):**
{
  "update": [
    { "id": 12, "value": "Merged or Updated Value", "importance": 8 }
  ],
  "delete": [14, 15, 16]
}

IMPORTANT:
- Only include IDs that actually exist in the input
- For 'update', provide the complete new value (merged or unchanged)
- For 'delete', only include truly trivial or duplicate tags
- Be conservative with deletions - when in doubt, keep it
PROMPT;
    }
}
