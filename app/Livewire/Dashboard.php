<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Persona;
use App\Models\EventSchedule;
use App\Models\MemoryTag;
use App\Models\User;
use App\Facades\GeminiBrain;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public $loading = false;

    public function triggerWakeUpRoutine()
    {
        $this->loading = true;

        $user = Auth::user();
        $persona = $user->persona;

        if (!$persona) {
            session()->flash('error', 'No persona configured for this user.');
            $this->loading = false;
            return;
        }

        try {
            // Generate daily plan with outfit selection
            $planData = GeminiBrain::generateDailyPlan(
                $persona->memoryTags,
                $persona->system_prompt,
                $persona->wake_time,
                $persona->sleep_time
            );

            $events = $planData['events'];
            $dailyOutfit = $planData['daily_outfit'];
            $nightOutfit = $planData['night_outfit'];

            // Save events to database
            foreach ($events as $event) {
                EventSchedule::create([
                    'persona_id' => $persona->id,
                    'scheduled_at' => $event['scheduled_at'],
                    'type' => $event['type'],
                    'context_prompt' => $event['content'],
                    'status' => 'pending',
                ]);
            }

            // Save/Update daily outfit to memory_tags
            if ($dailyOutfit) {
                MemoryTag::updateOrCreate(
                    [
                        'persona_id' => $persona->id,
                        'category' => 'daily_outfit',
                    ],
                    [
                        'target' => 'self',
                        'value' => $dailyOutfit,
                        'context' => 'Morning routine on ' . now()->format('Y-m-d'),
                    ]
                );
            }

            // Save/Update night outfit to memory_tags
            if ($nightOutfit) {
                MemoryTag::updateOrCreate(
                    [
                        'persona_id' => $persona->id,
                        'category' => 'night_outfit',
                    ],
                    [
                        'target' => 'self',
                        'value' => $nightOutfit,
                        'context' => 'Morning routine on ' . now()->format('Y-m-d'),
                    ]
                );
            }

            session()->flash('success', 'Daily plan generated successfully! ' . count($events) . ' events scheduled.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to generate daily plan: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function render()
    {
        $user = Auth::user();

        // 1. Fetch all personas with relationships
        $personas = Persona::where('user_id', $user->id)
            ->with([
                'memoryTags' => function ($query) {
                    $query->where('category', 'current_mood')
                        ->latest()
                        ->limit(1);
                },
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                }
            ])
            ->get()
            ->map(function ($persona) {
                // Calculate is_awake
                $now = \Carbon\Carbon::now();
                $wakeTime = \Carbon\Carbon::parse($persona->wake_time);
                $sleepTime = \Carbon\Carbon::parse($persona->sleep_time);

                $persona->is_awake = $now->between($wakeTime, $sleepTime);

                return $persona;
            });

        // 2. Upcoming events (next 5)
        $upcomingEvents = EventSchedule::whereHas('persona', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'pending')
            ->where('scheduled_at', '>=', now())
            ->with('persona')
            ->orderBy('scheduled_at', 'asc')
            ->limit(5)
            ->get();

        // 3. Life updates (last 24 hours)
        $lifeUpdates = MemoryTag::whereHas('persona', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('updated_at', '>=', now()->subHours(24))
            ->with('persona')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // 4. Usage stats for today
        $todayStart = now()->startOfDay();
        $stats = [
            'messages_count' => \App\Models\Message::whereHas('persona', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->where('created_at', '>=', $todayStart)
                ->count(),

            'photos_count' => \App\Models\Message::whereHas('persona', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->whereNotNull('image_path')
                ->where('created_at', '>=', $todayStart)
                ->count(),

            'voice_count' => \App\Models\Message::whereHas('persona', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->where('content', 'like', '%[AUDIO:%')
                ->where('created_at', '>=', $todayStart)
                ->count(),
        ];

        return view('livewire.dashboard', [
            'personas' => $personas,
            'upcomingEvents' => $upcomingEvents,
            'lifeUpdates' => $lifeUpdates,
            'stats' => $stats,
        ])->layout('layouts.app');
    }
}
