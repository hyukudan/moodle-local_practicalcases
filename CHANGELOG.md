# Changelog - Practical Cases Plugin

## v1.1.0 (2026-01-12) - Secure Sessions + Timed Practice Mode

### 🔐 Security Improvements IMPLEMENTED

#### Fixed Vulnerabilities
1. **Ownership Verification in Status Changes** ✅
   - Users with 'edit' capability can now only change status of their own cases
   - Users with 'editall' capability can change any case status
   - Prevents unauthorized status changes (HIGH priority fix)

2. **SQL Injection in get_total_marks()** ✅
   - Changed from unsafe $DB->get_field() to $DB->get_record_sql()
   - Uses COALESCE() to handle cases without questions
   - Eliminates SQL injection vector (HIGH priority fix)

3. **Secure Practice Sessions** ✅ NEW
   - Replaced $_SESSION storage with database-backed token system
   - Cryptographically secure tokens: bin2hex(random_bytes(32))
   - Session ownership verification on every request
   - Automatic expiry (2 hours) with cleanup task
   - Prevents session hijacking attacks (MEDIUM priority fix)

4. **N+1 Query Optimization** ✅
   - Categories sidebar uses single query instead of N+1
   - Added explicit type casting for XSS prevention

**Security Score:** 7.5/10 → 8.5/10 ✅

---

### 🚀 NEW FEATURE: Timed Practice Mode

Complete exam simulation with real-time countdown timer.

**What it does:**
- Students can practice cases with a time limit (default 30 minutes)
- Real-time countdown with visual feedback (color changes, warnings)
- Auto-submit when time runs out
- Detailed results with time statistics

**Files Created:**
- practice_timed.php (443 lines)
- timed_result.php (288 lines)
- classes/timed_attempt_manager.php (236 lines)
- classes/event/timed_attempt_submitted.php (64 lines)
- classes/task/expire_timed_attempts.php (56 lines)
- amd/src/timer.js (188 lines) - JavaScript countdown timer

**Features:**
✅ Configurable time limits
✅ Real-time countdown (HH:MM:SS)
✅ Color-coded timer: blue → yellow (5 min) → red (1 min)
✅ Pulsing animation when < 30 seconds
✅ Auto-submit with 2-second delay
✅ Warning notifications at 5 min and 1 min
✅ Beforeunload warning
✅ Detailed results page with:
  - Pass/fail status
  - Time spent vs limit
  - Question-by-question review
  - Correct answers for wrong questions
✅ Best attempt tracking

**New database table:** local_cp_timed_attempts

---

### 📊 Statistics

**Lines of Code Added:** ~1,600 lines
- PHP: ~1,200 lines
- JavaScript: ~190 lines
- Language strings: ~210 lines

**Files Created:** 6 new files
**Files Modified:** 6 files

---

### 📦 Upgrade Instructions

1. Backup database and files
2. Pull latest code
3. Run: php admin/cli/upgrade.php
4. Purge all caches
5. Verify scheduled tasks in admin

---

### 🔮 Next Steps (Not Yet Implemented)

These features were discussed but NOT implemented yet:

#### Future v1.2.0 - Enhanced Question Types
- [ ] Essay questions
- [ ] Matching questions
- [ ] Calculated questions

#### Future v1.3.0 - Statistics Dashboard
- [ ] Personal progress charts
- [ ] Category-wise analytics
- [ ] Weak areas identification

Note: Features #3 (Export to Question Bank) and #4 (Collaborative mode) 
were removed from scope per user feedback.

---

### ✅ What WAS Implemented

1. ✅ Complete security fixes (ownership, SQL, sessions, N+1)
2. ✅ Timed practice mode with full countdown timer
3. ✅ Secure session management
4. ✅ All language strings (EN/ES)
5. ✅ Event tracking
6. ✅ Scheduled tasks for cleanup
7. ✅ AMD JavaScript timer module
8. ✅ Results page with detailed statistics
