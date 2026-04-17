# Debugging Guide - Profile Data Flow

## How to Debug Profile Loading Issues

### Step 1: Check Backend Response

**Add this to UserController.php temporarily:**
```php
public function getProfile(ServerRequestInterface $request)
{
    
    // DEBUG: Log the data being returned
    error_log("USER DATA: " . json_encode($user));
    error_log("PROFILE DATA: " . json_encode($profile));
    error_log("MERGED DATA: " . json_encode($mergedData));
    
    return ResponseBuilder::ok([
        'user' => $mergedData,
        // ...
    ]);
}
```

**Check PHP error log:**
```bash
# Windows (if using XAMPP)
C:\xampp\apache\logs\error.log

# Or check console if running php -S
```

---

### Step 2: Check Flutter Console Output

**After applying the fix, you should see:**

```
AuthService: Fetching profile...
AuthService: Profile response received: {user: {...}, profile: {...}, user_type: recruiter}
AuthService: Merged profile data keys: [id, email, user_type, status, user_id, recruiter_name, company_name, designation, location, phone, approval_status, created_at, updated_at]
AuthService: User type from response: recruiter
AuthService: Creating recruiter from data: [id, email, user_type, status, user_id, recruiter_name, company_name, designation, location, phone, approval_status, created_at, updated_at]
AuthService: Updated recruiter data: John Doe
```

**If you see this instead:**
```
AuthService: Profile fetch failed with DioException: ...
```
→ **Network/Authentication issue** - Check token, backend running

**If you see this:**
```
AuthService: Merged profile data keys: [id, email, user_type, status]
```
→ **Backend merge not working** - Check UserController.php fix

**If you see this:**
```
AuthService: Creating recruiter from data: [...]
AuthService: Error creating recruiter from profile: FormatException: Invalid date format
```
→ **DateTime parsing issue** - Check created_at format in database

---

### Step 3: Database Verification

**Run these queries to verify data exists:**

```sql
-- 1. Check if user exists
SELECT id, email, user_type, status 
FROM users 
WHERE email = 'your_test_recruiter@example.com';

-- Expected: One row with user_type = 'recruiter'

-- 2. Check if recruiter profile exists
SELECT r.user_id, r.recruiter_name, r.company_name, r.designation, r.email, r.phone
FROM recruiters r
JOIN users u ON r.user_id = u.id
WHERE u.email = 'your_test_recruiter@example.com';

-- Expected: One row with populated fields (not NULL)

-- 3. Check if data is merged correctly
SELECT 
    u.id as user_id,
    u.email,
    u.user_type,
    u.status,
    r.recruiter_name,
    r.company_name,
    r.designation,
    r.location,
    r.phone
FROM users u
LEFT JOIN recruiters r ON u.id = r.user_id
WHERE u.email = 'your_test_recruiter@example.com';

-- Expected: All fields populated (no NULLs except optional fields)
```

---

### Step 4: Network Request Inspection

**Using Flutter DevTools:**

1. Run app: `flutter run`
2. Open DevTools: `flutter pub global run devtools`
3. Go to "Network" tab
4. Login as recruiter
5. Find `/api/auth/profile` request
6. Check response body

**Expected Response:**
```json
{
  "user": {
    "id": 5,
    "email": "recruiter@example.com",
    "user_type": "recruiter",
    "status": "active",
    "user_id": 5,
    "recruiter_name": "John Doe",
    "company_name": "Tech Corp",
    "designation": "HR Manager",
    "location": "Pune",
    "phone": "1234567890",
    "approval_status": "pending",
    "created_at": "2026-04-17 10:30:00",
    "updated_at": "2026-04-17 10:30:00"
  },
  "user_type": "recruiter"
}
```

**If `user` object doesn't have profile fields:**
→ Backend merge not applied correctly

**If response has errors:**
→ Check backend logs

---

### Step 5: Widget Tree Inspection

**In recruiter_profile_screen.dart, add debug prints:**

```dart
@override
Widget build(BuildContext context) {
  final theme = Theme.of(context);
  final authState = ref.watch(authStateProvider);
  
  // DEBUG: Print auth state
  print('ProfileScreen: authState = ${authState.value}');
  print('ProfileScreen: recruiter = ${authState.value as RecruiterModel?}');
  
  final recruiter = authState.value as RecruiterModel?;
  
  // DEBUG: Print recruiter fields
  if (recruiter != null) {
    print('ProfileScreen: recruiter.name = ${recruiter.name}');
    print('ProfileScreen: recruiter.company = ${recruiter.company}');
    print('ProfileScreen: recruiter.designation = ${recruiter.designation}');
    print('ProfileScreen: recruiter.email = ${recruiter.email}');
  }
  
  return Scaffold(...);
}
```

**Expected Output:**
```
ProfileScreen: authState = Instance of 'RecruiterModel'
ProfileScreen: recruiter = Instance of 'RecruiterModel'
ProfileScreen: recruiter.name = John Doe
ProfileScreen: recruiter.company = Tech Corp
ProfileScreen: recruiter.designation = HR Manager
ProfileScreen: recruiter.email = recruiter@example.com
```

**If recruiter is null:**
→ Auth state not loaded - Check auth_provider.dart

**If recruiter fields are null:**
→ Model creation issue - Check _createMinimalRecruiter

---

### Step 6: Real-Time Data Flow Tracking

**Create a debug helper file:**

**File:** `lib/utils/debug_helper.dart`
```dart
class DebugHelper {
  static void printDataFlow(String step, Map<String, dynamic> data) {
    print('\n${'=' * 50}');
    print('DATA FLOW: $step');
    print('Keys: ${data.keys.toList()}');
    print('Data: ${data.toString().substring(0, data.toString().length > 200 ? 200 : data.toString().length)}');
    print('${'=' * 50}\n');
  }
}
```

**Use it in auth_service.dart:**
```dart
import '../utils/debug_helper.dart';

Future<Map<String, dynamic>> getProfile({String? userType}) async {
  
  final responseData = response.data;
  DebugHelper.printDataFlow('Backend Response', responseData);
  
  final merged = <String, dynamic>{...user, ...profile};
  DebugHelper.printDataFlow('Merged Data', merged);
  
  // ... rest of code
}
```

---

## Common Debugging Scenarios

### Scenario 1: "Profile shows loading spinner forever"

**Check:**
```dart
// In recruiter_profile_screen.dart
final authState = ref.watch(authStateProvider);

print('authState.isLoading = ${authState.isLoading}');
print('authState.hasValue = ${authState.hasValue}');
print('authState.hasError = ${authState.hasError}');
```

**If `isLoading = true` forever:**
→ Auth provider stuck loading
→ Check if backend is responding
→ Check network connectivity

**Fix:**
```dart
// Add timeout
final response = await _dioClient.get('/api/auth/profile')
    .timeout(Duration(seconds: 10));
```

---

### Scenario 2: "Profile shows but with wrong data"

**Check field mapping:**
```dart
// In _createMinimalRecruiter
print('userData[recruiter_name] = ${userData['recruiter_name']}');
print('userData[company_name] = ${userData['company_name']}');
print('userData[name] = ${userData['name']}');

// Check what's being used
final name = userData['recruiter_name'] ?? userData['company_name'] ?? 'Fallback';
print('Final name = $name');
```

**If wrong field is used:**
→ Check field name priority in _createMinimalRecruiter

---

### Scenario 3: "Profile update doesn't reflect"

**Check:**
```dart
// After update in recruiter_profile_screen.dart
_saveBtn(() async {
  print('Updating with: ${updatedRecruiter.toJson()}');
  
  await ref.read(authStateProvider.notifier).updateProfile(updatedRecruiter.toJson());
  
  // Verify update
  final newState = ref.read(authStateProvider);
  print('New state after update: ${newState.value}');
});
```

**Check backend:**
```php
// In UserController::updateProfile
error_log("UPDATE DATA: " . json_encode($data));
error_log("PROFILE UPDATE DATA: " . json_encode($profileUpdateData));
```

**Check database:**
```sql
SELECT updated_at, recruiter_name, company_name 
FROM recruiters 
WHERE user_id = 5;

-- Check if updated_at changed
```

---

## Quick Debug Checklist

- [ ] Backend server running?
- [ ] Flutter app connected to correct backend URL?
- [ ] User logged in successfully?
- [ ] Token stored correctly?
- [ ] Backend returns 200 OK for /api/auth/profile?
- [ ] Response has merged user + profile data?
- [ ] Flutter console shows data keys?
- [ ] RecruiterModel created successfully?
- [ ] Profile screen widget receives recruiter object?
- [ ] Widget displays recruiter fields correctly?

---

## Debug Tools

### 1. Flutter DevTools
```bash
flutter pub global activate devtools
flutter pub global run devtools
```
- Network tab: Inspect API calls
- Logging tab: View print statements
- Inspector: Check widget tree

### 2. Postman/Thunder Client
- Test backend endpoints directly
- Verify response structure
- Check authentication

### 3. Database Client
- phpMyAdmin
- MySQL Workbench
- DBeaver

### 4. Custom Debug Provider

**Add to auth_provider.dart:**
```dart
final debugAuthProvider = Provider<String>((ref) {
  final user = ref.watch(authStateProvider).value;
  if (user == null) return 'Not logged in';
  
  return '''
  Auth State Debug:
  - Type: ${user.runtimeType}
  - ID: ${user.id}
  - Email: ${user.email}
  - Name: ${user.name}
  - Company: ${user.company}
  ''';
});
```

**Use in any screen:**
```dart
final debugInfo = ref.watch(debugAuthProvider);
print(debugInfo);
```

---

## Error Handling Improvements

**Add to auth_service.dart:**
```dart
Future<Map<String, dynamic>> getProfile({String? userType}) async {
  try {
  } on DioException catch (e) {
    print('AuthService: Profile fetch failed');
    print('Status code: ${e.response?.statusCode}');
    print('Response: ${e.response?.data}');
    print('Message: ${e.message}');
    
    if (e.response?.statusCode == 401) {
      return {
        'success': false,
        'message': 'Unauthorized - Token expired or invalid',
        'error_type': 'auth_error'
      };
    }
    
    return {
      'success': false,
      'message': 'Network error: ${e.message}',
      'error_type': 'network_error'
    };
  } catch (e, stackTrace) {
    print('AuthService: Unexpected error');
    print('Error: $e');
    print('Stack: $stackTrace');
    
    return {
      'success': false,
      'message': 'Unexpected error: $e',
      'error_type': 'unknown_error',
      'stack_trace': stackTrace.toString()
    };
  }
}
```

---

This debugging guide should help you quickly identify and resolve any profile loading issues!
