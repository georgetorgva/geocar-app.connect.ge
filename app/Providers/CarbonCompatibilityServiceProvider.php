<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class CarbonCompatibilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Restore old Carbon 2 behaviour: ->add('30 days'), ->sub('1 month'), etc.

        Carbon::macro('add', function ($value, $unit = null) {
            if (is_string($value) && $unit === null) {
                // "30 days", "2 weeks", "1 month", etc.
                return $this->addUnitFromString($value);
            }
            // add(30, 'day') or add(5)
            return $this->rawAddUnit($unit ?? 'second', $value, 1);
        });

        Carbon::macro('sub', function ($value, $unit = null) {
            if (is_string($value) && $unit === null) {
                return $this->subUnitFromString($value);
            }
            return $this->rawAddUnit($unit ?? 'second', $value, -1);
        });
    }
}
