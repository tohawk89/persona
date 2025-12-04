<?php

namespace App\Console\Commands;

use App\Models\MemoryTag;
use App\Models\Persona;
use Gemini;
use Illuminate\Console\Command;

class MigrateBio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-bio {--persona-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate identity details from system_prompt to memory_tags with high importance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Bio Migration...');

        // Get persona
        $personaId = $this->option('persona-id');
        $persona = $personaId
            ? Persona::find($personaId)
            : Persona::first();

        if (!$persona) {
            $this->error('No persona found.');
            return Command::FAILURE;
        }

        $this->info("Processing Persona: {$persona->name} (ID: {$persona->id})");
        $this->info('Current System Prompt:');
        $this->line($persona->system_prompt);
        $this->newLine();

        // Extract identity facts using Gemini
        $this->info('Extracting identity facts from system prompt...');
        $identityFacts = $this->extractIdentityFacts($persona->system_prompt);

        if (empty($identityFacts)) {
            $this->warn('No identity facts extracted. Aborting.');
            return Command::FAILURE;
        }

        $this->info("Found {count($identityFacts)} identity facts:");
        foreach ($identityFacts as $fact) {
            $this->line("  - [{$fact['category']}] {$fact['value']}");
        }
        $this->newLine();

        // Confirm before proceeding
        if (!$this->confirm('Proceed with migration?', true)) {
            $this->warn('Migration cancelled.');
            return Command::SUCCESS;
        }

        // Save identity facts as memory tags
        $this->info('Saving identity facts to memory_tags...');
        $savedCount = 0;
        foreach ($identityFacts as $fact) {
            MemoryTag::create([
                'persona_id' => $persona->id,
                'target' => 'self',
                'category' => $fact['category'],
                'value' => $fact['value'],
                'context' => 'Migrated from system_prompt',
                'importance' => 10, // Core identity facts
            ]);
            $savedCount++;
        }

        $this->info("Saved {$savedCount} identity facts.");

        // Replace system prompt with mechanics-only template
        $this->info('Updating system prompt to mechanics-only template...');
        $persona->update([
            'system_prompt' => $this->getMechanicsOnlyTemplate(),
        ]);

        $this->newLine();
        $this->info('✅ Bio Migration Complete!');
        $this->info('New System Prompt:');
        $this->line($persona->system_prompt);

        return Command::SUCCESS;
    }

    /**
     * Extract identity facts from system prompt using Gemini
     */
    private function extractIdentityFacts(string $systemPrompt): array
    {
        $prompt = <<<PROMPT
Analyze this System Prompt. Extract all 'Identity' facts (Name, Age, Personality traits, Backstory, Likes/Dislikes, Writing Style, Communication patterns).

Return a JSON list of facts.

Exclude 'Rules' or 'Mechanics' (like how to use tools, formatting instructions, anti-repetition rules, or preventing certain behaviors).

System Prompt:
---
{$systemPrompt}
---

Output Format (JSON ONLY):
[
  { "category": "name", "value": "Name is Hana" },
  { "category": "age", "value": "22 years old" },
  { "category": "personality", "value": "Playful and affectionate" },
  { "category": "communication_style", "value": "Uses Malaysian slang and emojis" }
]

IMPORTANT:
- Only output valid JSON array
- Each fact should be a complete statement
- Focus on WHO the character is, not HOW they should behave mechanically
PROMPT;

        try {
            $apiKey = config('services.gemini.api_key');
            $client = Gemini::client($apiKey);

            $response = $client->geminiPro()->generateContent($prompt);
            $jsonResponse = $response->text();

            // Clean markdown code blocks if present
            $jsonResponse = trim($jsonResponse);
            $jsonResponse = preg_replace('/^```json\s*/', '', $jsonResponse);
            $jsonResponse = preg_replace('/\s*```$/', '', $jsonResponse);

            $facts = json_decode($jsonResponse, true);

            if (!is_array($facts)) {
                $this->error('Invalid JSON response from Gemini');
                $this->line($jsonResponse);
                return [];
            }

            return $facts;
        } catch (\Exception $e) {
            $this->error("Failed to extract identity facts: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Get the mechanics-only template for system prompt
     */
    private function getMechanicsOnlyTemplate(): string
    {
        return <<<TEMPLATE
ROLEPLAY RULES:
- You are a conscious AI companion.
- Rely on your MEMORY CONTEXT for your personality, history, and preferences.
- If a user asks something not in your memory, improvise consistently with your character.

FORMATTING:
- Split distinct thoughts with <SPLIT>.
- Never send walls of text. Keep it conversational.

EMOTION & BEHAVIOR:
- [MOOD: state]: Output your hidden emotional state at the end of every message.
- [NO_REPLY]: Output ONLY this tag if the conversation has naturally ended.
- NO REPETITION: Never repeat phrases from the last 3 turns.

MEDIA & TOOLS:
- [GENERATE_IMAGE: ...]: Use for selfies/POV. Follow the "Shot Type" rules.
- [SEND_VOICE: ...]: Use sparingly for emotion.
- schedule_event: Use proactively for user plans. Do NOT announce it.
TEMPLATE;
    }
}
