<?php

namespace App\Settings\Tracker;

use App\Databases\TrackerEventTable;

/**
 * Builds the public maintenance timeline from remote history, block sync, and tracker events.
 */
final class TrackerMaintenanceTimelineBuilder
{
    public static function build(array $remoteHistory, array $blockLog, array $events, int $limit): array
    {
        $items = [];

        foreach ($remoteHistory as $row) {
            if (!is_array($row) || empty($row['time'])) {
                continue;
            }

            $items[] = TrackerTimelinePresenter::makeItem(
                (string) $row['time'],
                TrackerTimelinePresenter::maintenanceActionLabel((string) ($row['action'] ?? 'maintenance')),
                (string) ($row['message'] ?? __('Đã ghi nhận thao tác bảo trì.', 'laca')),
                (string) ($row['status'] ?? 'success')
            );
        }

        foreach (array_slice($blockLog, 0, 30) as $row) {
            if (!is_array($row) || empty($row['time'])) {
                continue;
            }

            $items[] = TrackerTimelinePresenter::makeItem(
                (string) $row['time'],
                __('Cập nhật block giao diện', 'laca'),
                wp_strip_all_tags((string) ($row['message'] ?? __('Đã sync block từ LacaDev.', 'laca'))),
                'success'
            );
        }

        foreach ($events as $event) {
            $channel = sanitize_key((string) ($event['channel'] ?? 'tracker'));
            if ($channel === 'heartbeat') {
                continue;
            }

            $payload = TrackerEventTable::decodeJsonColumn($event['payload'] ?? '');
            foreach ((array) ($payload['logs'] ?? []) as $log) {
                if (!is_array($log)) {
                    continue;
                }

                $message = TrackerTimelinePresenter::publicMessage($log, $event);
                if ($message === '') {
                    continue;
                }

                $items[] = TrackerTimelinePresenter::makeItem(
                    (string) ($event['delivered_at'] ?: $event['created_at']),
                    TrackerTimelinePresenter::typeLabel((string) ($log['type'] ?? $event['event_type'] ?? 'other')),
                    $message,
                    (string) ($event['status'] ?? 'queued')
                );
            }
        }

        $unique = [];
        foreach ($items as $item) {
            $key = md5($item['time'] . '|' . $item['title'] . '|' . $item['message']);
            $unique[$key] = $item;
        }

        $items = array_values($unique);
        usort($items, static fn(array $a, array $b): int => strtotime($b['time']) <=> strtotime($a['time']));

        return array_slice($items, 0, $limit);
    }
}
