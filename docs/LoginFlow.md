# Login Flow — BlueSky NDC Platform

> **Living Document** — প্রতিটি সেকশন আলাদাভাবে আপডেট করা যাবে।  
> Last Updated: 2026-06-17 *(doc synced with codebase + login 2FA secret bootstrap)*  
> **Project:** `blueskyb2b` — BlueSky login refactor ported here. B2B users table has no `type` column; `user.type == 2` login block not applied.

---

## সূচিপত্র

1. [ফ্লো ওভারভিউ (Diagram)](#ফ্লো-ওভারভিউ)
2. [ধাপ ১ — Frontend: Login Page](#ধাপ-১--frontend-login-page)
3. [ধাপ ২ — Backend: Login Validation](#ধাপ-২--backend-login-validation)
4. [ধাপ ৩ — Frontend: Token Storage & Redirect](#ধাপ-৩--frontend-token-storage--redirect)
5. [ধাপ ৪ — Router Guard (2FA / Force Pass)](#ধাপ-৪--router-guard-2fa--force-pass)
6. [ধাপ ৫ — 2FA Flow (QR + OTP)](#ধাপ-৫--2fa-flow-qr--otp)
7. [ধাপ ৬ — Password Expired / Force Change](#ধাপ-৬--password-expired--force-change)
8. [ধাপ ৭ — Token Lifecycle](#ধাপ-৭--token-lifecycle)
9. [ধাপ ৮ — Logout](#ধাপ-৮--logout)
10. [লজিক সারসংক্ষেপ](#লজিক-সারসংক্ষেপ)
11. [সংশ্লিষ্ট ফাইলসমূহ](#সংশ্লিষ্ট-ফাইলসমূহ)
12. [Changelog](#changelog)

---

## ফ্লো ওভারভিউ

```
[Browser: Login.vue]
       │
       ├─ onMounted → getIPinfo() (geolocation-db.com, 5s AbortController timeout)
       │                └─ fail/timeout → authStore.sInfo = {} (server normalizeIpInfo fallback)
       │
       ├─ User submit (authStore.email + form.password)
       │
       ▼
POST /api/login  { email, password, IPinfo }
       │
       ▼
[AuthController::login()]
       │
       ├─ Validate: email required, password min 8
       ├─ User exist?              NO  → "User not found !"
       ├─ user.type == 2?          YES → "User not found !" (blocked user type)
       ├─ is_active == 1?          NO  → "User not active !"
       ├─ login_attamp < 3?        NO  → "Locked Account. Use forget Password."
       ├─ Password correct?        NO  → login_history Failed, attamp++, RA in response
       │       └─ attamp >= 3?     YES → "Locked Account"
       ├─ login_attamp = 0, login_history Success (এক row)
       ├─ ensureGoogle2faSecretForUser()  ← require_2fa=1, registered_2fa=0, secret null হলে gen
       ├─ respondWithToken() → JWT + whitelisted fields
       └─ Password expired?        YES → message "Your password must be change." (token still returned)
              │
              ▼
[Frontend: Login.vue — store + redirect]
       │
       ├─ require_2fa == 0 → isLogged=true, otp flags skip → router Home
       │
       └─ require_2fa == 1 → router register2fa (/Reg2Fa)
              │
              ▼
[Router guard: app.js — meta RE / OTP / auth]
       │
       ├─ forcePassChange == true → ForcePassChange (password expired priority)
       ├─ registered_2fa == 0 (DB) → register2fa (QR page)
       ├─ registered_2fa == 1 + otpChecked == 0 → otp (/otp)
       └─ otpChecked == 1 → Home (protected routes allowed)
              │
              ▼
[2faregister.vue — first-time QR setup]
       │
       ├─ Show secret (red text) + QR (otpauth from stored qr OR rebuilt from secret+email)
       ├─ User scans Google Authenticator (email label bind নয় — secret matter করে)
       └─ "Enable 2FA" → getotp_regisered=1 (frontend only) → router otp
              │
              ▼
[otp.vue — 6-digit TOTP]
       │
       ├─ POST /api/registerOTP { otp }  + Bearer JWT
       ├─ Google2FA::verifyKey(secret, otp)
       ├─ valid → DB registered_2fa=1, isLogged=true, clear secret/qr from store → Home
       └─ invalid → toast error, getotpChecked=0
```

---

## ধাপ ১ — Frontend: Login Page

**ফাইল:** `resources/js/components/auth/Login.vue`

### IP / Device Info সংগ্রহ — `getIPinfo()`

```javascript
// onMounted → getIPinfo()
const controller = new AbortController();
const timeout = setTimeout(() => controller.abort(), 5000);

fetch('https://geolocation-db.com/json/', { signal: controller.signal })
  .then(resp => resp.json())
  .then(data => {
    clearTimeout(timeout);
    data.devicetype = MF.initCap(deviceType());
    data.os = osName() + ' ' + osVersion();
    authStore.sInfo = data;   // { IPv4, country_code, city, devicetype, os }
  })
  .catch(() => {
    clearTimeout(timeout);
    authStore.sInfo = {};     // server-side normalizeIpInfo() fallback
  });
```

### Form Submit — `handleSubmit()`

| Field | Source |
|-------|--------|
| email | `authStore.email` |
| password | `form.password` |

**Client validation:** email required, password min **4** chars  
**Server validation:** password min **8** chars — mismatch সম্ভব (client pass, server 422)

```javascript
POST /api/login {
  email: authStore.email,
  password: form.password,
  IPinfo: authStore.sInfo
}
```

### Success redirect (Login.vue — guard এর আগে initial push)

```javascript
if (require_2fa == 0) {
  isLogged = true; getotp_regisered = 1; getotpChecked = 1;
  router.push({ name: 'Home' });
} else {
  router.push({ name: 'register2fa' });   // সবসময় register2fa — otp সরাসরি নয়
}
```

Password expired হলে `forcePassChange = true` set হয়; redirect require_2fa অনুযায়ী। Guard পরে `ForcePassChange` এ পাঠায়।

### Error Handling (catch)

| HTTP Status | Data | Action |
|-------------|------|--------|
| 404 / 422 | `RA` (remaining attempts) | toast + `loginAttapms` update |
| 404 / 422 | `PE` (password expired) | toast + redirect `ForcePassChange` |
| অন্যান্য | `error` | toast error message |

---

## ধাপ ২ — Backend: Login Validation

**ফাইল:** `app/Http/Controllers/AuthController.php` — `login()`

### Request validation

```php
$request->validate(['email' => 'required|email', 'password' => 'required|min:8']);
$ipinfo = $this->normalizeIpInfo($request);
```

### চেক ক্রম (sequential, fail → early return)

#### চেক ১ — User Exist
```php
$user = User::where('email', $request->email)->first();
!$user → ErrorResponse('User not found !')
```

#### চেক ২ — Blocked User Type
```php
$user->type == 2 → ErrorResponse('User not found !')   // generic message, no user enumeration
```

#### চেক ৩ — Account Active
```php
$user->is_active == 0 → ErrorResponse('User not active !')
```

#### চেক ৪ — Account Lock
```php
$user->login_attamp >= 3 → ErrorResponse('Locked Account. Use forget Password.')
```

#### চেক ৫ — Password Correct
```php
$token = auth()->attempt($request->only('email', 'password'));
```
**Fail হলে:**
- `login_histories` insert (`login_attamp = 'Failed'`)
- `$user->login_attamp++` → `$user->save()`
- `attamp >= 3` → "Locked Account" + `RA`
- otherwise → "Wrong Password !" + `RA`

#### চেক ৬ — Password Expiry (block করে না)
```php
$toDate = Carbon::parse($user->password_updated_at)->addDays($user->password_max_expired);
$passwordValid = now()->lt($toDate);
```

#### সফল লগইন
```php
$user->login_attamp = 0;
$user->save();

DB::table('login_histories')->insert([...]);  // একটিমাত্র Success row

$authenticated = auth()->user();
$this->ensureGoogle2faSecretForUser($authenticated);

$success = $this->respondWithToken($token);

if (!$passwordValid) {
    return SuccessResponse($success, 'Your password must be change.');
}
return SuccessResponse($success, 'Authorized User Login.');
```

### 2FA Secret Bootstrap — `ensureGoogle2faSecretForUser()`

Seeder/admin দিয়ে তৈরি user যাদের secret null — login এ one-time generate।

```php
// শুধু এই তিনটা true হলে:
require_2fa === 1
registered_2fa === 0
google2fa_secret empty

// তখন:
$creds = createGoogle2faCredentials($user->email);  // shared with register()
$user->google2fa_secret = $creds['secret'];
$user->google2fa_qr     = $creds['qr'];            // otpauth:// URL
$user->save();

// কখনো existing secret overwrite হয় না
```

### `createGoogle2faCredentials()` — register + login shared

```php
$secret = $google2fa->generateSecretKey();
$qr     = $google2fa->getQRCodeUrl('BlueSky', $email, $secret);
// $qr = otpauth://totp/BlueSky:email?secret=...&issuer=BlueSky&algorithm=SHA1&digits=6&period=30
```

> **Note:** `users.google2fa_qr` column `varchar(150)` — দীর্ঘ otpauth URL truncate হতে পারে। Frontend `2faregister.vue` secret+email থেকে otpauth rebuild করে (fallback)।

### `respondWithToken()` — Return Structure

```php
[
  'id'               => $user->id,
  'name'             => $user->name,
  'email'            => $user->email,
  'is_active'        => $user->is_active,
  'require_2fa'      => $user->require_2fa,
  'registered_2fa'   => $user->registered_2fa,
  'google2fa_secret' => $user->registered_2fa ? null : $user->google2fa_secret,
  'google2fa_qr'     => $user->registered_2fa ? null : $user->google2fa_qr,
  'access_token'     => $token,
  'token_type'       => 'bearer',
  'expires_in_sec'   => TTL * 60,
]
```

### `normalizeIpInfo()` — Fallback

```php
// Browser geolocation না পেলে
defaults: IPv4 = request()->ip(), country_code='', city='', devicetype='Desktop', os='Unknown'
```

### Register endpoint (reference)

`POST /api/register` — user create এর সময় same `createGoogle2faCredentials()` → secret+qr save → `respondWithToken()` (SuccessResponse wrapper ছাড়া raw array return)।

---

## ধাপ ৩ — Frontend: Token Storage & Redirect

**ফাইল:** `resources/js/stores/authStore.js`, `resources/js/components/auth/Login.vue`

### Pinia Store (persist: true → localStorage)

```javascript
authStore.token               = AES_encrypt(access_token)
authStore.email               = res.data.data.email
authStore.name                = res.data.data.name
authStore.ExpireInSec         = res.data.data.expires_in_sec
authStore.getRequire_2fa      = res.data.data.require_2fa
authStore.getotp_regisered    = res.data.data.registered_2fa   // DB value
authStore.getgoogle2fa_secret = res.data.data.google2fa_secret
authStore.getgoogle2fa_qr     = res.data.data.google2fa_qr

// already registered → frontend store clear (backend already null)
if (registered_2fa == 1) {
  getgoogle2fa_secret = '';
  getgoogle2fa_qr = '';
}

authStore.runTaskWithTimer(expires_in_sec);
```

### AES Encryption (JWT obfuscation)

```javascript
const passphrase = "MySecPassBlueSky"
CryptoJS.AES.encrypt(text, passphrase)
CryptoJS.AES.decrypt(ciphertext, passphrase)
```

> ⚠️ Key JS source-এ visible — obfuscation মাত্র। Production-grade: httpOnly cookie (flow change লাগে)।

### Boolean Flags

```javascript
authStore.isLogged         // ref(false)
authStore.forcePassChange  // ref(false)
authStore.getotpChecked    // ref(0) — OTP verified this session
```

### Token Validation — `hasToken()`

```javascript
function hasToken() {
  if (!token.value) return false;
  try {
    const payload = JSON.parse(atob(decryptWithAES(token.value).split('.')[1]));
    return Date.now() < payload.exp * 1000;
  } catch {
    return false;
  }
}
```

---

## ধাপ ৪ — Router Guard (2FA / Force Pass)

**ফাইল:** `resources/js/app.js` — `router.beforeEach`

Route meta (`resources/js/routers.js`):

| Route | name | meta |
|-------|------|------|
| `/` | Login | `guest: true` |
| `/Reg2Fa` | register2fa | `RE: true` |
| `/otp` | otp | `OTP: true` |
| `/ForcePassChange` | ForcePassChange | `FPC: true` |
| `/home` … | Home etc. | `auth: true` |

### No token

```
!hasToken() + meta.auth / RE / OTP → redirect Login
```

### Token + guest route

```
hasToken() + meta.guest → redirect Home
```

### Token + protected route (`meta.auth`)

```
require_2fa == 1:
  getotp_regisered == 0  → register2fa
  getotp_regisered == 1 && getotpChecked == 0  → otp
  getotpChecked == 0  → otp
```

### Token + RE route (`register2fa`)

```
forcePassChange == true  → ForcePassChange
require_2fa == 1 && getotp_regisered == 1  → otp   (already setup, skip QR)
require_2fa == 0 && getotp_regisered == 1  → Home
require_2fa == 1 && getotpChecked == 1     → Home
```

### Token + OTP route

```
forcePassChange == true  → ForcePassChange
require_2fa == 1 && getotp_regisered == 0  → register2fa   (QR first)
require_2fa == 0 && getotp_regisered == 1  → Home
require_2fa == 1 && getotpChecked == 1     → Home
```

> **Frontend vs DB `registered_2fa`:** Login এ store = DB value। `2faregister.vue` "Enable 2FA" click → `getotp_regisered = 1` (frontend only, DB still 0 until `registerOTP` success)। Guard এই flag দিয়ে QR skip করে otp page এ পাঠায়।

---

## ধাপ ৫ — 2FA Flow (QR + OTP)

**ফাইলসমূহ:**
- `resources/js/components/auth/2faregister.vue` — QR + secret display
- `resources/js/components/auth/otp.vue` — OTP verify
- `app/Http/Controllers/AuthController.php` — `registerOTP()`

### When 2FA runs

```
require_2fa == 0 → skip entirely (isLogged=true on login)
require_2fa == 1 → QR and/or OTP required
```

### DB `registered_2fa` vs user journey

| DB `registered_2fa` | User sees | Next step |
|---------------------|-----------|-----------|
| 0 | `/Reg2Fa` QR + red secret | Scan → Enable 2FA → `/otp` |
| 1 | Guard redirects `/otp` directly | Enter 6-digit code |

### QR generation — `2faregister.vue`

```javascript
const qrValue = computed(() => {
  const stored = getgoogle2fa_qr.trim();
  if (stored.startsWith('otpauth://')) return stored;

  // DB qr truncated/empty হলে secret+email থেকে rebuild
  const secret = getgoogle2fa_secret.trim();
  const holder = email.trim();
  return `otpauth://totp/BlueSky:${holder}?secret=${secret}&issuer=BlueSky&algorithm=SHA1&digits=6&period=30`;
});
```

`<vue-qrcode :value="qrValue" tag="svg" />` — `@chenfengyuan/vue-qrcode`, global register in `app.js`।

**Authenticator email:** otpauth URL এ email শুধু label। Authenticator app এ যেকোনো name দিয়ে save করলেও OTP কাজ করে — verify শুধু secret+code।

### Enable 2FA button — `goOTP()`

```javascript
authStore.getotp_regisered = 1;   // frontend session flag only
router.push({ name: 'otp' });
```

### OTP Verify — `registerOTP()`

```php
$user = User::where('email', auth()->user()->email)->first();
$valid = Google2FA::verifyKey($user->google2fa_secret, $otp);

valid:
  $user->registered_2fa = 1;
  $user->save();
  return { require_2fa, registered_2fa: 1 }

invalid:
  return ErrorResponse('Unauthorized OTP')
```

### Frontend — `otp.vue`

```javascript
POST /api/registerOTP { otp }
Authorization: Bearer <decrypted JWT>

success:
  isLogged = true
  getotpChecked = 1
  getgoogle2fa_secret = ''
  getgoogle2fa_qr = ''
  router.push({ name: 'Home' })

fail:
  getotpChecked = 0
  toast error
```

---

## ধাপ ৬ — Password Expired / Force Change

**ফাইলসমূহ:**
- `resources/js/components/auth/forcePasswordChange.vue`
- `app/Http/Controllers/AuthController.php` — `ForcePassReset()`

### Trigger

```
Backend login → password expired → token + message "Your password must be change."
Frontend → forcePassChange = true
Router guard (RE/OTP route) → ForcePassChange redirect
```

Login.vue initial push register2fa হলেও guard `forcePassChange` priority দিয়ে ForcePassChange এ override করে।

### `ForcePassReset()` — Validation

1. `old_password` → Laravel `current_password` rule
2. new == old → Error
3. Last 3 passwords reuse check (`Password_history`)
4. New password: min 8, mixed case, numbers, symbols
5. `eDays`: 1–90 (expiry duration days)

### Password Update

```php
$user->password = $request->password;   // model mutator/hash
$user->password_updated_at = now();
$user->password_max_expired = $request->eDays;
$user->login_attamp = 0;
$user->save();
```

---

## ধাপ ৭ — Token Lifecycle

**ফাইল:** `resources/js/stores/authStore.js`, `resources/js/components/App.vue`

### `runTaskWithTimer(seconds)`

```javascript
setInterval(() => {
  abc_timer--;
  if (abc_timer < 10) showExpireWarrning = true;
  if (abc_timer < 0) {
    token = '';
    isLogged = false;
    clearInterval(intervalId);
  }
}, 1000);
```

### Expiry warning — `App.vue`

Token শেষ ১০s এ `iziToast` — user "Refresh" click → `GET /api/refresh` → new token + `refreshToken(expires_in_sec)`।

### Page reload — `App.vue onBeforeMount`

```javascript
if (hasToken() && isLogged) {
  ReminTime = JWT exp - now (seconds);
  refreshToken(ReminTime, false);   // timer resume, ExpireInSec unchanged
} else {
  logout();
}
```

### `refreshToken(ePireINSec, setExpire = true)`

```javascript
killRunningTask();
if (setExpire) ExpireInSec = ePireINSec;
isLogged = true;
runTaskWithTimer(ePireINSec);
```

**API:** `GET /api/refresh` → `AuthController::refresh()`

```php
return { access_token, expires_in_sec };
```

---

## ধাপ ৮ — Logout

**ফাইল:** `resources/js/components/auth/Logout.vue`, `AuthController::logout()`

### Frontend `authStore.logout()`

```javascript
clearInterval(intervalId);
token = ''; name = ''; email stays unless cleared elsewhere
abc_timer = 0; ExpireInSec = 0;
getRequire_2fa = 1; getotp_regisered = 0; getotpChecked = 0;
isLogged = false; forcePassChange = false;
getgoogle2fa_secret = ''; getgoogle2fa_qr = '';
loginAttapms = 0; showExpireWarrning = false;
```

### Backend

```php
auth()->logout();   // JWT invalidate/blacklist
return SuccessResponse([], 'Successfully logged out.');
```

---

## লজিক সারসংক্ষেপ

| বিষয় | লজিক |
|-------|-------|
| **Account Lock** | 3 fail → lock। Password reset → `login_attamp=0` |
| **User type 2** | Login blocked — generic "User not found !" |
| **IP/Device Log** | প্রতি attempt → `login_histories` |
| **JWT** | `tymon/jwt-auth` |
| **Token Storage** | localStorage + AES obfuscation (XSS risk) |
| **Client vs Server password min** | Client 4, server 8 |
| **2FA on/off** | `require_2fa` flag |
| **2FA secret create** | `register()` always; `login()` if null + not registered |
| **2FA secret never overwrite** | Existing secret on login untouched |
| **2FA response hide** | `registered_2fa=1` → secret/qr null in API |
| **2FA verify** | TOTP `verifyKey(secret, otp)` — email irrelevant |
| **QR display** | stored otpauth OR frontend rebuild from secret |
| **Login redirect** | require_2fa=1 → register2fa (not otp directly) |
| **Router guard** | Final routing: QR / OTP / ForcePass / Home |
| **Frontend registered flag** | goOTP sets `getotp_regisered=1` before DB confirms |
| **Password expiry** | Login allowed, forcePassChange + guard redirect |
| **Password policy** | 8+ chars, mixed, number, symbol; last 3 reuse blocked |
| **Token timer** | Last 10s warning; 0 → logout |
| **DB queries (login)** | Single `User::first()` reuse |

---

## সংশ্লিষ্ট ফাইলসমূহ

| ফাইল | ভূমিকা |
|------|--------|
| `app/Http/Controllers/AuthController.php` | login, register, registerOTP, ForcePassReset, refresh, logout |
| `resources/js/components/auth/Login.vue` | Login UI, geolocation, submit, initial redirect |
| `resources/js/components/auth/2faregister.vue` | QR display, secret, Enable 2FA → otp |
| `resources/js/components/auth/otp.vue` | OTP input + verify |
| `resources/js/components/auth/forcePasswordChange.vue` | Force password change UI |
| `resources/js/components/auth/forgetPassMail.vue` | Forgot password email |
| `resources/js/components/auth/reserPass.vue` | Password reset via email link |
| `resources/js/stores/authStore.js` | Pinia auth state (persisted) |
| `resources/js/app.js` | Router guard — auth / 2FA / forcePassChange |
| `resources/js/routers.js` | Route definitions + meta |
| `resources/js/components/App.vue` | Token timer resume, expiry refresh toast |
| `config/auth.php` | JWT guard config |

---

## Changelog

### 2026-06-17 — Doc sync + 2FA login bootstrap

| # | Change |
|---|--------|
| 1 | Doc: overview diagram — register2fa → otp flow (not direct OTP) |
| 2 | Doc: `ensureGoogle2faSecretForUser()` on login documented |
| 3 | Doc: `createGoogle2faCredentials()` shared helper |
| 4 | Doc: router guard section (RE / OTP / auth meta) |
| 5 | Doc: `2faregister.vue` qrValue rebuild fallback |
| 6 | Doc: `goOTP()` frontend-only `getotp_regisered=1` |
| 7 | Doc: client password min 4 vs server min 8 |
| 8 | Doc: `user.type == 2` block + null-check order fix |
| 9 | Doc: Authenticator email = label only |
| 10 | Doc: password expired + forcePassChange guard priority |
| 11 | Code: login `$user` null check before `$user->type` |

### 2026-06-17 — Security Refactor

| # | ছিল | হয়েছে |
|---|-----|--------|
| 1 | `login()`: ৩ DB query | ১টি `first()` — object reuse |
| 2 | Success login_history দুইবার insert | এক row |
| 3 | Redundant attamp condition | Remove |
| 4 | `me()`: all users | `auth()->user()` |
| 5 | `respondWithToken()`: all columns | Whitelist |
| 6 | secret/qr always in response | `registered_2fa=1` → null |
| 7 | `isLogged` AES string | plain boolean |
| 8 | `forcePassChange` AES string | plain boolean |
| 9 | `hasToken()` issuer check | expiry only |
| 10 | Timer expire → `isLogged = AES('1')` | `isLogged = false` |
| 11 | duplicate refreshToken helpers | `refreshToken(sec, setExpire)` |
| 12 | Geolocation no timeout | 5s timeout + fallback |
| 13 | inconsistent forcePassChange checks | `!authStore.forcePassChange` |
