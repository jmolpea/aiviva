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
 * Brazilian Portuguese language strings for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activityname']   = 'Nome da atividade';

$string['aiviva:addinstance']        = 'Adicionar uma atividade AI Viva';

$string['aiviva:grade']              = 'Avaliar envios';

$string['aiviva:manageoverrides']    = 'Gerenciar substituições de usuário e grupo';

$string['aiviva:manageplugin']       = 'Gerenciar configurações do plugin';

$string['aiviva:submit']             = 'Enviar uma apresentação';

$string['aiviva:view']               = 'Visualizar a atividade AI Viva';

$string['aiviva:viewallsubmissions'] = 'Visualizar todos os envios';

$string['attemptsinfo']    = 'Tentativas usadas: {$a->used} / {$a->max} ({$a->remaining} restantes)';

$string['avatar_1']      = 'Avatar 1 (neutro)';

$string['avatar_2']      = 'Avatar 2 (feminino)';

$string['avatar_3']      = 'Avatar 3 (masculino)';

$string['avatar_custom'] = 'Imagem personalizada';

$string['backup_files']       = 'Incluir arquivos de vídeo/áudio (pode ser grande)';

$string['backup_settings']    = 'Incluir configurações da atividade AI Viva';

$string['backup_submissions'] = 'Incluir envios de estudantes';

$string['col_actions']          = 'Ações';

$string['col_grade']            = 'Nota';

$string['col_status']           = 'Status';

$string['col_student']          = 'Estudante';

$string['col_submitted']        = 'Enviado em';

$string['col_workflow']         = 'Status de revisão';

$string['completiongrade']  = 'O estudante deve receber uma nota';

$string['completionsubmit'] = 'O estudante deve enviar a atividade';

$string['confirm_delete_submission'] = 'Tem certeza de que deseja excluir este envio? Esta ação não pode ser desfeita.';

$string['confirm_pdf_upload']     = 'Confirma que seu documento está pronto? Uma vez enviado, não poderá ser alterado nesta tentativa.';

$string['confirm_video_submit']   = 'Enviar sua gravação? Esta tentativa será definitiva.';

$string['content_flagged']          = 'O conteúdo foi sinalizado pelo filtro de segurança de IA.';

$string['continue_to_step2']     = 'Continuar para a Etapa 2 →';

$string['continue_to_step3']     = 'Continuar para a Etapa 3 →';

$string['conversation_log']       = 'Registro da sessão';

$string['delete_submission']         = 'Excluir envio';

$string['error_analysis_timeout']   = 'A análise está demorando mais que o esperado. Atualize a página para verificar o progresso.';

$string['error_duration_invalid']   = 'A duração deve ser de pelo menos 1 minuto.';

$string['error_file_too_large']     = 'O arquivo excede o tamanho máximo de {$a} MB.';

$string['error_maxfilesize_exceeds_global'] = 'Não pode exceder o máximo global de {$a} MB definido pelo administrador.';

$string['error_maxfilesize_toosmall'] = 'O tamanho máximo deve ser de pelo menos 1 MB.';

$string['error_not_pdf']            = 'Apenas arquivos PDF são aceitos.';

$string['error_screen_permission']  = 'Permissão de gravação de tela negada. Por favor, autorize a captura de tela e tente novamente.';

$string['error_video_too_large']    = 'O vídeo excede o tamanho máximo de {$a} MB.';

$string['evaluation_complete']    = '✅ Avaliação concluída. Redirecionando…';

$string['evaluation_pending']     = 'A banca de IA está avaliando seu desempenho. Isso pode levar um momento…';

$string['evaluator_invalid_response'] = 'O avaliador de IA retornou uma resposta inválida. Contate seu professor.';

$string['event_assessment_completed'] = 'Avaliação por IA concluída';

$string['event_grade_issued']         = 'Nota emitida';

$string['event_submission_created']   = 'Envio criado';

$string['feedback']              = 'Feedback';

$string['gdpr_consent_label']  = 'Entendo e concordo que meu PDF, vídeo e áudio serão processados pela API da OpenAI.';

$string['gdpr_consent_required']   = 'Você deve fornecer consentimento LGPD na página da atividade antes de enviar arquivos.';

$string['gdpr_default_notice'] = '<p>Para concluir esta atividade, seu documento PDF, gravação de tela e respostas faladas serão enviados à <strong>API da OpenAI</strong> para análise e avaliação.</p><p>Seu nome pessoal será substituído por um identificador anônimo antes de qualquer envio de dados. Os dados não são retidos pela OpenAI além da requisição imediata. Os arquivos são excluídos automaticamente deste servidor após {$a} dias.</p><p>Ao prosseguir, você consente com esse processamento de acordo com nossa política de privacidade.</p>';

$string['gdpr_notice_title']   = 'Aviso de privacidade — Processamento por IA';

$string['grade_override_saved'] = 'Nota salva com sucesso.';

$string['grade_pending_review']  = 'Sua nota está sendo revisada pelo(a) professor(a). Você será notificado(a) quando for publicada.';

$string['gradenotification_body']     = <<<'EOT'
Sua nota para '{$a->activityname}' em '{$a->coursename}' foi publicada.

Nota: {$a->grade}

Ver resultados: {$a->link}
EOT;

$string['gradenotification_bodyhtml'] = '<p>Sua nota para <strong>{$a->activityname}</strong> em <em>{$a->coursename}</em> foi publicada.</p><p>Nota: <strong>{$a->grade}</strong></p><p><a href="{$a->link}">Ver resultados</a></p>';

$string['gradenotification_small']    = 'Nota publicada: {$a->activityname}';

$string['gradenotification_subject']  = 'Sua nota está disponível: {$a->activityname}';

$string['grading_header']   = 'Avaliação e fluxo de trabalho';

$string['grading_workflow'] = 'Ativar revisão do professor antes de publicar';

$string['groupsubmission'] = 'Envio em grupo';

$string['groupsubmission_help'] = 'Permite que grupos enviem juntos. Requer grupos configurados no curso.';

$string['invalidsubmissionstatus'] = 'Esta ação não é permitida no estado atual do envio.';

$string['maxattempts']    = 'Número máximo de tentativas';

$string['maxattempts_help'] = 'Número máximo de vezes que um estudante pode tentar esta atividade. 0 = ilimitado.';

$string['maximumgrade']     = 'Nota máxima';

$string['model_economical']      = '(econômico)';

$string['model_recommended']     = '(recomendado)';

$string['modulename']        = 'AI Viva';

$string['modulenameplural']  = 'AI Vivas';

$string['no_overrides_yet']        = 'Nenhuma substituição foi configurada.';

$string['no_submissions_yet']   = 'Ainda não há envios.';

$string['noinstances']       = 'Nenhuma atividade AI Viva neste curso.';

$string['notify_student']   = 'Notificar o estudante quando a nota for publicada';

$string['openai_api_error']         = 'Erro do serviço de IA: {$a}';

$string['openai_model_eval']     = 'Modelo de IA para avaliação final';

$string['openai_model_pdf']      = 'Modelo de IA para análise de PDF';

$string['openai_model_tribunal'] = 'Modelo de IA para a banca';

$string['override_add']            = 'Adicionar substituição';

$string['override_confirm_delete'] = 'Tem certeza de que deseja excluir esta substituição?';

$string['override_delete']         = 'Excluir substituição';

$string['override_deleted']        = 'Substituição excluída.';

$string['override_edit']           = 'Editar substituição';

$string['override_group']          = 'Grupo';

$string['override_maxattempts']    = 'Número máximo de tentativas';

$string['override_saved']          = 'Substituição salva.';

$string['override_timeclose']      = 'Fechamento';

$string['override_timeopen']       = 'Abertura';

$string['override_type']           = 'Tipo de substituição';

$string['override_type_group']     = 'Substituição de grupo';

$string['override_type_user']      = 'Substituição de usuário';

$string['override_user']           = 'Usuário';

$string['overrides_heading']       = 'Substituições de usuário/grupo';

$string['pdf_analysis_done']      = '✅ Análise concluída. Seu documento está pronto!';

$string['pdf_dropzone_label']     = 'Arraste seu PDF aqui ou clique para selecionar';

$string['pdf_selected']           = 'Selecionado: {$a->name} ({$a->size})';

$string['pdf_uploaded_analysing'] = '✅ Documento recebido. A IA está analisando seu trabalho…';

$string['pluginadministration'] = 'Administração do AI Viva';

$string['pluginname']        = 'AI Viva';

$string['privacy:metadata:aiviva_submissions']                     = 'Informações sobre cada envio do estudante.';

$string['privacy:metadata:aiviva_submissions:final_feedback']      = 'O texto de feedback final fornecido ao estudante.';

$string['privacy:metadata:aiviva_submissions:final_grade']         = 'A nota final atribuída ao estudante.';

$string['privacy:metadata:aiviva_submissions:gdpr_consent']        = 'Se o estudante deu consentimento LGPD/GDPR.';

$string['privacy:metadata:aiviva_submissions:gdpr_consent_time']   = 'Quando o estudante deu o consentimento.';

$string['privacy:metadata:aiviva_submissions:pdf_analysis']        = 'Análise de IA do PDF do estudante.';

$string['privacy:metadata:aiviva_submissions:status']              = 'Status atual do envio.';

$string['privacy:metadata:aiviva_submissions:timecreated']         = 'Quando o envio foi criado.';

$string['privacy:metadata:aiviva_submissions:timesubmitted']       = 'Quando o envio foi concluído.';

$string['privacy:metadata:aiviva_submissions:tribunal_transcript'] = 'Transcrição completa da sessão da banca.';

$string['privacy:metadata:aiviva_submissions:userid']              = 'ID do estudante que fez o envio.';

$string['privacy:metadata:aiviva_submissions:video_analysis']      = 'Análise de IA da apresentação em vídeo.';

$string['privacy:metadata:aiviva_submissions:video_transcript']    = 'Transcrição Whisper da apresentação em vídeo.';

$string['privacy:metadata:aiviva_tribunal_messages']               = 'Registro detalhado de cada turno na sessão da banca.';

$string['privacy:metadata:aiviva_tribunal_messages:message_text']  = 'O texto do que foi dito.';

$string['privacy:metadata:aiviva_tribunal_messages:speaker']       = 'Quem falou neste turno.';

$string['privacy:metadata:aiviva_tribunal_messages:timestamp']     = 'Quando este turno ocorreu.';

$string['privacy:metadata:core_files']                            = 'Envios em PDF, gravações de tela e respostas de áudio do tribunal são armazenados no sistema de arquivos do Moodle.';

$string['privacy:metadata:openai']                                 = 'O conteúdo é enviado à API da OpenAI para análise. Os nomes dos estudantes são anonimizados antes do envio.';

$string['privacy:metadata:openai:anonymised_content']              = 'Conteúdo do documento ou apresentação com o nome do estudante anonimizado.';

$string['privacy:metadata:openai:audio_transcript']                = 'Transcrição do áudio falado pelo estudante.';

$string['privacy:metadata:openai:conversation_turns']              = 'Texto das respostas faladas do estudante durante a banca.';

$string['privacy:metadata:openai:video_frames']                    = 'Quadros extraídos da gravação de tela do estudante.';

$string['publish_grade']        = 'Publicar nota';

$string['push_to_talk']           = 'Segure para responder';

$string['rate_limit_exceeded']      = 'Você fez muitas requisições. Por favor, aguarde um momento.';

$string['recording_started']      = '🔴 Gravando — comece sua apresentação!';

$string['recording_time_up']      = '⏱ Tempo esgotado. Salvando sua apresentação…';

$string['regen_all']        = 'Re-analisar tudo';

$string['regen_confirm']    = 'Isso substituirá a análise atual por uma nova. Pode levar vários minutos. Continuar?';

$string['regen_cooldown']          = 'Por favor, aguarde antes de regenerar novamente. Esta operação tem um período de espera para evitar uso excessivo da API.';

$string['regen_evaluation'] = 'Recalcular avaliação final';

$string['regen_heading']    = 'Regenerar análise IA';

$string['regen_pdf']        = 'Re-analisar PDF (Passo 1)';

$string['regen_running']    = 'Processando… aguarde (pode levar 1–3 minutos)';

$string['regen_success']    = 'Concluído! Recarregando…';

$string['regen_video']      = 'Re-analisar vídeo (Passo 2)';

$string['results_title']         = 'Seus resultados';

$string['retry_recording']        = 'Gravar novamente';

$string['return_to_student']    = 'Devolver para revisão';

$string['safety_extra_prompt'] = 'Restrições de conteúdo adicionais (opcional)';

$string['security_header']     = 'Segurança da atividade';

$string['settings_advanced_heading']         = 'Avançado';

$string['settings_anonymize_desc']           = 'Os nomes reais dos estudantes são <strong>sempre</strong> substituídos por um hash SHA-256 antes de serem enviados à OpenAI.';

$string['settings_anonymize_heading']        = 'Anonimização de estudantes';

$string['settings_anonymize_salt']           = 'Salt de anonimização';

$string['settings_anonymize_salt_desc']      = 'String aleatória adicionada ao hash para maior segurança.';

$string['settings_api_rate_limit']           = 'Máximo de chamadas de API por usuário por minuto';

$string['settings_api_rate_limit_desc']      = 'Limite de taxa por usuário do Moodle.';

$string['settings_api_timeout']              = 'Tempo limite de requisição da API (segundos)';

$string['settings_api_timeout_desc']         = 'Tempo máximo para aguardar uma resposta da OpenAI.';

$string['settings_apikeys_heading']          = 'Chaves de API da OpenAI';

$string['settings_apikeys_heading_desc']     = 'Estas chaves são armazenadas de forma criptografada.';

$string['settings_cost_estimate_desc']       = 'Custo estimado por sessão completa de estudante:<br/>GPT-4o: ~$0,15–$0,40 USD | GPT-4o mini: ~$0,03–$0,08 USD';

$string['settings_cost_estimate_heading']    = 'Estimativas de custo';

$string['settings_disk_warning_threshold']   = 'Limite de aviso de espaço em disco (GB)';

$string['settings_disk_warning_threshold_desc'] = 'Exibir aviso de administrador quando o espaço livre cair abaixo deste valor.';

$string['settings_enable_gpt4o']             = 'Habilitar GPT-4o';

$string['settings_enable_gpt4o_desc']        = 'GPT-4o — maior qualidade, maior custo.';

$string['settings_enable_gpt4o_mini']        = 'Habilitar GPT-4o mini';

$string['settings_enable_gpt4o_mini_desc']   = 'GPT-4o mini — boa qualidade, menor custo.';

$string['settings_ffmpeg_path']              = 'Caminho do binário FFmpeg';

$string['settings_ffmpeg_path_desc']         = 'Caminho absoluto para o binário FFmpeg (ex.: /usr/bin/ffmpeg). Deixe em branco para usar apenas frames do cliente. Deve ser caminho absoluto — caminhos relativos são rejeitados por segurança.';

$string['settings_gdpr_heading']             = 'Aviso de privacidade (LGPD/GDPR)';

$string['settings_gdpr_heading_desc']        = 'Este aviso é exibido aos estudantes antes de começar. Eles devem aceitá-lo para prosseguir.';

$string['settings_gdpr_notice_text']         = 'Texto do aviso de privacidade';

$string['settings_gdpr_notice_text_desc']    = 'Texto HTML exibido aos estudantes.';

$string['settings_global_max_video_size']    = 'Tamanho máximo global de vídeo (MB)';

$string['settings_global_max_video_size_desc'] = 'Atividades individuais não podem exceder este limite.';

$string['settings_models_heading']           = 'Modelos de IA disponíveis';

$string['settings_models_heading_desc']      = 'Selecione quais modelos os professores podem escolher.';

$string['settings_openai_apikey']            = 'Chave de API principal da OpenAI';

$string['settings_openai_apikey_desc']       = 'Sua chave de API da OpenAI.';

$string['settings_openai_apikey_secondary']  = 'Chave de API secundária da OpenAI (opcional)';

$string['settings_openai_apikey_secondary_desc'] = 'Se configurada, as chamadas ao Whisper e TTS usarão esta chave.';

$string['settings_safety_content_filter']    = 'Ativar moderação de conteúdo da OpenAI';

$string['settings_safety_content_filter_desc'] = 'Passa todo o conteúdo do usuário pela API de Moderação da OpenAI antes de enviar ao GPT.';

$string['settings_safety_max_tokens']        = 'Máximo de tokens por chamada de API';

$string['settings_safety_max_tokens_desc']   = 'Limite de tokens de saída para todas as chamadas.';

$string['settings_security_heading']         = 'Segurança';

$string['settings_security_heading_desc']    = 'Filtros de segurança aplicados a todas as chamadas de IA.';

$string['settings_servertools_heading']       = 'Ferramentas do servidor';

$string['settings_servertools_heading_desc']  = 'Binários opcionais do servidor para melhorar o processamento de vídeo.';

$string['settings_storage_heading']          = 'Armazenamento e retenção';

$string['settings_storage_heading_desc']     = 'Configure os limites de armazenamento e a limpeza automática.';

$string['settings_video_purge_days']         = 'Período padrão de retenção de vídeo (dias)';

$string['settings_video_purge_days_desc']    = 'Vídeos e áudios mais antigos são excluídos automaticamente. 0 = desativado.';

$string['start_activity']  = 'Estou pronto para começar';

$string['start_recording']        = 'Iniciar gravação de tela';

$string['step1_description'] = 'Instruções para o estudante';

$string['step1_header']      = 'Etapa 1 — Documento PDF';

$string['step1_prompt']      = 'Prompt de análise por IA';

$string['step1_prompt_help'] = 'Instrução enviada à IA para analisar o PDF do estudante. O nome é anonimizado automaticamente.';

$string['step1_title']       = 'Documento PDF';

$string['step2_description'] = 'Instruções para o estudante';

$string['step2_duration']    = 'Duração máxima da apresentação';

$string['step2_header']      = 'Etapa 2 — Apresentação em Vídeo';

$string['step2_maxfilesize'] = 'Tamanho máximo do arquivo de vídeo (MB)';

$string['step2_prompt']      = 'Prompt de análise de vídeo por IA';

$string['step2_title']       = 'Apresentação em Vídeo';

$string['step3_duration']    = 'Duração da sessão da banca (minutos)';

$string['step3_header']      = 'Etapa 3 — Banca Examinadora';

$string['step3_prompt_eval'] = 'Prompt de avaliação final';

$string['step3_title']       = 'Banca Examinadora';

$string['stop_recording']         = 'Finalizar apresentação';

$string['submission']            = 'Envio';

$string['submission_deleted']        = 'Envio excluído.';

$string['submissionnotification_body']    = <<<'EOT'
O(a) estudante ({$a->studentname}) concluiu '{$a->activityname}' em '{$a->coursename}' e o envio está pronto para revisão.

Ver envios: {$a->link}
EOT;

$string['submissionnotification_bodyhtml'] = '<p>O(a) estudante <strong>{$a->studentname}</strong> concluiu <em>{$a->activityname}</em> e o envio está pronto para revisão.</p><p><a href="{$a->link}">Ver envios</a></p>';

$string['submissionnotification_small']   = 'Novo envio: {$a->activityname}';

$string['submissionnotification_subject'] = 'Novo envio para revisão: {$a->activityname}';

$string['submissions_heading']  = 'Envios';

$string['submit_video']           = 'Enviar apresentação';

$string['task_analyze_pdf']          = 'AI Viva: Analisar PDF enviado';

$string['task_analyze_video']        = 'AI Viva: Analisar apresentação em vídeo';

$string['task_evaluate_submission']  = 'AI Viva: Gerar avaliação final';

$string['task_purge_old_files']      = 'AI Viva: Remover arquivos de vídeo/áudio antigos';

$string['tribunal_ending']        = 'A sessão está encerrando…';

$string['tribunal_finished']      = 'Sessão encerrada. Preparando sua avaliação…';

$string['tribunal_loading']       = 'Conectando à banca examinadora…';

$string['tribunal_log']          = 'Registro da banca';

$string['tribunal_member_avatar']        = 'Avatar';

$string['tribunal_member_avatar_custom'] = 'Enviar imagem de avatar personalizada';

$string['tribunal_member_header']        = 'Membro da banca {$a}';

$string['tribunal_member_name']          = 'Nome';

$string['tribunal_member_prompt']        = 'Personalidade e estilo de interrogação';

$string['tribunal_member_role']          = 'Papel / Cargo';

$string['tribunal_member_voice']         = 'Voz TTS';

$string['tribunal_ready_notice']  = 'Você está prestes a iniciar sua sessão de defesa oral. Ao pressionar o botão, o cronômetro começará e a banca iniciará as perguntas. Não será possível voltar ou pausar a sessão.';

$string['tribunal_ready_title']   = 'Sala de Defesa — Pronto para começar?';

$string['tribunal_room_title']    = 'Sala de Defesa — {$a}';

$string['tribunal_start_btn']     = 'Iniciar sessão da banca';

$string['tribunal_thinking']      = 'A banca está deliberando…';

$string['unlimited']      = 'Ilimitado';

$string['upload_pdf']             = 'Enviar documento';

$string['uploading_video']        = 'Enviando sua gravação…';

$string['video_analysis_done']    = '✅ Apresentação analisada. Pronta para a banca!';

$string['video_uploaded_analysing'] = '✅ Gravação enviada. Analisando sua apresentação…';

$string['voice_alloy']   = 'Alloy — versátil, neutro';

$string['voice_echo']    = 'Echo — ressonante, masculino';

$string['voice_fable']   = 'Fable — expressivo, sotaque britânico';

$string['voice_nova']    = 'Nova — caloroso, feminino';

$string['voice_onyx']    = 'Onyx — grave, autoritário';

$string['voice_shimmer'] = 'Shimmer — suave, claro';

$string['warning_1min']           = '⚠️ Falta 1 minuto';

$string['warning_2min']           = '⚠️ Faltam 2 minutos';

$string['workflow_inreview']    = 'Em revisão';

$string['workflow_readyforrelease'] = 'Pronto para publicar';

$string['workflow_released']    = 'Publicado';

$string['your_grade']            = 'Sua nota:';
