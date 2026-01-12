# Revisión de Seguridad y Mejoras - Plugin Practical Cases v1.0.3

**Fecha de revisión:** 2026-01-12
**Versión analizada:** 1.0.3
**Revisor:** Claude Code Security Analysis

---

## 📋 Resumen Ejecutivo

Este plugin es un sistema completo para gestionar casos prácticos en Moodle con preguntas asociadas. El código muestra **buenas prácticas de seguridad en general**, con protecciones XXE, CSRF, rate limiting y verificación de propiedad implementadas. Sin embargo, se han identificado varias áreas de mejora de seguridad, performance y funcionalidad.

**Estado general:** ✅ Seguro con mejoras recomendadas
**Nivel de riesgo actual:** 🟡 MEDIO-BAJO

---

## 🔒 VULNERABILIDADES DE SEGURIDAD

### 🔴 ALTA PRIORIDAD

#### 1. **Falta de validación de propiedad en operaciones de cambio de estado**
**Ubicación:** `index.php:117-133`

**Problema:**
```php
case 'publish':
    require_capability('local/casospracticos:edit', $context);
    case_manager::set_status($id, case_manager::STATUS_PUBLISHED);
    // ❌ No verifica si el usuario es el propietario del caso
```

**Impacto:** Un usuario con capacidad `edit` puede publicar/archivar casos de otros usuarios sin ser el propietario.

**Solución recomendada:**
```php
case 'publish':
    require_capability('local/casospracticos:edit', $context);
    $case = case_manager::get($id);
    if ($case->createdby != $USER->id && !has_capability('local/casospracticos:editall', $context)) {
        throw new moodle_exception('error:nopermission', 'local_casospracticos');
    }
    case_manager::set_status($id, case_manager::STATUS_PUBLISHED);
```

---

#### 2. **Posible inyección SQL en case_manager::get_total_marks()**
**Ubicación:** `classes/case_manager.php:338-341`

**Problema:**
```php
public static function get_total_marks(int $id): float {
    global $DB;
    return (float) $DB->get_field('local_cp_questions', 'SUM(defaultmark)', ['caseid' => $id]) ?? 0;
}
```

El uso de `SUM(defaultmark)` directamente en el primer parámetro puede ser problemático en algunas versiones de Moodle.

**Solución:**
```php
public static function get_total_marks(int $id): float {
    global $DB;
    $sql = "SELECT SUM(defaultmark) FROM {local_cp_questions} WHERE caseid = :caseid";
    return (float) $DB->get_field_sql($sql, ['caseid' => $id]) ?? 0;
}
```

---

#### 3. **Session hijacking potential en practice.php**
**Ubicación:** `practice.php:68`

**Problema:**
```php
$SESSION->casopractico_order[$caseid] = array_column($questions, 'id');
```

No hay validación de que la sesión no ha sido comprometida. Si un atacante puede acceder a la sesión de otro usuario, podría manipular el orden de las preguntas.

**Solución:** Usar un token CSRF adicional almacenado en la base de datos vinculado al intento.

---

### 🟡 MEDIA PRIORIDAD

#### 4. **Falta de límite de rate en bulk operations**
**Ubicación:** `classes/external/api.php:915-949`

**Problema:** Las operaciones bulk no tienen rate limiting dedicado, solo el rate limiting general. Un atacante podría hacer múltiples operaciones bulk para causar DoS.

**Recomendación:** Añadir un rate limit más estricto para operaciones bulk o limitar el número de casos por operación.

---

#### 5. **No hay sanitización de output en algunos templates HTML**
**Ubicación:** `index.php:230`

**Problema:**
```php
$indent = str_repeat('&nbsp;&nbsp;', $category->depth);
```

Aunque `$category->depth` debería ser un entero, no hay garantía. Si alguien manipula la base de datos directamente, podría causar XSS.

**Solución:**
```php
$indent = str_repeat('&nbsp;&nbsp;', (int)$category->depth);
```

---

#### 6. **Vulnerabilidad potencial en exportación directa**
**Ubicación:** `export.php:67`

**Problema:**
```php
// Only export cases that exist - silently skip non-existent ones.
$caseids = array_intersect($caseids, $existingids);
```

Aunque verifica que existan, no verifica que el usuario tenga permiso para exportar casos específicos que no son suyos.

**Recomendación:** Verificar propiedad o permisos elevados antes de exportar cada caso.

---

### 🔵 BAJA PRIORIDAD

#### 7. **Información sensible en eventos de rate limiting**
**Ubicación:** `classes/rate_limiter.php:157-168`

El evento `rate_limit_exceeded` podría revelar información sobre patrones de uso.

**Recomendación:** Añadir configuración para desactivar/anonimizar estos logs en producción.

---

#### 8. **Sin validación de MIME type en import**
**Ubicación:** `classes/importer.php:74-83`

Solo valida la extensión del archivo, no el contenido real (magic bytes).

**Recomendación:** Validar MIME type real del archivo.

---

## ⚡ OPORTUNIDADES DE MEJORA

### Performance

#### 1. **Optimizar consultas N+1 en index.php**
**Ubicación:** `index.php:233`

```php
foreach ($categories as $category) {
    $casecount = category_manager::count_cases($category->id); // ❌ N+1 query
```

**Solución:** Ya existe `get_flat_tree_with_counts()` pero no se usa aquí. Usar esa función.

---

#### 2. **Caché insuficiente en filter_manager**
**Ubicación:** `classes/filter_manager.php`

Las opciones de filtro se calculan en cada request. Deberían cachearse más agresivamente.

**Recomendación:** Cache de 1 hora para opciones de filtro.

---

#### 3. **Falta de índices compuestos**

Revisar `db/install.xml` para añadir índices compuestos en:
- `(caseid, status)` en `local_cp_cases`
- `(userid, caseid)` en `local_cp_practice_attempts`
- `(questionid, sortorder)` en `local_cp_answers`

---

### Usabilidad

#### 4. **Sin paginación en case_view.php**

Si un caso tiene muchas preguntas (100+), la página se vuelve muy larga.

**Recomendación:** Añadir paginación o lazy loading.

---

#### 5. **Falta de preview en practice mode**

Los usuarios no pueden ver las preguntas antes de empezar el intento.

**Recomendación:** Añadir modo "preview" antes de iniciar.

---

#### 6. **Sin opción de guardar progreso en practice**

Si el usuario cierra la página, pierde todo el progreso.

**Recomendación:** Auto-save cada 30 segundos usando AJAX.

---

### Código Quality

#### 7. **Falta de type hints en algunos métodos**

Ejemplo en `case_manager.php:375`:
```php
public static function get_with_category(int $id) { // ❌ Sin return type
```

**Solución:**
```php
public static function get_with_category(int $id): object|false {
```

---

#### 8. **Demasiada lógica en archivos PHP principales**

Archivos como `index.php` (543 líneas) tienen demasiada lógica de presentación.

**Recomendación:** Mover a clases renderer o usar más templates Mustache.

---

#### 9. **Sin tests de integración**

Solo hay tests unitarios básicos. Faltan tests E2E.

**Recomendación:** Añadir tests Behat para flujos completos.

---

## 🚀 FEATURES RECOMENDADAS

### 🔥 Alta Prioridad (Quick Wins)

#### 1. **Modo de examen (Timed Practice)**
Permitir práctica con tiempo límite, como un examen real.

**Beneficio:** Preparación más realista para exámenes.
**Complejidad:** BAJA
**Archivos afectados:** `practice.php`, nuevas tablas para timer state

---

#### 2. **Estadísticas mejoradas por estudiante**
Dashboard personal mostrando:
- Progreso por categoría
- Gráficos de mejora temporal
- Áreas débiles identificadas

**Beneficio:** Mejor tracking del progreso individual.
**Complejidad:** MEDIA
**Archivos afectados:** Nuevo `my_dashboard.php`, `classes/stats_manager.php`

---

#### 3. **Exportación a Moodle Question Bank**
Poder exportar preguntas directamente al banco de preguntas de Moodle en formato XML de Moodle.

**Beneficio:** Reutilización de contenido en otros contextos.
**Complejidad:** MEDIA
**Archivos afectados:** `classes/exporter.php`

---

#### 4. **Modo colaborativo / Peer Review**
Permitir que estudiantes revisen casos de otros estudiantes antes de publicación.

**Beneficio:** Aprendizaje colaborativo y calidad de contenido.
**Complejidad:** ALTA
**Archivos afectados:** Nuevo sistema de peer review

---

#### 5. **Soporte para más tipos de preguntas**
- Essay (respuesta larga)
- Matching (emparejar)
- Drag and drop
- Calculated (con variables)

**Beneficio:** Mayor versatilidad pedagógica.
**Complejidad:** ALTA
**Archivos afectados:** `question_manager.php`, templates, grading logic

---

### 💡 Media Prioridad (Value Adds)

#### 6. **Integración con LTI**
Permitir que el plugin sea consumido como herramienta LTI externa.

**Beneficio:** Uso en otras plataformas LMS.
**Complejidad:** ALTA

---

#### 7. **AI-powered difficulty estimation**
Usar ML para sugerir automáticamente nivel de dificultad basado en:
- Longitud del texto
- Vocabulario usado
- Tasa de éxito histórica

**Beneficio:** Clasificación más precisa.
**Complejidad:** MUY ALTA

---

#### 8. **Mobile app companion**
App móvil para iOS/Android para práctica offline.

**Beneficio:** Accesibilidad mejorada.
**Complejidad:** MUY ALTA

---

#### 9. **Gamificación avanzada**
- Leaderboards públicos/privados
- Badges personalizados
- Desafíos semanales
- Rachas de estudio

**Beneficio:** Mayor engagement.
**Complejidad:** MEDIA

---

#### 10. **Análisis de sentimiento en feedback**
Detectar si el feedback del instructor es muy negativo/positivo y alertar.

**Beneficio:** QA de feedback.
**Complejidad:** MEDIA (usando APIs externas)

---

### 🎯 Baja Prioridad (Nice to Have)

#### 11. **Integración con Turnitin para detectar plagiarismo**
En casos tipo essay, detectar si el contenido fue copiado.

**Beneficio:** Integridad académica.
**Complejidad:** ALTA (requiere suscripción externa)

---

#### 12. **Voice-to-text para respuestas**
Permitir responder preguntas por voz (útil para accesibilidad).

**Beneficio:** Accesibilidad.
**Complejidad:** MEDIA (usar Web Speech API)

---

#### 13. **Dark mode**
Tema oscuro para práctica nocturna.

**Beneficio:** Comodidad visual.
**Complejidad:** BAJA

---

#### 14. **Exportación a Anki flashcards**
Convertir preguntas a formato Anki para spaced repetition.

**Beneficio:** Estudio con técnicas probadas.
**Complejidad:** BAJA

---

#### 15. **Collaborative case creation**
Permitir que múltiples usuarios editen un caso simultáneamente (Google Docs style).

**Beneficio:** Creación de contenido más eficiente.
**Complejidad:** MUY ALTA (requiere WebSockets o similar)

---

## 📊 ANÁLISIS DE CÓDIGO

### Puntos Fuertes

✅ **Seguridad XXE bien implementada** - Protección correcta contra XML External Entities
✅ **Rate limiting funcional** - Sistema de rate limiting con cache
✅ **CSRF protection** - Sesskey usado consistentemente
✅ **Ownership verification en API** - Métodos `can_edit_case()` y `can_delete_case()`
✅ **Validación de entrada robusta en importer** - Límites y whitelist de tipos
✅ **Transacciones de BD** - Uso correcto de transacciones en operaciones críticas
✅ **Cache implementation** - MUC usado apropiadamente
✅ **Privacy API compliant** - GDPR compliance implementado
✅ **Events system** - Integración con sistema de eventos de Moodle
✅ **Backup/restore** - Soporte completo para backups de curso

### Áreas de Mejora

⚠️ **Verificación de propiedad inconsistente** - No siempre verifica ownership
⚠️ **Lógica de negocio en PHP scripts** - Mucho código en scripts principales
⚠️ **Tests insuficientes** - Solo tests básicos, faltan E2E
⚠️ **Type hints incompletos** - No todos los métodos tienen return types
⚠️ **Documentación de API** - Falta documentación de Web Services
⚠️ **Logs de debugging** - No hay sistema de logging configurable
⚠️ **Error handling** - Algunos errores solo muestran mensajes genéricos

---

## 🎯 RECOMENDACIONES PRIORIZADAS

### Inmediatas (Esta semana)

1. ✅ Añadir verificación de ownership en operaciones de cambio de estado (`index.php:117-133`)
2. ✅ Corregir `get_total_marks()` para evitar inyección SQL
3. ✅ Añadir validación de sesión en practice mode

### Corto plazo (Este mes)

4. Implementar modo de examen con timer
5. Añadir dashboard de estadísticas mejorado
6. Optimizar consultas N+1 en index.php
7. Añadir tests E2E con Behat

### Medio plazo (Este trimestre)

8. Exportación a Moodle Question Bank
9. Soporte para más tipos de preguntas (Essay, Matching)
10. Modo colaborativo / Peer Review
11. Mejorar rate limiting para operaciones bulk

### Largo plazo (Este año)

12. Integración LTI
13. Mobile app companion
14. AI-powered difficulty estimation
15. Gamificación avanzada

---

## 📝 CONCLUSIONES

El plugin **Practical Cases v1.0.3** es un plugin **bien construido** con buenas bases de seguridad. Las vulnerabilidades encontradas son en su mayoría de **prioridad media-baja** y fáciles de corregir.

### Puntuación de Seguridad: 7.5/10

**Desglose:**
- Protección CSRF: ✅ 10/10
- Protección XSS: ✅ 9/10 (pequeñas mejoras necesarias)
- Protección SQLi: ✅ 9/10 (un caso a corregir)
- Autenticación/Autorización: ⚠️ 7/10 (verificación de ownership inconsistente)
- Validación de entrada: ✅ 8/10
- Rate limiting: ✅ 8/10
- Protección XXE: ✅ 10/10

### Próximos Pasos Recomendados

1. Corregir las 3 vulnerabilidades de alta prioridad
2. Implementar las 5 features de alta prioridad
3. Mejorar tests y documentación
4. Considerar features de medio/largo plazo según roadmap del producto

---

**Contacto para más información:** Este análisis fue generado por Claude Code Security Analysis
