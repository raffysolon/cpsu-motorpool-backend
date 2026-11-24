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

`availability_status` is calculated from `last_heartbeat_at` only:

| Last accepted heartbeat age | Status |
| --- | --- |
| No accepted heartbeat | `unreachable` |
| 0 through 60 seconds | `online` |
| More than 60 through 120 seconds | `unstable` |
| More than 120 seconds | `unreachable` |

A future timestamp within the configured tolerance has age zero for this
calculation. Thus it can keep the calculated status online for up to the
30-second tolerance before its timestamp catches up with server time, extending
the online window by up to 30 seconds compared with an accurate timestamp.
Duplicate or older timestamps do not update the stored value. A future timestamp
can therefore cause subsequent timestamps that are earlier than it to be
ignored until the tracker clock catches up. Keep tracker clocks synchronized;
with the current timestamp-only protocol, a corrected clock cannot be
distinguished from an out-of-order heartbeat.

Heartbeat validation is repeated after the device row is locked inside the
transaction. The lock serializes concurrent updates; after a newer heartbeat
commits, an older or duplicate request cannot overwrite it. Availability is
computed from the stored heartbeat timestamp, so a stale request does not
become fresh merely because it was received recently.

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

No database migration is needed; the calculation reuses the existing
`last_heartbeat_at` column.

## Scheduler

The existing `trackers:check-heartbeats` schedule runs once per minute and
continues maintaining the legacy `is_online` flag. The calculated
`availability_status` is evaluated when a response is serialized, so its
60/120-second boundaries do not depend on scheduler timing.

Render must run the Laravel scheduler separately from its web process, either
as a Cron Job invoking `php artisan schedule:run` once per minute or as a
persistent worker running `php artisan schedule:work`. Use the same application
environment and shared database-backed cache for scheduler locks. An HTTP
uptime ping does not execute Laravel scheduled commands. No Render scheduler
service is configured in this repository; deployment configuration remains a
separate operational step.
