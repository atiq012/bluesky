# Image Upload Standard (Multi-Domain, Single Storage)

## Goal

Use one industry-standard upload flow across the application:

- One physical storage location for all agent images
- Relative path in DB (never absolute server path)
- Same image accessible from multiple domains
- Centralized resize/compression/validation logic
- No duplicate upload code in controllers

---

## Architecture

### 1) Physical Storage (single source)

All uploaded agent images are stored under one physical base path:

- Local default: `public/uploads/agents`
- Live target: `/home/devblues/public_html/uploads/agents`

### 2) DB Storage (relative web path only)

Database stores only relative/public path, for example:

- `/uploads/agents/agency_img/15062026-101530_686f1c.jpg`
- `/uploads/agents/trade_licence_img/15062026-101540_98ab12.jpg`

### 3) Multi-domain access

Both domains should resolve `/uploads/...` to the same physical files.

Example:
- `https://dev.blueskyndc.com/uploads/agents/...`
- `https://devb2b.blueskyndc.com/uploads/agents/...`

To make this work, the second domain must point its `uploads` path to the first domain's shared upload directory (via symlink/alias/bind-mount; hosting constraints apply).

---

## Configuration

Config file: `config/agent_uploads.php`

```php
'base_path' => env('AGENT_UPLOAD_BASE_PATH', public_path('uploads/agents')),
'db_public_prefix' => env('AGENT_UPLOAD_DB_PREFIX', '/uploads/agents'),
```

### Required `.env` (live)

```env
AGENT_UPLOAD_BASE_PATH=/home/devblues/public_html/uploads/agents
AGENT_UPLOAD_DB_PREFIX=/uploads/agents
```

### Local `.env` (optional override)

If not set, local fallback already works with `public/uploads/agents`.

### Runtime Path Matrix (important)

Set `AGENT_UPLOAD_BASE_PATH` based on runtime, not based on editor workspace:

- Host runtime (`php artisan serve` on host):
  - `/home/gb053/Projects/blueskyb2b/public/uploads/agents`
- Container runtime (app served from `/var/www/html` inside container):
  - `/var/www/html/public/uploads/agents`

If app runs in container and project is bind-mounted, `/var/www/html/...` is usually correct inside app code.

---

## Service Contract

Service: `app/Services/ImageService.php`

### Public methods

- `uploadAgentImage(UploadedFile $image, string $fieldKey, ?string $oldDbPath = null): string`
  - Decides target folder by `$fieldKey`
  - If GD available: resizes image (max 1600x1600, aspect ratio preserved)
  - If GD available: encodes JPEG with adaptive quality (target ~300KB)
  - If GD unavailable: falls back to saving original file (no resize/compress)
  - Saves to configured base path
  - Returns DB-ready relative path (`/uploads/agents/...`)
- `deleteByDbPath(?string $dbPath): bool`
  - Converts DB relative path to absolute file path and deletes if exists
- `resolveAttachmentTypeByField(string $fieldKey): string`
  - Returns `agent_images.attachment_type` value from request field

### Folder mapping (single source of truth)

- `logo` -> `agency_img`
- `tradeFiles` -> `trade_licence_img`
- `cacFiles` -> `ca_img`
- `iataFiles` -> `iata_img`
- `hajjFiles` -> `hajj_licence_img`
- `tinFiles` -> `tin_img`
- `nidFiles` -> `nid_img`

If new upload type is added, update this mapping in one place.

---

## Controller Usage Pattern

Use service in controller; do not manually call `move()` + directory checks in every action.

### Single image field

```php
$imageService = app(\App\Services\ImageService::class);

if ($request->hasFile('logo')) {
    $agent->logo_path = $imageService->uploadAgentImage(
        $request->file('logo'),
        'logo',
        $agent->logo_path // optional old path for replacement
    );
}
```

### Multiple attachment fields

```php
$fileFields = ['nidFiles', 'tradeFiles', 'cacFiles', 'iataFiles', 'hajjFiles', 'tinFiles'];

foreach ($fileFields as $field) {
    if (! $request->hasFile($field)) {
        continue;
    }

    $requestFiles = $request->file($field);
    $files = is_array($requestFiles) ? $requestFiles : [$requestFiles];

    foreach ($files as $singleImage) {
        if (! $singleImage) continue;

        $agentImg = new \App\Models\Agent\AgentImage();
        $agentImg->agent_id = $agent->id;
        $agentImg->attachment_type = $imageService->resolveAttachmentTypeByField($field);
        $agentImg->attachment_path = $imageService->uploadAgentImage($singleImage, $field);
        $agentImg->save();
    }
}
```

---

## Frontend Rules (recommended)

- Restrict file types to image only (`jpg`, `jpeg`, `png`; optionally `webp`)
- Enforce max size at UI level (for better UX)
- Backend still validates (UI validation is not security)
- Store one file per card where business requires single file
- Always show preview from selected file or DB path

---

## Validation Standard (backend)

Always validate request before upload.

Example:

```php
$request->validate([
    'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    'tradeFiles.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    'cacFiles.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
]);
```

---

## Replace/Delete Policy

- On update, pass old DB path to `uploadAgentImage(..., $oldDbPath)` for safe replacement
- On record delete, remove associated files with `deleteByDbPath`
- Never store absolute `/home/...` in DB

---

## Deployment Checklist

1. Set `.env` values:
   - `AGENT_UPLOAD_BASE_PATH`
   - `AGENT_UPLOAD_DB_PREFIX`
2. Ensure upload directory exists and writable
3. Ensure second domain can read same uploads path
4. Verify URL from both domains:
   - `https://<domain>/uploads/agents/...`
5. Confirm DB rows contain relative path only

---

## Permissions Standard

Upload path must be writable by app runtime user (`apache`, `www-data`, `php-fpm` pool user, etc.).

Minimum required writable paths:

- `storage`
- `bootstrap/cache`
- upload base path (`.../public/uploads/agents`)

Local/container quick verification:

```bash
php -r "var_export(is_writable('/var/www/html/storage/logs'));"
php -r "var_export(is_writable('/var/www/html/public/uploads/agents/agency_img'));"
```

If host path is bind-mounted into container, fix permissions on host path.

---

## Troubleshooting

### 1) `laravel.log could not be opened in append mode`

Cause: `storage/logs` not writable by runtime user.

Fix:
- Correct owner/group for runtime user
- Ensure write permission on `storage` and `bootstrap/cache`

### 2) `Failed to open stream: Permission denied` on upload path

Cause: upload base path exists but not writable.

Fix:
- Create target subfolders (`agency_img`, `trade_licence_img`, etc.)
- Ensure runtime user write access
- Verify path is correct for runtime context (host vs container)

### 3) `Call to undefined function ...imagecreatefromjpeg()`

Cause: GD extension missing.

Behavior now:
- Service gracefully falls back to original file save

Recommended:
- Enable GD in PHP for resize/compression

### 4) Date insert errors while testing registration

Example: invalid datetime for `established_date`.

Fix:
- Normalize date format in backend to `Y-m-d` before insert.

---

## AI Implementation Checklist (for future agents)

When any task says "add/update image upload":

1. Use `ImageService`, do not write new ad-hoc upload logic
2. Add/adjust field->folder mapping in `ImageService` if needed
3. Keep DB path relative (`/uploads/...`)
4. Use config-driven base path (`config('agent_uploads.base_path')`)
5. Add backend validation rules
6. Keep controller thin; no duplicate `File::makeDirectory` blocks
7. If multi-domain sharing required, verify infra mapping (`uploads` alias/symlink)

If these are not followed, implementation is considered non-standard.
