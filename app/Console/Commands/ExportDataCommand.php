<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\{Message, MemoryTag, Persona};
use Carbon\Carbon;

class ExportDataCommand extends Command
{
    protected $signature = 'app:export-data {--days=2 : Number of days to look back}';
    protected $description = 'Export chat logs and memory tags for analysis';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        
        $this->info("📊 Exporting data from the last {$days} days...");
        $this->newLine();

        // Get the active persona
        $persona = Persona::where('is_active', true)->first();
        
        if (!$persona) {
            $this->error('❌ No active persona found');
            return Command::FAILURE;
        }

        // Build the export content
        $content = $this->buildExportContent($persona, $days);

        // Save to file
        $filename = 'system_export_' . now()->format('Y-m-d_His') . '.md';
        $path = "exports/{$filename}";
        
        Storage::disk('public')->put($path, $content);
        
        $fullPath = storage_path("app/public/{$path}");
        $publicUrl = asset("storage/{$path}");

        $this->newLine();
        $this->info('✅ Export completed successfully!');
        $this->newLine();
        $this->line("📁 File saved to: <fg=cyan>{$fullPath}</>");
        $this->line("🌐 Public URL: <fg=cyan>{$publicUrl}</>");
        $this->newLine();
        
        // Display statistics
        $messageCount = Message::where('persona_id', $persona->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
        
        $memoryCount = MemoryTag::where('persona_id', $persona->id)->count();
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Messages Exported', $messageCount],
                ['Memory Tags Exported', $memoryCount],
                ['Export Period', "{$days} days"],
            ]
        );

        return Command::SUCCESS;
    }

    private function buildExportContent(Persona $persona, int $days): string
    {
        $exportDate = now()->format('Y-m-d H:i:s');
        $cutoffDate = now()->subDays($days);
        
        $content = [];
        
        // Header
        $content[] = "# System Export (Last {$days} Days)";
        $content[] = "";
        $content[] = "**Generated:** {$exportDate}";
        $content[] = "**Persona:** {$persona->name}";
        $content[] = "**Period:** " . $cutoffDate->format('Y-m-d H:i') . " to " . now()->format('Y-m-d H:i');
        $content[] = "";
        $content[] = "---";
        $content[] = "";

        // Section 1: Memory Brain
        $content[] = "## Memory Brain (Current State)";
        $content[] = "";
        $content[] = $this->buildMemorySection($persona);
        
        // Section 2: Chat Logs
        $content[] = "## Chat Logs";
        $content[] = "";
        $content[] = $this->buildChatLogsSection($persona, $days);

        return implode("\n", $content);
    }

    private function buildMemorySection(Persona $persona): string
    {
        $memoryTags = MemoryTag::where('persona_id', $persona->id)
            ->orderBy('category')
            ->orderBy('target')
            ->get();

        if ($memoryTags->isEmpty()) {
            return "_No memory tags stored yet._\n";
        }

        $content = [];
        
        // Group by target
        $userTags = $memoryTags->where('target', 'user');
        $selfTags = $memoryTags->where('target', 'self');

        // User facts
        if ($userTags->isNotEmpty()) {
            $content[] = "### 👤 User Facts";
            $content[] = "";
            
            foreach ($userTags->groupBy('category') as $category => $tags) {
                $content[] = "**{$category}:**";
                foreach ($tags as $tag) {
                    $contextInfo = $tag->context ? " _(Context: {$tag->context})_" : "";
                    $content[] = "- {$tag->value}{$contextInfo}";
                }
                $content[] = "";
            }
        }

        // Self facts
        if ($selfTags->isNotEmpty()) {
            $content[] = "### 🤖 Persona Facts";
            $content[] = "";
            
            foreach ($selfTags->groupBy('category') as $category => $tags) {
                $content[] = "**{$category}:**";
                foreach ($tags as $tag) {
                    $contextInfo = $tag->context ? " _(Context: {$tag->context})_" : "";
                    $content[] = "- {$tag->value}{$contextInfo}";
                }
                $content[] = "";
            }
        }

        return implode("\n", $content);
    }

    private function buildChatLogsSection(Persona $persona, int $days): string
    {
        $messages = Message::where('persona_id', $persona->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($messages->isEmpty()) {
            return "_No messages in the selected period._\n";
        }

        $content = [];
        $content[] = "### Conversation History";
        $content[] = "";
        $content[] = "_Total messages: {$messages->count()}_";
        $content[] = "";
        $content[] = "```";

        $currentDate = null;

        foreach ($messages as $message) {
            $messageDate = $message->created_at->format('Y-m-d');
            
            // Add date separator
            if ($messageDate !== $currentDate) {
                if ($currentDate !== null) {
                    $content[] = "";
                }
                $content[] = "=== " . $message->created_at->format('l, F j, Y') . " ===";
                $content[] = "";
                $currentDate = $messageDate;
            }

            // Format message
            $timestamp = $message->created_at->format('H:i');
            $sender = $message->sender_type === 'user' ? 'USER' : 'BOT';
            $messageContent = $message->content ?? '[No text content]';
            
            // Handle multi-line content
            $lines = explode("\n", $messageContent);
            $firstLine = array_shift($lines);
            
            $imagePath = $message->image_path ? " (Image: {$message->image_path})" : "";
            
            $content[] = "[{$timestamp}] [{$sender}]: {$firstLine}{$imagePath}";
            
            // Add continuation lines with proper indentation
            foreach ($lines as $line) {
                $content[] = "              {$line}";
            }
        }

        $content[] = "```";
        $content[] = "";

        // Add statistics
        $userMessages = $messages->where('sender_type', 'user')->count();
        $botMessages = $messages->where('sender_type', 'bot')->count();
        $messagesWithImages = $messages->whereNotNull('image_path')->count();

        $content[] = "### Statistics";
        $content[] = "";
        $content[] = "| Metric | Count |";
        $content[] = "|--------|-------|";
        $content[] = "| User Messages | {$userMessages} |";
        $content[] = "| Bot Messages | {$botMessages} |";
        $content[] = "| Messages with Images | {$messagesWithImages} |";
        $content[] = "| **Total** | **{$messages->count()}** |";
        $content[] = "";

        return implode("\n", $content);
    }
}
