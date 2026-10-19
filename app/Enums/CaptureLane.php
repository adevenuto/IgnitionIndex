<?php

namespace App\Enums;

use App\Support\Capture\FuelLane;
use App\Support\Capture\LaneDefinition;

/**
 * The entry types assisted capture can read for. Each case names the lane's
 * definition — its fields, what to look for, and what to double-check — so a
 * new lane is a new case and one class, and nothing else in the pipeline moves.
 */
enum CaptureLane: string
{
    case Fuel = 'fuel';

    public function definition(): LaneDefinition
    {
        return match ($this) {
            self::Fuel => new FuelLane,
        };
    }
}
