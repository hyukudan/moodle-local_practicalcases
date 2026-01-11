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
