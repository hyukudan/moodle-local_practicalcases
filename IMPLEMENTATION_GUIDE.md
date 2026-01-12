# Guía de Implementación - Correcciones Prioritarias

Esta guía proporciona ejemplos de código específicos para implementar las correcciones de seguridad prioritarias identificadas en la revisión.

---

## 🔴 CORRECCIÓN #1: Verificación de Ownership en Cambios de Estado

### Archivo: `index.php`

**Código actual (VULNERABLE):**
```php
// Process actions.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'publish':
            require_capability('local/casospracticos:edit', $context);
            case_manager::set_status($id, case_manager::STATUS_PUBLISHED);
            \core\notification::success(get_string('caseupdated', 'local_casospracticos'));
            break;

        case 'archive':
            require_capability('local/casospracticos:edit', $context);
            case_manager::set_status($id, case_manager::STATUS_ARCHIVED);
            \core\notification::success(get_string('caseupdated', 'local_casospracticos'));
            break;

        case 'draft':
            require_capability('local/casospracticos:edit', $context);
            case_manager::set_status($id, case_manager::STATUS_DRAFT);
            \core\notification::success(get_string('caseupdated', 'local_casospracticos'));
            break;
    }
}
```

**Código corregido (SEGURO):**
```php
// Process actions.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'deletecat':
            // ... código existente ...
            break;

        case 'deletecase':
            // ... código existente ...
            break;

        case 'publish':
        case 'archive':
        case 'draft':
            require_capability('local/casospracticos:edit', $context);

            // Verificar propiedad del caso
            $case = case_manager::get($id);
            if (!$case) {
                throw new moodle_exception('error:casenotfound', 'local_casospracticos');
            }

            // Solo el owner o usuarios con editall pueden cambiar el estado
            if ($case->createdby != $USER->id && !has_capability('local/casospracticos:editall', $context)) {
                throw new moodle_exception('error:nopermission', 'local_casospracticos');
            }

            // Determinar el nuevo estado según la acción
            $newstatus = match($action) {
                'publish' => case_manager::STATUS_PUBLISHED,
                'archive' => case_manager::STATUS_ARCHIVED,
                'draft' => case_manager::STATUS_DRAFT,
                default => throw new coding_exception('Invalid action: ' . $action)
            };

            case_manager::set_status($id, $newstatus);
            \core\notification::success(get_string('caseupdated', 'local_casospracticos'));
            break;
    }

    // Redirect to avoid resubmission.
    redirect(new moodle_url('/local/casospracticos/index.php', ['category' => $categoryid]));
}
```

**Strings a añadir en `lang/en/local_casospracticos.php`:**
```php
$string['error:nopermission'] = 'You do not have permission to perform this action on this case';
$string['error:casenotfound'] = 'Case not found';
```

**Strings a añadir en `lang/es/local_casospracticos.php`:**
```php
$string['error:nopermission'] = 'No tienes permiso para realizar esta acción en este caso';
$string['error:casenotfound'] = 'Caso no encontrado';
```

---

## 🔴 CORRECCIÓN #2: SQL Injection en get_total_marks()

### Archivo: `classes/case_manager.php`

**Código actual (POTENCIALMENTE VULNERABLE):**
```php
/**
 * Get total marks for a case.
 *
 * @param int $id Case ID
 * @return float Total marks
 */
public static function get_total_marks(int $id): float {
    global $DB;
    return (float) $DB->get_field('local_cp_questions', 'SUM(defaultmark)', ['caseid' => $id]) ?? 0;
}
```

**Código corregido (SEGURO):**
```php
/**
 * Get total marks for a case.
 *
 * @param int $id Case ID
 * @return float Total marks
 */
public static function get_total_marks(int $id): float {
    global $DB;

    $sql = "SELECT SUM(defaultmark)
            FROM {local_cp_questions}
            WHERE caseid = :caseid";

    $total = $DB->get_field_sql($sql, ['caseid' => $id]);

    return (float) ($total ?? 0);
}
```

**Alternativa con manejo de error mejorado:**
```php
/**
 * Get total marks for a case.
 *
 * @param int $id Case ID
 * @return float Total marks
 * @throws dml_exception If database error occurs
 */
public static function get_total_marks(int $id): float {
    global $DB;

    try {
        $sql = "SELECT COALESCE(SUM(defaultmark), 0) as total
                FROM {local_cp_questions}
                WHERE caseid = :caseid";

        $result = $DB->get_record_sql($sql, ['caseid' => $id]);

        return (float) $result->total;

    } catch (\dml_exception $e) {
        // Log the error for debugging
        debugging('Error calculating total marks for case ' . $id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        return 0.0;
    }
}
```

---

## 🔴 CORRECCIÓN #3: Session Security en Practice Mode

### Archivo: `practice.php`

**Problema actual:**
```php
// Shuffle questions if requested.
if ($shuffle && !$submit) {
    shuffle($questions);
    // Store shuffled order in session.
    $SESSION->casopractico_order[$caseid] = array_column($questions, 'id');
}
```

**Solución: Crear tabla de intentos con orden almacenado**

### Paso 1: Crear nueva tabla de intentos

**Archivo: `db/install.xml`** (añadir nueva tabla)
```xml
<TABLE NAME="local_cp_practice_sessions" COMMENT="Active practice sessions with question order">
    <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="caseid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="questionorder" TYPE="text" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="token" TYPE="char" LENGTH="64" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="timeexpiry" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
    </FIELDS>
    <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="caseid_fk" TYPE="foreign" FIELDS="caseid" REFTABLE="local_cp_cases" REFFIELDS="id"/>
        <KEY NAME="userid_fk" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
    </KEYS>
    <INDEXES>
        <INDEX NAME="userid_caseid_idx" UNIQUE="false" FIELDS="userid, caseid"/>
        <INDEX NAME="token_idx" UNIQUE="true" FIELDS="token"/>
        <INDEX NAME="timeexpiry_idx" UNIQUE="false" FIELDS="timeexpiry"/>
    </INDEXES>
</TABLE>
```

### Paso 2: Crear manager para sesiones

**Nuevo archivo: `classes/practice_session_manager.php`**
```php
<?php
namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager for practice sessions.
 */
class practice_session_manager {

    /** @var string Table name */
    const TABLE = 'local_cp_practice_sessions';

    /** @var int Session expiry time in seconds (2 hours) */
    const SESSION_EXPIRY = 7200;

    /**
     * Create a new practice session.
     *
     * @param int $caseid Case ID
     * @param int $userid User ID
     * @param array $questionorder Question IDs in order
     * @return string Session token
     */
    public static function create_session(int $caseid, int $userid, array $questionorder): string {
        global $DB;

        // Clean up any existing session for this user/case
        self::cleanup_user_session($caseid, $userid);

        $record = new \stdClass();
        $record->caseid = $caseid;
        $record->userid = $userid;
        $record->questionorder = json_encode($questionorder);
        $record->token = bin2hex(random_bytes(32)); // Secure random token
        $record->timecreated = time();
        $record->timeexpiry = time() + self::SESSION_EXPIRY;

        $DB->insert_record(self::TABLE, $record);

        return $record->token;
    }

    /**
     * Get session by token.
     *
     * @param string $token Session token
     * @return object|false Session record or false
     */
    public static function get_session(string $token) {
        global $DB;

        $session = $DB->get_record(self::TABLE, ['token' => $token]);

        if (!$session) {
            return false;
        }

        // Check if expired
        if ($session->timeexpiry < time()) {
            self::delete_session($token);
            return false;
        }

        return $session;
    }

    /**
     * Get question order from session.
     *
     * @param string $token Session token
     * @return array|false Question IDs in order or false
     */
    public static function get_question_order(string $token) {
        $session = self::get_session($token);

        if (!$session) {
            return false;
        }

        return json_decode($session->questionorder, true);
    }

    /**
     * Delete a session.
     *
     * @param string $token Session token
     */
    public static function delete_session(string $token): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['token' => $token]);
    }

    /**
     * Clean up user's existing session for a case.
     *
     * @param int $caseid Case ID
     * @param int $userid User ID
     */
    private static function cleanup_user_session(int $caseid, int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['caseid' => $caseid, 'userid' => $userid]);
    }

    /**
     * Clean up expired sessions (called by scheduled task).
     */
    public static function cleanup_expired_sessions(): void {
        global $DB;
        $DB->delete_records_select(self::TABLE, 'timeexpiry < :now', ['now' => time()]);
    }
}
```

### Paso 3: Actualizar practice.php

**Código actualizado:**
```php
<?php
require_once(__DIR__ . '/../../config.php');

use local_casospracticos\case_manager;
use local_casospracticos\question_manager;
use local_casospracticos\stats_manager;
use local_casospracticos\practice_session_manager;

$caseid = required_param('id', PARAM_INT);
$submit = optional_param('submit', 0, PARAM_BOOL);
$shuffle = optional_param('shuffle', 0, PARAM_BOOL);
$sessiontoken = optional_param('token', '', PARAM_ALPHANUM);

$context = context_system::instance();
require_login();
require_capability('local/casospracticos:view', $context);

// ... código de setup de página ...

// Get questions with answers.
$questions = question_manager::get_with_answers($caseid);

// Handle session-based question order
if ($shuffle && !$submit && empty($sessiontoken)) {
    // New session: shuffle and create token
    shuffle($questions);
    $questionids = array_column($questions, 'id');
    $sessiontoken = practice_session_manager::create_session($caseid, $USER->id, $questionids);

    // Redirect to include token in URL
    redirect(new moodle_url('/local/casospracticos/practice.php', [
        'id' => $caseid,
        'token' => $sessiontoken
    ]));

} else if (!empty($sessiontoken)) {
    // Restore order from session
    $session = practice_session_manager::get_session($sessiontoken);

    if (!$session) {
        // Session expired or invalid
        \core\notification::error(get_string('error:sessionexpired', 'local_casospracticos'));
        redirect(new moodle_url('/local/casospracticos/practice.php', ['id' => $caseid]));
    }

    // Verify session belongs to current user
    if ($session->userid != $USER->id) {
        throw new moodle_exception('error:invalidsession', 'local_casospracticos');
    }

    $questionorder = json_decode($session->questionorder, true);

    // Reorder questions according to session
    $orderedquestions = [];
    foreach ($questionorder as $qid) {
        foreach ($questions as $q) {
            if ($q->id == $qid) {
                $orderedquestions[] = $q;
                break;
            }
        }
    }
    $questions = $orderedquestions;
}

// ... resto del código de procesamiento de respuestas ...

// Al finalizar el intento, limpiar la sesión
if ($submit && !empty($sessiontoken)) {
    practice_session_manager::delete_session($sessiontoken);
}

// ... código de output ...
```

### Paso 4: Crear scheduled task para limpiar sesiones expiradas

**Nuevo archivo: `classes/task/cleanup_practice_sessions.php`**
```php
<?php
namespace local_casospracticos\task;

use local_casospracticos\practice_session_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to clean up expired practice sessions.
 */
class cleanup_practice_sessions extends \core\task\scheduled_task {

    /**
     * Get task name.
     */
    public function get_name() {
        return get_string('task:cleanuppracticesessions', 'local_casospracticos');
    }

    /**
     * Execute task.
     */
    public function execute() {
        practice_session_manager::cleanup_expired_sessions();
    }
}
```

**Registrar en `db/tasks.php`:**
```php
$tasks = [
    // ... tareas existentes ...
    [
        'classname' => 'local_casospracticos\task\cleanup_practice_sessions',
        'blocking' => 0,
        'minute' => '*/30',  // Cada 30 minutos
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
```

**Strings a añadir:**
```php
// En lang/en/local_casospracticos.php
$string['error:sessionexpired'] = 'Your practice session has expired. Please start a new attempt.';
$string['error:invalidsession'] = 'Invalid practice session';
$string['task:cleanuppracticesessions'] = 'Clean up expired practice sessions';

// En lang/es/local_casospracticos.php
$string['error:sessionexpired'] = 'Tu sesión de práctica ha expirado. Por favor inicia un nuevo intento.';
$string['error:invalidsession'] = 'Sesión de práctica inválida';
$string['task:cleanuppracticesessions'] = 'Limpiar sesiones de práctica expiradas';
```

### Paso 5: Script de upgrade

**Añadir a `db/upgrade.php`:**
```php
function xmldb_local_casospracticos_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // ... upgrades existentes ...

    if ($oldversion < 2026011200) {
        // Define table local_cp_practice_sessions to be created.
        $table = new xmldb_table('local_cp_practice_sessions');

        // Adding fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('caseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('questionorder', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('token', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeexpiry', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('caseid_fk', XMLDB_KEY_FOREIGN, ['caseid'], 'local_cp_cases', ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes.
        $table->add_index('userid_caseid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'caseid']);
        $table->add_index('token_idx', XMLDB_INDEX_UNIQUE, ['token']);
        $table->add_index('timeexpiry_idx', XMLDB_INDEX_NOTUNIQUE, ['timeexpiry']);

        // Create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026011200, 'local', 'casospracticos');
    }

    return true;
}
```

---

## 🟡 MEJORA #1: Optimizar N+1 Queries en index.php

### Archivo: `index.php`

**Código actual (INEFICIENTE):**
```php
foreach ($categories as $category) {
    $class = $category->id == $categoryid ? 'list-group-item active' : 'list-group-item';
    $indent = str_repeat('&nbsp;&nbsp;', $category->depth);
    $url = new moodle_url('/local/casospracticos/index.php', ['category' => $category->id]);

    $casecount = category_manager::count_cases($category->id); // ❌ N+1 query
    $badge = html_writer::tag('span', $casecount, ['class' => 'badge bg-secondary float-end']);

    // ... resto del código ...
}
```

**Código optimizado:**
```php
// Usar el método que ya existe pero no se usa aquí
$categories = category_manager::get_flat_tree_with_counts(); // ✅ Single query con JOINs

foreach ($categories as $category) {
    $class = $category->id == $categoryid ? 'list-group-item active' : 'list-group-item';
    $indent = str_repeat('&nbsp;&nbsp;', (int)$category->depth); // ✅ Cast explícito
    $url = new moodle_url('/local/casospracticos/index.php', ['category' => $category->id]);

    $casecount = $category->casecount ?? 0; // ✅ Ya viene del query
    $badge = html_writer::tag('span', $casecount, ['class' => 'badge bg-secondary float-end']);

    // ... resto del código ...
}
```

**Si `get_flat_tree_with_counts()` no existe, añadir a `classes/category_manager.php`:**
```php
/**
 * Get flat tree with case counts (optimized, single query).
 *
 * @return array Categories with casecount field
 */
public static function get_flat_tree_with_counts(): array {
    global $DB;

    $sql = "SELECT c.*, COUNT(DISTINCT cs.id) as casecount
            FROM {local_cp_categories} c
            LEFT JOIN {local_cp_cases} cs ON cs.categoryid = c.id
            GROUP BY c.id
            ORDER BY c.sortorder ASC";

    $categories = $DB->get_records_sql($sql);

    // Build depth information
    $result = [];
    foreach ($categories as $cat) {
        $cat->depth = self::calculate_depth($cat->id, $categories);
        $result[] = $cat;
    }

    return $result;
}

/**
 * Calculate depth of a category (recursive).
 *
 * @param int $categoryid Category ID
 * @param array $allcategories All categories
 * @return int Depth level
 */
private static function calculate_depth(int $categoryid, array $allcategories): int {
    foreach ($allcategories as $cat) {
        if ($cat->id == $categoryid) {
            if ($cat->parent == 0) {
                return 0;
            }
            return 1 + self::calculate_depth($cat->parent, $allcategories);
        }
    }
    return 0;
}
```

---

## 📋 Checklist de Testing

Después de implementar cada corrección, verificar:

### Corrección #1 (Ownership)
- [ ] Usuario A no puede publicar caso de Usuario B
- [ ] Usuario con capability `editall` SÍ puede publicar caso de Usuario B
- [ ] Admin SÍ puede publicar cualquier caso
- [ ] El owner puede cambiar estado de su propio caso
- [ ] Error messages apropiados se muestran

### Corrección #2 (SQL)
- [ ] `get_total_marks()` retorna valor correcto
- [ ] Funciona con caso sin preguntas (retorna 0)
- [ ] Funciona con múltiples preguntas
- [ ] No hay SQL injection posible

### Corrección #3 (Session)
- [ ] Shuffle mantiene orden entre refrescos de página
- [ ] Sesión expira después de 2 horas
- [ ] Usuario A no puede usar token de Usuario B
- [ ] Token inválido redirige correctamente
- [ ] Sesiones antiguas se limpian automáticamente

### Optimización N+1
- [ ] Página carga más rápido (medir con xdebug/blackfire)
- [ ] Solo 1 query para categorías+counts (verificar en query log)
- [ ] Funciona con categorías anidadas

---

## 🚀 Script de Deploy

**Archivo: `deploy_fixes.sh`**
```bash
#!/bin/bash
# Script para deployar las correcciones de seguridad

echo "🔧 Deploying security fixes for local_casospracticos..."

# 1. Backup database
echo "📦 Creating database backup..."
php admin/cli/backup.php --mode=automated

# 2. Put site in maintenance mode
echo "🔒 Enabling maintenance mode..."
php admin/cli/maintenance.php --enable

# 3. Apply code changes
echo "📝 Applying code changes..."
# (Aquí iría tu sistema de deploy - git pull, rsync, etc.)

# 4. Run upgrade
echo "⬆️  Running database upgrades..."
php admin/cli/upgrade.php --non-interactive

# 5. Purge caches
echo "🗑️  Purging caches..."
php admin/cli/purge_caches.php

# 6. Disable maintenance mode
echo "🔓 Disabling maintenance mode..."
php admin/cli/maintenance.php --disable

echo "✅ Deploy complete!"
echo "⚠️  Remember to test critical functionality:"
echo "   - Case creation/editing"
echo "   - Practice mode"
echo "   - Exports"
echo "   - Bulk operations"
```

---

## 📚 Referencias

- [Moodle Security Guidelines](https://docs.moodle.org/dev/Security)
- [Moodle Coding Style](https://docs.moodle.org/dev/Coding_style)
- [Moodle DB API](https://docs.moodle.org/dev/Data_manipulation_API)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
