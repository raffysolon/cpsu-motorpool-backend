<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GatewaySyncStatusController extends Controller
{
    public function store(Request $request)
    {
        $reports = $request->validate([
            'devices' => ['required', 'array', 'max:25'],
            'devices.*.device_id' => ['required', 'string', 'max:100', 'distinct', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'devices.*.state' => ['required', 'in:receiving,waiting_for_connection,upload_delayed,needs_review,uploaded'],
            'devices.*.pending_count' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'devices.*.review_count' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ])['devices'];

        $devices = Device::whereIn('device_id', array_column($reports, 'device_id'))
            ->pluck('id', 'device_id');
        $reportedAt = now()->utc()->toDateTimeString();
        $rows = [];
        foreach ($reports as $report) {
            $id = $devices->get($report['device_id']);
            if ($id === null) {
                continue;
            }
            $rows[] = [
                'device_registry_id' => $id,
                'state' => $report['state'],
                'pending_count' => $report['pending_count'],
                'review_count' => $report['review_count'],
                'reported_at' => $reportedAt,
            ];
        }
        if ($rows !== []) {
            DB::table('gateway_sync_statuses')->upsert($rows, ['device_registry_id'], [
                'state', 'pending_count', 'review_count', 'reported_at',
            ]);
        }

        return response()->noContent();
    }
}
