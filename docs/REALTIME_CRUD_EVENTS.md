---

# Realtime CRUD Events – Generic Convention (Ably)

> **For Cursor AI:** Use convention for all list CRUDs (Department, Designation, Branch, etc.). Use `BroadcastResourceEvent` + `AblyService`; no entity-specific event classes needed.

---

## 1. Overview

- **One generic queue job:** `BroadcastResourceEvent` (publishes via `AblyService`).
- **Channel** = entity key (e.g. `departments`, `designations`, `branches`) — public Ably channels.
- **Event names** = `Created`, `Updated`, `Deleted` (same on every channel).
- **Payload** = entity-specific (full list row for Created/Updated; at least `id` for Deleted). Include **`actor_id`** (user who acted) so frontend skips refetch for that user.

---

## 2. Backend – AblyService style (recommended)

### 2.1 AblyService

- **Path:** `app/Services/AblyService.php`
- Publishes to Ably via REST SDK:
  - `publishToPublic(string $channelName, string $event, array $data): bool`

### 2.2 Generic queue job (publish in background)

- **Path:** `app/Jobs/BroadcastResourceEvent.php`
- Queue job: publish runs in background so **save response is fast**.
- Constructor:
  - `channelName` (e.g. `departments`)
  - `event` (`Created|Updated|Deleted`)
  - `payload` (array)
- `handle(AblyService $ably)`: calls `publishToPublic($channelName, $event, $payload)`.

### 2.3 Usage in any controller

- **Entity key** = channel name (e.g. `departments`, `designations`). Use plural, lowercase.
- After mutating:
  - **store:** Include `actor_id` in payload (e.g. `array_merge($payload, ['actor_id' => $request->user()->id])`), then `BroadcastResourceEvent::dispatch('departments', 'Created', $payload);`
  - **update:** Same; include `actor_id`, dispatch `'Updated'`.
  - **destroy:** `BroadcastResourceEvent::dispatch('departments', 'Deleted', ['id' => $id, 'actor_id' => $request->user()->id]);`

For Designation CRUD, use `'designations'` and designation payload; no entity-specific job needed.

---

## 3. Frontend – Subscription (same for every list)

- **Composable (recommended):** Use **`useRealtimeList(channelKey, onInvalidate, options)`** from `resources/js/src/composables/useRealtimeList.js`. Example: `useRealtimeList('departments', invalidateDepartments, { actorIdKey: 'actor_id' })`. Subscribes to channel, listens for `Created`, `Updated`, `Deleted`, calls `onInvalidate` unless payload `actor_id` (when `actorIdKey` is set) matches current user — skips redundant refetch for actor. Cleans up on unmount. See **`docs/DEPARTMENT_CRUD_PROCESS_FLOW.md`** §2.2.1.
- **Channel:** Entity key (e.g. `departments`). AblyService publishes; composable subscribes.
- **Events:** `Created`, `Updated`, `Deleted` (no entity prefix).
- **On event:** Composable calls invalidate/refetch so list table updates.
- **Manual alternative:** Subscribe to `channelKey`, listen for `Created`, `Updated`, `Deleted`.

---

## 4. Summary Table

| Item            | Value                                                                 |
| --------------- | --------------------------------------------------------------------- |
| Backend style   | AblyService + `BroadcastResourceEvent` job (recommended)              |
| Channel         | Entity key, e.g. `departments`, `designations`                        |
| Event names     | `Created`, `Updated`, `Deleted`                                       |
| Controller fire | `BroadcastResourceEvent::dispatch('departments', 'Created', $payload);` etc. Payload should include `actor_id` for skip-actor optimization. |
| Frontend listen | `useRealtimeList('departments', invalidateDepartments, { actorIdKey: 'actor_id' })` so actor skips redundant refetch. |

---

## 5. Troubleshooting – Other users' list not updating

**User 1** sees change; **User 2** (list page open) does not:

1. **Backend must publish to Ably**
   - Set `ABLY_KEY` in `.env`. Queue worker must run so `BroadcastResourceEvent` calls `AblyService->publishToPublic()` (or use `QUEUE_CONNECTION=sync`).

2. **Frontend must subscribe**
   - Set `VITE_ABLY_KEY` in `.env`, restart dev server. User 2 must be on list page when event fires so `useRealtimeList` is active.

3. **List still not updating for User 2:** confirm queue worker running (or sync driver), both users on list page; check Ably dashboard for message activity.

---

*Use convention in every CRUD needing realtime list updates (Department, Designation, Branch, etc.). Entity-specific docs should reference this file and use `BroadcastResourceEvent` with entity channel key.*