<?php

namespace App\Console\Commands;

use App\Models\MemoryTag;
use App\Models\Persona;
use App\Models\WardrobeItem;
use Illuminate\Console\Command;

class MigrateOutfitsFromMemory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-outfits-from-memory
                            {--dry-run : Preview migration without saving}
                            {--delete-old : Delete old memory tags after successful migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate outfit data from memory_tags to wardrobe_items table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $deleteOld = $this->option('delete-old');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be saved');
            $this->newLine();
        }

        // Find all memory tags with outfit categories
        $outfitTags = MemoryTag::whereIn('category', ['daily_outfit', 'night_outfit'])
            ->with('persona')
            ->get();

        if ($outfitTags->isEmpty()) {
            $this->info('✅ No outfit memory tags found. Nothing to migrate.');
            return Command::SUCCESS;
        }

        $this->info("📦 Found {$outfitTags->count()} outfit memory tags to migrate");
        $this->newLine();

        $migratedCount = 0;
        $skippedCount = 0;
        $deletedCount = 0;

        $progressBar = $this->output->createProgressBar($outfitTags->count());
        $progressBar->start();

        foreach ($outfitTags as $tag) {
            $persona = $tag->persona;

            if (!$persona) {
                $skippedCount++;
                $progressBar->advance();
                continue;
            }

            // Map category to slot
            $slotName = $this->mapCategoryToSlot($tag->category);

            // Check if wardrobe item already exists
            $exists = WardrobeItem::where('persona_id', $persona->id)
                ->where('slot_name', $slotName)
                ->where('description', $tag->value)
                ->exists();

            if ($exists) {
                $skippedCount++;
                $progressBar->advance();
                continue;
            }

            if (!$dryRun) {
                // Create wardrobe item
                WardrobeItem::create([
                    'persona_id' => $persona->id,
                    'slot_name' => $slotName,
                    'description' => $tag->value,
                    'is_primary' => true,
                ]);

                // Delete old memory tag if requested
                if ($deleteOld) {
                    $tag->delete();
                    $deletedCount++;
                }
            }

            $migratedCount++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Migration Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Migrated: {$migratedCount}");
        $this->info("⏭️  Skipped (already exists): {$skippedCount}");

        if ($deleteOld && !$dryRun) {
            $this->info("🗑️  Deleted old tags: {$deletedCount}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            $this->info('✨ Migration completed successfully!');
        }

        return Command::SUCCESS;
    }

    /**
     * Map memory tag category to wardrobe slot name
     */
    private function mapCategoryToSlot(string $category): string
    {
        return match ($category) {
            'daily_outfit' => 'casual_daytime',
            'night_outfit' => 'casual_nighttime',
            default => 'casual_daytime',
        };
    }
}
