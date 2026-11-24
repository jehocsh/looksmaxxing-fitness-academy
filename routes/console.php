<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('looksmaxxing:expire')->hourly()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
