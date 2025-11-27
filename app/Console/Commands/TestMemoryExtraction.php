<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{User, Message, MemoryTag};
use App\Facades\GeminiBrain;

class TestMemoryExtraction extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:test-memory
                            {--user-id=1 : The user ID to test memory extraction for}
                            {--messages=10 : Number of recent messages to analyze}';

    /**
     * The console command description.
     */
    protected $description = 'Test memory extraction with add/update/remove operations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $messageCount = $this->option('messages');

        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $persona = $user->persona;

        if (!$persona) {
            $this->error("User has no persona configured");
            return 1;
        }

        $this->info("Testing memory extraction for user: {$user->name}");
        $this->newLine();

        // Show current memory state
        $this->info("📚 CURRENT MEMORY STATE:");
        $currentTags = $persona->memoryTags()->get(['id', 'target', 'category', 'value']);

        if ($currentTags->isEmpty()) {
            $this->warn("  No memory tags yet");
        } else {
            $this->table(
                ['ID', 'Target', 'Category', 'Value'],
                $currentTags->map(fn($tag) => [
                    $tag->id,
                    $tag->target,
                    $tag->category,
                    substr($tag->value, 0, 50)
                ])
            );
        }

        $this->newLine();

        // Get recent messages
        $recentMessages = Message::where('user_id', $user->id)
            ->latest()
            ->take($messageCount)
            ->get();

        if ($recentMessages->isEmpty()) {
            $this->error("No messages found for this user");
            return 1;
        }

        $this->info("📝 ANALYZING {$recentMessages->count()} RECENT MESSAGES...");
        $this->newLine();

        // Call memory extraction
        $this->info("🧠 Calling Gemini for memory analysis...");
        $changes = GeminiBrain::extractMemoryTags($recentMessages, $persona);

        // Display results
        $this->newLine();
        $this->info("✨ GEMINI ANALYSIS RESULTS:");
        $this->newLine();

        // ADD operations
        $addCount = count($changes['add'] ?? []);
        $this->info("➕ ADD ({$addCount} new facts):");
        if ($addCount > 0) {
            foreach ($changes['add'] as $tag) {
                $this->line("  • [{$tag['target']}] {$tag['category']}: {$tag['value']}");
                if (isset($tag['context'])) {
                    $this->line("    Context: {$tag['context']}", 'comment');
                }
            }
        } else {
            $this->line("  (none)");
        }
        $this->newLine();

        // UPDATE operations
        $updateCount = count($changes['update'] ?? []);
        $this->info("🔄 UPDATE ({$updateCount} modifications):");
        if ($updateCount > 0) {
            foreach ($changes['update'] as $update) {
                $existing = MemoryTag::find($update['id']);
                if ($existing) {
                    $this->line("  • ID {$update['id']}: {$existing->category}");
                    $this->line("    OLD: {$existing->value}", 'comment');
                    $this->line("    NEW: {$update['value']}", 'info');
                }
            }
        } else {
            $this->line("  (none)");
        }
        $this->newLine();

        // REMOVE operations
        $removeCount = count($changes['remove'] ?? []);
        $this->info("🗑️  REMOVE ({$removeCount} deletions):");
        if ($removeCount > 0) {
            foreach ($changes['remove'] as $tagId) {
                $existing = MemoryTag::find($tagId);
                if ($existing) {
                    $this->line("  • ID {$tagId}: [{$existing->target}] {$existing->category} = {$existing->value}");
                }
            }
        } else {
            $this->line("  (none)");
        }
        $this->newLine();

        // Ask for confirmation
        if (!$this->confirm('Apply these changes to the database?', true)) {
            $this->warn('Changes discarded');
            return 0;
        }

        // Execute changes
        $this->info("💾 Applying changes...");

        // ADD
        foreach ($changes['add'] ?? [] as $tagData) {
            MemoryTag::create([
                'persona_id' => $persona->id,
                'target' => $tagData['target'],
                'category' => $tagData['category'],
                'value' => $tagData['value'],
                'context' => $tagData['context'] ?? "Test extraction on " . now()->format('Y-m-d H:i'),
            ]);
        }

        // UPDATE
        foreach ($changes['update'] ?? [] as $updateData) {
            $tag = MemoryTag::find($updateData['id']);
            if ($tag && $tag->persona_id === $persona->id) {
                $tag->update([
                    'value' => $updateData['value'],
                    'context' => $updateData['context'] ?? "Test update on " . now()->format('Y-m-d H:i'),
                ]);
            }
        }

        // REMOVE
        if (!empty($changes['remove'])) {
            $tagsToRemove = MemoryTag::whereIn('id', $changes['remove'])
                ->where('persona_id', $persona->id)
                ->pluck('id')
                ->toArray();
            MemoryTag::destroy($tagsToRemove);
        }

        $this->newLine();
        $this->info("✅ Memory extraction completed!");
        $this->line("  Added: {$addCount} | Updated: {$updateCount} | Removed: {$removeCount}");

        return 0;
    }
}
