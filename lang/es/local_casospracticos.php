<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language strings for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Casos Prácticos';
$string['casospracticos'] = 'Casos Prácticos';

// Capabilities.
$string['casospracticos:view'] = 'Ver casos prácticos';
$string['casospracticos:create'] = 'Crear casos prácticos';
$string['casospracticos:edit'] = 'Editar casos prácticos';
$string['casospracticos:delete'] = 'Eliminar casos prácticos';
$string['casospracticos:managecategories'] = 'Gestionar categorías';
$string['casospracticos:export'] = 'Exportar casos prácticos';
$string['casospracticos:import'] = 'Importar casos prácticos';
$string['casospracticos:insertquiz'] = 'Insertar casos en cuestionarios';
$string['casospracticos:review'] = 'Revisar casos prácticos';
$string['casospracticos:viewaudit'] = 'Ver registro de auditoría';
$string['casospracticos:bulk'] = 'Realizar operaciones en lote';

// Categories.
$string['categories'] = 'Categorías';
$string['category'] = 'Categoría';
$string['newcategory'] = 'Nueva categoría';
$string['editcategory'] = 'Editar categoría';
$string['deletecategory'] = 'Eliminar categoría';
$string['categoryname'] = 'Nombre de la categoría';
$string['categorydescription'] = 'Descripción';
$string['parentcategory'] = 'Categoría padre';
$string['nocategories'] = 'Aún no hay categorías';
$string['toplevel'] = 'Nivel superior';
$string['categorycreated'] = 'Categoría creada correctamente';
$string['categoryupdated'] = 'Categoría actualizada correctamente';
$string['categorydeleted'] = 'Categoría eliminada correctamente';
$string['categoryhaschildren'] = 'No se puede eliminar una categoría con subcategorías';
$string['categoryhascases'] = 'No se puede eliminar una categoría con casos';

// Cases.
$string['cases'] = 'Casos';
$string['case'] = 'Caso';
$string['newcase'] = 'Nuevo caso';
$string['editcase'] = 'Editar caso';
$string['deletecase'] = 'Eliminar caso';
$string['viewcase'] = 'Ver caso';
$string['casename'] = 'Nombre del caso';
$string['casestatement'] = 'Enunciado';
$string['casestatement_help'] = 'La descripción larga o planteamiento del problema que los estudiantes leerán antes de responder las preguntas.';
$string['nocases'] = 'No hay casos en esta categoría';
$string['casecreated'] = 'Caso creado correctamente';
$string['caseupdated'] = 'Caso actualizado correctamente';
$string['casedeleted'] = 'Caso eliminado correctamente';
$string['confirmdeletecase'] = '¿Está seguro de que desea eliminar este caso? Esto también eliminará todas sus preguntas.';
$string['difficulty'] = 'Dificultad';
$string['difficulty_help'] = 'Nivel de dificultad del 1 (fácil) al 5 (difícil)';
$string['tags'] = 'Etiquetas';

// Status.
$string['status'] = 'Estado';
$string['status_draft'] = 'Borrador';
$string['status_pending_review'] = 'Pendiente de revisión';
$string['status_in_review'] = 'En revisión';
$string['status_approved'] = 'Aprobado';
$string['status_published'] = 'Publicado';
$string['status_archived'] = 'Archivado';

// Questions.
$string['questions'] = 'Preguntas';
$string['question'] = 'Pregunta';
$string['newquestion'] = 'Nueva pregunta';
$string['editquestion'] = 'Editar pregunta';
$string['deletequestion'] = 'Eliminar pregunta';
$string['questiontext'] = 'Texto de la pregunta';
$string['questiontype'] = 'Tipo de pregunta';
$string['noquestions'] = 'No hay preguntas en este caso';
$string['questioncreated'] = 'Pregunta creada correctamente';
$string['questionupdated'] = 'Pregunta actualizada correctamente';
$string['questiondeleted'] = 'Pregunta eliminada correctamente';
$string['defaultmark'] = 'Puntuación por defecto';
$string['generalfeedback'] = 'Retroalimentación general';
$string['shuffleanswers'] = 'Barajar respuestas';
$string['singleanswer'] = 'Respuesta única';
$string['multipleanswers'] = 'Respuestas múltiples';

// Question types.
$string['qtype_multichoice'] = 'Opción múltiple';
$string['qtype_truefalse'] = 'Verdadero/Falso';
$string['qtype_shortanswer'] = 'Respuesta corta';
$string['qtype_matching'] = 'Emparejamiento';

// Answers.
$string['answers'] = 'Respuestas';
$string['answer'] = 'Respuesta';
$string['newanswer'] = 'Añadir respuesta';
$string['answertext'] = 'Texto de la respuesta';
$string['fraction'] = 'Calificación';
$string['fraction_help'] = '1.0 = respuesta correcta, 0 = respuesta incorrecta. Use valores intermedios para crédito parcial.';
$string['feedback'] = 'Retroalimentación';
$string['correctanswer'] = 'Correcta';
$string['incorrectanswer'] = 'Incorrecta';

// Import/Export.
$string['export'] = 'Exportar';
$string['import'] = 'Importar';
$string['exportcases'] = 'Exportar casos';
$string['importcases'] = 'Importar casos';
$string['exportformat'] = 'Formato de exportación';
$string['importfile'] = 'Archivo a importar';
$string['exportsuccessful'] = 'Exportación completada correctamente';
$string['importsuccessful'] = 'Importación completada correctamente. {$a->cases} casos y {$a->questions} preguntas importadas.';
$string['importerror'] = 'Error durante la importación: {$a}';
$string['exporthelp'] = 'Seleccione los casos a exportar o una categoría completa. Si no selecciona ningún caso específico y la categoría está en "Todos", se exportarán todos los casos.';

// Quiz integration.
$string['insertintoquiz'] = 'Insertar en cuestionario';
$string['selectquiz'] = 'Seleccionar cuestionario';
$string['randomquestions'] = 'Preguntas aleatorias';
$string['randomquestions_help'] = 'Número de preguntas aleatorias a incluir de este caso. Deje en 0 para incluir todas las preguntas.';
$string['includestatement'] = 'Incluir enunciado';
$string['includestatement_help'] = 'Si está habilitado, el enunciado del caso se insertará como descripción antes de las preguntas.';
$string['insertsuccessful'] = 'Caso insertado en el cuestionario correctamente';
$string['inserterror'] = 'Error al insertar el caso en el cuestionario: {$a}';

// Navigation and general.
$string['managecases'] = 'Gestionar casos prácticos';
$string['backtocases'] = 'Volver a casos';
$string['backtocategories'] = 'Volver a categorías';
$string['actions'] = 'Acciones';
$string['preview'] = 'Vista previa';
$string['print'] = 'Imprimir';
$string['numquestions'] = '{$a} preguntas';
$string['created'] = 'Creado';
$string['modified'] = 'Modificado';
$string['createdby'] = 'Creado por';
$string['filter'] = 'Filtrar';
$string['clear'] = 'Limpiar';
$string['selected'] = 'Seleccionados';
$string['move'] = 'Mover';
$string['publish'] = 'Publicar';
$string['archive'] = 'Archivar';

// Difficulty levels.
$string['difficulty1'] = 'Muy fácil';
$string['difficulty2'] = 'Fácil';
$string['difficulty3'] = 'Medio';
$string['difficulty4'] = 'Difícil';
$string['difficulty5'] = 'Muy difícil';

// Pagination and display.
$string['showingcases'] = 'Mostrando {$a->from} a {$a->to} de {$a->total} casos';
$string['showingitems'] = 'Mostrando {$a->from} a {$a->to} de {$a->total} elementos';
$string['searchcases'] = 'Buscar casos...';

// Bulk operations.
$string['bulkoperations'] = 'Operaciones en lote';
$string['selectall'] = 'Seleccionar todo';
$string['deselectall'] = 'Deseleccionar todo';
$string['withselected'] = 'Con los seleccionados...';
$string['bulkdelete'] = 'Eliminar seleccionados';
$string['bulkmove'] = 'Mover a categoría';
$string['bulkpublish'] = 'Publicar seleccionados';
$string['bulkarchive'] = 'Archivar seleccionados';
$string['bulkexport'] = 'Exportar seleccionados';
$string['confirmdeleteselected'] = '¿Está seguro de que desea eliminar {$a} casos seleccionados? Esta acción no se puede deshacer.';
$string['confirmpublishselected'] = '¿Está seguro de que desea publicar {$a} casos seleccionados?';
$string['confirmarchiveselected'] = '¿Está seguro de que desea archivar {$a} casos seleccionados?';
$string['casesdeleted'] = '{$a} casos eliminados correctamente';
$string['casesmoved'] = '{$a} casos movidos correctamente';
$string['casespublished'] = '{$a} casos publicados correctamente';
$string['casesarchived'] = '{$a} casos archivados correctamente';
$string['nocasesselected'] = 'No hay casos seleccionados';
$string['selecttargetcategory'] = 'Seleccionar categoría de destino';

// Workflow.
$string['submitforreview'] = 'Enviar a revisión';
$string['assignreviewer'] = 'Asignar revisor';
$string['approve'] = 'Aprobar';
$string['reject'] = 'Rechazar';
$string['requestrevision'] = 'Solicitar revisión';
$string['unarchive'] = 'Desarchivar';
$string['casesubmittedreview'] = 'Caso enviado a revisión';
$string['caseapproved'] = 'Caso aprobado';
$string['caserejected'] = 'Caso rechazado';
$string['casepublished'] = 'Caso publicado';
$string['casearchived'] = 'Caso archivado';
$string['invalidtransition'] = 'Transición de estado inválida';
$string['noquestionsforsubmit'] = 'El caso debe tener al menos una pregunta antes de enviarlo a revisión';

// Review dashboard.
$string['reviewdashboard'] = 'Panel de revisiones';
$string['pendingreview'] = 'Pendientes de revisión';
$string['myreviews'] = 'Mis revisiones';
$string['mypendingreview'] = 'Mis revisiones pendientes';
$string['approvedcases'] = 'Casos aprobados';
$string['allreviews'] = 'Todas las revisiones';
$string['reviews'] = 'Revisiones';
$string['reviewer'] = 'Revisor';
$string['caseswaitingassignment'] = 'Casos esperando asignación';
$string['assigntome'] = 'Asignarme';
$string['reviewsubmitted'] = 'Revisión enviada correctamente';
$string['reviewerassigned'] = 'Revisor asignado correctamente';
$string['noitemsfound'] = 'No se encontraron elementos';
$string['reviewstatus_pending'] = 'Pendiente';
$string['reviewstatus_approved'] = 'Aprobado';
$string['reviewstatus_rejected'] = 'Rechazado';
$string['reviewstatus_revision'] = 'Revisión solicitada';

// Audit log.
$string['auditlog'] = 'Registro de auditoría';
$string['noauditlogs'] = 'No se encontraron registros de auditoría';
$string['objecttype'] = 'Tipo de objeto';
$string['objectid'] = 'ID del objeto';
$string['action'] = 'Acción';
$string['changes'] = 'Cambios';
$string['ipaddress'] = 'Dirección IP';
$string['unknownuser'] = 'Usuario desconocido';
$string['action_create'] = 'Crear';
$string['action_update'] = 'Actualizar';
$string['action_delete'] = 'Eliminar';
$string['action_publish'] = 'Publicar';
$string['action_archive'] = 'Archivar';
$string['action_submit_review'] = 'Enviar a revisión';
$string['action_approve'] = 'Aprobar';
$string['action_reject'] = 'Rechazar';

// Events.
$string['eventcasecreated'] = 'Caso práctico creado';
$string['eventcaseupdated'] = 'Caso práctico actualizado';
$string['eventcasedeleted'] = 'Caso práctico eliminado';
$string['eventcasepublished'] = 'Caso práctico publicado';
$string['eventpracticeattemptcompleted'] = 'Intento de práctica completado';

// Search.
$string['search:case'] = 'Casos prácticos';

// Settings.
$string['settings:general'] = 'Configuración general';
$string['settings:enablequizintegration'] = 'Habilitar integración con cuestionarios';
$string['settings:enablequizintegration_desc'] = 'Permitir insertar casos prácticos en cuestionarios de Moodle';
$string['settings:enablesearch'] = 'Habilitar indexación de búsqueda';
$string['settings:enablesearch_desc'] = 'Indexar casos prácticos en la búsqueda global de Moodle';
$string['settings:defaultdifficulty'] = 'Dificultad por defecto';
$string['settings:defaultdifficulty_desc'] = 'Nivel de dificultad predeterminado para nuevos casos';
$string['settings:workflow'] = 'Configuración del flujo de trabajo';
$string['settings:enableworkflow'] = 'Habilitar flujo de aprobación';
$string['settings:enableworkflow_desc'] = 'Requerir que los casos sean revisados y aprobados antes de publicar';
$string['settings:importexport'] = 'Configuración de importación/exportación';
$string['settings:maximportsize'] = 'Tamaño máximo de archivo de importación';
$string['settings:maximportsize_desc'] = 'Tamaño máximo en bytes para archivos de importación';
$string['settings:defaultexportformat'] = 'Formato de exportación por defecto';
$string['settings:defaultexportformat_desc'] = 'Formato predeterminado al exportar casos';
$string['settings:questiontypes'] = 'Tipos de preguntas';
$string['settings:questiontypes_desc'] = 'Configurar qué tipos de preguntas están disponibles para los casos';
$string['settings:allowedqtypes'] = 'Tipos de preguntas permitidos';
$string['settings:allowedqtypes_desc'] = 'Seleccionar qué tipos de preguntas se pueden usar en casos prácticos';
$string['settings:display'] = 'Configuración de visualización';
$string['settings:casesperpage'] = 'Casos por página';
$string['settings:casesperpage_desc'] = 'Número de casos a mostrar por página en el listado';
$string['settings:showquestioncount'] = 'Mostrar número de preguntas';
$string['settings:showquestioncount_desc'] = 'Mostrar el número de preguntas en el listado de casos';
$string['settings:showdifficulty'] = 'Mostrar dificultad';
$string['settings:showdifficulty_desc'] = 'Mostrar el nivel de dificultad en el listado de casos';
$string['settings:notifications'] = 'Notificaciones';
$string['settings:notifyonpublish'] = 'Notificar al publicar';
$string['settings:notifyonpublish_desc'] = 'Enviar notificaciones cuando se publica un caso';
$string['settings:practicemode'] = 'Modo práctica';
$string['settings:passthreshold'] = 'Umbral de aprobación (%)';
$string['settings:passthreshold_desc'] = 'Porcentaje requerido para aprobar un intento de práctica';
$string['settings:auditlogretention'] = 'Retención del registro de auditoría (días)';
$string['settings:auditlogretention_desc'] = 'Número de días para conservar las entradas del registro de auditoría (las entradas antiguas se limpian automáticamente)';

// Errors.
$string['error:categorynotfound'] = 'Categoría no encontrada';
$string['error:casenotfound'] = 'Caso no encontrado';
$string['error:questionnotfound'] = 'Pregunta no encontrada';
$string['error:nopermission'] = 'No tiene permiso para realizar esta acción';
$string['error:invaliddata'] = 'Datos proporcionados no válidos';
$string['error:nocases'] = 'No hay casos seleccionados';

// Privacy.
$string['privacy:metadata:local_cp_cases'] = 'Almacena los casos prácticos creados por los usuarios';
$string['privacy:metadata:local_cp_cases:createdby'] = 'El ID del usuario que creó el caso';
$string['privacy:metadata:local_cp_cases:timecreated'] = 'La fecha de creación del caso';
$string['privacy:metadata:local_cp_cases:timemodified'] = 'La fecha de última modificación del caso';
$string['privacy:metadata:local_cp_audit_log'] = 'Registro de auditoría de todas las acciones realizadas';
$string['privacy:metadata:local_cp_audit_log:userid'] = 'El ID del usuario que realizó la acción';
$string['privacy:metadata:local_cp_audit_log:action'] = 'La acción realizada por el usuario';
$string['privacy:metadata:local_cp_audit_log:ipaddress'] = 'La dirección IP del usuario';
$string['privacy:metadata:local_cp_audit_log:timecreated'] = 'Cuándo se realizó la acción';
$string['privacy:metadata:local_cp_reviews'] = 'Revisiones de casos para el flujo de trabajo';
$string['privacy:metadata:local_cp_reviews:reviewerid'] = 'El ID del revisor';
$string['privacy:metadata:local_cp_reviews:comments'] = 'Comentarios del revisor';
$string['privacy:metadata:local_cp_reviews:status'] = 'El estado de la revisión';
$string['privacy:metadata:local_cp_reviews:timecreated'] = 'Cuándo se creó la revisión';
$string['privacy:metadata:local_cp_practice_attempts'] = 'Almacena los intentos de práctica realizados por los usuarios';
$string['privacy:metadata:local_cp_practice_attempts:userid'] = 'El ID del usuario que realizó el intento';
$string['privacy:metadata:local_cp_practice_attempts:score'] = 'La puntuación obtenida en el intento';
$string['privacy:metadata:local_cp_practice_attempts:maxscore'] = 'La puntuación máxima posible';
$string['privacy:metadata:local_cp_practice_attempts:percentage'] = 'El porcentaje de puntuación';
$string['privacy:metadata:local_cp_practice_attempts:timestarted'] = 'Cuándo se inició el intento';
$string['privacy:metadata:local_cp_practice_attempts:timefinished'] = 'Cuándo se completó el intento';
$string['privacy:metadata:local_cp_practice_responses'] = 'Almacena las respuestas individuales a preguntas en intentos de práctica';
$string['privacy:metadata:local_cp_practice_responses:response'] = 'La respuesta del usuario a la pregunta';
$string['privacy:metadata:local_cp_practice_responses:score'] = 'La puntuación de esta respuesta';
$string['privacy:metadata:local_cp_practice_responses:iscorrect'] = 'Si la respuesta fue correcta';

// Default category.
$string['defaultcategory'] = 'Casos importados';

// Practice mode.
$string['practice'] = 'Práctica';
$string['practicecase'] = 'Practicar este caso';
$string['results'] = 'Resultados';
$string['yourscoreis'] = 'Tu puntuación: {$a->score} / {$a->max} ({$a->percentage}%)';
$string['retry'] = 'Intentar de nuevo';
$string['shufflequestions'] = 'Mezclar preguntas';
$string['correctansweris'] = 'Respuesta correcta';

// Statistics.
$string['statistics'] = 'Estadísticas';
$string['totalviews'] = 'Visualizaciones totales';
$string['quizinsertions'] = 'Inserciones en cuestionarios';
$string['practiceattempts'] = 'Intentos de práctica';
$string['averagescore'] = 'Puntuación media';
$string['questionperformance'] = 'Rendimiento por pregunta';
$string['attempts'] = 'Intentos';
$string['correctrate'] = 'Tasa de acierto';
$string['avgpoints'] = 'Puntos medios';
$string['usageinquizzes'] = 'Uso en cuestionarios';
$string['timesinserted'] = 'Veces insertado';
$string['lastused'] = 'Último uso';
$string['recentpracticeattempts'] = 'Intentos de práctica recientes';
$string['timetaken'] = 'Tiempo empleado';
$string['scoredistribution'] = 'Distribución de puntuaciones';
$string['nostatisticsyet'] = 'Aún no hay estadísticas disponibles';
$string['notusedyet'] = 'Este caso aún no se ha usado en ningún cuestionario';
$string['nopracticeattempts'] = 'Aún no hay intentos de práctica registrados';
$string['range020'] = '0-20%';
$string['range2140'] = '21-40%';
$string['range4160'] = '41-60%';
$string['range6180'] = '61-80%';
$string['range81100'] = '81-100%';

// Attempts.
$string['attempt'] = 'Intento';
$string['myattempts'] = 'Mis intentos';
$string['viewmyattempts'] = 'Ver mis intentos';
$string['reviewattempt'] = 'Revisar intento';
$string['noattemptsyet'] = 'Aún no has realizado ningún intento';
$string['startpractice'] = 'Comenzar a practicar';
$string['totalattempts'] = 'Intentos totales';
$string['bestscore'] = 'Mejor puntuación';
$string['passrate'] = 'Tasa de aprobados';
$string['yourrecentattempts'] = 'Tus intentos recientes';
$string['youranswer'] = 'Tu respuesta';
$string['yourmark'] = 'Tu puntuación';
$string['tryagain'] = 'Intentar de nuevo';
$string['practicenow'] = 'Practicar ahora';
$string['started'] = 'Iniciado';
$string['completed'] = 'Completado';
$string['correct'] = 'Correcto';
$string['incorrect'] = 'Incorrecto';
$string['review'] = 'Revisar';

// Help texts.
$string['cases_help'] = 'Mantenga presionado Ctrl/Cmd para seleccionar múltiples casos. Si selecciona casos específicos, solo se exportarán esos. Si no se seleccionan casos, se exportarán todos los casos de la categoría elegida.';

// Scheduled tasks.
$string['task:cleanupabandoned'] = 'Limpiar intentos de práctica abandonados';

// Achievements / Gamification.
$string['achievements'] = 'Logros';
$string['gamificationdisabled'] = 'La gamificación está actualmente deshabilitada';
$string['uniquecases'] = 'Casos únicos completados';
$string['perfectscores'] = 'Puntuaciones perfectas';
$string['achievementsprogress'] = 'Logros: {$a->earned} de {$a->total} conseguidos';
$string['earned'] = '¡Conseguido!';
$string['externalintegration'] = 'Integrado con {$a} para recompensas adicionales';
$string['eventachievementearned'] = 'Logro conseguido';
$string['settings:enablegamification'] = 'Habilitar gamificación';
$string['settings:enablegamification_desc'] = 'Habilitar logros y características de gamificación';

// Achievement types.
$string['achievement:first_attempt'] = 'Primeros Pasos';
$string['achievement:first_attempt_desc'] = 'Completa tu primer intento de práctica';
$string['achievement:five_cases'] = 'Empezando';
$string['achievement:five_cases_desc'] = 'Practica 5 casos diferentes';
$string['achievement:ten_cases'] = 'Estudiante Dedicado';
$string['achievement:ten_cases_desc'] = 'Practica 10 casos diferentes';
$string['achievement:twentyfive_cases'] = 'Maestro de Casos';
$string['achievement:twentyfive_cases_desc'] = 'Practica 25 casos diferentes';
$string['achievement:perfect_score'] = 'Perfeccionista';
$string['achievement:perfect_score_desc'] = 'Obtén una puntuación perfecta (100%)';
$string['achievement:five_perfect'] = 'Excelencia';
$string['achievement:five_perfect_desc'] = 'Obtén 5 puntuaciones perfectas';
$string['achievement:streak_10'] = '¡En Racha!';
$string['achievement:streak_10_desc'] = 'Aprueba 10 casos seguidos';
$string['achievement:week_streak'] = 'Constante';
$string['achievement:week_streak_desc'] = 'Practica todos los días durante una semana';
$string['achievement:category_complete'] = 'Experto en Categoría';
$string['achievement:category_complete_desc'] = 'Completa todos los casos de una categoría';
$string['achievement:high_achiever'] = 'Alto Rendimiento';
$string['achievement:high_achiever_desc'] = 'Mantén un promedio superior al 90% después de 10 intentos';

// Accessibility strings.
$string['caselist'] = 'Lista de casos prácticos';
$string['caseactions'] = 'Acciones del caso';
$string['questionactions'] = 'Acciones de la pregunta';
$string['questionnumber'] = 'Número de pregunta';
$string['questionform'] = 'Formulario de pregunta';
$string['questiontype_help'] = 'Selecciona el tipo de pregunta a crear';
$string['defaultmark_help'] = 'Puntos otorgados por una respuesta completamente correcta';
$string['generalfeedback_help'] = 'Retroalimentación mostrada después de responder la pregunta';
$string['categoryoptions'] = 'Opciones para la categoría';
$string['removeanswer'] = 'Eliminar esta respuesta';
