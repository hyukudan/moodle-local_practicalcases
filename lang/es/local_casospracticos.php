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
$string['numquestions'] = '{$a} preguntas';
$string['created'] = 'Creado';
$string['modified'] = 'Modificado';
$string['createdby'] = 'Creado por';

// Errors.
$string['error:categorynotfound'] = 'Categoría no encontrada';
$string['error:casenotfound'] = 'Caso no encontrado';
$string['error:questionnotfound'] = 'Pregunta no encontrada';
$string['error:nopermission'] = 'No tiene permiso para realizar esta acción';
$string['error:invaliddata'] = 'Datos proporcionados no válidos';

// Privacy.
$string['privacy:metadata:local_cp_cases'] = 'Almacena los casos prácticos creados por los usuarios';
$string['privacy:metadata:local_cp_cases:createdby'] = 'El ID del usuario que creó el caso';
$string['privacy:metadata:local_cp_cases:timecreated'] = 'La fecha de creación del caso';
$string['privacy:metadata:local_cp_cases:timemodified'] = 'La fecha de última modificación del caso';
<?php
// Additional strings for events, settings, search - Spanish.

// Events.
$string['eventcasecreated'] = 'Caso práctico creado';
$string['eventcaseupdated'] = 'Caso práctico actualizado';
$string['eventcasedeleted'] = 'Caso práctico eliminado';
$string['eventcasepublished'] = 'Caso práctico publicado';

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
$string['settings:importexport'] = 'Importar/Exportar';
$string['settings:maximportsize'] = 'Tamaño máximo de archivo de importación';
$string['settings:maximportsize_desc'] = 'Tamaño máximo en bytes para importar casos';
$string['settings:defaultexportformat'] = 'Formato de exportación por defecto';
$string['settings:defaultexportformat_desc'] = 'Formato predeterminado al exportar casos';
$string['settings:questiontypes'] = 'Tipos de pregunta';
$string['settings:questiontypes_desc'] = 'Configurar qué tipos de pregunta están disponibles';
$string['settings:allowedqtypes'] = 'Tipos de pregunta permitidos';
$string['settings:allowedqtypes_desc'] = 'Seleccione qué tipos de pregunta se pueden usar en los casos';
$string['settings:display'] = 'Configuración de visualización';
$string['settings:casesperpage'] = 'Casos por página';
$string['settings:casesperpage_desc'] = 'Número de casos a mostrar por página en la lista';
$string['settings:showquestioncount'] = 'Mostrar número de preguntas';
$string['settings:showquestioncount_desc'] = 'Mostrar el número de preguntas de cada caso en la lista';
$string['settings:showdifficulty'] = 'Mostrar dificultad';
$string['settings:showdifficulty_desc'] = 'Mostrar el nivel de dificultad en la lista de casos';
$string['settings:notifications'] = 'Notificaciones';
$string['settings:notifyonpublish'] = 'Notificar al publicar';
$string['settings:notifyonpublish_desc'] = 'Enviar notificación a los administradores cuando se publica un caso';

// Difficulty levels.
$string['difficulty1'] = 'Muy fácil';
$string['difficulty2'] = 'Fácil';
$string['difficulty3'] = 'Medio';
$string['difficulty4'] = 'Difícil';
$string['difficulty5'] = 'Muy difícil';

// Question types.
$string['qtype_multichoice'] = 'Opción múltiple';
$string['qtype_truefalse'] = 'Verdadero/Falso';
$string['qtype_shortanswer'] = 'Respuesta corta';
$string['qtype_matching'] = 'Emparejamiento';

// Statuses.
$string['status_draft'] = 'Borrador';
$string['status_published'] = 'Publicado';
$string['status_archived'] = 'Archivado';

// Default category.
$string['defaultcategory'] = 'Casos importados';

// Pagination.
$string['page'] = 'Página';
$string['of'] = 'de';
$string['showingcases'] = 'Mostrando {$a->start} a {$a->end} de {$a->total} casos';
$string['nocasesfound'] = 'No se encontraron casos con los criterios especificados';

// Search and filter.
$string['searchcases'] = 'Buscar casos';
$string['filterbycat'] = 'Filtrar por categoría';
$string['filterbystatus'] = 'Filtrar por estado';
$string['filterbydifficulty'] = 'Filtrar por dificultad';
$string['allcategories'] = 'Todas las categorías';
$string['allstatuses'] = 'Todos los estados';
$string['alldifficulties'] = 'Todas las dificultades';
$string['clearsearch'] = 'Limpiar búsqueda';
$string['advancedfilters'] = 'Filtros avanzados';

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
$string['confirmdeleteselected'] = '¿Está seguro de que desea eliminar los casos seleccionados? Esta acción no se puede deshacer.';
$string['casesdeleted'] = '{$a} casos eliminados correctamente';
$string['casesmoved'] = '{$a} casos movidos correctamente';
$string['casespublished'] = '{$a} casos publicados correctamente';
$string['casesarchived'] = '{$a} casos archivados correctamente';
<?php
// Additional strings for bulk operations, workflow, audit - Spanish.

// Workflow statuses.
$string['status_pending_review'] = 'Pendiente de revisión';
$string['status_in_review'] = 'En revisión';
$string['status_approved'] = 'Aprobado';

// Review statuses.
$string['review_pending'] = 'Pendiente';
$string['review_approved'] = 'Aprobado';
$string['review_rejected'] = 'Rechazado';
$string['review_revision'] = 'Revisión solicitada';

// Workflow actions.
$string['submitforreview'] = 'Enviar a revisión';
$string['assignreviewer'] = 'Asignar revisor';
$string['approve'] = 'Aprobar';
$string['reject'] = 'Rechazar';
$string['requestrevision'] = 'Solicitar revisión';
$string['publish'] = 'Publicar';
$string['archive'] = 'Archivar';
$string['unarchive'] = 'Desarchivar';

// Workflow messages.
$string['casesubmittedreview'] = 'Caso enviado a revisión';
$string['caseapproved'] = 'Caso aprobado';
$string['caserejected'] = 'Caso rechazado';
$string['casepublished'] = 'Caso publicado';
$string['casearchived'] = 'Caso archivado';
$string['invalidtransition'] = 'Transición de estado inválida';
$string['noquestionsforsubmit'] = 'El caso debe tener al menos una pregunta antes de enviarlo a revisión';
$string['invalidstatusforreviewer'] = 'El caso no está en un estado revisable';
$string['notassignedreviewer'] = 'No está asignado como revisor de este caso';
$string['invaliddecision'] = 'Decisión de revisión inválida';

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
$string['bulkexportpdf'] = 'Exportar como PDF';
$string['bulkexportcsv'] = 'Exportar como CSV';
$string['confirmdeleteselected'] = '¿Está seguro de que desea eliminar {$a} casos seleccionados? Esta acción no se puede deshacer.';
$string['confirmpublishselected'] = '¿Está seguro de que desea publicar {$a} casos seleccionados?';
$string['confirmarchiveselected'] = '¿Está seguro de que desea archivar {$a} casos seleccionados?';
$string['casesdeleted'] = '{$a} casos eliminados correctamente';
$string['casesmoved'] = '{$a} casos movidos correctamente';
$string['casespublished'] = '{$a} casos publicados correctamente';
$string['casesarchived'] = '{$a} casos archivados correctamente';
$string['selectedcases'] = '{$a} seleccionados';
$string['nocasesselected'] = 'No hay casos seleccionados';
$string['moveto'] = 'Mover a...';
$string['selecttargetcategory'] = 'Seleccionar categoría destino';

// Audit log.
$string['auditlog'] = 'Registro de auditoría';
$string['auditlogs'] = 'Registros de auditoría';
$string['viewauditlog'] = 'Ver registro de auditoría';
$string['noauditlogs'] = 'No se encontraron registros de auditoría';
$string['audit:action_create'] = 'Creado';
$string['audit:action_update'] = 'Actualizado';
$string['audit:action_delete'] = 'Eliminado';
$string['audit:action_publish'] = 'Publicado';
$string['audit:action_archive'] = 'Archivado';
$string['audit:action_submit_review'] = 'Enviado a revisión';
$string['audit:action_approve'] = 'Aprobado';
$string['audit:action_reject'] = 'Rechazado';
$string['audit:action_bulk_delete'] = 'Eliminación masiva';
$string['audit:action_bulk_move'] = 'Movimiento masivo';
$string['audit:action_bulk_publish'] = 'Publicación masiva';
$string['audit:action_bulk_archive'] = 'Archivado masivo';
$string['audit:action_import'] = 'Importado';
$string['audit:action_export'] = 'Exportado';
$string['auditobjecttype'] = 'Tipo de objeto';
$string['auditaction'] = 'Acción';
$string['audituser'] = 'Usuario';
$string['audittime'] = 'Fecha/Hora';
$string['auditchanges'] = 'Cambios';
$string['auditipaddress'] = 'Dirección IP';
$string['filterbyaction'] = 'Filtrar por acción';
$string['filterbyuser'] = 'Filtrar por usuario';
$string['filterbydaterange'] = 'Filtrar por rango de fechas';
$string['exportauditlog'] = 'Exportar registro de auditoría';

// Advanced filters.
$string['advancedfilters'] = 'Filtros avanzados';
$string['searchcases'] = 'Buscar casos';
$string['filterbycat'] = 'Filtrar por categoría';
$string['filterbystatus'] = 'Filtrar por estado';
$string['filterbydifficulty'] = 'Filtrar por dificultad';
$string['filterbytags'] = 'Filtrar por etiquetas';
$string['filterbycreator'] = 'Filtrar por creador';
$string['filterbydatefrom'] = 'Creado desde';
$string['filterbydateto'] = 'Creado hasta';
$string['filterbyquestioncount'] = 'Número de preguntas';
$string['allcategories'] = 'Todas las categorías';
$string['allstatuses'] = 'Todos los estados';
$string['alldifficulties'] = 'Todas las dificultades';
$string['alltags'] = 'Todas las etiquetas';
$string['allcreators'] = 'Todos los creadores';
$string['clearsearch'] = 'Limpiar búsqueda';
$string['clearfilters'] = 'Limpiar todos los filtros';
$string['applyfilters'] = 'Aplicar filtros';
$string['activefilters'] = 'Filtros activos';
$string['noresults'] = 'No se encontraron casos con los criterios especificados';
$string['showing'] = 'Mostrando {$a->start} a {$a->end} de {$a->total}';

// Sorting.
$string['sortby'] = 'Ordenar por';
$string['sortbyname'] = 'Nombre';
$string['sortbycreated'] = 'Fecha de creación';
$string['sortbymodified'] = 'Fecha de modificación';
$string['sortbydifficulty'] = 'Dificultad';
$string['sortbyquestions'] = 'Número de preguntas';
$string['sortasc'] = 'Ascendente';
$string['sortdesc'] = 'Descendente';

// Export formats.
$string['exportformat'] = 'Formato de exportación';
$string['exportpdf'] = 'Exportar como PDF';
$string['exportcsv'] = 'Exportar como CSV';
$string['exportxml'] = 'Exportar como XML';
$string['exportjson'] = 'Exportar como JSON';
$string['exportoptions'] = 'Opciones de exportación';
$string['includeanswers'] = 'Incluir respuestas';
$string['includecorrect'] = 'Marcar respuestas correctas';
$string['includefeedback'] = 'Incluir retroalimentación';
$string['pagebreakpercase'] = 'Salto de página entre casos';
$string['flatformat'] = 'Formato plano (una fila por pregunta)';

// Reviews.
$string['reviews'] = 'Revisiones';
$string['reviewhistory'] = 'Historial de revisiones';
$string['pendingreviews'] = 'Revisiones pendientes';
$string['myreviews'] = 'Mis revisiones';
$string['reviewcase'] = 'Revisar caso';
$string['reviewcomments'] = 'Comentarios de revisión';
$string['submitreview'] = 'Enviar revisión';
$string['nopendingreviews'] = 'No hay revisiones pendientes';
$string['reviewedon'] = 'Revisado el {$a}';
$string['reviewedby'] = 'Revisado por {$a}';
$string['awaitingreview'] = 'Esperando revisión';

// Settings additions.
$string['settings:workflow'] = 'Configuración del flujo de trabajo';
$string['settings:enableworkflow'] = 'Habilitar flujo de aprobación';
$string['settings:enableworkflow_desc'] = 'Requerir que los casos sean revisados y aprobados antes de publicar';
$string['settings:auditretention'] = 'Retención del registro de auditoría (días)';
$string['settings:auditretention_desc'] = 'Número de días para mantener las entradas del registro de auditoría. Establecer en 0 para mantener indefinidamente.';

// Capabilities.
$string['casospracticos:review'] = 'Revisar casos prácticos';
$string['casospracticos:viewauditlog'] = 'Ver registro de auditoría';
$string['casospracticos:bulkoperations'] = 'Realizar operaciones en lote';

// Errors.
$string['error:bulkdeletefailed'] = 'Error al eliminar algunos casos';
$string['error:bulkmovefailed'] = 'Error al mover algunos casos';
$string['error:categorynotfound'] = 'Categoría no encontrada';
$string['error:invalidformat'] = 'Formato de exportación inválido';
