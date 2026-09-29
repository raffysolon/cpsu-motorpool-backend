# Security Fix: Role-Based Authorization

## Problem Fixed
**CRITICAL VULNERABILITY**: Dati, bisan unsa nga naka-login nga user (including drivers) pwede mo-access sa TANAN nga admin endpoints:
- Delete/create drivers
- Delete/create vehicles
- Approve/deny trips
- Access all system data

## Solution Implemented
Added middleware-based role checking para ma-separate ang admin ug driver permissions.

## Files Changed

### 1. `app/Http/Middleware/EnsureUserHasRole.php` (NEW)
- Custom middleware para sa role validation
- Supports multiple roles (e.g., `role:admin,coordinator`)
- Returns 403 Forbidden if role dili match

### 2. `bootstrap/app.php`
- Registered ang `role` middleware alias
- Pwede na gamiton as `->middleware('role:admin')`

### 3. `routes/api.php`
- Separated routes into two groups:
  - **Admin-Only Routes**: `middleware(['auth:sanctum', 'role:admin'])`
  - **Authenticated Routes**: `middleware('auth:sanctum')` (Admin + Driver)

## Admin-Only Endpoints (Drivers BLOCKED)

### Driver Management
- `GET /api/drivers` - List all drivers
- `POST /api/drivers` - Create new driver
- `PUT /api/drivers/{driver}` - Update driver
- `DELETE /api/drivers/{driver}` - Delete driver
- `POST /api/drivers/{driver}/reset-password` - Reset driver password

### Vehicle Management
- `GET /api/vehicles` - List all vehicles
- `POST /api/vehicles` - Create new vehicle
- `PUT /api/vehicles/{vehicle}` - Update vehicle
- `DELETE /api/vehicles/{vehicle}` - Delete vehicle

### Coordinator Assignments
- `GET /api/coordinator-assignments` - List all assignments
- `POST /api/coordinator-assignments` - Create assignment
- `PUT /api/coordinator-assignments/{assignment}` - Update assignment
- `DELETE /api/coordinator-assignments/{assignment}` - Delete assignment

### Trip Management (Admin Actions)
- `GET /api/trips` - View ALL trips (admin overview)
- `POST /api/trips/admin-create` - Create trip as admin
- `GET /api/available-drivers` - Check available drivers
- `GET /api/available-vehicles` - Check available vehicles
- `PUT /api/trips/{trip}/approve` - Approve trip request
- `PUT /api/trips/{trip}/deny` - Deny trip request

## Shared Endpoints (Admin + Driver)

### Profile & Settings
- `PUT /api/change-password` - Change own password
- `GET /api/my-assignment` - View own coordinator assignment

### Notifications
- `GET /api/notifications` - View own notifications
- `PUT /api/notifications/{notification}/read` - Mark as read
- `GET /api/notifications/unread-count` - Get unread count

### Trip Management (Driver Actions)
- `GET /api/my-trips` - View own trips only
- `POST /api/trips` - Create trip request (driver)
- `GET /api/trips/{trip}` - View single trip details
- `PUT /api/trips/{trip}` - Update trip (with auth check in controller)
- `POST /api/trips/{trip}/start` - Start trip (driver only)
- `POST /api/trips/{trip}/end` - End trip (driver only)
- `POST /api/trips/{trip}/start-return` - Start return trip
- `POST /api/trips/{trip}/end-return` - End return trip
- `GET /api/trips/{trip}/print` - Print trip ticket
- `GET /api/trips/preview-pdf` - Preview PDF

## Error Responses

### 401 Unauthenticated
```json
{
  "message": "Unauthenticated."
}
```
**Cause**: No token or invalid token provided

### 403 Forbidden
```json
{
  "message": "Unauthorized. This action requires admin role.",
  "your_role": "driver"
}
```
**Cause**: User is authenticated but doesn't have required role

## Testing

### Test as Driver (SHOULD FAIL)
```bash
# Login as driver
curl -X POST https://cpsu-motorpool-backend.onrender.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"driver@cpsu.edu.ph","password":"password123"}'

# Try to access admin endpoint (SHOULD GET 403)
curl -X GET https://cpsu-motorpool-backend.onrender.com/api/drivers \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN"

# Expected Response:
# {
#   "message": "Unauthorized. This action requires admin role.",
#   "your_role": "driver"
# }
```

### Test as Admin (SHOULD SUCCEED)
```bash
# Login as admin
curl -X POST https://cpsu-motorpool-backend.onrender.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cpsu.edu.ph","password":"admin123"}'

# Access admin endpoint (SHOULD GET 200)
curl -X GET https://cpsu-motorpool-backend.onrender.com/api/drivers \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"

# Expected: List of all drivers
```

## Next Steps

### Deploy to Production
1. Commit changes to git
2. Push to Render
3. Test all endpoints after deployment

### Frontend Updates Needed
Your Flutter apps need to handle 403 errors:
- Admin app: Should work normally
- Driver app: Will get 403 if accidentally calling admin endpoints (good!)

### Example Error Handling (Flutter)
```dart
try {
  final response = await http.get(
    Uri.parse('$baseUrl/api/drivers'),
    headers: {'Authorization': 'Bearer $token'}
  );
  
  if (response.statusCode == 403) {
    // User doesn't have permission
    showError('You do not have permission to access this feature.');
  }
} catch (e) {
  // Handle error
}
```

## Security Improvements Still Needed
1. ✅ Role-based authorization (FIXED)
2. ⚠️ CORS restriction (next priority)
3. ⚠️ Rate limiting
4. ⚠️ Token expiration
5. ⚠️ Password policy improvement

---
**Fixed by**: Kiro AI Assistant  
**Date**: September 29, 2026  
**Status**: ✅ READY FOR DEPLOYMENT
