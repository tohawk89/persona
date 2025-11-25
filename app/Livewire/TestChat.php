<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\GeminiBrainService;
use Illuminate\Support\Facades\Auth;

class TestChat extends Component
{
    public $chatHistory = [];
    public $inputMessage = '';
    public $persona;
    public $loading = false;
    public $contextImported = false;

    protected $rules = [
        'inputMessage' => 'required|string|min:1|max:1000',
    ];

    public function mount()
    {
        $this->persona = Auth::user()->persona;

        if (!$this->persona) {
            session()->flash('error', 'Please create a persona first.');
            return redirect()->route('persona.manager');
        }

        // Check if there's a triggered event from schedule
        if (session()->has('trigger_event_id')) {
            $this->contextImported = true;
            $eventType = session('trigger_event_type');
            $eventPrompt = session('trigger_event_prompt');
            $eventTime = session('trigger_event_time');

            // Add context info message
            $this->chatHistory[] = [
                'role' => 'system',
                'content' => "🧪 Testing Scheduled Event\nType: " . ($eventType === 'image_generation' ? '🖼️ Image Generation' : '💬 Text Message') . "\nScheduled: {$eventTime}\n\n⚡ Simulating event trigger...",
                'timestamp' => now()->format('H:i'),
            ];

            $this->dispatch('chat-message-sent');

            try {
                // Generate response using the event prompt as context
                $service = app(GeminiBrainService::class);

                // Build the simulated response based on event type
                if ($eventType === 'image_generation') {
                    // For image generation, manually generate the image
                    $imageUrl = $service->generateImage($eventPrompt, $this->persona);

                    if ($imageUrl) {
                        $simulatedMessage = "Here's something for you! [IMAGE: {$imageUrl}]";
                    } else {
                        $simulatedMessage = "I tried to share an image with you, but something went wrong 😔";
                    }
                } else {
                    // For text, use Gemini to generate natural response based on the prompt
                    $simulatedMessage = $service->generateTestResponse(
                        $this->persona,
                        "Generate a message with this context: {$eventPrompt}",
                        []
                    );
                }

                // Add the triggered event response
                $this->chatHistory[] = [
                    'role' => 'bot',
                    'content' => $simulatedMessage,
                    'timestamp' => now()->format('H:i'),
                ];
            } catch (\Exception $e) {
                $this->chatHistory[] = [
                    'role' => 'system',
                    'content' => "❌ Error triggering event: " . $e->getMessage(),
                    'timestamp' => now()->format('H:i'),
                ];
            }

            // Clear session
            session()->forget(['trigger_event_id', 'trigger_event_type', 'trigger_event_prompt', 'trigger_event_time']);
        }
    }

    public function sendMessage()
    {
        $this->validate();

        if (empty($this->inputMessage)) {
            return;
        }

        $userMessage = $this->inputMessage;
        $this->inputMessage = '';

        // Add user message to chat history
        $this->chatHistory[] = [
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now()->format('H:i'),
        ];

        // Force Livewire to update the view
        $this->dispatch('chat-message-sent');

        $this->loading = true;

        try {
            // Generate response using GeminiBrainService
            $service = app(GeminiBrainService::class);
            $response = $service->generateTestResponse(
                $this->persona,
                $userMessage,
                $this->chatHistory
            );

            // Add bot response to chat history
            $this->chatHistory[] = [
                'role' => 'bot',
                'content' => $response,
                'timestamp' => now()->format('H:i'),
            ];
        } catch (\Exception $e) {
            $this->chatHistory[] = [
                'role' => 'bot',
                'content' => 'Error: ' . $e->getMessage(),
                'timestamp' => now()->format('H:i'),
            ];
        }

        $this->loading = false;

        // Scroll to bottom of chat after bot response
        $this->dispatch('chat-message-sent');
    }

    public function clearChat()
    {
        $this->chatHistory = [];
        session()->flash('success', 'Chat history cleared.');
    }

    public function render()
    {
        return view('livewire.test-chat')->layout('layouts.app');
    }
}
