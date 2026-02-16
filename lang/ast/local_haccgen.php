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

$string['TOPICTITLE'] = 'Títulu del Tópicu';
$string['TOPICTITLE_help'] = 'El nome del to cursu (por exemplu: „Web Development 101”).';
$string['TOPICTITLE_placeholder'] = 'Introduce’l títulu del tópicu';
$string['addtopic'] = 'Afigir más tópicos';
$string['advanced'] = 'Avanzáu';
$string['apierror'] = 'Falló la xeneración de conteníu dende la API: {error}';
$string['apikey'] = 'Clave d’API';
$string['apikey_desc'] = 'Introduce la to clave d’API equí.';
$string['apisecret'] = 'Segredu d’API';
$string['apisecret_desc'] = 'Introduce’l to segredu d’API equí. Estará ocultu por seguridá.';
$string['apisettings'] = 'Axustes d’API';
$string['apiurl'] = 'URL de la Plataforma de Conteníu Moodle API';
$string['apiurl_desc'] = 'Introduce la URL base pa la API de la Plataforma de Conteníu de Moodle (por exemplu https://your-api-gateway-url.execute-api.ap-south-1.amazonaws.com).';
$string['areyousure'] = '¿Tas seguru que quies eliminar esti borrador?';
$string['audience'] = 'Públichu';
$string['back'] = 'Enrere';
$string['basics'] = 'Básicos';
$string['beginner'] = 'Iniciantes';
$string['cancel'] = 'Cancelar';
$string['clicksubtopiccontent'] = 'Fai clic nun sub-tópicu pa veyer el conteníu.';
$string['conclusion'] = 'Concluyin';
$string['content'] = 'Conteníu';
$string['content_generated'] = '¡Conteníu del cursu xeneráu con éxitu!';
$string['contentgenerationfailed'] = 'La xeneración del conteníu falló: {$a}';
$string['contentwillappear'] = 'El conteníu apaecerá equí depués que selecciones un sub-tópicu.';
$string['continue'] = 'Continuar';
$string['conversational'] = 'Conversacional';
$string['course_title_default'] = 'Cursu Sin Títulu';
$string['courseduration'] = 'Duración del Cursu';
$string['courseduration_help'] = 'Selecciona la duración aproximada del cursu.';
$string['courseduration_label'] = 'Duración';
$string['courseisfor'] = 'Esti cursu „{$a->title}” ta diseñáu pa {$a->audience}';
$string['courselanguage'] = 'Llingua del Cursu';
$string['courseoutline'] = 'Esquem de Conteníu';
$string['createhaccgen'] = 'Crear Cursu IA dende Formulariu';
$string['custom_prompt'] = 'Mensaje Personalizáu';
$string['deletedraftbtn'] = 'Eliminar Borrador';
$string['description'] = 'Descripción';
$string['details'] = 'Detalles';
$string['disclaimer_text'] = '<strong>Descargo de responsabilidad:</strong> El conteníu xeneráu por HACC Gen ta asistíu por IA y ha de tar revisáu y personalizáu pel creador del cursu enantes del so usu final.';
$string['draftdeletedsuccess'] = 'Borrador elimináu con éxitu.';
$string['draftnotfound'] = 'Nun s’atopó borrador.';
$string['draftsaved'] = 'Borrador guardáu con éxitu.';
$string['draftsavedsuccess'] = 'El borrador guardóse con éxitu. Podes continuar editando o proseguir al próximu pasu.';
$string['duration_10min'] = 'Aproximadament 10 minutos';
$string['duration_15min'] = 'Aproximadament 15 minutos';
$string['duration_30min'] = 'Aproximadament 30 minutos';
$string['editquizquestions'] = 'Editar les Cuestions del Quiz';
$string['engaging'] = 'Atrayente';
$string['english'] = 'Inglés';
$string['error_numeric'] = 'Esti campu tiene de ser un númberu positivu.';
$string['error_required'] = 'Esti campu ye obrigatoriu.';
$string['error_required_fields'] = 'Por favor completa totos los camps obligatoriu­s.';
$string['finalize'] = 'Finalizar';
$string['formal'] = 'Formal';
$string['generate'] = 'Xenerar';
$string['generatecontent'] = 'Continuar';
$string['generated_topics'] = 'Tópicos xeneráos del cursu';
$string['generated_topics_list'] = 'Llista de tópicos xeneráos del cursu';
$string['generatescorm'] = 'Xenerar Paquete SCORM';
$string['generatingcourse'] = 'Xenerando’l to conteníu del cursu…';
$string['hindi'] = 'Hindi';
$string['instructiontext'] = 'Testu d’Instrucción';
$string['instructiontext_desc'] = 'Equí apareixerá esti testu na parte superior de la páxina de xeneración del cursu.';
$string['intermediate'] = 'Intermediu';
$string['introduction'] = 'Introducción';
$string['invalid_pdf'] = 'Por favor sube un ficheru PDF válidu.';
$string['invalid_topic_count'] = 'Por favor introduce un númberu de tópicos ente 2 y 10.';
$string['invalidcontentdata'] = 'Dáronse datos de conteníu inválidos.';
$string['invalidjson'] = 'Formato JSON inválidu.';
$string['invalidstep'] = 'Pasu inválidu: {$a}';
$string['invalidtopicorder'] = 'La estructura de tópicos nun s’algamó: {$a}';
$string['js_error'] = 'Ocurrió un error. Por favor inténtalo de nuevu.';
$string['language'] = 'Seleccionar Llingua';
$string['learningobjectives'] = 'Obxectivos d’Aprendizaxe';
$string['levelofunderstanding'] = 'Nivel d’Entendimientu';
$string['levelofunderstanding_help'] = 'Selecciona’l nivel de dificultá del conteníu del cursu.';
$string['levelofunderstanding_label'] = 'Nivel';
$string['linksecret'] = 'Segredu d’Enllaz Firmáu';
$string['linksecret_desc'] = 'Segredu usáu pa firmar enllaces públi­cos.';
$string['linksecretdesc'] = 'Un llargu segredu al azar usáu pa firmar URLs de ficheros temporales. Volver-rotaresto invalidará los enllaces pendientes.';
$string['loaddraft'] = 'Cargar Borrador Anterior';
$string['loaddraftbtn'] = 'Cargar Borrador';
$string['loadingquestions'] = 'Cargando cuestiones…';
$string['manageai'] = 'HACC Gen';
$string['missingcredentials'] = 'Falten credenciaus d’API.';
$string['missingfield'] = 'Falta’l campu obligatori: {$a}';
$string['no_content_generated'] = 'Nun se xeneró conteníu. Por favor inténtalo de nuevu.';
$string['no_topics'] = 'Nun s’han xeneráu tópicos. Por favor torna atrás y revisa los tos datos.';
$string['no_topics_error'] = 'Nun se xeneroron ni proporcionoron tópicos.';
$string['noapiurl'] = 'La URL del API nun ta configurao pa la Plataforma de Conteníu de Moodle.';
$string['nocontent'] = 'Nun hai conteníu disponible.';
$string['nodraftsfound'] = 'Nun se atoporon borradors pa esti cursu.';
$string['noendpoint'] = 'Nun se devolvió endpoint de conteníu pol serviciu de subscribición.';
$string['nosubtopics'] = 'Nun s’an atopáu sub-tópicos pa esti tópicu.';
$string['not_selected'] = 'Nun seleccionau';
$string['notopics'] = 'Nun hai tópicos disponibles.';
$string['numberoftopics'] = 'Númberu de Tópicos';
$string['pdfuploaded'] = 'PDF subíu';
$string['please_complete'] = 'Por favor completa’l formulariu';
$string['please_select'] = 'Por favor selecciona una opción válid­a.';
$string['pluginname'] = 'HACC Gen';
$string['previewtitle'] = 'Previsualización';
$string['proceed'] = 'Proce­der';
$string['provider_desc'] = 'Selecciona qué proveedor d’IA usar pa la xeneración del cursu.';
$string['provider_for_content'] = 'Proveedor pa’l Conteníu (pasu 4)';
$string['provider_for_content_outline'] = 'Proveedor pa l’Esquem­ de Conteníu (pasu 3)';
$string['publiclinkttl'] = 'Tempu d’Expiración de Enllaces Públicos';
$string['publiclinkttl_desc'] = 'Cuantu tiempu van tar válidos los enllaces públi­cos (por exemplu 1 hora).';
$string['quizpreview'] = 'Previsualización del Quiz';
$string['quotes'] = "Empezamos… Descubrí l’oxetivu darréu de los tos métodos d’aprendizaxe";
$string['returntostep1'] = 'Tornar al Pasu 1';
$string['review'] = 'Revisión';
$string['save'] = 'Guardar';
$string['savechanges'] = 'Guardar cambéos';
$string['savecreate'] = 'Guardar y Crear Cursu';
$string['savedraft'] = 'Guardar Borrador';
$string['sectioncreationfailed'] = 'Falló crear o actualizar les sel seccions del cursu: {$a}';
$string['select_duration'] = 'Seleccionar duración';
$string['select_language'] = 'Seleccionar Llingua';
$string['select_level'] = 'Seleccionar nivel';
$string['select_tone'] = 'Seleccionar ton';
$string['selectdraft'] = 'Selecciona un Borrador:';
$string['selectsubtopic'] = 'Selecciona un sub-tópicu pa veyer el conteníu';
$string['settings_desc'] = 'Configura los axustes pa l’plugin del Xenerador de Corsos IA pa personalizar la creación del cursu y l’integración de la API.';
$string['step1'] = 'Pasu 1: Detalles del Cursu';
$string['step2'] = 'Pasu 2: Preferencies d’Aprendizaxe';
$string['step3'] = 'Pasu 3: Revisión de Tópicos';
$string['step_progress'] = 'Progrés na creación del cursu';
$string['subscription_url'] = 'URL de Subscribición';
$string['subscription_url_desc'] = 'Introduce la URL del serviciu de xestión de subscribición.';
$string['subscriptionexpired'] = 'La xeneración de conteníu nun ta disponible nun momentu. Por favor contacta info@dynamicpixel.co.in.';
$string['summary'] = 'Resumu';
$string['target_audience_default'] = 'Públichu Xeneral';
$string['targetaudience'] = 'Públichu Destín';
$string['targetaudience_help'] = 'Pa quién ta dirixíu esti cursu: por exemplu Estudiantes, Profesionals (añade múltiples etiquetes).';
$string['targetaudience_list'] = 'Llista d’etiquetes del públichu destín';
$string['targetaudience_placeholder'] = 'Afige etiquetes del públichu (preme Enter pa afigir)';
$string['tinymce_height'] = 'Altor del Editor TinyMCE';
$string['tinymce_height_desc'] = 'Altor del editor TinyMCE en píxeles.';
$string['tinymce_plugins'] = 'Plugins TinyMCE';
$string['tinymce_plugins_desc'] = 'Llista de plugins TinyMCE a habilitar pa l’editor de Corsos IA.';
$string['tinymce_toolbar'] = 'Barra d’Ferramientes TinyMCE';
$string['tinymce_toolbar_desc'] = 'Configuración de la barra d’eines TinyMCE en l’editor de Corsos IA.';
$string['toneofnarrative'] = 'Ton de Narrativa';
$string['toneofnarrative_help'] = 'Selecciona l’estilu narrativu pa’l conteníu del cursu.';
$string['topics'] = 'Tópicos';
$string['upload_error'] = 'Ocurrió un error al subir el ficheru.';
$string['upload_pdf'] = 'Subir PDF';
