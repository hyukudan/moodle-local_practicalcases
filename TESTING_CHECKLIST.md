# Testing Checklist - Practical Cases Plugin v1.2.0

Comprehensive testing checklist for QA validation before production deployment.

---

## Test Environment Setup

**Prerequisites:**
- [ ] Fresh Moodle 4.4+ installation
- [ ] Plugin installed and activated
- [ ] Test data created (categories, cases, questions)
- [ ] Multiple test users with different roles:
  - Admin user
  - Teacher user (with edit capability)
  - Teacher user (without editall capability)
  - Student user

---

## Section 1: Core Functionality Tests

### 1.1 Category Management

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| CAT-001 | Create a new category | Category appears in list | ☐ Pass ☐ Fail | |
| CAT-002 | Create nested subcategory | Hierarchy displays correctly | ☐ Pass ☐ Fail | |
| CAT-003 | Edit category name | Changes reflect immediately | ☐ Pass ☐ Fail | |
| CAT-004 | Delete empty category | Category removed successfully | ☐ Pass ☐ Fail | |
| CAT-005 | Try to delete category with cases | Warning/error shown | ☐ Pass ☐ Fail | |
| CAT-006 | Reorder categories | New order persists | ☐ Pass ☐ Fail | |
| CAT-007 | View categories sidebar | Shows correct case counts | ☐ Pass ☐ Fail | |

### 1.2 Case Management

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| CASE-001 | Create new case (draft) | Case saved with draft status | ☐ Pass ☐ Fail | |
| CASE-002 | Add long statement (5000+ chars) | Full text saved correctly | ☐ Pass ☐ Fail | |
| CASE-003 | Set difficulty level (1-5) | Level displays on case card | ☐ Pass ☐ Fail | |
| CASE-004 | Add tags to case | Tags searchable/filterable | ☐ Pass ☐ Fail | |
| CASE-005 | Change status: draft → published | Status updates, visible to students | ☐ Pass ☐ Fail | |
| CASE-006 | Archive published case | Status updates, hidden from students | ☐ Pass ☐ Fail | |
| CASE-007 | Edit existing case | Changes saved, version incremented | ☐ Pass ☐ Fail | |
| CASE-008 | Delete case with questions | Cascade delete works | ☐ Pass ☐ Fail | |
| CASE-009 | Duplicate case | Copy created with all questions | ☐ Pass ☐ Fail | |
| CASE-010 | View case as student | See statement and questions | ☐ Pass ☐ Fail | |

### 1.3 Question Management

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| Q-001 | Add multichoice question (single) | Question created with answers | ☐ Pass ☐ Fail | |
| Q-002 | Add multichoice question (multiple) | Multiple selections allowed | ☐ Pass ☐ Fail | |
| Q-003 | Add true/false question | Two answer options only | ☐ Pass ☐ Fail | |
| Q-004 | Add short answer question | Text input expected | ☐ Pass ☐ Fail | |
| Q-005 | Add essay question (NEW v1.2.0) | Large textarea displayed | ☐ Pass ☐ Fail | |
| Q-006 | Add matching question (NEW v1.2.0) | Pairs interface shown | ☐ Pass ☐ Fail | |
| Q-007 | Set question mark value | Custom mark applied in scoring | ☐ Pass ☐ Fail | |
| Q-008 | Add general feedback | Feedback shown after answer | ☐ Pass ☐ Fail | |
| Q-009 | Reorder questions | New order reflected in practice | ☐ Pass ☐ Fail | |
| Q-010 | Delete question | Removed from case | ☐ Pass ☐ Fail | |
| Q-011 | Edit question text | Changes saved correctly | ☐ Pass ☐ Fail | |
| Q-012 | Shuffle answers enabled | Answers randomized in practice | ☐ Pass ☐ Fail | |

---

## Section 2: Security Tests (v1.0.4 Fixes)

### 2.1 Ownership Verification

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| SEC-001 | Teacher A creates case | Teacher A is owner | ☐ Pass ☐ Fail | |
| SEC-002 | Teacher B tries to publish A's case | Permission denied error | ☐ Pass ☐ Fail | |
| SEC-003 | Teacher B tries to archive A's case | Permission denied error | ☐ Pass ☐ Fail | |
| SEC-004 | Admin publishes any case | Success (has editall) | ☐ Pass ☐ Fail | |
| SEC-005 | Teacher B with editall publishes A's case | Success | ☐ Pass ☐ Fail | |
| SEC-006 | Student tries to change case status | No action buttons visible | ☐ Pass ☐ Fail | |

### 2.2 SQL Injection Prevention

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| SEC-007 | View case with ID: `1 OR 1=1` | 404 or safe error, no data leak | ☐ Pass ☐ Fail | |
| SEC-008 | Search with: `'; DROP TABLE--` | Safe handling, no SQL execution | ☐ Pass ☐ Fail | |
| SEC-009 | Case name with SQL: `<script>alert(1)` | Escaped, no execution | ☐ Pass ☐ Fail | |
| SEC-010 | View total marks for case | Uses parameterized query | ☐ Pass ☐ Fail | |

### 2.3 Session Security

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| SEC-011 | Start practice session | Token created in database | ☐ Pass ☐ Fail | |
| SEC-012 | Check token format | 64-char hex string | ☐ Pass ☐ Fail | |
| SEC-013 | Try accessing with invalid token | Access denied | ☐ Pass ☐ Fail | |
| SEC-014 | Session expires after 2 hours | Token no longer valid | ☐ Pass ☐ Fail | |
| SEC-015 | User A tries to use User B's token | Access denied (ownership check) | ☐ Pass ☐ Fail | |
| SEC-016 | Complete practice | Token cleaned up | ☐ Pass ☐ Fail | |

### 2.4 XSS Prevention

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| SEC-017 | Case name: `<script>alert(1)</script>` | HTML escaped, no alert | ☐ Pass ☐ Fail | |
| SEC-018 | Category name: `<img src=x onerror=alert(1)>` | Escaped, no execution | ☐ Pass ☐ Fail | |
| SEC-019 | Question text: `<svg onload=alert(1)>` | Escaped, no execution | ☐ Pass ☐ Fail | |
| SEC-020 | Answer text with HTML tags | Properly sanitized | ☐ Pass ☐ Fail | |

---

## Section 3: Practice Mode Tests

### 3.1 Regular Practice Mode

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| PRAC-001 | Start practice on published case | Practice interface loads | ☐ Pass ☐ Fail | |
| PRAC-002 | Answer multichoice (correct) | Marked correct, full marks | ☐ Pass ☐ Fail | |
| PRAC-003 | Answer multichoice (incorrect) | Marked wrong, 0 marks | ☐ Pass ☐ Fail | |
| PRAC-004 | Answer true/false question | Graded correctly | ☐ Pass ☐ Fail | |
| PRAC-005 | Answer short answer (exact match) | Full marks awarded | ☐ Pass ☐ Fail | |
| PRAC-006 | Answer short answer (case mismatch) | Accepted (case-insensitive) | ☐ Pass ☐ Fail | |
| PRAC-007 | Submit essay answer | Score = 0, manual grading message | ☐ Pass ☐ Fail | |
| PRAC-008 | Complete matching question (all correct) | 100% score | ☐ Pass ☐ Fail | |
| PRAC-009 | Partial matching (2/4 correct) | 50% partial credit | ☐ Pass ☐ Fail | |
| PRAC-010 | Skip questions (leave unanswered) | 0 marks for skipped | ☐ Pass ☐ Fail | |
| PRAC-011 | Submit practice | Results page displays | ☐ Pass ☐ Fail | |
| PRAC-012 | View results breakdown | All answers and feedback shown | ☐ Pass ☐ Fail | |
| PRAC-013 | Retry same case | New attempt created | ☐ Pass ☐ Fail | |
| PRAC-014 | View attempt history | All past attempts listed | ☐ Pass ☐ Fail | |

### 3.2 Timed Practice Mode (v1.1.0)

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| TIME-001 | Click "Timed Practice" button | Redirect to timed practice page | ☐ Pass ☐ Fail | |
| TIME-002 | Timed practice starts | Timer begins countdown | ☐ Pass ☐ Fail | |
| TIME-003 | Timer format | Shows HH:MM:SS format | ☐ Pass ☐ Fail | |
| TIME-004 | Timer color: > 5 min remaining | Blue (alert-info) | ☐ Pass ☐ Fail | |
| TIME-005 | Timer color: 5-1 min remaining | Yellow (alert-warning) | ☐ Pass ☐ Fail | |
| TIME-006 | Timer color: < 1 min remaining | Red (alert-danger) | ☐ Pass ☐ Fail | |
| TIME-007 | Warning at 5 min | Notification appears | ☐ Pass ☐ Fail | |
| TIME-008 | Warning at 1 min | Notification appears | ☐ Pass ☐ Fail | |
| TIME-009 | Timer < 30 seconds | Pulsing animation | ☐ Pass ☐ Fail | |
| TIME-010 | Submit before time expires | Redirects to results | ☐ Pass ☐ Fail | |
| TIME-011 | Timer expires (00:00:00) | Auto-submit after 2 seconds | ☐ Pass ☐ Fail | |
| TIME-012 | Results show time statistics | Time spent / time limit displayed | ☐ Pass ☐ Fail | |
| TIME-013 | Multiple timed attempts | Best attempt tracked | ☐ Pass ☐ Fail | |
| TIME-014 | Try to navigate away | Beforeunload warning shows | ☐ Pass ☐ Fail | |
| TIME-015 | Abandon timed attempt (close tab) | Marked as expired by cleanup task | ☐ Pass ☐ Fail | |

---

## Section 4: Essay & Matching Questions (v1.2.0)

### 4.1 Essay Questions

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| ESSAY-001 | Create essay question | Question saved with qtype='essay' | ☐ Pass ☐ Fail | |
| ESSAY-002 | Display in practice mode | Large textarea (8 rows) shown | ☐ Pass ☐ Fail | |
| ESSAY-003 | Submit short essay (100 words) | Saved correctly | ☐ Pass ☐ Fail | |
| ESSAY-004 | Submit long essay (1000+ words) | Full text saved | ☐ Pass ☐ Fail | |
| ESSAY-005 | Submit with HTML/formatting | PARAM_RAW allows rich content | ☐ Pass ☐ Fail | |
| ESSAY-006 | Submit with URLs | Links preserved | ☐ Pass ☐ Fail | |
| ESSAY-007 | Check score | Always 0 (manual grading) | ☐ Pass ☐ Fail | |
| ESSAY-008 | Check feedback | "Manual grading required" message | ☐ Pass ☐ Fail | |
| ESSAY-009 | View results page | Essay text displayed in full | ☐ Pass ☐ Fail | |
| ESSAY-010 | Essay in timed practice | Works same as regular practice | ☐ Pass ☐ Fail | |

### 4.2 Matching Questions

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| MATCH-001 | Create matching question | Question saved with qtype='matching' | ☐ Pass ☐ Fail | |
| MATCH-002 | Add 4 matching pairs | All pairs saved | ☐ Pass ☐ Fail | |
| MATCH-003 | Display in practice | Dropdowns for each item | ☐ Pass ☐ Fail | |
| MATCH-004 | Answer options shuffled | Options in random order | ☐ Pass ☐ Fail | |
| MATCH-005 | Match all correctly | Score = 100% | ☐ Pass ☐ Fail | |
| MATCH-006 | Match 3/4 correctly | Score = 75% (partial credit) | ☐ Pass ☐ Fail | |
| MATCH-007 | Match 1/4 correctly | Score = 25% | ☐ Pass ☐ Fail | |
| MATCH-008 | Match none correctly | Score = 0% | ☐ Pass ☐ Fail | |
| MATCH-009 | View results: correct matches | ✓ indicator shown | ☐ Pass ☐ Fail | |
| MATCH-010 | View results: wrong matches | Correct answer revealed | ☐ Pass ☐ Fail | |
| MATCH-011 | Case-insensitive matching | "Paris" = "paris" | ☐ Pass ☐ Fail | |
| MATCH-012 | Matching in timed practice | Works same as regular practice | ☐ Pass ☐ Fail | |

---

## Section 5: Performance & Database Tests

### 5.1 Query Optimization

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| PERF-001 | Load main index with 100 cases | Single query for categories | ☐ Pass ☐ Fail | |
| PERF-002 | Check category sidebar | No N+1 queries | ☐ Pass ☐ Fail | |
| PERF-003 | Load case with 20 questions | Bulk fetch answers | ☐ Pass ☐ Fail | |
| PERF-004 | View stats for case | Single aggregation query | ☐ Pass ☐ Fail | |
| PERF-005 | Search cases (filter by tag) | Indexed query used | ☐ Pass ☐ Fail | |
| PERF-006 | Load practice attempts history | Efficient pagination | ☐ Pass ☐ Fail | |

### 5.2 Database Integrity

| Test ID | Test Case | Expected Result | Status | SQL Check |
|---------|-----------|----------------|--------|-----------|
| DB-001 | All tables exist | 12 tables present | ☐ Pass ☐ Fail | `SHOW TABLES LIKE 'local_cp_%'` |
| DB-002 | Foreign keys valid | No orphaned records | ☐ Pass ☐ Fail | `SELECT * FROM local_cp_questions WHERE caseid NOT IN (SELECT id FROM local_cp_cases)` |
| DB-003 | Indexes created | Performance indexes present | ☐ Pass ☐ Fail | `SHOW INDEX FROM local_cp_cases` |
| DB-004 | No NULL in required fields | Constraints enforced | ☐ Pass ☐ Fail | `SELECT * FROM local_cp_cases WHERE name IS NULL` |
| DB-005 | Token uniqueness | All tokens unique | ☐ Pass ☐ Fail | `SELECT token, COUNT(*) FROM local_cp_practice_sessions GROUP BY token HAVING COUNT(*) > 1` |
| DB-006 | Cascade deletes work | Child records removed | ☐ Pass ☐ Fail | Delete a case and check questions table |

---

## Section 6: Scheduled Tasks Tests

### 6.1 Cleanup Tasks

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| TASK-001 | List all scheduled tasks | 4 casospracticos tasks shown | ☐ Pass ☐ Fail | |
| TASK-002 | Run cleanup_practice_sessions | Expired sessions removed | ☐ Pass ☐ Fail | |
| TASK-003 | Verify session cleanup | Sessions > 2 hours deleted | ☐ Pass ☐ Fail | |
| TASK-004 | Run expire_timed_attempts | Abandoned attempts expired | ☐ Pass ☐ Fail | |
| TASK-005 | Verify timed attempts cleanup | Old in_progress → expired | ☐ Pass ☐ Fail | |
| TASK-006 | Run cleanup_abandoned_attempts | Attempts > 7 days removed | ☐ Pass ☐ Fail | |
| TASK-007 | Run cleanup_audit_logs | Logs > 90 days removed | ☐ Pass ☐ Fail | |
| TASK-008 | Check task execution logs | No errors in logs | ☐ Pass ☐ Fail | |

---

## Section 7: User Interface Tests

### 7.1 Responsive Design

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| UI-001 | Desktop view (1920x1080) | Layout optimal | ☐ Pass ☐ Fail | |
| UI-002 | Tablet view (768x1024) | Responsive layout | ☐ Pass ☐ Fail | |
| UI-003 | Mobile view (375x667) | Mobile-friendly | ☐ Pass ☐ Fail | |
| UI-004 | Timer on mobile | Visible and readable | ☐ Pass ☐ Fail | |
| UI-005 | Long case name wraps | No overflow | ☐ Pass ☐ Fail | |

### 7.2 Accessibility

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| A11Y-001 | Keyboard navigation | All actions accessible | ☐ Pass ☐ Fail | |
| A11Y-002 | Screen reader (timer) | ARIA labels present | ☐ Pass ☐ Fail | |
| A11Y-003 | Color contrast | WCAG AA compliant | ☐ Pass ☐ Fail | |
| A11Y-004 | Form labels | All inputs labeled | ☐ Pass ☐ Fail | |
| A11Y-005 | Focus indicators | Visible on all elements | ☐ Pass ☐ Fail | |

### 7.3 Internationalization

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| I18N-001 | Switch to Spanish | All strings translated | ☐ Pass ☐ Fail | |
| I18N-002 | New strings (v1.1.0) | Timed practice strings present | ☐ Pass ☐ Fail | |
| I18N-003 | New strings (v1.2.0) | Essay/matching strings present | ☐ Pass ☐ Fail | |
| I18N-004 | Date/time formatting | Uses user locale | ☐ Pass ☐ Fail | |

---

## Section 8: Integration Tests

### 8.1 Moodle Integration

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| INT-001 | User enrollments | Respects course contexts | ☐ Pass ☐ Fail | |
| INT-002 | Capability checks | Moodle capabilities work | ☐ Pass ☐ Fail | |
| INT-003 | Event logging | Events in Moodle logs | ☐ Pass ☐ Fail | |
| INT-004 | Navigation block | Plugin appears in nav | ☐ Pass ☐ Fail | |
| INT-005 | Theme compatibility | Works with Boost theme | ☐ Pass ☐ Fail | |
| INT-006 | Cache API | Uses Moodle caching | ☐ Pass ☐ Fail | |

### 8.2 Backup & Restore

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| BACKUP-001 | Backup plugin data | Backup includes all tables | ☐ Pass ☐ Fail | |
| BACKUP-002 | Restore to new site | Data restored correctly | ☐ Pass ☐ Fail | |
| BACKUP-003 | Restore maintains relationships | Foreign keys intact | ☐ Pass ☐ Fail | |

---

## Section 9: Edge Cases & Stress Tests

### 9.1 Edge Cases

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| EDGE-001 | Case with 0 questions | Handled gracefully | ☐ Pass ☐ Fail | |
| EDGE-002 | Case with 100+ questions | Loads without timeout | ☐ Pass ☐ Fail | |
| EDGE-003 | Question with very long text (10k chars) | Saved and displayed | ☐ Pass ☐ Fail | |
| EDGE-004 | Unicode in question text | Displays correctly (emoji, etc) | ☐ Pass ☐ Fail | |
| EDGE-005 | Concurrent practice on same case | No conflicts | ☐ Pass ☐ Fail | |
| EDGE-006 | Multiple timed attempts simultaneously | Each independent | ☐ Pass ☐ Fail | |
| EDGE-007 | Timer at exactly 0:00:00 | Auto-submit triggered | ☐ Pass ☐ Fail | |
| EDGE-008 | Very short time limit (30 seconds) | Works correctly | ☐ Pass ☐ Fail | |
| EDGE-009 | Very long time limit (24 hours) | No overflow issues | ☐ Pass ☐ Fail | |
| EDGE-010 | Matching with 1 pair only | Edge case handled | ☐ Pass ☐ Fail | |
| EDGE-011 | Essay with empty submission | Accepted (score = 0) | ☐ Pass ☐ Fail | |

### 9.2 Stress Tests

| Test ID | Test Case | Expected Result | Status | Notes |
|---------|-----------|----------------|--------|-------|
| STRESS-001 | 1000 cases in database | Index loads < 3 seconds | ☐ Pass ☐ Fail | |
| STRESS-002 | 50 concurrent practice sessions | No deadlocks | ☐ Pass ☐ Fail | |
| STRESS-003 | 100 concurrent timed attempts | Timers accurate | ☐ Pass ☐ Fail | |
| STRESS-004 | 10,000 audit log entries | Cleanup task completes | ☐ Pass ☐ Fail | |

---

## Section 10: Regression Tests

Test that existing functionality still works after v1.2.0 updates:

| Test ID | Feature | Expected Result | Status | Notes |
|---------|---------|----------------|--------|-------|
| REG-001 | Old multichoice questions | Still work correctly | ☐ Pass ☐ Fail | |
| REG-002 | Existing practice attempts | History preserved | ☐ Pass ☐ Fail | |
| REG-003 | Audit logs from v1.0 | Still viewable | ☐ Pass ☐ Fail | |
| REG-004 | Bulk operations | Still functional | ☐ Pass ☐ Fail | |
| REG-005 | Export to PDF/CSV | Works with new qtypes | ☐ Pass ☐ Fail | |
| REG-006 | Search functionality | Finds essay/matching questions | ☐ Pass ☐ Fail | |
| REG-007 | Advanced filters | All filters working | ☐ Pass ☐ Fail | |

---

## Test Summary

**Total Test Cases:** 180+

**Test Coverage:**
- Core Functionality: 40 tests
- Security: 20 tests
- Practice Mode: 29 tests
- Essay & Matching: 22 tests
- Performance: 12 tests
- Scheduled Tasks: 8 tests
- UI/UX: 15 tests
- Integration: 9 tests
- Edge Cases & Stress: 15 tests
- Regression: 7 tests

---

## Test Execution Log

**Test Execution Date:** _________________
**Tester Name:** _________________
**Environment:** ☐ Development ☐ Staging ☐ Production

**Results Summary:**
- Total Tests: _____
- Passed: _____
- Failed: _____
- Blocked: _____
- Pass Rate: _____%

**Critical Failures (P0):**
_______________________________________________
_______________________________________________

**High Priority Failures (P1):**
_______________________________________________
_______________________________________________

**Medium Priority Failures (P2):**
_______________________________________________
_______________________________________________

**Sign-off:**
- [ ] All P0 and P1 tests passed
- [ ] Known issues documented
- [ ] Ready for production deployment

**QA Lead Approval:** _________________
**Date:** _________________

---

## Automated Testing

For continuous integration, run PHPUnit tests:

```bash
# Run all plugin tests
vendor/bin/phpunit --group local_casospracticos

# Run specific test class
vendor/bin/phpunit local/casospracticos/tests/question_manager_test.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/ local/casospracticos/tests/
```

**Minimum Coverage Target:** 80%

---

**Testing Complete** ✅
