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
 * English language strings for local_haccgen.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$string['TOPICTITLE'] = 'Título del tema';
$string['TOPICTITLE_help'] = 'Escribe el nombre de tu curso aquí (por ejemplo: Web Development 101).';
$string['TOPICTITLE_placeholder'] = 'Introduce el título';
$string['about'] = 'Acerca de este curso';
$string['aboutthiscourse'] = 'Sobre este curso';
$string['addtopic'] = 'Agregar más temas';
$string['advanced'] = 'Avanzado';
$string['apierror'] = 'Error al generar contenido desde la API: {error}';
$string['apikey'] = 'Clave API';
$string['apikey_desc'] = 'Introduce la clave API para autenticarte con el servicio AI. Manténla segura.';
$string['apisecret'] = 'Secreto API';
$string['apisecret_desc'] = 'Introduce tu secreto de API aquí. Se mostrará de forma segura.';
$string['apisettings'] = 'Ajustes API';

$string['apitimeout'] = 'Tiempo de espera API';
$string['apitimeout_desc'] = 'Tiempo máximo (en segundos) para esperar respuestas de la API. Recomendado: 30-60s.';
$string['apiurl'] = 'URL de la API';
$string['apiurl_desc'] = 'Introduce la URL base de la API para generar contenido (p. ej.: https://api.example.com/course).';
$string['areyousure'] = '¿Seguro que quieres eliminar este borrador?';
$string['audience'] = 'Audiencia';
$string['back'] = 'Volver';
$string['basics'] = 'Conceptos básicos';
$string['beginner'] = 'Principiante';
$string['cancel'] = 'Cancelar';
$string['clicksubtopiccontent'] = 'Haz clic en un subtema para ver su contenido.';
$string['completion_both'] = 'Finalizar: ver diapositivas y aprobar quiz';
$string['completion_condition'] = 'Condición de finalización';
$string['completion_on_last_button'] = 'Al pulsar Finish en la última diapositiva';
$string['completion_on_launch'] = 'Al iniciar el curso';
$string['completion_on_view_all'] = 'Tras ver todas las diapositivas';
$string['completion_passquiz'] = 'Finalizar: aprobar quiz';
$string['completion_viewslides'] = 'Finalizar: ver diapositivas';
$string['completionmode'] = 'Modo de finalización';
$string['completionmode_desc'] = 'Determina cuándo se marca el SCORM como completado.';
$string['conclusion'] = 'Conclusión';
$string['content'] = 'Contenido';
$string['content_generated'] = '¡Contenido del curso generado con éxito!';
$string['contentgenerationfailed'] = 'Fallo al generar contenido: {$a}';
$string['contentwillappear'] = 'El contenido aparecerá aquí después de seleccionar un subtema.';
$string['continue'] = 'Continuar';
$string['course_title_default'] = 'Curso sin título';
$string['courseduration'] = 'Duración del curso';
$string['courseduration_help'] = 'Selecciona la duración aproximada del curso.';
$string['courseduration_label'] = 'Duración';
$string['courseisfor'] = 'El curso {$a->title} está diseñado para {$a->audience}';
$string['courselanguage'] = 'Idioma del curso';
$string['courseoutline'] = 'Esquema del curso';
$string['createhaccgen'] = 'Crear curso AI desde el formulario';
$string['custom_prompt'] = 'Prompt personalizado';
$string['debuglogging'] = 'Habilitar registro de depuración';
$string['debuglogging_desc'] = 'Activa registros detallados para llamadas a la API y errores. Desactiva en producción.';
$string['defaultduration'] = 'Duración predeterminada del curso';
$string['defaultduration_desc'] = 'Duración por defecto si el usuario no la especifica.';
$string['deletedraftbtn'] = 'Eliminar borrador';
$string['description'] = 'Descripción';
$string['details'] = 'Detalles';
$string['disclaimer_text'] = '<strong>Aviso:</strong> El contenido generado por HACC Gen es asistido por IA y debe revisarse y personalizarse antes de su uso final.';
$string['draftdeletedsuccess'] = 'Borrador eliminado correctamente.';
$string['draftnotfound'] = 'Borrador no encontrado.';
$string['draftsaved'] = 'Borrador guardado correctamente.';
$string['draftsavedsuccess'] = 'Borrador guardado. Puedes seguir editando o pasar al siguiente paso.';
$string['duration_10min'] = 'Aproximadamente 10 minutos';
$string['duration_120minutes'] = 'Aproximadamente 120 minutos';
$string['duration_15min'] = 'Aproximadamente 15 minutos';
$string['duration_30min'] = 'Aproximadamente 30 minutos';
$string['duration_60minutes'] = 'Aproximadamente 60 minutos';
$string['duration_90minutes'] = 'Aproximadamente 90 minutos';

$string['editquizquestions'] = 'Editar preguntas del quiz';
$string['enablepdfupload'] = 'Habilitar subida de PDF';
$string['enablepdfupload_desc'] = 'Permite a los usuarios subir un PDF en el Paso 1 para generar contenido.';
$string['engaging'] = 'Atractivo';
$string['english'] = 'Inglés';
$string['error_api'] = 'Error al comunicar con la API: {$a}.';
$string['error_api_empty'] = 'La API no ha devuelto temas. Revisa tu entrada y prueba de nuevo.';
$string['error_course_creation'] = 'Error al crear el curso: {$a}.';
$string['error_numeric'] = 'Este campo debe contener un número positivo.';
$string['error_required'] = 'Este campo es obligatorio.';
$string['error_required_fields'] = 'Por favor completa todos los campos obligatorios.';
$string['finalize'] = 'Finalizar';
$string['formal'] = 'Formal';
$string['generate'] = 'Generar curso';
$string['generatecontent'] = 'Continuar';
$string['generated_topics'] = 'Temas generados';
$string['generated_topics_list'] = 'Lista de temas generados';
$string['generatescorm'] = 'Generar paquete SCORM';
$string['generatingcourse'] = 'Generando tu curso…';
$string['hindi'] = 'Hindi';
$string['inactive'] = 'Tu suscripción ha expirado; contacta a info@dynamicpixel.co.in.';
$string['instructiontext'] = 'Texto de instrucciones';
$string['instructiontext_desc'] = 'Texto que aparecerá en la parte superior de la página de generación de curso.';
$string['intermediate'] = 'Intermedio';
$string['invalid_pdf'] = 'Por favor sube un archivo PDF válido.';
$string['invalid_topic_count'] = 'Por favor introduce un número de temas entre 2 y 10.';
$string['invalidcontentdata'] = 'Se proporcionaron datos de contenido no válidos.';
$string['invalidjson'] = 'JSON inválido: {$a}';
$string['invalidtopicorder'] = 'Orden de temas inválido: {$a}';
$string['js_error'] = 'Ocurrió un error. Por favor intenta de nuevo.';
$string['language'] = 'Seleccionar idioma';
$string['learningobjectives'] = 'Objetivos de aprendizaje';
$string['learningobjectivesfor'] = 'Objetivos de aprendizaje - {$a}';
$string['levelofunderstanding'] = 'Nivel de comprensión';
$string['levelofunderstanding_help'] = 'Selecciona el nivel de dificultad para el contenido del curso.';
$string['levelofunderstanding_label'] = 'Nivel';
$string['linksecret'] = 'Secreto para enlaces firmados';
$string['linksecret_desc'] = 'Un secreto largo usado para firmar URLs temporales de archivos.';
$string['loaddraft'] = 'Cargar borrador anterior';
$string['loaddraftbtn'] = 'Cargar borrador';
$string['loadingquestions'] = 'Cargando preguntas...';
$string['log_hasquiz'] = 'Tiene quiz';
$string['log_quizlist'] = 'Lista de quizzes';
$string['log_totalquizzes'] = 'Total de quizzes';
$string['log_totalslides'] = 'Total de diapositivas';
$string['manageai'] = 'HACC Gen';
$string['maxtopics'] = 'Número máximo de temas';
$string['maxtopics_desc'] = 'Establece el número máximo de temas que se pueden generar (recomendado: 5-20).';

$string['missingcredentials'] = 'Faltan credenciales de la API.';
$string['missingfield'] = 'Falta campo obligatorio: {$a}';
$string['no'] = 'No';
$string['no_content_generated'] = 'No se generó contenido. Intenta de nuevo.';
$string['no_topics'] = 'No se generaron temas. Revisa tus entradas.';
$string['no_topics_error'] = 'No se generaron temas.';
$string['noapiurl'] = 'No se ha configurado la URL de la API para la plataforma de contenido.';
$string['nocontent'] = 'No hay contenido disponible.';
$string['nocontentavailable'] = 'No hay contenido disponible';
$string['nodraftsfound'] = 'No se encontraron borradores para este curso.';
$string['noendpoint'] = 'El servicio de suscripción no devolvió un endpoint.';
$string['noquizavailable'] = 'No hay quiz disponible';
$string['nosubtopics'] = 'No se encontraron subtemas para este tema.';
$string['not_selected'] = 'No seleccionado';
$string['notopics'] = 'No hay temas disponibles.';
$string['numberoftopics'] = 'Número de temas';
$string['passgrade'] = 'Nota de aprobado (%)';
$string['passgrade_desc'] = 'Porcentaje mínimo requerido para aprobar el quiz o SCORM.';
$string['passingscore'] = 'Puntuación mínima por defecto';
$string['passingscore_desc'] = 'La puntuación mínima necesaria para aprobar SCORM/quiz.';
$string['pdfuploaded'] = 'PDF subido';
$string['please_complete'] = 'Por favor completa el formulario';
$string['please_select'] = 'Por favor selecciona una opción válida';
$string['pluginname'] = 'HACC Gen';
$string['previewtitle'] = 'Vista previa';
$string['proceed'] = 'Continuar';
$string['provider_desc'] = 'Elige qué proveedor de IA usar para generar el curso.';
$string['provider_for_content'] = 'Proveedor para contenido (Paso 4)';
$string['provider_for_content_outline'] = 'Proveedor para esquema de contenido (Paso 3)';
$string['publiclinkttl'] = 'Tiempo de expiración de enlace público';
$string['publiclinkttl_desc'] = 'Cuánto tiempo serán válidos los enlaces públicos (p. ej.: 1 hora).';
$string['quizdefault'] = 'Quiz {$a}';
$string['quizpreview'] = 'Vista previa del quiz';
$string['quotes'] = 'Comencemos… descubre el propósito detrás de tus objetivos de aprendizaje';
$string['returntostep1'] = 'Volver al Paso 1';
$string['review'] = 'Revisión';

$string['save'] = 'Guardar';
$string['saveandcontinue'] = 'Guardar y continuar';
$string['savechanges'] = 'Guardar cambios';
$string['savecreate'] = 'Guardar y crear curso';
$string['scoring_mixed'] = 'Mixto (50% quiz + 50% visualización de diapositivas)';
$string['scoring_quiz_only'] = 'Solo quiz (100% puntuación del quiz)';
$string['scoringmethod'] = 'Método de puntuación';
$string['scoringmethod_desc'] = 'Elige cómo calcular la puntuación final.';
$string['scorm_summary'] = 'Resumen del paquete SCORM';
$string['scormtype'] = 'Tipo de generación SCORM';
$string['scormtype_desc'] = 'Selecciona si generar Single SCO o Multi SCO.';
$string['scormtype_multi'] = 'Multi SCO';
$string['scormtype_single'] = 'Single SCO 1.2';
$string['scormversion'] = 'Versión SCORM por defecto';
$string['scormversion1.2'] = 'SCORM 1.2';
$string['scormversion2004'] = 'SCORM 2004';
$string['scormversion_desc'] = 'Selecciona la versión SCORM para el paquete generado.';
$string['sectioncreationfailed'] = 'No se pudieron crear las secciones del curso: {$a}';
$string['select_duration'] = 'Seleccionar duración';
$string['select_language'] = 'Selecciona un idioma';
$string['select_level'] = 'Seleccionar nivel';
$string['select_tone'] = 'Seleccionar tono';
$string['selectdraft'] = 'Selecciona un borrador:';
$string['selectsubtopic'] = 'Selecciona un subtema para ver su contenido';
$string['settings_desc'] = 'Configura los ajustes del plugin AI Course Generator.';
$string['step1'] = 'Paso 1: Detalles del curso';
$string['step2'] = 'Paso 2: Preferencias de aprendizaje';
$string['step3'] = 'Paso 3: Revisar temas';
$string['step_progress'] = 'Progreso de creación del curso';
$string['subscription_url'] = 'URL de suscripción';
$string['subscription_url_desc'] = 'Introduce la URL del API del gestor de suscripciones.';
$string['subscriptionexpired'] = 'La generación de contenido no está disponible actualmente.';
$string['summary'] = 'Resumen';
$string['target_audience_default'] = 'Público general';
$string['targetaudience'] = 'Público objetivo';
$string['targetaudience_help'] = 'Para quién está pensado este curso (p. ej.: Estudiantes, Profesionales). Puedes añadir varias etiquetas.';
$string['targetaudience_list'] = 'Lista de etiquetas de audiencia';
$string['targetaudience_placeholder'] = 'Añade etiquetas de audiencia (pulsa Enter)';
$string['tinymce_height'] = 'Altura del editor TinyMCE';
$string['tinymce_height_desc'] = 'Altura del editor TinyMCE en píxeles.';
$string['tinymce_plugins'] = 'Plugins TinyMCE';
$string['tinymce_plugins_desc'] = 'Lista de plugins TinyMCE a habilitar para el editor del curso AI.';
$string['tinymce_toolbar'] = 'Barra de herramientas TinyMCE';
$string['tinymce_toolbar_desc'] = 'Configuración de la barra de herramientas para el editor TinyMCE.';
$string['toneofnarrative'] = 'Tono narrativo';
$string['toneofnarrative_help'] = 'Selecciona el estilo narrativo para el contenido del curso.';
$string['topics'] = 'Temas';
$string['trackallpages'] = 'Rastrear todas las páginas';
$string['trackingmode'] = 'Modo de seguimiento';
$string['trackingmode_desc'] = 'Elige cómo se rastrearán las páginas SCORM.';
$string['tracklastslide'] = 'Rastrear solo la última diapositiva y quizzes con botón Finish';
$string['unauthorized'] = 'No estás autorizado para realizar esta solicitud.';
$string['untitledsubtopic'] = 'Subtema sin título';
$string['upload_error'] = 'Error al subir el archivo.';
$string['upload_pdf'] = 'Subir PDF';
$string['yes'] = 'Sí';
