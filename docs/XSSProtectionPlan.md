# XSS Protection Plan — blueskyb2b (BlueSky-র সাথে sync)

> **Living document — plan only.**
> **Do not start building until the user approves this file.**
> Version: v1 · Last updated: 2026-08-30
> Audit verified against commit `7ac1fb97` (branch `main`)
> Companion doc: `BlueSky/docs/XSSProtectionPlan.md` v2.1 — strategy, middleware design, layer priority সেখানে বিস্তারিত

---

## ০. এই doc কেন আলাদা

BlueSky = admin panel, blueskyb2b = agency portal — same DB, কিন্তু **component set আলাদা**। BlueSky-র file list blindly copy করলে ভুল হবে:

- b2b-তে helpdesk **`v-html` দিয়ে** render করে; BlueSky-তে jQuery `.append()` দিয়ে — একই bug, আলাদা code
- b2b-তে agent approval page-এ jQuery injection আছে যা **BlueSky-তে নেই**
- BlueSky-তে `myGroup/payment/` আছে, b2b-তে নেই
- b2b-তে `group/create.vue` policy `v-html` আছে, BlueSky-তে নেই

**Strategy, layer priority, middleware design, rollout — সব BlueSky doc §2, §5, §8 থেকে নেবে।** এই doc শুধু b2b-র audit + file list।

---

## ১. সারাংশ — b2b vs BlueSky

| Item | BlueSky | blueskyb2b |
|------|---------|------------|
| Laravel | 10.24 · `app/Http/Kernel.php` | ✅ same |
| `routes/web.php` POST/PUT/PATCH/DELETE | 0 | ✅ **0** — `api` group যথেষ্ট |
| `routes/api.php` POST/PUT/PATCH/DELETE | 157 | **117** |
| Blade unescaped `{!! !!}` | 0 | ✅ **0** |
| `mews/purifier` | ❌ | ❌ |
| `dompurify` | ❌ | ❌ |
| Real `v-html` | 5 (3 file) | ⚠️ **12 (6 file)** |
| tippy `allowHTML` | 4 | 3 |
| jQuery raw HTML build | helpdesk + user/log | ⚠️ helpdesk **নেই**, বদলে **agent 3 file** + user/log |
| `document.write` print | 3 | 1 |
| SheetJS CSV export | 2 | 1 |
| SVG upload hole | 1 | ✅ same 1 |

**b2b-র অবস্থা BlueSky-র চেয়ে খারাপ** — helpdesk-এর Quill HTML সরাসরি `v-html`-এ যাচ্ছে, ৬ জায়গায়, কোনো sanitize ছাড়া।

---

## ২. Audit (verified @ `7ac1fb97`)

### Backend

| Area | Status |
|------|--------|
| Laravel 10.24, `app/Http/Kernel.php` | ✅ Kernel-এ middleware register হবে |
| `routes/web.php` mutating route | ✅ **0 টা** → `api` group cover করে (verified) |
| `routes/api.php` mutating route | **117 টা** → blast radius |
| Global XSS middleware | ❌ নেই |
| Blade `{!! !!}` | ✅ **0 টা** |
| `mews/purifier` | ❌ নেই |

### `v-html` — 12 টা, 6 file

**Helpdesk — সরাসরি stored XSS (P0, সবচেয়ে জরুরি)**

Quill HTML সরাসরি render হচ্ছে, server-এ Purifier নেই, client-এ DOMPurify নেই:

| File:line | Field |
|-----------|-------|
| [helpdesk/index.vue:873](../resources/js/components/admin/helpdesk/index.vue#L873) | `ticketData.description` |
| [helpdesk/index.vue:900](../resources/js/components/admin/helpdesk/index.vue#L900) | `msg.note` |
| [helpdesk/requestDetails.vue:546](../resources/js/components/admin/helpdesk/requestDetails.vue#L546) | `ticketData.description` |
| [helpdesk/requestDetails.vue:595](../resources/js/components/admin/helpdesk/requestDetails.vue#L595) | `note.note` |
| [helpdesk/requestDetails.vue:731](../resources/js/components/admin/helpdesk/requestDetails.vue#L731) | `ticketData.description` |
| [helpdesk/requestDetails.vue:758](../resources/js/components/admin/helpdesk/requestDetails.vue#L758) | `msg.note` |

> যেকোনো agent ticket-এ `<img src=x onerror=...>` লিখলে admin/agent যেই ticket খুলবে তার browser-এ চলবে। Agency portal — untrusted user input, তাই BlueSky-র চেয়ে exposure বেশি।

**Group / common**

| File:line | Field | Risk |
|-----------|-------|------|
| [group/index.vue:436](../resources/js/components/admin/group/index.vue#L436) | `row.route_display` + `replaceAll('|','<br>')` | Medium |
| [group/index.vue:455](../resources/js/components/admin/group/index.vue#L455) | `row.route_date_display` | Medium |
| [group/index.vue:493](../resources/js/components/admin/group/index.vue#L493) | `row.payment_info` | Medium |
| [group/view.vue:371](../resources/js/components/admin/group/view.vue#L371) | `policy.items` | Low–Medium |
| [group/create.vue:667](../resources/js/components/admin/group/create.vue#L667) | `policy.items` — **b2b-only, BlueSky-তে নেই** | Low–Medium |
| [DeleteConfirmModal.vue:81](../resources/js/components/common/DeleteConfirmModal.vue#L81) | `displayMessage` — `itemName` interpolate | **High** |

> [group/payment/index.vue:1514](../resources/js/components/admin/group/payment/index.vue#L1514) — শুধু CSS comment, actual `v-html` না।

### tippy `allowHTML` — 3 টা

`AppTooltip.vue` prop `allowHtml` → tippy `allowHTML` → raw HTML render। `v-html`-এর সমান risk।

| File:line | Builder | অবস্থা |
|-----------|---------|--------|
| [booking/index.vue:695](../resources/js/components/admin/booking/index.vue#L695) | `paxTooltipHtml(row)` | value `Number()` coerced → আজ injectable না, pattern unsafe |
| [booking/attemptList.vue:202](../resources/js/components/admin/booking/attemptList.vue#L202) | `paxTooltipHtml(row)` | একই |
| [group/payment/index.vue:551](../resources/js/components/admin/group/payment/index.vue#L551) | `fareBreakdownHtml(row)` | ⚠️ `row.currency` ([:123](../resources/js/components/admin/group/payment/index.vue#L123)) **raw string** HTML-এ |

### jQuery raw HTML build — b2b-only, BlueSky-তে নেই

Agent approval page-এ approver-এর লেখা text আর file path সরাসরি HTML string-এ:

| File:line | Injected |
|-----------|----------|
| [agent/agentView.vue:126](../resources/js/components/admin/agent/agentView.vue#L126) | `value.attachment_path` — **attribute context** (`img src="..."`) |
| [agent/agentView.vue:142](../resources/js/components/admin/agent/agentView.vue#L142) | `value.approver_name`, `value.remarks` |
| [agent/agentView.vue:146](../resources/js/components/admin/agent/agentView.vue#L146) | `value.approver_name`, `value.remarks` |
| [agent/agentApproved.vue:128](../resources/js/components/admin/agent/agentApproved.vue#L128) | `attachment_path` — attribute context |
| [agent/agentApproved.vue:145](../resources/js/components/admin/agent/agentApproved.vue#L145) | `approver_name`, `remarks` |
| [agent/agentApproved.vue:149](../resources/js/components/admin/agent/agentApproved.vue#L149) | `approver_name`, `remarks` |
| [agent/agentRecomanded.vue:124](../resources/js/components/admin/agent/agentRecomanded.vue#L124) | `attachment_path` — attribute context |
| [user/log.vue:24](../resources/js/components/admin/user/log.vue#L24) | `$("#name").html(name)` — Medium |
| [user/log.vue:27](../resources/js/components/admin/user/log.vue#L27) | `$("#staff_id").html(emp_id)` — Low |

> `attachment_path` **attribute context**-এ — `" onerror=alert(1) x="` দিলে breakout হয়, আর `strip_tags` এটা আটকায় **না** (কোনো tag নেই)। Escape helper-এ `"` আর `` ` `` অবশ্যই লাগবে।

### `document.write` print window

| File:line | Function | Injected |
|-----------|----------|----------|
| [group/payment/index.vue:379](../resources/js/components/admin/group/payment/index.vue#L379) | `exportPdf()` | `${row[h] ?? ''}` — **প্রতিটা column raw** |

**Risk: High** — `remarks` / `reference_no`-তে payload থাকলে Export PDF click করা মাত্র same-origin window-তে execute হবে।

### CSV export — formula injection

[group/payment/index.vue:367](../resources/js/components/admin/group/payment/index.vue#L367) SheetJS দিয়ে CSV লেখে (`bookType: 'csv'`)। `=`, `+`, `-`, `@` দিয়ে শুরু হওয়া cell Excel formula হিসেবে চালাবে (`=cmd|'/c calc'!A1`)।

XLSX export (একই guard দেওয়া ভালো): [group/payment/index.vue:360](../resources/js/components/admin/group/payment/index.vue#L360), [group/allUploadedPAX.vue:61](../resources/js/components/admin/group/allUploadedPAX.vue#L61), [group/paxUpload.vue:89,112](../resources/js/components/admin/group/paxUpload.vue#L89)।

### Rich text — Quill

b2b-তে Quill **চার** file-এ: [helpdesk/create.vue:318](../resources/js/components/admin/helpdesk/create.vue#L318), [helpdesk/edit.vue:224](../resources/js/components/admin/helpdesk/edit.vue#L224), [helpdesk/index.vue:113](../resources/js/components/admin/helpdesk/index.vue#L113), [helpdesk/requestDetails.vue:393](../resources/js/components/admin/helpdesk/requestDetails.vue#L393)।

Controller side — কোনো sanitize নেই:

| File:line | Field |
|-----------|-------|
| [RequestController.php:236](../app/Http/Controllers/Admin/HelpDesk/RequestController.php#L236) | `description` raw assign |
| [RequestController.php:412](../app/Http/Controllers/Admin/HelpDesk/RequestController.php#L412) | `description` raw assign |
| [RequestDetailsController.php:48](../app/Http/Controllers/Admin/HelpDesk/RequestDetailsController.php#L48) | `note` raw insert (`'note' => 'required|string'` শুধু) |

### File upload

| Controller | Rule | সমস্যা |
|------------|------|--------|
| [ReservationPaxController.php:114-115](../app/Http/Controllers/Admin/API/ReservationPaxController.php#L114) | `['nullable','image','max:1024']` — **`mimes:` নেই** | ⚠️ Laravel-এর `image` rule default-এ **svg allow করে** → same-origin serve → stored XSS |
| [DepositController.php:57](../app/Http/Controllers/Admin/Deposit/DepositController.php#L57) | `image\|mimes:jpg,jpeg,png,webp` | ✅ safe |

> BlueSky-র `GroupPNRPaymentController` contradictory rule b2b-তে নেই।

---

## ৩. Build phases (b2b)

BlueSky-র সাথে **একই layer priority** — frontend output আগে, middleware পরে। কারণ BlueSky doc §2-এ।

| Phase | কাজ | Priority | Est. |
|-------|-----|----------|------|
| 1 | Helpdesk `v-html` + DOMPurify | **P0 — সবচেয়ে জরুরি** | ~1.5h |
| 2 | বাকি frontend output fixes | **P0** | ~3h |
| 3 | Upload `mimes:` fix (SVG block) | **P0** (ছোট) | ~15m |
| 4 | SanitizeInput middleware + config | P1 | ~2h |
| 5 | Purifier on helpdesk controllers | P1 | ~1h |
| 6 | CSP | P2 optional | — |

### Phase 1 — Helpdesk (P0)

6 টা `v-html` → `DOMPurify.sanitize()` দিয়ে। পুরনো DB row-এ ইতিমধ্যে যা আছে সেটা এই layer ছাড়া কেউ আটকাবে না।

```js
// Quill HTML render করার আগে — পুরনো row কখনো sanitize হয়নি, তাই
// server-side Purifier যোগ করলেও এটা বাদ দেওয়া যাবে না
import DOMPurify from 'dompurify';

function purify(html) {
    return DOMPurify.sanitize(html ?? '', {
        ALLOWED_TAGS: ['p','br','strong','em','u','s','ol','ul','li','a','blockquote','code','pre'],
        ALLOWED_ATTR: ['href','target','rel'],
    });
}
```

```vue
<div class="hd-details-box mb-2" v-html="purify(ticketData.description)"></div>
```

### Phase 2 — বাকি frontend

| Task | File |
|------|------|
| `v-html` সরাও, text bind | `DeleteConfirmModal.vue` |
| `v-html` → `white-space: pre-line` + `{{ }}` | `group/index.vue` (3 জায়গা) |
| `v-html` review — static হলে `{{ }}` | `group/view.vue`, `group/create.vue` |
| jQuery build → `escapeHtml()` + `safeUrl()` (attribute) | `agent/agentView.vue`, `agent/agentApproved.vue`, `agent/agentRecomanded.vue` |
| `.html()` → `.text()` | `user/log.vue` |
| tooltip builder escape; `row.currency` | `booking/index.vue`, `booking/attemptList.vue`, `group/payment/index.vue` |
| `document.write` — সব interpolated value escape | `group/payment/index.vue` |
| CSV formula-injection prefix guard | `group/payment/index.vue` |

**New helper:** `resources/js/Helpers/escapeHtml.js` — BlueSky-র সাথে **byte-identical** রাখো:

```js
// jQuery / template-string দিয়ে HTML বানানো legacy জায়গার জন্য।
// নতুন code-এ ব্যবহার করবে না — Vue template + {{ }} ব্যবহার করো।
export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"'`]/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#39;', '`': '&#96;',
    })[ch]);
}

// URL attribute-এর জন্য আলাদা — escapeHtml যথেষ্ট না, javascript: scheme আটকাতে হয়
export function safeUrl(value) {
    const url = String(value ?? '').trim();
    return /^(https?:|\/|data:image\/)/i.test(url) ? url : '';
}
```

### Phase 3 — Upload (P0, ছোট)

`ReservationPaxController::uploadFiles` — `mimes:jpg,jpeg,png,webp` যোগ করো। Repo-wide grep: `mimes:` ছাড়া আর `'image'` আছে কিনা।

### Phase 4 — Middleware (P1)

`SanitizeInput.php` + `config/sanitize.php` — BlueSky doc §8 হুবহু। মূল সিদ্ধান্তগুলো একই:

- `$request->all()` **না**, `input()` — নাহলে `UploadedFile` object input bag-এ ঢোকে
- Global key blacklist না — route-scoped opt-in
- `SANITIZE_INPUT_ENABLED` kill switch + `dry_run` log-only rollout (117 route blast radius)
- strip-এর পরে খালি হলে `null` — নাহলে `nullable` validation behavior বদলায়

### Phase 5 — Purifier (P1)

```bash
composer require mews/purifier
```

| File:line | Field |
|-----------|-------|
| `RequestController.php:236` | `description` |
| `RequestController.php:412` | `description` |
| `RequestDetailsController.php:48` | `note` |

Allowed tags DOMPurify-র list-এর সাথে **একই** রাখো, নাহলে save আর render-এ দুই রকম behavior।

---

## ৪. ফাইল তালিকা

### নতুন

| File | Purpose | Phase |
|------|---------|-------|
| `resources/js/Helpers/escapeHtml.js` | `escapeHtml` + `safeUrl` | 2 |
| `app/Http/Middleware/SanitizeInput.php` | Input sanitize | 4 |
| `config/sanitize.php` | Kill switch + except | 4 |
| `config/purifier.php` | After composer | 5 |
| `tests/Feature/SanitizeInputTest.php` | sqlite `:memory:` | 4 |

### পরিবর্তন

| File | Change | Phase |
|------|--------|-------|
| `resources/js/components/admin/helpdesk/index.vue` | 2 টা `v-html` → DOMPurify | 1 |
| `resources/js/components/admin/helpdesk/requestDetails.vue` | 4 টা `v-html` → DOMPurify | 1 |
| `resources/js/components/common/DeleteConfirmModal.vue` | Remove `v-html` | 2 |
| `resources/js/components/admin/group/index.vue` | Remove `v-html` (3) | 2 |
| `resources/js/components/admin/group/view.vue` | Review `v-html` | 2 |
| `resources/js/components/admin/group/create.vue` | Review `v-html` | 2 |
| `resources/js/components/admin/agent/agentView.vue` | 3 টা jQuery append escape | 2 |
| `resources/js/components/admin/agent/agentApproved.vue` | 3 টা jQuery append escape | 2 |
| `resources/js/components/admin/agent/agentRecomanded.vue` | 1 টা jQuery append escape | 2 |
| `resources/js/components/admin/user/log.vue` | `.html()` → `.text()` | 2 |
| `resources/js/components/admin/booking/index.vue` | Tooltip builder escape | 2 |
| `resources/js/components/admin/booking/attemptList.vue` | Tooltip builder escape | 2 |
| `resources/js/components/admin/group/payment/index.vue` | Tooltip + `document.write` + CSV guard | 2 |
| `app/Http/Controllers/Admin/API/ReservationPaxController.php` | `mimes:` যোগ — SVG block | 3 |
| `app/Http/Kernel.php` | Register `SanitizeInput` on `api` | 4 |
| `app/Http/Controllers/Admin/HelpDesk/RequestController.php` | Purifier on `description` (2 জায়গা) | 5 |
| `app/Http/Controllers/Admin/HelpDesk/RequestDetailsController.php` | Purifier on `note` | 5 |
| `composer.json` / `package.json` | Purifier + DOMPurify | 1, 5 |

---

## ৫. Test plan (b2b)

### Positive — attack আটকায় কিনা

| # | Input | কোথায় | Expected |
|---|-------|-------|----------|
| 1 | `<script>alert(1)</script>` | helpdesk ticket description | render-এ text, alert না |
| 2 | `<img src=x onerror=alert(1)>` | helpdesk thread note | no alert (**b2b-র #১ risk**) |
| 3 | `<img src=x onerror=alert(1)>` | user name → delete modal | no alert |
| 4 | `" onerror=alert(1) x="` | agent `attachment_path` | no alert — **attribute-context test** |
| 5 | `<script>` | agent approval `remarks` | text দেখাবে |
| 6 | `<b>bold</b>` + `<script>` | helpdesk note | bold থাকবে, script যাবে |
| 7 | `<svg onload=alert(1)>` upload | `passport_image` | validation reject |
| 8 | `<img src=x onerror=...>` in payment `remarks` → Export PDF | print window | no alert |
| 9 | `=cmd\|'/c calc'!A1` in `reference_no` → CSV export | Excel-এ খুললে | formula run হবে না |
| 10 | পুরনো DB row-এ manually `<script>` insert → helpdesk খোলো | thread | DOMPurify আটকাবে |

### Negative — legit data নষ্ট হয় না

| # | Input | Expected |
|---|-------|----------|
| 11 | `Rate < 5% for group` | অক্ষত |
| 12 | `Smith & Sons Travel` | `&` মিলবে, `&amp;` দেখাবে না |
| 13 | Password `<Pa$$w0rd>` | login কাজ করবে |
| 14 | Fare rule text with `<` | truncate হবে না |

### Regression — 117 route

| # | Flow | Expected |
|---|------|----------|
| 15 | Agency search → price → book → ticket | কোনো field mangle না |
| 16 | Agent registration + document upload | file আসল, input bag-এ object না |
| 17 | Deposit create + reference file | আগের মতো |
| 18 | Helpdesk create → note → close | Quill formatting অক্ষত |
| 19 | Group payment XLSX / CSV / PDF export | আগের মতো render |

sqlite `:memory:` only — shared MySQL-এ `php artisan test` না।

---

## ৬. Sync নিয়ম

| Item | দুই repo-তে |
|------|-------------|
| `escapeHtml.js` | ✅ byte-identical |
| `SanitizeInput.php` | ✅ byte-identical |
| `config/sanitize.php` | ✅ byte-identical |
| DOMPurify allowed-tag list | ✅ identical |
| Purifier allowed-tag list | ✅ identical — DOMPurify-র সাথেও মিলবে |
| Component fix | ⚠️ **path আলাদা** — এই doc-এর file list ব্যবহার করো, BlueSky-রটা না |
| Migration | ❌ কিছু না — same DB |

**Order:** BlueSky আগে ship + verify, তারপর b2b। শেয়ার্ড helper/middleware BlueSky থেকে copy, component fix এই doc ধরে।

---

## ৭. Open questions

1. `group/view.vue` + `group/create.vue`-এর `policy.items` — hardcoded না DB থেকে? DB হলে risk Medium।
2. Agent approval page (`agentView` / `agentApproved` / `agentRecomanded`) — jQuery block Vue-তে rewrite, নাকি minimum escape patch? তিনটে file-এ প্রায় duplicate code, rewrite করলে একটা component-এ আসতে পারে।
3. Helpdesk `v-html` — DOMPurify যথেষ্ট, নাকি Quill config থেকেই allowed format কমাব?
4. `dry_run` window কতদিন Phase 4 enable করার আগে?

---

## ৮. Approval checklist

- [ ] Phase 1 helpdesk DOMPurify (6 জায়গা) — সবার আগে, OK?
- [ ] Phase 2 frontend file list (11 file) OK?
- [ ] Phase 3 upload `mimes:` fix OK?
- [ ] Phase 4 middleware — BlueSky-র design হুবহু, kill switch + dry_run OK?
- [ ] Phase 5 Purifier (`composer require mews/purifier`) OK?
- [ ] BlueSky আগে, b2b পরে — এই order OK?
- [ ] §7 open questions-এর উত্তর

---

## Changelog

| Date | Version | Change |
|------|---------|--------|
| 2026-08-30 | v1 | b2b-র নিজস্ব audit commit `7ac1fb97`-এ। Strategy BlueSky doc v2.1 থেকে, file list b2b-র নিজের। **b2b-only findings:** helpdesk 6 টা direct `v-html` (Quill HTML, কোনো sanitize নেই); agent approval 3 file-এ jQuery injection (`attachment_path` attribute context); `group/create.vue` policy `v-html`। **BlueSky-র সাথে common:** `DeleteConfirmModal`, `group/index.vue`, `user/log.vue`, tooltip `allowHTML`, `document.write` exportPdf, CSV formula injection, `ReservationPaxController` SVG hole |
