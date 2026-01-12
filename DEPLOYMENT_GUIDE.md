# Deployment Guide - Practical Cases Plugin v1.2.0

Complete guide for deploying the Practical Cases plugin to production.

---

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Deployment Steps](#deployment-steps)
3. [Post-Deployment Verification](#post-deployment-verification)
4. [Configuration](#configuration)
5. [Rollback Procedures](#rollback-procedures)
6. [Troubleshooting](#troubleshooting)

---

## Pre-Deployment Checklist

### Environment Requirements

- [ ] Moodle 4.4 or higher installed
- [ ] PHP 8.1 or higher
- [ ] Database: MySQL 8.0+ or PostgreSQL 13+
- [ ] Sufficient disk space for database growth (estimate: +50MB for 1000 cases)
- [ ] Backup system in place

### Pre-Deployment Tasks

- [ ] Review CHANGELOG.md for all changes in v1.2.0
- [ ] Test in staging environment first
- [ ] Backup production database
- [ ] Backup Moodle files directory
- [ ] Notify users of maintenance window (recommended: 15-30 minutes)
- [ ] Verify scheduled tasks cron is running properly
- [ ] Document current plugin version for rollback reference

### Security Review

- [ ] Verify all capabilities are correctly assigned
- [ ] Review role permissions for:
  - `local/casospracticos:view`
  - `local/casospracticos:edit`
  - `local/casospracticos:editall`
  - `local/casospracticos:approve`
  - `local/casospracticos:delete`
- [ ] Check firewall rules allow cron job access
- [ ] Verify SSL/TLS certificates are valid

---

## Deployment Steps

### Step 1: Backup Current System

```bash
# 1. Backup database
mysqldump -u [user] -p [database_name] > backup_$(date +%Y%m%d_%H%M%S).sql

# OR for PostgreSQL
pg_dump -U [user] [database_name] > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Backup plugin files
cd /path/to/moodle/local
tar -czf casospracticos_backup_$(date +%Y%m%d_%H%M%S).tar.gz casospracticos/

# 3. Document current version
cat /path/to/moodle/local/casospracticos/version.php | grep "plugin->version"
```

### Step 2: Put Site in Maintenance Mode

```bash
# Enable maintenance mode
php admin/cli/maintenance.php --enable

# Verify maintenance mode is active
curl -I https://your-moodle-site.com | grep "503"
```

### Step 3: Deploy Plugin Files

**Option A: From Git Repository**

```bash
cd /path/to/moodle/local/casospracticos

# Fetch latest changes
git fetch origin claude/review-moodle-plugin-uJEPe

# Pull the specific branch
git pull origin claude/review-moodle-plugin-uJEPe

# Verify version
cat version.php | grep "plugin->version"
# Should show: 2026011216
```

**Option B: Manual File Upload**

```bash
# Upload plugin files to server
scp -r local_casospracticos/ user@server:/path/to/moodle/local/

# Set correct permissions
chown -R www-data:www-data /path/to/moodle/local/casospracticos
chmod -R 755 /path/to/moodle/local/casospracticos
```

### Step 4: Run Database Upgrade

```bash
cd /path/to/moodle

# Run upgrade script
php admin/cli/upgrade.php --non-interactive

# Verify upgrade completed successfully
echo $?  # Should output: 0
```

### Step 5: Purge Caches

```bash
# Purge all caches
php admin/cli/purge_caches.php

# Verify JavaScript is recompiled
ls -la /path/to/moodle/localcache/mustache/
ls -la /path/to/moodle/localcache/lang/
```

### Step 6: Verify Scheduled Tasks

```bash
# List scheduled tasks
php admin/cli/scheduled_task.php --list | grep casospracticos

# Expected output:
# local_casospracticos\task\cleanup_abandoned_attempts
# local_casospracticos\task\cleanup_audit_logs
# local_casospracticos\task\cleanup_practice_sessions
# local_casospracticos\task\expire_timed_attempts
```

### Step 7: Disable Maintenance Mode

```bash
# Disable maintenance mode
php admin/cli/maintenance.php --disable

# Verify site is accessible
curl -I https://your-moodle-site.com | grep "200"
```

---

## Post-Deployment Verification

### Database Verification

```sql
-- 1. Verify new tables exist
SHOW TABLES LIKE 'local_cp_%';

-- Expected tables:
-- local_cp_categories
-- local_cp_cases
-- local_cp_questions
-- local_cp_answers
-- local_cp_audit_log
-- local_cp_reviews
-- local_cp_usage
-- local_cp_practice_attempts
-- local_cp_practice_responses
-- local_cp_practice_sessions (NEW in v1.1.0)
-- local_cp_timed_attempts (NEW in v1.1.0)
-- local_cp_achievements

-- 2. Verify practice_sessions table structure
DESCRIBE local_cp_practice_sessions;

-- Expected columns:
-- id, userid, caseid, token, timecreated, timeexpiry

-- 3. Verify timed_attempts table structure
DESCRIBE local_cp_timed_attempts;

-- Expected columns:
-- id, userid, caseid, token, timelimit, score, maxscore, percentage,
-- status, responses, timestarted, timesubmitted, timecreated

-- 4. Check for any data integrity issues
SELECT COUNT(*) FROM local_cp_cases WHERE status IS NULL;
-- Should return: 0

SELECT COUNT(*) FROM local_cp_questions WHERE qtype NOT IN ('multichoice', 'truefalse', 'shortanswer', 'essay', 'matching');
-- Should return: 0
```

### Functional Testing Checklist

#### Basic Functionality
- [ ] Login as admin
- [ ] Navigate to Site administration → Plugins → Local plugins → Practical Cases
- [ ] Verify plugin version shows v1.2.0 (2026011216)
- [ ] Check no error messages in admin notifications

#### Security Fixes (v1.0.4)
- [ ] Test ownership verification:
  1. Create a case as User A
  2. Login as User B (without 'editall' capability)
  3. Try to change status of User A's case
  4. Should fail with permission error

- [ ] Test SQL injection fix:
  1. Navigate to any case view page
  2. Check total marks display correctly
  3. Monitor database logs for proper parameterized queries

#### Secure Sessions (v1.1.0)
- [ ] Start regular practice mode
- [ ] Verify session token in database: `SELECT * FROM local_cp_practice_sessions WHERE userid = [your_id];`
- [ ] Check token is 64 characters (hex format)
- [ ] Try accessing practice with invalid token → should fail
- [ ] Complete practice and verify session is cleaned up

#### Timed Practice Mode (v1.1.0)
- [ ] Navigate to a published case
- [ ] Click "Timed Practice" button
- [ ] Verify countdown timer appears and counts down
- [ ] Check timer color changes:
  - Blue: > 5 minutes remaining
  - Yellow: 5-1 minutes remaining
  - Red: < 1 minute remaining
- [ ] Verify warnings appear at 5 min and 1 min
- [ ] Submit before time expires → verify results page
- [ ] Start new attempt and let timer expire → verify auto-submit
- [ ] Check attempt record in database:
  ```sql
  SELECT * FROM local_cp_timed_attempts WHERE userid = [your_id] ORDER BY timecreated DESC LIMIT 1;
  ```

#### Essay Questions (v1.2.0)
- [ ] Create a new case with Essay question type
- [ ] Practice the case in regular mode
- [ ] Submit essay response (multi-line text)
- [ ] Verify score shows 0 with "Manual grading required" message
- [ ] Check response is stored: `SELECT response FROM local_cp_practice_responses WHERE questionid = [essay_q_id];`

#### Matching Questions (v1.2.0)
- [ ] Create a new case with Matching question type
- [ ] Add multiple matching pairs (e.g., 4 pairs)
- [ ] Practice the case
- [ ] Answer with 2 correct + 2 incorrect matches
- [ ] Verify partial credit: score = 50% (2/4)
- [ ] Check correct answers are shown for wrong matches

#### Scheduled Tasks
- [ ] Run cleanup task manually:
  ```bash
  php admin/cli/scheduled_task.php --execute='\local_casospracticos\task\cleanup_practice_sessions'
  ```
- [ ] Verify expired sessions are removed
- [ ] Run timed attempts expiry:
  ```bash
  php admin/cli/scheduled_task.php --execute='\local_casospracticos\task\expire_timed_attempts'
  ```
- [ ] Check abandoned attempts are marked as expired

#### Performance Verification
- [ ] Navigate to main cases list page
- [ ] Enable Moodle debugging and database query logging
- [ ] Check categories sidebar generates only 1 query (not N+1)
- [ ] Verify page load time < 2 seconds
- [ ] Test with 100+ cases to ensure no performance degradation

---

## Configuration

### Plugin Settings

Navigate to: **Site administration → Plugins → Local plugins → Practical Cases → Settings**

Available settings:

1. **Enable Timed Practice Mode** (default: Yes)
   - Enables/disables timed practice functionality globally

2. **Default Time Limit** (default: 30 minutes)
   - Default time limit for timed practice attempts

3. **Session Expiry** (default: 2 hours)
   - How long practice sessions remain valid

4. **Enable Audit Logging** (default: Yes)
   - Track all changes to cases, questions, and answers

5. **Cleanup Retention** (default: 90 days)
   - How long to keep old audit logs before cleanup

### Scheduled Tasks Configuration

Navigate to: **Site administration → Server → Scheduled tasks**

Filter for "casospracticos" and verify schedules:

| Task | Schedule | Purpose |
|------|----------|---------|
| cleanup_abandoned_attempts | Daily at 3:30 AM | Remove practice attempts older than 7 days |
| cleanup_audit_logs | Daily at 2:00 AM | Remove audit logs older than retention period |
| cleanup_practice_sessions | Every 30 minutes | Remove expired session tokens |
| expire_timed_attempts | Every 15 minutes | Mark abandoned timed attempts as expired |

**Recommended adjustments for high-traffic sites:**
- Increase frequency of `cleanup_practice_sessions` to every 15 minutes
- Run `expire_timed_attempts` every 5 minutes during peak hours

### Capability Assignments

Verify role capabilities are correctly assigned:

**For Students:**
- `local/casospracticos:view` → Yes
- `local/casospracticos:edit` → No
- `local/casospracticos:editall` → No
- `local/casospracticos:approve` → No

**For Teachers:**
- `local/casospracticos:view` → Yes
- `local/casospracticos:edit` → Yes
- `local/casospracticos:editall` → No
- `local/casospracticos:approve` → Yes

**For Admins:**
- All capabilities → Yes

### Performance Tuning

For sites with 1000+ cases:

1. **Enable Database Query Caching:**
```php
// In config.php
$CFG->cachejs = true;
$CFG->langstringcache = true;
```

2. **Increase Session Cache:**
```php
// In config.php
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = '127.0.0.1';
```

3. **Enable OpCache:**
```php
// In php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
```

---

## Rollback Procedures

### Emergency Rollback (If Critical Issues Found)

**Step 1: Enable Maintenance Mode**
```bash
php admin/cli/maintenance.php --enable
```

**Step 2: Restore Database Backup**
```bash
# MySQL
mysql -u [user] -p [database_name] < backup_[timestamp].sql

# PostgreSQL
psql -U [user] -d [database_name] -f backup_[timestamp].sql
```

**Step 3: Restore Plugin Files**
```bash
cd /path/to/moodle/local
rm -rf casospracticos/
tar -xzf casospracticos_backup_[timestamp].tar.gz
```

**Step 4: Purge Caches**
```bash
php admin/cli/purge_caches.php
```

**Step 5: Disable Maintenance Mode**
```bash
php admin/cli/maintenance.php --disable
```

### Partial Rollback (Keep Data, Revert Code)

If you need to keep user data but revert code changes:

```bash
# 1. Enable maintenance mode
php admin/cli/maintenance.php --enable

# 2. Checkout previous version
cd /path/to/moodle/local/casospracticos
git checkout [previous_commit_hash]

# 3. Note: Database changes remain (no data loss)
# New tables (practice_sessions, timed_attempts) will remain but unused

# 4. Purge caches
php admin/cli/purge_caches.php

# 5. Disable maintenance mode
php admin/cli/maintenance.php --disable
```

---

## Troubleshooting

### Common Issues

#### Issue 1: JavaScript Timer Not Loading

**Symptoms:**
- Timed practice page shows but timer doesn't appear
- Console error: "Cannot find AMD module"

**Solution:**
```bash
# Purge JavaScript cache
php admin/cli/purge_caches.php

# Verify AMD module exists
ls -la /path/to/moodle/local/casospracticos/amd/src/timer.js

# Check if module is minified
ls -la /path/to/moodle/local/casospracticos/amd/build/timer.min.js

# If not, run grunt (development only)
grunt amd
```

#### Issue 2: Database Upgrade Fails

**Symptoms:**
- Error during `php admin/cli/upgrade.php`
- Message: "Table already exists" or "Field already exists"

**Solution:**
```sql
-- Check if tables exist
SHOW TABLES LIKE 'local_cp_practice_sessions';
SHOW TABLES LIKE 'local_cp_timed_attempts';

-- If they exist, the upgrade will skip creation (this is normal)
-- If upgrade still fails, manually update plugin version:
UPDATE mdl_config_plugins
SET value = '2026011216'
WHERE plugin = 'local_casospracticos' AND name = 'version';
```

#### Issue 3: Scheduled Tasks Not Running

**Symptoms:**
- Expired sessions not being cleaned up
- Abandoned timed attempts remain "in_progress"

**Solution:**
```bash
# 1. Verify cron is running
php admin/cli/cron.php

# 2. Check last run time
php admin/cli/scheduled_task.php --list | grep casospracticos

# 3. Force run specific task
php admin/cli/scheduled_task.php --execute='\local_casospracticos\task\cleanup_practice_sessions'

# 4. Check cron logs
tail -f /var/log/moodle/cron.log
```

#### Issue 4: Permission Denied Errors

**Symptoms:**
- Users can't change case status
- Error: "You don't have permission to perform this action"

**Solution:**
```sql
-- 1. Verify capabilities are assigned
SELECT * FROM mdl_role_capabilities
WHERE capability LIKE 'local/casospracticos%';

-- 2. Check user's role assignments
SELECT * FROM mdl_role_assignments WHERE userid = [affected_user_id];

-- 3. Manually grant capability (if needed)
-- Navigate to: Site administration → Users → Permissions → Define roles
-- Edit role and enable required capabilities
```

#### Issue 5: Essay/Matching Questions Not Displaying

**Symptoms:**
- Essay or Matching questions show as "Unknown question type"
- Questions don't render properly

**Solution:**
```php
// 1. Verify question type constants in question_manager.php
// Should include:
// const QTYPE_ESSAY = 'essay';
// const QTYPE_MATCHING = 'matching';

// 2. Check language strings exist
php admin/cli/purge_caches.php

// 3. Verify question type in database
SELECT qtype, COUNT(*) FROM mdl_local_cp_questions GROUP BY qtype;
```

### Debug Mode

Enable debugging to diagnose issues:

```php
// Add to config.php (DEVELOPMENT ONLY - NEVER IN PRODUCTION)
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
$CFG->debugsqltrace = true; // Shows all SQL queries
```

**Remember to disable debugging in production:**
```php
$CFG->debug = 0;
$CFG->debugdisplay = 0;
```

### Support Resources

- **Plugin Documentation:** See README.md and CHANGELOG.md
- **Moodle Forums:** https://moodle.org/mod/forum/
- **Plugin Issue Tracker:** [Add your GitHub issues URL here]
- **Emergency Contact:** [Add your support email/phone here]

---

## Post-Deployment Monitoring

### First 24 Hours

Monitor these metrics closely:

1. **Error Logs:**
   ```bash
   tail -f /path/to/moodle/error.log
   grep -i "casospracticos" /path/to/moodle/error.log
   ```

2. **Database Growth:**
   ```sql
   SELECT
     table_name,
     ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
   FROM information_schema.TABLES
   WHERE table_name LIKE 'local_cp_%'
   ORDER BY (data_length + index_length) DESC;
   ```

3. **Performance Metrics:**
   - Page load times for main index
   - Practice mode start time
   - Timed practice submission time

4. **User Activity:**
   ```sql
   SELECT COUNT(*) FROM local_cp_practice_sessions;
   SELECT COUNT(*) FROM local_cp_timed_attempts WHERE status = 'finished';
   ```

### First Week

- [ ] Review scheduled task execution logs
- [ ] Check for any PHP warnings/notices
- [ ] Monitor user feedback/support tickets
- [ ] Verify cleanup tasks are working
- [ ] Analyze performance under load

---

## Security Hardening Checklist

After deployment, verify these security measures:

- [ ] All user inputs are properly sanitized
- [ ] CSRF tokens are present on all forms
- [ ] Session tokens are cryptographically secure (64-char hex)
- [ ] Database queries use parameterized statements
- [ ] File uploads (if any) have proper validation
- [ ] Rate limiting is functional
- [ ] Audit logging is capturing all changes
- [ ] Backup retention policy is enforced

---

## Deployment Sign-Off

**Deployment completed by:** _________________
**Date:** _________________
**Time:** _________________
**Version deployed:** 1.2.0 (2026011216)
**Rollback tested:** ☐ Yes ☐ No
**All verifications passed:** ☐ Yes ☐ No

**Notes:**
_______________________________________________
_______________________________________________
_______________________________________________

---

**Deployment Status: READY FOR PRODUCTION** ✅
