# Security Fix: CORS Restriction

## Problem Fixed
**HIGH-RISK VULNERABILITY**: Dati, ang CORS config naka-set to `'allowed_origins' => ['*']`, meaning **BISAN ASA nga website** pwede mo-access sa imo API.

### Attack Scenario (Before Fix)
1. Admin naka-login sa legit site (`cpsumotorpool-admin.netlify.app`)
2. Admin mo-visit sa malicious website (`evil.com`)
3. Malicious website mo-run ug JavaScript code:
   ```javascript
   // Hacker's code on evil.com
   fetch('https://cpsu-motorpool-backend.onrender.com/api/drivers/1', {
     method: 'DELETE',
     headers: {
       'Authorization': 'Bearer ' + stolenToken
     }
   })
   // ✅ SUCCESS! Driver deleted from evil.com
   ```
4. Backend accepts request from `evil.com` tungod kay `allowed_origins = ['*']`
5. Data stolen, manipulated, or deleted

## Solution Implemented
Restricted CORS to **SPECIFIC DOMAINS ONLY**

## Changes Made

### File: `config/cors.php`

**Before:**
```php
'allowed_origins' => ['*'],  // ❌ ANY website allowed
'supports_credentials' => false,
```

**After:**
```php
'allowed_origins' => [
    'https://cpsumotorpool-admin.netlify.app',  // ✅ Production only
    'http://localhost:3000',                     // ✅ Local dev
    'http://localhost:8080',                     // ✅ Local dev (alt)
    'http://127.0.0.1:3000',                     // ✅ Local dev (alt)
    'http://localhost',                          // ✅ Local testing
],

'allowed_origins_patterns' => [
    // Allow Netlify preview URLs (deploy previews)
    '#^https://.*--cpsumotorpool-admin\.netlify\.app$#',
],

'supports_credentials' => true,  // ✅ Support for cookies/auth
```

## What is Now Protected

### ✅ Allowed Origins (Will Work)
1. **Production Admin App**
   - `https://cpsumotorpool-admin.netlify.app`
   
2. **Netlify Deploy Previews** (for testing before merge)
   - `https://deploy-preview-123--cpsumotorpool-admin.netlify.app`
   - `https://branch-name--cpsumotorpool-admin.netlify.app`
   
3. **Local Development**
   - `http://localhost:3000` (Flutter web default)
   - `http://localhost:8080` (Vue.js default)
   - `http://127.0.0.1:3000` (alternative localhost)
   - `http://localhost` (general testing)

### ❌ Blocked Origins (Will FAIL)
- `https://evil.com` ← **BLOCKED**
- `https://phishing-site.com` ← **BLOCKED**
- `https://malicious-app.com` ← **BLOCKED**
- Any unauthorized website ← **BLOCKED**

## How Browser Blocks Malicious Requests

When hacker tries from `evil.com`:

**Request:**
```javascript
// Code on evil.com
fetch('https://cpsu-motorpool-backend.onrender.com/api/drivers', {
  headers: {'Authorization': 'Bearer abc123'}
})
```

**Browser Console Error:**
```
Access to fetch at 'https://cpsu-motorpool-backend.onrender.com/api/drivers' 
from origin 'https://evil.com' has been blocked by CORS policy: 
The 'Access-Control-Allow-Origin' header has a value 
'https://cpsumotorpool-admin.netlify.app' that is not equal to 
the supplied origin.
```

**Result:** ❌ Request BLOCKED before hitting your backend!

## Attack Scenarios Now Prevented

### 1. CSRF (Cross-Site Request Forgery) ✅ BLOCKED
```javascript
// Malicious site tries to delete driver
fetch('https://cpsu-motorpool-backend.onrender.com/api/drivers/1', {
  method: 'DELETE'
})
// ❌ CORS error: Origin not allowed
```

### 2. Data Theft ✅ BLOCKED
```javascript
// Malicious site tries to steal trip data
fetch('https://cpsu-motorpool-backend.onrender.com/api/trips')
  .then(r => r.json())
  .then(data => sendToHacker(data))
// ❌ CORS error: Origin not allowed
```

### 3. Unauthorized Actions ✅ BLOCKED
```javascript
// Malicious site tries to create fake admin
fetch('https://cpsu-motorpool-backend.onrender.com/api/drivers', {
  method: 'POST',
  body: JSON.stringify({
    name: 'Hacker',
    role: 'admin',
    email: 'hacker@evil.com'
  })
})
// ❌ CORS error: Origin not allowed
```

## Testing After Deployment

### Test 1: Verify Production Works
```bash
# Should work from admin app
curl -X GET https://cpsu-motorpool-backend.onrender.com/api/trips \
  -H "Origin: https://cpsumotorpool-admin.netlify.app" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -v

# Expected: 200 OK with Access-Control-Allow-Origin header
```

### Test 2: Verify Blocking Works
```bash
# Should be blocked from unauthorized origin
curl -X GET https://cpsu-motorpool-backend.onrender.com/api/trips \
  -H "Origin: https://evil.com" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -v

# Expected: No Access-Control-Allow-Origin header (blocked by Laravel)
```

### Test 3: Browser Dev Tools Test
1. Open your admin app: `https://cpsumotorpool-admin.netlify.app`
2. Open browser console
3. Run:
   ```javascript
   fetch('/api/trips')
     .then(r => r.json())
     .then(data => console.log('✅ Works!', data))
     .catch(err => console.error('❌ Failed', err))
   ```
4. Should work (same origin)

5. Open different site (e.g., google.com)
6. Open browser console
7. Run:
   ```javascript
   fetch('https://cpsu-motorpool-backend.onrender.com/api/trips')
     .then(r => r.json())
     .then(data => console.log('Hacker success', data))
     .catch(err => console.error('✅ BLOCKED!', err))
   ```
8. Should see CORS error (blocked!)

## Adding New Allowed Origins

If you need to add new legitimate domains:

### Example: Add staging environment
```php
'allowed_origins' => [
    'https://cpsumotorpool-admin.netlify.app',      // Production
    'https://staging-cpsu-motorpool.netlify.app',   // ← Add staging
    'http://localhost:3000',
    // ... others
],
```

### Example: Add mobile app domain (if using WebView)
```php
'allowed_origins' => [
    'https://cpsumotorpool-admin.netlify.app',
    'capacitor://localhost',  // ← Capacitor mobile app
    'http://localhost:3000',
    // ... others
],
```

## Important Notes

### Driver Mobile App
The driver mobile app (Flutter APK) **does NOT need CORS** because:
- Native mobile apps don't have CORS restrictions
- Only web browsers enforce CORS
- Mobile HTTP requests bypass CORS entirely

So the driver app will continue working fine! 👍

### supports_credentials
Changed to `true` to allow:
- Cookies
- Authorization headers
- HTTP authentication
- Client-side SSL certificates

This is standard for APIs using bearer tokens.

## Security Improvements Status

1. ✅ Role-based authorization (FIXED - Security Fix #1)
2. ✅ CORS restriction (FIXED - Security Fix #2)
3. ⚠️ Rate limiting (TODO - Next priority)
4. ⚠️ Token expiration (TODO)
5. ⚠️ Password policy (TODO)

## What's Next

After deployment:
1. Test admin app on Netlify (should work)
2. Test driver mobile app (should still work - no CORS)
3. Try accessing API from unauthorized site (should fail)

---
**Fixed by**: Kiro AI Assistant  
**Date**: September 29, 2026  
**Status**: ✅ READY FOR DEPLOYMENT  
**Impact**: Prevents cross-site attacks and unauthorized API access
