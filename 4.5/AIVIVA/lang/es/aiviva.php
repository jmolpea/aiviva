<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish language strings for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activityname']   = 'Nombre de la actividad';

$string['aiviva:addinstance']        = 'Añadir una actividad AI Viva';

$string['aiviva:grade']              = 'Calificar entregas';

$string['aiviva:manageoverrides']    = 'Gestionar anulaciones de usuario y grupo';

$string['aiviva:manageplugin']       = 'Gestionar la configuración del plugin';

$string['aiviva:submit']             = 'Enviar una presentación';

$string['aiviva:view']               = 'Ver la actividad AI Viva';

$string['aiviva:viewallsubmissions'] = 'Ver todas las entregas';

$string['attemptsinfo']    = 'Intentos usados: {$a->used} / {$a->max} ({$a->remaining} restantes)';

$string['avatar_1']      = 'Avatar 1 (neutro)';

$string['avatar_2']      = 'Avatar 2 (femenino)';

$string['avatar_3']      = 'Avatar 3 (masculino)';

$string['avatar_custom'] = 'Imagen personalizada';

$string['backup_files']       = 'Incluir archivos de video/audio (puede ser grande)';

$string['backup_settings']    = 'Incluir configuración de la actividad AI Viva';

$string['backup_submissions'] = 'Incluir entregas de estudiantes';

$string['col_actions']         = 'Acciones';

$string['col_grade']           = 'Calificación';

$string['col_status']          = 'Estado';

$string['col_student']         = 'Estudiante';

$string['col_submitted']       = 'Enviado';

$string['col_workflow']        = 'Estado de revisión';

$string['completiongrade']  = 'El estudiante debe recibir una calificación';

$string['completionsubmit'] = 'El estudiante debe enviar la actividad';

$string['confirm_delete_submission'] = '¿Estás seguro de que quieres eliminar esta entrega? Esta acción no se puede deshacer.';

$string['confirm_pdf_upload']     = '¿Confirmas que tu documento está listo? Una vez enviado, no podrás modificarlo en este intento.';

$string['confirm_video_submit']   = '¿Enviar tu grabación? Este intento será definitivo.';

$string['content_flagged']          = 'El contenido fue marcado por el filtro de seguridad de IA.';

$string['continue_to_step2']     = 'Continuar al Paso 2 →';

$string['continue_to_step3']     = 'Continuar al Paso 3 →';

$string['conversation_log']       = 'Registro de la sesión';

$string['delete_submission']         = 'Eliminar entrega';

$string['error_analysis_timeout']   = 'El análisis está tardando más de lo esperado. Por favor, recarga la página para comprobar el progreso.';

$string['error_duration_invalid']   = 'La duración debe ser de al menos 1 minuto.';

$string['error_file_too_large']     = 'El archivo supera el tamaño máximo de {$a} MB.';

$string['error_maxfilesize_exceeds_global'] = 'No puede superar el máximo global de {$a} MB establecido por el administrador.';

$string['error_maxfilesize_toosmall'] = 'El tamaño máximo debe ser de al menos 1 MB.';

$string['error_not_pdf']            = 'Solo se aceptan archivos PDF.';

$string['error_screen_permission']  = 'Se denegó el permiso para grabar la pantalla. Por favor, permite la captura de pantalla e inténtalo de nuevo.';

$string['error_video_too_large']    = 'El video supera el tamaño máximo de {$a} MB.';

$string['evaluation_complete']    = '✅ Evaluación completada. Redirigiendo…';

$string['evaluation_pending']     = 'El tribunal de IA está evaluando tu desempeño. Esto puede tardar un momento…';

$string['evaluator_invalid_response'] = 'El evaluador de IA devolvió una respuesta inválida. Contacta a tu instructor.';

$string['event_assessment_completed'] = 'Evaluación IA completada';

$string['event_grade_issued']         = 'Calificación emitida';

$string['event_submission_created']   = 'Entrega creada';

$string['feedback']              = 'Retroalimentación';

$string['gdpr_consent_label']  = 'Entiendo y acepto que mi PDF, video y audio serán procesados por la API de OpenAI.';

$string['gdpr_consent_required']   = 'Debes dar tu consentimiento RGPD en la página de la actividad antes de subir archivos.';

$string['gdpr_default_notice'] = '<p>Para completar esta actividad, tu documento PDF, grabación de pantalla y respuestas habladas serán enviados a la <strong>API de OpenAI</strong> para su análisis y evaluación.</p><p>Tu nombre personal será reemplazado por un identificador anónimo antes de enviar cualquier dato. Los datos no son retenidos por OpenAI más allá de la solicitud inmediata. Los archivos se eliminan automáticamente de este servidor después de {$a} días.</p><p>Al continuar, consientes este procesamiento de acuerdo con nuestra política de privacidad.</p>';

$string['gdpr_notice_title']   = 'Aviso de privacidad — Procesamiento con IA';

$string['grade_override_saved'] = 'Calificación guardada correctamente.';

$string['grade_pending_review']  = 'Tu calificación está siendo revisada por tu instructor. Recibirás una notificación cuando sea publicada.';

$string['gradenotification_body']     = <<<'EOT'
Tu calificación para '{$a->activityname}' en '{$a->coursename}' ha sido publicada.

Calificación: {$a->grade}

Ver tus resultados: {$a->link}
EOT;

$string['gradenotification_bodyhtml'] = '<p>Tu calificación para <strong>{$a->activityname}</strong> en <em>{$a->coursename}</em> ha sido publicada.</p><p>Calificación: <strong>{$a->grade}</strong></p><p><a href="{$a->link}">Ver tus resultados</a></p>';

$string['gradenotification_small']    = 'Calificación publicada: {$a->activityname}';

$string['gradenotification_subject']  = 'Tu calificación está lista: {$a->activityname}';

$string['grading_header']   = 'Calificación y flujo de trabajo';

$string['grading_workflow'] = 'Activar revisión del profesor antes de publicar';

$string['groupsubmission'] = 'Entrega grupal';

$string['groupsubmission_help'] = 'Permite que los grupos envíen juntos. Requiere grupos configurados en el curso.';

$string['invalidsubmissionstatus'] = 'Esta acción no está permitida en el estado actual de la entrega.';

$string['maxattempts']    = 'Número máximo de intentos';

$string['maxattempts_help'] = 'Número máximo de veces que un estudiante puede intentar esta actividad. 0 = ilimitado.';

$string['maximumgrade']     = 'Calificación máxima';

$string['model_economical']      = '(económico)';

$string['model_recommended']     = '(recomendado)';

$string['modulename']        = 'AI Viva';

$string['modulenameplural']  = 'AI Vivas';

$string['no_overrides_yet']        = 'No se han configurado excepciones.';

$string['no_submissions_yet']  = 'Todavía no hay entregas.';

$string['noinstances']       = 'No hay actividades AI Viva en este curso.';

$string['notify_student']   = 'Notificar al estudiante cuando se publique la nota';

$string['openai_api_error']         = 'Error del servicio de IA: {$a}';

$string['openai_model_eval']     = 'Modelo IA para evaluación final';

$string['openai_model_pdf']      = 'Modelo IA para análisis de PDF';

$string['openai_model_tribunal'] = 'Modelo IA para el tribunal';

$string['override_add']            = 'Añadir excepción';

$string['override_confirm_delete'] = '¿Estás seguro de que quieres eliminar esta excepción?';

$string['override_delete']         = 'Eliminar excepción';

$string['override_deleted']        = 'Excepción eliminada.';

$string['override_edit']           = 'Editar excepción';

$string['override_group']          = 'Grupo';

$string['override_maxattempts']    = 'Número máximo de intentos';

$string['override_saved']          = 'Excepción guardada.';

$string['override_timeclose']      = 'Cierre';

$string['override_timeopen']       = 'Apertura';

$string['override_type']           = 'Tipo de excepción';

$string['override_type_group']     = 'Excepción de grupo';

$string['override_type_user']      = 'Excepción de usuario';

$string['override_user']           = 'Usuario';

$string['overrides_heading']       = 'Excepciones de usuario/grupo';

$string['pdf_analysis_done']      = '✅ Análisis completado. ¡Tu documento está listo!';

$string['pdf_dropzone_label']     = 'Arrastra tu PDF aquí o haz clic para buscar';

$string['pdf_selected']           = 'Seleccionado: {$a->name} ({$a->size})';

$string['pdf_uploaded_analysing'] = '✅ Documento recibido. La IA está analizando tu trabajo…';

$string['pluginadministration'] = 'Administración de AI Viva';

$string['pluginname']        = 'AI Viva';

$string['privacy:metadata:aiviva_submissions']                     = 'Información sobre cada entrega del estudiante.';

$string['privacy:metadata:aiviva_submissions:final_feedback']      = 'El texto de retroalimentación final.';

$string['privacy:metadata:aiviva_submissions:final_grade']         = 'La calificación final otorgada al estudiante.';

$string['privacy:metadata:aiviva_submissions:gdpr_consent']        = 'Si el estudiante dio su consentimiento RGPD.';

$string['privacy:metadata:aiviva_submissions:gdpr_consent_time']   = 'Cuándo el estudiante dio su consentimiento RGPD.';

$string['privacy:metadata:aiviva_submissions:pdf_analysis']        = 'Análisis IA del PDF del estudiante.';

$string['privacy:metadata:aiviva_submissions:status']              = 'Estado actual de la entrega.';

$string['privacy:metadata:aiviva_submissions:timecreated']         = 'Cuándo se creó la entrega.';

$string['privacy:metadata:aiviva_submissions:timesubmitted']       = 'Cuándo se completó la entrega.';

$string['privacy:metadata:aiviva_submissions:tribunal_transcript'] = 'Transcripción completa de la sesión del tribunal.';

$string['privacy:metadata:aiviva_submissions:userid']              = 'ID del estudiante que realizó la entrega.';

$string['privacy:metadata:aiviva_submissions:video_analysis']      = 'Análisis IA de la presentación en video.';

$string['privacy:metadata:aiviva_submissions:video_transcript']    = 'Transcripción Whisper de la presentación en video.';

$string['privacy:metadata:aiviva_tribunal_messages']               = 'Registro detallado de cada turno en la sesión del tribunal.';

$string['privacy:metadata:aiviva_tribunal_messages:message_text']  = 'El texto de lo que se dijo.';

$string['privacy:metadata:aiviva_tribunal_messages:speaker']       = 'Quién habló en este turno.';

$string['privacy:metadata:aiviva_tribunal_messages:timestamp']     = 'Cuándo ocurrió este turno.';

$string['privacy:metadata:core_files']                            = 'Los envíos en PDF, grabaciones de pantalla y respuestas de audio del tribunal se almacenan en el sistema de ficheros de Moodle.';

$string['privacy:metadata:openai']                                 = 'El contenido se envía a la API de OpenAI para análisis. Los nombres de los estudiantes se anonimizan antes del envío.';

$string['privacy:metadata:openai:anonymised_content']              = 'Contenido del documento o presentación con el nombre del estudiante anonimizado.';

$string['privacy:metadata:openai:audio_transcript']                = 'Transcripción del audio hablado del estudiante.';

$string['privacy:metadata:openai:conversation_turns']              = 'Texto de las respuestas habladas del estudiante durante el tribunal.';

$string['privacy:metadata:openai:video_frames']                    = 'Fotogramas extraídos de la grabación de pantalla del estudiante.';

$string['publish_grade']       = 'Publicar calificación';

$string['push_to_talk']           = 'Mantén pulsado para responder';

$string['rate_limit_exceeded']      = 'Has realizado demasiadas solicitudes. Por favor, espera un momento.';

$string['recording_started']      = '🔴 Grabando — ¡comienza tu presentación!';

$string['recording_time_up']      = '⏱ Tiempo finalizado. Guardando tu presentación…';

$string['regen_all']        = 'Re-analizar todo';

$string['regen_confirm']    = '¿Seguro? Esto reemplazará el análisis actual por uno nuevo. Puede tardar varios minutos.';

$string['regen_cooldown']          = 'Por favor, espera antes de volver a regenerar. Esta operación tiene un período de espera para evitar un uso excesivo de la API.';

$string['regen_evaluation'] = 'Recalcular evaluación final';

$string['regen_heading']    = 'Regenerar análisis IA';

$string['regen_pdf']        = 'Re-analizar PDF (Paso 1)';

$string['regen_running']    = 'Procesando… por favor espera (puede tardar 1–3 minutos)';

$string['regen_success']    = '¡Completado! Recargando…';

$string['regen_video']      = 'Re-analizar vídeo (Paso 2)';

$string['results_title']         = 'Tus resultados';

$string['retry_recording']        = 'Volver a grabar';

$string['return_to_student']   = 'Devolver para revisión';

$string['safety_extra_prompt'] = 'Restricciones de contenido adicionales (opcional)';

$string['security_header']     = 'Seguridad de la actividad';

$string['settings_advanced_heading']         = 'Avanzado';

$string['settings_anonymize_desc']           = 'Los nombres reales de los estudiantes se reemplazan <strong>siempre</strong> por un hash SHA-256 antes de enviarlos a OpenAI.';

$string['settings_anonymize_heading']        = 'Anonimización de estudiantes';

$string['settings_anonymize_salt']           = 'Salt de anonimización';

$string['settings_anonymize_salt_desc']      = 'Cadena aleatoria añadida al hash para mayor seguridad.';

$string['settings_api_rate_limit']           = 'Máximo de llamadas a la API por usuario por minuto';

$string['settings_api_rate_limit_desc']      = 'Límite de velocidad por usuario de Moodle.';

$string['settings_api_timeout']              = 'Tiempo de espera de la API (segundos)';

$string['settings_api_timeout_desc']         = 'Tiempo máximo para esperar una respuesta de OpenAI.';

$string['settings_apikeys_heading']          = 'Claves API de OpenAI';

$string['settings_apikeys_heading_desc']     = 'Estas claves se almacenan cifradas.';

$string['settings_cost_estimate_desc']       = 'Coste estimado por sesión completa de estudiante:<br/>GPT-4o: ~$0,15–$0,40 USD | GPT-4o mini: ~$0,03–$0,08 USD';

$string['settings_cost_estimate_heading']    = 'Estimación de costes';

$string['settings_disk_warning_threshold']   = 'Umbral de advertencia de espacio en disco (GB)';

$string['settings_disk_warning_threshold_desc'] = 'Mostrar advertencia de administrador cuando el espacio libre caiga por debajo de este valor.';

$string['settings_enable_gpt4o']             = 'Habilitar GPT-4o';

$string['settings_enable_gpt4o_desc']        = 'GPT-4o — mayor calidad, mayor coste.';

$string['settings_enable_gpt4o_mini']        = 'Habilitar GPT-4o mini';

$string['settings_enable_gpt4o_mini_desc']   = 'GPT-4o mini — buena calidad, menor coste.';

$string['settings_ffmpeg_path']              = 'Ruta del binario FFmpeg';

$string['settings_ffmpeg_path_desc']         = 'Ruta absoluta al binario FFmpeg para extracción de fotogramas (p. ej. /usr/bin/ffmpeg). Dejar en blanco para usar sólo fotogramas del cliente. Debe ser ruta absoluta — las rutas relativas se rechazan por seguridad.';

$string['settings_gdpr_heading']             = 'Aviso RGPD';

$string['settings_gdpr_heading_desc']        = 'Este aviso se muestra a los estudiantes antes de comenzar. Deben aceptarlo para continuar.';

$string['settings_gdpr_notice_text']         = 'Texto del aviso RGPD';

$string['settings_gdpr_notice_text_desc']    = 'Texto HTML mostrado a los estudiantes.';

$string['settings_global_max_video_size']    = 'Tamaño máximo global de video (MB)';

$string['settings_global_max_video_size_desc'] = 'Las actividades individuales no pueden superar este límite.';

$string['settings_models_heading']           = 'Modelos de IA disponibles';

$string['settings_models_heading_desc']      = 'Selecciona qué modelos pueden elegir los profesores.';

$string['settings_openai_apikey']            = 'Clave API principal de OpenAI';

$string['settings_openai_apikey_desc']       = 'Tu clave API de OpenAI.';

$string['settings_openai_apikey_secondary']  = 'Clave API secundaria de OpenAI (opcional)';

$string['settings_openai_apikey_secondary_desc'] = 'Si se configura, las llamadas a Whisper y TTS usarán esta clave.';

$string['settings_safety_content_filter']    = 'Activar moderación de contenido de OpenAI';

$string['settings_safety_content_filter_desc'] = 'Pasa todo el contenido del usuario por la API de Moderación de OpenAI antes de enviarlo a GPT.';

$string['settings_safety_max_tokens']        = 'Máximo de tokens por llamada a la API';

$string['settings_safety_max_tokens_desc']   = 'Límite de tokens de salida para todas las llamadas.';

$string['settings_security_heading']         = 'Seguridad';

$string['settings_security_heading_desc']    = 'Filtros de seguridad aplicados a todas las llamadas a la IA.';

$string['settings_servertools_heading']       = 'Herramientas del servidor';

$string['settings_servertools_heading_desc']  = 'Binarios opcionales del servidor para mejorar el procesamiento de vídeo.';

$string['settings_storage_heading']          = 'Almacenamiento y retención';

$string['settings_storage_heading_desc']     = 'Configura los límites de almacenamiento y la purga automática.';

$string['settings_video_purge_days']         = 'Período de retención de video predeterminado (días)';

$string['settings_video_purge_days_desc']    = 'Los videos y audios más antiguos se eliminan automáticamente. 0 = desactivado.';

$string['start_activity']  = 'Estoy listo para comenzar';

$string['start_recording']        = 'Iniciar grabación de pantalla';

$string['step1_description'] = 'Instrucciones para el estudiante';

$string['step1_header']      = 'Paso 1 — Documento PDF';

$string['step1_prompt']      = 'Prompt de análisis IA';

$string['step1_prompt_help'] = 'Instrucción enviada a la IA para analizar el PDF del estudiante. El nombre se anonimiza automáticamente.';

$string['step1_title']       = 'Documento PDF';

$string['step2_description'] = 'Instrucciones para el estudiante';

$string['step2_duration']    = 'Duración máxima de la presentación';

$string['step2_header']      = 'Paso 2 — Videopresentación';

$string['step2_maxfilesize'] = 'Tamaño máximo del archivo de video (MB)';

$string['step2_prompt']      = 'Prompt de análisis de video IA';

$string['step2_title']       = 'Videopresentación';

$string['step3_duration']    = 'Duración de la sesión del tribunal (minutos)';

$string['step3_header']      = 'Paso 3 — Tribunal de Defensa';

$string['step3_prompt_eval'] = 'Prompt de evaluación final';

$string['step3_title']       = 'Tribunal de Defensa';

$string['stop_recording']         = 'Finalizar presentación';

$string['submission']            = 'Entrega';

$string['submission_deleted']        = 'Entrega eliminada.';

$string['submissionnotification_body']    = <<<'EOT'
El estudiante ({$a->studentname}) ha completado '{$a->activityname}' en '{$a->coursename}' y su entrega está lista para revisión.

Ver entregas: {$a->link}
EOT;

$string['submissionnotification_bodyhtml'] = '<p>El estudiante <strong>{$a->studentname}</strong> ha completado <em>{$a->activityname}</em> y su entrega está lista para revisión.</p><p><a href="{$a->link}">Ver entregas</a></p>';

$string['submissionnotification_small']   = 'Nueva entrega: {$a->activityname}';

$string['submissionnotification_subject'] = 'Nueva entrega para revisar: {$a->activityname}';

$string['submissions_heading'] = 'Entregas';

$string['submit_video']           = 'Enviar presentación';

$string['task_analyze_pdf']          = 'AI Viva: Analizar PDF enviado';

$string['task_analyze_video']        = 'AI Viva: Analizar videopresentación';

$string['task_evaluate_submission']  = 'AI Viva: Generar evaluación final';

$string['task_purge_old_files']      = 'AI Viva: Purgar archivos de video/audio antiguos';

$string['tribunal_ending']        = 'La sesión está finalizando…';

$string['tribunal_finished']      = 'Sesión del tribunal finalizada. Preparando tu evaluación…';

$string['tribunal_loading']       = 'Conectando con el tribunal…';

$string['tribunal_log']          = 'Registro del tribunal';

$string['tribunal_member_avatar']        = 'Avatar';

$string['tribunal_member_avatar_custom'] = 'Subir imagen de avatar personalizado';

$string['tribunal_member_header']        = 'Miembro del tribunal {$a}';

$string['tribunal_member_name']          = 'Nombre';

$string['tribunal_member_prompt']        = 'Personalidad y estilo de interrogación';

$string['tribunal_member_role']          = 'Rol / Cargo';

$string['tribunal_member_voice']         = 'Voz TTS';

$string['tribunal_ready_notice']  = 'Estás a punto de iniciar tu sesión de defensa oral. Una vez que pulses el botón, el temporizador arrancará y el tribunal comenzará a hacerte preguntas. No podrás retroceder ni pausar la sesión.';

$string['tribunal_ready_title']   = 'Sala de Defensa — ¿Listo para comenzar?';

$string['tribunal_room_title']    = 'Sala de Defensa — {$a}';

$string['tribunal_start_btn']     = 'Iniciar sesión del tribunal';

$string['tribunal_thinking']      = 'El tribunal está deliberando…';

$string['unlimited']      = 'Ilimitado';

$string['upload_pdf']             = 'Subir documento';

$string['uploading_video']        = 'Subiendo tu grabación…';

$string['video_analysis_done']    = '✅ Presentación analizada. ¡Lista para el tribunal!';

$string['video_uploaded_analysing'] = '✅ Grabación subida. Analizando tu presentación…';

$string['voice_alloy']   = 'Alloy — versátil, neutro';

$string['voice_echo']    = 'Echo — resonante, masculino';

$string['voice_fable']   = 'Fable — expresivo, acento británico';

$string['voice_nova']    = 'Nova — cálido, femenino';

$string['voice_onyx']    = 'Onyx — grave, autoritario';

$string['voice_shimmer'] = 'Shimmer — suave, claro';

$string['warning_1min']           = '⚠️ Queda 1 minuto';

$string['warning_2min']           = '⚠️ Quedan 2 minutos';

$string['workflow_inreview']   = 'En revisión';

$string['workflow_readyforrelease'] = 'Listo para publicar';

$string['workflow_released']   = 'Publicado';

$string['your_grade']            = 'Tu calificación:';
