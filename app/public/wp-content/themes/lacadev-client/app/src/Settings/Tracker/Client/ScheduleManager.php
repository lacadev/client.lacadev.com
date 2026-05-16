<?php

namespace App\Settings\Tracker\Client;

class ScheduleManager
{
    /**
     * @param array<string, array<string, mixed>> $schedules
     * @return array<string, array<string, mixed>>
     */
    public function addCronSchedules(array $schedules): array
    {
        $schedules['laca_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Every 5 minutes', 'laca'),
        ];

        $schedules['laca_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Weekly', 'laca'),
        ];

        return $schedules;
    }

    public function nextWeeklySummaryRun(): int
    {
        $nextRun = strtotime('next monday 01:30:00 UTC');

        return $nextRun ? (int) $nextRun : time() + WEEK_IN_SECONDS;
    }

    public function ensureRecurringEvent(string $hook, string $schedule, int $timestamp): void
    {
        if (function_exists('wp_get_scheduled_event')) {
            $event = wp_get_scheduled_event($hook);
            if ($event && ($event->schedule ?? '') === $schedule) {
                return;
            }

            if ($event) {
                wp_clear_scheduled_hook($hook);
            }
        } elseif (wp_next_scheduled($hook)) {
            return;
        }

        wp_schedule_event($timestamp, $schedule, $hook);
    }
}
