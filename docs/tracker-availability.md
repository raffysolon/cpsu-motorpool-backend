# Tracker heartbeat availability

## Timing configuration

The backend defaults are configurable through these environment variables:

| Variable | Default | Meaning |
| --- | ---: | --- |
| `TRACKER_HEARTBEAT_INTERVAL_SECONDS` | `30` | Expected tracker heartbeat cadence; the backend does not make firmware send at this interval. |
| `TRACKER_HEARTBEAT_MAX_AGE_SECONDS` | `60` | Maximum age of an accepted heartbeat timestamp; the exact boundary is accepted. |
| `TRACKER_HEARTBEAT_FUTURE_TOLERANCE_SECONDS` | `30` | Existing allowance for small tracker/server clock differences. |
| `TRACKER_ONLINE_THRESHOLD_SECONDS` | `60` | Maximum heartbeat age classified as online. |
| `TRACKER_UNREACHABLE_THRESHOLD_SECONDS` | `120` | Age above which availability is unreachable. |

The existing `offline_after_seconds` value remains 300 seconds to preserve the
legacy persisted `is_online` behavior.

## Calculated availability

`last_heartbeat_at` remains the tracker-supplied UTC timestamp and replay
watermark. `last_heartbeat_received_at` records when the server accepted that
timestamp. Availability uses the earlier of these two timestamps: a future
sender clock cannot extend freshness past receipt, and a delayed heartbeat
cannot become fresh just because it arrived recently. Legacy rows without a
receipt time fall back to their sender timestamp.

| Last accepted heartbeat age | Status |
| --- | --- |
| No accepted heartbeat | `unreachable` |
| 0 through 60 seconds | `online` |
| More than 60 through 120 seconds | `unstable` |
| More than 120 seconds | `unreachable` |

The receive-time value prevents an accepted future sender timestamp from
extending availability. Existing rows are backfilled from their sender time;
future legacy values are clamped to the migration time. During this one-time
backfill, their exact historical receipt time cannot be recovered.

The legacy `last_seen` field is stored in the application timezone
(`Asia/Manila`) and serialized as an absolute timestamp. Heartbeat sender and
receipt fields remain UTC. The local seeder writes each value in its respective
timezone; historical production timestamps are not rewritten.

Duplicate or older sender timestamps do not update either timestamp. A future
sender timestamp can therefore cause subsequent timestamps that are earlier
than it to be ignored until the tracker clock catches up. Keep tracker clocks
synchronized; with the current timestamp-only protocol, a corrected clock
cannot be distinguished from an out-of-order heartbeat. Replay protection
continues to use the sender timestamp and is not weakened by the receive-time
availability calculation.

Heartbeat validation is repeated after the device row is locked inside the
transaction. The lock serializes concurrent updates; after a newer heartbeat
commits, an older or duplicate request cannot overwrite either timestamp.
Availability uses the sender time as a conservative freshness bound while
retaining server receipt time separately.

No heartbeat means no recent connectivity evidence, not proof that the tracker
is powered off. `unreachable` describes observed communication, not device
power state.

## API compatibility

Device API responses and heartbeat acknowledgements include the additive
`availability_status` field. For example:

```json
{
  "device_id": "VEHICLE-01",
  "is_online": true,
  "availability_status": "unstable"
}
```

The existing `is_online`, `last_seen`, `last_heartbeat_at`, and `online` list
filter are retained. The legacy `is_online` value may temporarily disagree
with `availability_status`: it is persisted, reflects qualifying heartbeat or
live/legacy GPS activity, and is cleared by the existing 300-second monitor.
The new status is computed on response from heartbeat time only. GPS uploads,
including live LTE, recovered LTE, recovered LoRa, and duplicate records, do
not change calculated heartbeat availability. Recovered records also remain
unable to revive the legacy online fields.

Heartbeat authentication and operation permissions are unchanged:
individual tracker credentials authorize their own heartbeat; gateway
credentials do not authorize heartbeats; temporary legacy authentication
remains disabled by default and device-bound when explicitly enabled.
Heartbeats do not require GPS, movement, or an active trip.

The additive migration adds `last_heartbeat_received_at` without changing the
heartbeat endpoint or existing fields.

## Live movement monitoring

Only newly accepted, fresh live GPS fixes (`live_lte`, or legacy live payloads)
can contribute to current movement alerts. Recovered LTE/LoRa records,
duplicates, stale capture times, invalid coordinates, and fixes below the
minimum satellite count are excluded. GPS speed is calculated from consecutive
valid points; the protocol does not need to provide a speed field.

The configurable defaults are 5 km/h minimum segment speed, 50 m minimum
displacement, and 30 seconds sustained movement, with at least four satellites
and no more than 60 seconds between fixes. Consecutive movement evidence is
stored per tracker so HTTP requests and the scheduled monitor share the same
state. Notifications use unique event keys and retain existing acknowledgment
behavior. An approved but unstarted trip notifies its driver and admins;
movement without an approved or active trip creates a high-priority admin
notification. Movement during an active trip is normal tracking.

Near-departure connectivity warnings are limited to the configured
`TRACKER_DEPARTURE_WARNING_BEFORE_MINUTES` window (10 minutes by default).
They say that no recent heartbeat was observed; missing data is not treated as
proof that a tracker is powered off. The five-minute overdue-departure monitor
notifies both the driver and admins.

The campus-return reminder is disabled unless local/deployment configuration
provides `TRACKER_CAMPUS_LATITUDE` and `TRACKER_CAMPUS_LONGITUDE`. It also
requires fresh GPS evidence of an active trip leaving campus and then returning
and remaining stationary. It only reminds the driver; it never ends a trip.
Campus coordinates and radius must be verified by the operator before
enabling this feature.

## Scheduler

The `trackers:check-heartbeats`, `trips:check-departures`, and
`trackers:check-movement` schedules run once per minute. Heartbeat availability
is evaluated when a response is serialized, so its 60/120-second boundaries
do not depend on scheduler timing. Movement alerts are also observed
synchronously after a new qualifying live GPS record is accepted; the scheduled
command independently processes recent records for recovery after a missed
request.

Render must run the Laravel scheduler separately from its web process, either
as a Cron Job invoking `php artisan schedule:run` once per minute or as a
persistent worker running `php artisan schedule:work`. Use the same application
environment and shared database-backed cache for scheduler locks. An HTTP
uptime ping does not execute Laravel scheduled commands. No Render scheduler
service is configured in this repository; deployment configuration remains a
separate operational step.

For local Chrome testing, run commands through `scripts/local-testing.ps1`.
After running the local seeder, invoke
`.\scripts\local-testing.ps1 check-movement` to produce the
synthetic approved-trip movement alert. Local credentials and GPS scenarios
must remain confined to the ignored SQLite test environment.

Run `.\scripts\local-testing.ps1 scenarios` to prepare and execute the
repeatable local monitoring scenarios. It creates distinct approved-pretrip,
unauthorized-movement, active-trip, unreachable-near-departure,
five-minute-overdue, and recovered/stale-GPS fixtures, then runs both movement
and departure monitoring twice to verify notification idempotency. Fixture GPS
records, trips, devices, and notifications use stable local identifiers; the
command preserves registration timestamps, notification history, and
acknowledgments and does not delete unrelated data. The near-departure fixture
is scheduled five minutes after its first creation; an existing fixture keeps
its original schedule on later runs. Campus-return reminders remain disabled
until verified campus coordinates are configured.
