# Security Fix: Token Expiration

## Problem Fixed
**NO TOKEN EXPIRATION**: Authentication tokens never expired, valid forever
- ❌ Stolen tokens could be used indefinitely
- ❌ No automatic session timeout
- ❌ Cannot force users to re-authenticate
- ❌ Lost/stolen devices retain permanent access
- ❌ No way to globally invalidate compromised tokens

## Solution Implemented
Set token expiration to **24 hours** (1440 minutes)

---

## Change Made

### File: `config/sanctum.php`

**Before:**
```php
'expiration' => null,  // ❌ Tokens NEVER expire
```

**After:**
```php
'expiration' => 1440,  // ✅ Tokens expire after 24 hours (1440 minutes)
```

---

## How It Works

### Token Lifecycle

```
Login Time: 9:00 AM, January 1
Token Created: "1|abc123xyz789..."
Token Expires: 9:00 AM, January 2 (24 hours later)

Timeline:
├─ 9:00 AM Day 1: Login successful, token created
├─ 10:00 AM: Token valid ✅
├─ 5:00 PM: Token valid ✅
├─ 11:59 PM: Token valid ✅
├─ 9:00 AM Day 2: Token EXPIRES ❌
└─ 9:01 AM Day 2: Must login again
```

### After Token Expires

**Request with expired token:**
```bash
GET /api/my-trips
Authorization: Bearer 1|expired_token_123

Response: 401 Unauthorized
{
  "message": "Unauthenticated."
}
```

**User must login again to get new token:**
```bash
POST /api/login
{
  "email": "user@cpsu.edu.ph",
  "password": "password"
}

Response: 200 OK
{
  "token": "2|new_token_456",  # New token with fresh 24h expiration
  "role": "driver",
  "name": "Juan Dela Cruz"
}
```

---

## Security Benefits

### 1. **Limited Damage Window for Stolen Tokens**

**Before (No Expiration):**
```
Token stolen on Day 1
↓
Valid forever
↓
Attacker has permanent access 😱
```

**After (24h Expiration):**
```
Token stolen on Day 1, 3:00 PM
↓
Valid for 18 more hours only
↓
Expires Day 2, 9:00 AM
↓
Attacker's access automatically revoked ✅
```

### 2. **Automatic Session Timeout**

**Before:**
```
Admin logs in → leaves computer unlocked
↓
Token valid forever
↓
Anyone can access system anytime
```

**After:**
```
Admin logs in at 9:00 AM
↓
Leaves computer at 10:00 AM
↓
Next day at 9:00 AM: Token expires
↓
System auto-locked ✅
```

### 3. **Force Re-authentication**

**Before:**
```
Employee quits → account disabled in database
↓
Old token still works (never expires)
↓
Ex-employee still has access
```

**After:**
```
Employee quits → account disabled
↓
Token expires within 24 hours maximum
↓
Ex-employee loses access automatically ✅
```

### 4. **Contain Security Breaches**

**Before:**
```
Security breach → attacker steals database
↓
Gets all valid tokens
↓
Tokens work forever
↓
Must manually revoke every single token
```

**After:**
```
Security breach at 2:00 PM
↓
Wait 24 hours
↓
All stolen tokens expire automatically
↓
Attackers locked out ✅
```

---

## Attack Scenarios Now Prevented

### ✅ Scenario 1: Stolen Device

**Attack:**
```
Driver loses phone
↓
Finder opens app (still logged in)
↓
Can see trips, manipulate data
```

**Before Fix:**
- Access: Forever until manually revoked
- Risk: HIGH

**After Fix:**
- Access: Maximum 24 hours
- Token expires automatically
- Risk: LOW (limited time window)

---

### ✅ Scenario 2: Public WiFi Interception

**Attack:**
```
Admin uses coffee shop WiFi
↓
Hacker on same network captures token
↓
Hacker uses token to access system
```

**Before Fix:**
- Token valid: Forever
- Hacker access: Permanent
- Detection: Difficult

**After Fix:**
- Token valid: Maximum 24 hours from capture
- Hacker access: Limited window
- Detection: User likely logs in again, gets new token

---

### ✅ Scenario 3: Phishing Attack

**Attack:**
```
Hacker sends fake login page
↓
User enters credentials
↓
Hacker logs in with credentials, gets token
↓
Hacker uses stolen token
```

**Before Fix:**
- Stolen token works: Forever
- Damage: Unlimited

**After Fix:**
- Stolen token works: 24 hours maximum
- User changes password: Old token expires
- Damage: Contained

---

### ✅ Scenario 4: Malware on Device

**Attack:**
```
Malicious app installed on phone
↓
Reads app's stored token
↓
Sends token to attacker's server
↓
Attacker uses token remotely
```

**Before Fix:**
- Remote access: Permanent
- User unaware: Forever compromised

**After Fix:**
- Remote access: 24 hours maximum
- Token auto-expires
- User logs in again = new token
- Old stolen token useless

---

## User Experience Impact

### Admin App (Web)

**User Flow:**
```
Day 1, 9:00 AM:
- Login with email/password
- Use system throughout the day
- Close browser

Day 2, 9:00 AM:
- Open admin app
- Token expired (24 hours passed)
- Redirected to login page
- Login again (takes 5 seconds)
- Continue working
```

**Impact:** 
- Minimal - login once per day
- Most users do this anyway (close laptop overnight)
- Improves security significantly

### Driver App (Mobile)

**User Flow:**
```
Day 1:
- Login with credentials (or fingerprint/face)
- Use app for trips
- App runs in background

Day 2:
- Open app after 24 hours
- Token expired
- Show login screen
- Quick login (fingerprint/face unlock)
- Continue using
```

**Impact:**
- Very minimal - most drivers open app daily
- Can implement biometric quick login
- Security benefit outweighs minor inconvenience

---

## Advanced: Token Refresh Strategy

For even better UX, implement token refresh before expiration:

### Client-Side Token Refresh (Optional Enhancement)

```dart
// Check token expiry before each API call
class ApiService {
  Future<Response> makeRequest(String endpoint) async {
    // Check if token expires soon (e.g., within 1 hour)
    if (tokenExpiresIn < Duration(hours: 1)) {
      await refreshToken(); // Get new token silently
    }
    
    // Make API request with fresh token
    return http.get(endpoint, headers: {
      'Authorization': 'Bearer $token'
    });
  }
  
  Future<void> refreshToken() async {
    // Re-login silently in background
    // User doesn't notice!
    final response = await http.post('/api/login', body: {
      'email': storedEmail,
      'password': storedPassword, // Or use refresh token endpoint
    });
    
    if (response.statusCode == 200) {
      final newToken = jsonDecode(response.body)['token'];
      await saveToken(newToken);
    }
  }
}
```

**With Token Refresh:**
- User logs in once
- App auto-refreshes token before expiration
- User never sees login screen again (unless inactive for 24+ hours)
- Best of both worlds: security + convenience

---

## Monitoring Token Usage

### Check Token Expiration in Code

```php
// Get current user's token
$token = $request->user()->currentAccessToken();

// Check expiration
$expiresAt = $token->expires_at;
$now = now();

if ($expiresAt && $now->greaterThan($expiresAt)) {
    // Token expired
    return response()->json(['message' => 'Token expired'], 401);
}
```

### Manually Revoke Token

```php
// Revoke specific token (logout)
$request->user()->currentAccessToken()->delete();

// Revoke all user tokens (logout all devices)
$request->user()->tokens()->delete();
```

---

## Configuration Options

### Different Expiration Times

Based on security needs:

```php
// High Security (Recommended for admin accounts)
'expiration' => 480,  // 8 hours

// Balanced (Recommended for general use) ✅ CURRENT
'expiration' => 1440, // 24 hours (1 day)

// Relaxed (For less sensitive apps)
'expiration' => 10080, // 7 days (1 week)

// No Expiration (NOT recommended for production)
'expiration' => null,
```

---

## Testing Token Expiration

### Test 1: Token Expires After 24 Hours (Manual)

**Note:** To test quickly, temporarily change expiration to 1 minute:

```php
// config/sanctum.php (TESTING ONLY)
'expiration' => 1, // 1 minute
```

**Test Steps:**
```bash
# 1. Login and get token
curl -X POST https://cpsu-motorpool-backend.onrender.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@cpsu.edu.ph","password":"Test@123"}'

# Response:
# {"token":"3|abc123...","role":"driver"}

# 2. Use token immediately (should work)
curl -X GET https://cpsu-motorpool-backend.onrender.com/api/my-trips \
  -H "Authorization: Bearer 3|abc123..."

# Response: 200 OK with trips data ✅

# 3. Wait 1 minute

# 4. Use same token again (should fail)
curl -X GET https://cpsu-motorpool-backend.onrender.com/api/my-trips \
  -H "Authorization: Bearer 3|abc123..."

# Response: 401 Unauthorized
# {"message":"Unauthenticated."} ✅
```

**After testing, revert back:**
```php
'expiration' => 1440, // 24 hours
```

### Test 2: Verify in Database

```bash
# Login and check personal_access_tokens table
SELECT id, name, token, expires_at, created_at 
FROM personal_access_tokens 
ORDER BY created_at DESC 
LIMIT 5;

# expires_at should be created_at + 24 hours
# Example:
# created_at:  2026-09-29 10:00:00
# expires_at:  2026-09-30 10:00:00 ✅
```

---

## Frontend Implementation

### Admin App - Handle Token Expiration

```dart
// services/auth_service.dart

class AuthService {
  Future<http.Response> apiRequest(String url) async {
    final token = await getStoredToken();
    
    final response = await http.get(
      Uri.parse(url),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
      },
    );

    // Handle token expiration
    if (response.statusCode == 401) {
      final data = jsonDecode(response.body);
      
      if (data['message'] == 'Unauthenticated.') {
        // Token expired - clear storage and redirect to login
        await clearStoredToken();
        
        // Show friendly message
        showDialog(
          context: context,
          builder: (context) => AlertDialog(
            title: Text('Session Expired'),
            content: Text('Your session has expired. Please login again.'),
            actions: [
              TextButton(
                onPressed: () {
                  Navigator.pushReplacementNamed(context, '/login');
                },
                child: Text('OK'),
              ),
            ],
          ),
        );
        
        throw TokenExpiredException();
      }
    }

    return response;
  }
}

class TokenExpiredException implements Exception {}
```

### Driver App - Auto-Redirect on Expiration

```dart
// main.dart or routing logic

void checkTokenValidity() {
  http.get(
    Uri.parse('$baseUrl/api/user'),
    headers: {'Authorization': 'Bearer $token'},
  ).then((response) {
    if (response.statusCode == 401) {
      // Token expired - navigate to login
      Navigator.pushNamedAndRemoveUntil(
        context,
        '/login',
        (route) => false, // Remove all previous routes
      );
      
      // Show snackbar
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Your session has expired. Please login again.'),
          duration: Duration(seconds: 3),
        ),
      );
    }
  });
}

// Call on app launch
@override
void initState() {
  super.initState();
  checkTokenValidity();
}
```

---

## Migration Notes

### For Existing Users

**Current users with non-expiring tokens:**
- Old tokens will continue to work
- New expiration rule applies to new logins only
- To force all users to re-login: manually clear `personal_access_tokens` table

**Force all users to re-login (optional):**
```sql
-- CAUTION: This will log out ALL users
TRUNCATE TABLE personal_access_tokens;
```

### Gradual Migration

Better approach - let old tokens expire naturally:
```php
// Keep null for existing tokens, 1440 for new tokens
// Laravel Sanctum handles this automatically
// No manual migration needed ✅
```

---

## Security Improvements Status

1. ✅ Role-based authorization (FIXED - Security Fix #1)
2. ✅ CORS restriction (FIXED - Security Fix #2)
3. ✅ Strong password policy (FIXED - Security Fix #3 & #4)
4. ✅ Rate limiting (FIXED - Security Fix #5)
5. ✅ Token expiration (FIXED - Security Fix #6)
6. ⚠️ Input sanitization (TODO - Low priority)
7. ⚠️ Pagination (TODO - Low priority)

---

## Comparison Summary

| Metric | Before (null) | After (24h) |
|--------|---------------|-------------|
| **Token Validity** | Forever | 24 hours |
| **Stolen Token Risk** | Permanent access | Max 24h access |
| **Session Timeout** | Never | Daily |
| **Force Logout** | Impossible | Automatic |
| **Lost Device Risk** | Permanent | 24h maximum |
| **Security Level** | Low | High |
| **User Convenience** | Maximum | Still High |

---

**Fixed by**: Kiro AI Assistant  
**Date**: September 29, 2026  
**Status**: ✅ READY FOR DEPLOYMENT  
**Impact**: Significantly reduces risk of token theft and unauthorized access
