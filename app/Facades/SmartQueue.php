<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isUserActive(\App\Models\User $user)
 * @method static bool shouldExecuteEvent(\App\Models\EventSchedule $event)
 * @method static bool processEvent(\App\Models\EventSchedule $event, callable $executeCallback)
 * @method static void rescheduleEvent(\App\Models\EventSchedule $event, ?int $delayMinutes = null)
 * @method static void updateUserInteraction(\App\Models\User $user)
 * @method static bool isWithinActiveHours(\App\Models\Persona $persona)
 *
 * @see \App\Services\SmartQueueService
 */
class SmartQueue extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\SmartQueueService::class;
    }
}
