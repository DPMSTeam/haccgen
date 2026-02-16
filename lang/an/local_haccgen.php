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
 * Job progress page for local_haccgen.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['TOPICTITLE'] = 'Títol d’o Tópico';
$string['TOPICTITLE_help'] = 'O nombre d’a tuya corsa (por eixemplo, „Desenvolupamiento Web 101”).';
$string['TOPICTITLE_placeholder'] = 'Entra’l títol d’o tópico';
$string['addtopic'] = 'Afigir Máis Tópicos';
$string['advanced'] = 'Avanzau';
$string['apierror'] = 'Falló la generación de contenido dende l’API: {error}';
$string['apikey'] = 'Clave d’API';
$string['apikey_desc'] = 'Entra la tuya clave d’API equí.';
$string['apisecret'] = 'Secreto d’API';
$string['apisecret_desc'] = 'Entra o tuyo secreto d’API equí. Estará escondiu pa seguridat.';
$string['apisettings'] = 'Ajustes d’API';
$string['apiurl'] = 'URL d’API de la Plataforma de Contenido Moodle';
$string['apiurl_desc'] = 'Entra a URL base pa la API de la Plataforma de Contenido de Moodle (por eixemplo https://your-api-gateway-url.execute-api.ap-south-1.amazonaws.com).';
$string['areyousure'] = '¿Seguro que quieres eliminar iste borrador?';
$string['audience'] = 'Audiéncia';
$string['back'] = 'Atrás';
$string['basics'] = 'Básicos';
$string['beginner'] = 'Principiantes';
$string['cancel'] = 'Cancelar';
$string['clicksubtopiccontent'] = 'Fai clic en un sub-tópico pa ver o contenido.';
$string['conclusion'] = 'Conclusión';
$string['content'] = 'Contenido';
$string['content_generated'] = '¡Contenido de la corsa generau con éxit!';
$string['contentgenerationfailed'] = 'Falló la generación de contenido: {$a}';
$string['contentwillappear'] = 'O contenido apareixerá aquí dende que selecciones un sub-tópico.';
$string['conversational'] = 'Conversacional';
$string['course_title_default'] = 'Corsa Sin Títol';
$string['courseduration'] = 'Duración d’a Corsa';
$string['courseduration_help'] = 'Selecciona la duración aproximada de la corsa.';
$string['courseduration_label'] = 'Duración';
$string['courseisfor'] = 'Ista corsa “{$a->title}” ta destinada a {$a->audience}';
$string['courselanguage'] = 'Lengua de la Corsa';
$string['courseoutline'] = 'Esquema de Contenido';
$string['createhaccgen'] = 'Crear Corsa IA d’o Formulario';
$string['custom_prompt'] = 'Prompt Personalizau';
$string['deletedraftbtn'] = 'Eliminar Borrador';
$string['description'] = 'Descripción';
$string['details'] = 'Detalls';
$string['disclaimer_text'] = '<strong>Descargo de responsabilidad:</strong> O contenido generau por HACC Gen ye asistiu por IA i debe ser revisau y personalizau por-o creador d’a corsa antes d’o so uso final.';
$string['draftdeletedsuccess'] = 'Borrador eliminau con éxit.';
$string['draftnotfound'] = 'Nun s’ha atopau borrador.';
$string['draftsaved'] = 'Borrador guardau con éxit.';
$string['draftsavedsuccess'] = 'Borrador guardau con éxitu. Puedé continuar editando u proceder al paso siguiente.';
$string['duration_10min'] = 'Aproximadamén 10 minutos';
$string['duration_15min'] = 'Aproximadamén 15 minutos';
$string['duration_30min'] = 'Aproximadamén 30 minutos';
$string['editquizquestions'] = 'Editar Preguntas de Quiz';
$string['engaging'] = 'Comprometidor';
$string['english'] = 'Inglés';
$string['error_numeric'] = 'Iste camp tien de calcar un número positivo.';
$string['error_required'] = 'Ye necesario iwal que completar iste camp.';
$string['error_required_fields'] = 'Por favor completa todos os camps necesarios.';
$string['finalize'] = 'Finalizar';
$string['formal'] = 'Formal';
$string['generate'] = 'Generar';
$string['generatecontent'] = 'Continuar';
$string['generated_topics'] = 'Tópicos de la Corsa Generaus';
$string['generated_topics_list'] = 'Llista de tópicos generaus de la corsa';
$string['generatescorm'] = 'Generar Paquete SCORM';
$string['generatingcourse'] = 'Generando o conteniu de la corsa…';
$string['hindi'] = 'Hindi';
$string['instructiontext'] = 'Texto d’instrucción';
$string['instructiontext_desc'] = 'Iste texto apareixerá na parte superior d’a páxina de generación de corsas.';
$string['intermediate'] = 'Intermedio';
$string['introduction'] = 'Introducción';
$string['invalid_pdf'] = 'Por favor sube un fichero PDF válidu.';
$string['invalid_topic_count'] = 'Por favor indica un número de tópicos entre 2 y 10.';
$string['invalidcontentdata'] = 'Dache de conteníu inválidu proporcionau.';
$string['invalidjson'] = 'Formato JSON inválidu.';
$string['invalidstep'] = 'Paso inválidu: {$a}';
$string['invalidtopicorder'] = 'Estructura de tópicos non se pudo analizar: {$a}';
$string['js_error'] = 'Ocurrió un error. Por favor, inténtalo de nueu.';
$string['language'] = 'Seleccionar Lengua';
$string['learningobjectives'] = 'Obchectivos d’Aprendizache';
$string['levelofunderstanding'] = 'Nivel de Comprensión';
$string['levelofunderstanding_help'] = 'Selecciona o nivel de dificultat del contenido de la corsa.';
$string['levelofunderstanding_label'] = 'Nivel';
$string['linksecret'] = 'Secreto de enlace firmado';
$string['linksecret_desc'] = 'Secreto usado ta firmar enlaces públicos.';
$string['linksecretdesc'] = 'Un secreto largo y aleatorio usado ta firmar URLs de ficheros temporals. Rotarlo invalidará los enlaces pendientes.';
$string['loaddraft'] = 'Cargar Borrador Anterior';
$string['loaddraftbtn'] = 'Cargar Borrador';
$string['loadingquestions'] = 'Cargando preguntas…';
$string['manageai'] = 'HACC Gen';
$string['missingcredentials'] = 'Faltan credenciaus d’API.';
$string['missingfield'] = 'Falta’l camp necesario: {$a}';
$string['no_content_generated'] = 'Nun s’ha generau ningún contenido. Por favor inténtalo de nueu.';
$string['no_topics'] = 'Nun s’han generau tópicos. Por favor torna atrás y revisa los teus datos.';
$string['no_topics_error'] = 'No se generoron u proporcionoron tópicos.';
$string['noapiurl'] = 'A URL de l’API no s’adreztra pa la Plataforma de Contenido de Moodle.';
$string['nocontent'] = 'Nun hai contenido disponible.';
$string['nodraftsfound'] = 'Nun se han atopau borradors pa ista corsa.';
$string['noendpoint'] = 'No s’ha retornau endpoint de contenido por o serviciu de subscribisión.';
$string['nosubtopics'] = 'Nun s’an atopau sub-tópicos pa iste tópico.';
$string['not_selected'] = 'Nun seleccionau';
$string['notopics'] = 'Nun hai tópicos disponibles.';
$string['numberoftopics'] = 'Número de Tópicos';
$string['pdfuploaded'] = 'PDF Subiu';
$string['please_complete'] = 'Por favor completa’l formulario';
$string['pluginname'] = 'HACC Gen';
$string['previewtitle'] = 'Previsualización';
$string['proceed'] = 'Proceder';
$string['provider_desc'] = 'Selecciona qué proveedor d’IA usar pa la generación de la corsa.';
$string['provider_for_content'] = 'Proveidor pal contenido (paso 4)';
$string['provider_for_content_outline'] = 'Proveidor pal esquema de contenido (paso 3)';
$string['publiclinkttl'] = 'Temps d’expiración d’enlaces públicos';
$string['publiclinkttl_desc'] = 'Cuantu tiempo yeran válids os enlaces públicos (por eixemplo 1 hora).';
$string['quizpreview'] = 'Previsualización de Quiz';
$string['quotes'] = "Empezamos… Descubrindo o propósito d’os teus obchectius d’aprendizache.";
$string['returntostep1'] = 'Volver al Paso 1';
$string['review'] = 'Revisión';
$string['save'] = 'Guardar';
$string['savechanges'] = 'Guardar cambios';
$string['savecreate'] = 'Guardar y Crear Corsa';
$string['savedraft'] = 'Guardar Borrador';
$string['sectioncreationfailed'] = 'Falló crear u actualizar seccions de la corsa: {$a}';
$string['select_duration'] = 'Seleccionar duración';
$string['select_language'] = 'Seleccionar una Lengua';
$string['select_level'] = 'Seleccionar nivel';
$string['select_tone'] = 'Seleccionar ton';
$string['selectdraft'] = 'Selecciona un Borrador:';
$string['selectsubtopic'] = 'Selecciona sub-tópico ta ver contenido';
$string['settings_desc'] = 'Configura los ajustes pa l’plugin de Generador de Corsas IA y personaliza la creación de la corsa y l’integración d’API.';
$string['step1'] = 'Paso 1: Datos d’a Corsa';
$string['step2'] = 'Paso 2: Preferencias d’aprendizache';
$string['step3'] = 'Paso 3: Revisión de Tópicos';
$string['step_progress'] = 'Progreso de creación de la corsa';
$string['subscription_url'] = 'URL de Subscribisión';
$string['subscription_url_desc'] = 'Entra la URL d’o serviciu de gestión de subscribisión.';
$string['subscriptionexpired'] = 'La generación de contenido nun ta disponible. Por favor contacta en info@dynamicpixel.co.in.';
$string['summary'] = 'Resumen';
$string['target_audience_default'] = 'Audiéncia Xeneral';
$string['targetaudience'] = 'Public al cual s’adreça';
$string['targetaudience_help'] = 'A quién va destinada ista corsa, por eixemplo Estudiants, Profesionals (amás d’etiquetas múltiples).';
$string['targetaudience_list'] = 'Llista d’etiquetas d’audiéncia';
$string['targetaudience_placeholder'] = 'Afigir etiquetas d’audiéncia (prema Enter ta afigir)';
$string['tinymce_height'] = 'Altor de l’Editor TinyMCE';
$string['tinymce_height_desc'] = 'Altor de l’editor TinyMCE en píxels.';
$string['tinymce_plugins'] = 'Plugins TinyMCE';
$string['tinymce_plugins_desc'] = 'Lista de plugins TinyMCE a habilitar pa l’editor de Corsas IA.';
$string['tinymce_toolbar'] = 'Barra d’eines TinyMCE';
$string['tinymce_toolbar_desc'] = 'Configuración pa la barra d’eines TinyMCE en l’editor de Corsas IA.';
$string['toneofnarrative'] = 'Ton de Narrativa';
$string['toneofnarrative_help'] = 'Selecciona l’estilu narrativo pa’l contenido de la corsa.';
$string['topics'] = 'Tópicos';
$string['upload_error'] = 'Ocurrió un error al subir o fichero.';
$string['upload_pdf'] = 'Subir PDF';
