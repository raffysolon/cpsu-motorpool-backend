# Tracker credentials and cutover

## Provisioning and rotation

The provisioning command targets one registered device and asks for confirmation:

```text
php artisan trackers:provision-credential <DEVICE_ID>
```

The command prints the newly generated 64-character hexadecimal credential once.
Copy it directly to an approved secure delivery channel; do not paste it into
tickets, chat, source control, deployment logs, or shell transcripts. The
database stores only its SHA-256 hash. The credential cannot be retrieved later.

Replacing an existing credential requires the explicit rotation option and a
second confirmation:

```text
php artisan trackers:provision-credential <DEVICE_ID> --rotate
```

If rotation is confirmed, the old credential stops working when the new hash is
saved. If the operation is declined, the credential remains unchanged.

## Configuration

Configure these values in the backend's protected environment configuration.
The values below are placeholders, not credentials:

```text
GATEWAY_API_KEY=<new-gateway-only-secret>
TRACKER_LEGACY_API_KEY=<former-shared-tracker-secret>
TRACKER_LEGACY_AUTH_ENABLED=false
TRACKER_LEGACY_DEVICE_ID=<permitted-device-id>
```

`GATEWAY_API_KEY` authenticates trusted Raspberry Pi gateway forwarding.
`TRACKER_LEGACY_API_KEY` temporarily accepts the former shared key when legacy
authentication is enabled. `TRACKER_LEGACY_AUTH_ENABLED` defaults to `false`.
For the currently permitted compatibility device, set
`TRACKER_LEGACY_DEVICE_ID=VEHICLE-01` explicitly. Legacy authentication is
rejected if either the key or device ID is missing, or if the submitted
`device_id` differs from the configured ID. The legacy key must differ from the
gateway key. A request using identical active gateway and legacy keys is
rejected with a configuration error; it is not guessed to be either caller.

Requests with `X-Tracker-Key` are always checked as individual tracker
credentials. A present but empty, malformed, repeated, unknown, or incorrect
tracker header is rejected and never falls back to either shared key. Generated
tracker credentials must be sent in the `X-Tracker-Key` header with the
matching `device_id`. The authenticated caller type is assigned internally by
the middleware; values in request data or client-supplied caller-type headers
are ignored.

## Permission matrix

| Authenticated caller | GPS permission | Heartbeat permission |
| --- | --- | --- |
| Individual tracker | `live_lte` and `recovered_lte`, for the credential's device ID | Its own device ID |
| Trusted gateway | `recovered_lora` only | None |
| Temporary legacy caller | No-source legacy payloads, `live_lte`, and `recovered_lte` for the one configured legacy device | The one configured legacy device |

Legacy authentication never authorizes `recovered_lora` and cannot authenticate
any device other than the configured legacy ID. Gateway LoRa uploads must
include `record_id`, `device_sequence`, and `captured_at`; the existing
ingestion validation continues to enforce those fields. Recovered GPS records
do not update the tracker's online or `last_seen` state.

## Migration and cutover sequence

1. Apply the additive device-registry schema migration through the normal,
   approved deployment process before provisioning credentials.
2. Before deploying the stricter permissions, configure distinct values:
   `GATEWAY_API_KEY=<new-gateway-only-secret>` and
   `TRACKER_LEGACY_API_KEY=<former-shared-tracker-secret>`. Only if
   `VEHICLE-01` still needs the old firmware path, explicitly set
   `TRACKER_LEGACY_AUTH_ENABLED=true` and
   `TRACKER_LEGACY_DEVICE_ID=VEHICLE-01`. Otherwise leave legacy auth disabled.
   Verify the two protected key values differ. Never paste actual values into
   this document or source control.
3. Coordinate backend configuration and Raspberry Pi key rotation in a
   maintenance window. The gateway must use the new key for LoRa forwarding;
   only `VEHICLE-01`, if configured as the legacy ID, may use the former key for
   LTE uploads and heartbeats. Other legacy devices are rejected. While the Pi
   still uses the former key, its LoRa requests are rejected as legacy callers.
   Keep the LoRa backlog on microSD and resume synchronization after the Pi has
   switched keys; do not silently discard or acknowledge rejected records.
4. Provision each registered tracker with
   `trackers:provision-credential <DEVICE_ID>` from an authorized, private
   terminal. Deliver each returned credential securely to its assigned tracker.
   Rotate a credential if it is lost or exposed; never attempt to recover it
   from the database.
5. In a separately approved firmware rollout, migrate LTE trackers to individual
   credentials and confirm their GPS and heartbeat requests authenticate
   individually. Do not treat successful gateway authentication as proof of
   tracker identity.
6. After all legacy devices have migrated, set
   `TRACKER_LEGACY_AUTH_ENABLED=false` and remove
   `TRACKER_LEGACY_API_KEY` and `TRACKER_LEGACY_DEVICE_ID`. Confirm the gateway
   continues using only its gateway-only key and rotate it again if the former
   shared key may have been exposed.

If the gateway and legacy credentials are equal while legacy authentication is
enabled, requests using that value receive an explicit configuration error.
There is no safe way to infer whether a holder of the same secret is the
gateway or a legacy tracker. Separate the values as part of the coordinated
rotation; do not enable permissive behavior to avoid the error. While the old
legacy key remains active, its holder can still impersonate the one configured
legacy device. Disabling the legacy flag does not disable the separate gateway
key.

## Rollback considerations

The device-registry migration is additive. If application rollback is needed,
keep the nullable credential-hash column in place; do not run its down
migration as part of an application rollback. Do not roll back to application
code that accepts the former shared key without a device-ID restriction. Keep
the gateway and legacy keys distinct, and leave legacy authentication disabled
unless the explicit `VEHICLE-01` compatibility path is required. If the
restricted legacy path is temporarily enabled, its configured device ID remains
the only permitted identity.

## Raspberry Pi and LoRa identity recommendations

The gateway key authenticates the Raspberry Pi, not the originating tracker.
The gateway must not be allowed to turn an unauthenticated LoRa `device_id`
into an authenticated identity merely by forwarding it.

Before relying on LoRa data as tracker-authenticated, use a unique cryptographic
identity per tracker and authenticate the complete packet contents, including
the device ID, protocol version, sequence/counter, timestamp, and GPS payload.
Verify that proof at the gateway and backend, reject replayed counters, and bind
the verified identity to the registered device before storing a record. Prefer
a per-device signature verified against a registered public key, or an
equivalently reviewed per-device message-authentication design; do not reuse the
shared gateway key as a LoRa key. Protect gateway-held secrets and limit which
device identities it is authorized to forward.

The current gateway credential proves only that the Raspberry Pi made the HTTP
request; it does not prove a radio packet originated from the claimed tracker.
This proposal requires a separately reviewed protocol and firmware rollout.
This stage does not implement LoRa packet authentication or change firmware.
