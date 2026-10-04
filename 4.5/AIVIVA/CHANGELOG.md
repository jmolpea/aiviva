# Changelog — mod_aiviva

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses [Semantic Versioning](https://semver.org/).

---

## [1.2.0] — 2026-10-04 (version 2026100302)

### Compatibility

- Supported on Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 (tested against the 5.3.0 release).

### Fixed

- Saving a user or group override failed with a "record not found" error.
- The poster image of the first built-in avatar was missing on case-sensitive servers (file name in mixed case).
- An examiner's audio that never arrived left the student waiting while the session clock ran. It is now requested again after eight seconds and, failing that, the question is shown as text; audio that stalls midway no longer blocks the session.

### Evaluation

- The presentation audio is recorded and transcribed in consecutive four-minute parts, so long presentations are transcribed in full instead of being rejected or cut short by the speech-to-text service.
- The presentation transcript is kept even if the analysis that follows it fails.
- AI answers that stop at the output limit are detected, retried with more room, and never stored incomplete.
- The models now receive the assignment itself (activity description and the instructions of each step), and the evaluator also receives the teacher's criteria for the document and the presentation.
- An analysis that is missing when the evaluation runs is attempted again first. If part of the student's work still could not be read, the grade is held for teacher review instead of being released automatically.

### Security and privacy

- OpenAI API keys are stored encrypted; keys saved by earlier versions are encrypted on upgrade.
- Regenerating an attempt and downloading its files now honour separate groups, like the submissions page.
- The unused per-activity API key column has been removed.

## [1.1.0] — 2026-10-02 (version 2026100202)

### Tribunal fluency

- Examiner speech is streamed: it starts playing about a second after it is requested instead of after the whole clip is synthesised.
- The student's answer is shown as soon as it has been transcribed, with the next examiner marked as "thinking", instead of nothing until the reply is ready.
- The examiners' briefing and opening words are prepared before the student presses start (after the presentation analysis, or while on the ready screen), so the session begins at once.
- The content filter runs once on each new answer rather than on the whole conversation at every turn.

### Other

- Standard monochrome activity icon (`pix/monologo.svg`).
- Status and error messages were hidden by a style rule; the selected PDF is now shown inside the drop zone.
- Tribunal answers were rejected by the transcription service; evaluation from cron failed to notify; a regeneration in progress could overwrite a grade published meanwhile.
- On upgrade, limits saved with the old lower defaults are raised and the old pre-filled privacy notice is cleared.


### Security and integrity

- Every student action checks the attempt's state: a document or recording cannot be replaced once analysed, and nothing can be changed after submission.
- The tribunal clock, turn order and transcript are owned by the server. Reloading the page resumes the same session; it no longer restarts the clock.
- Tribunal answers are recorded and transcribed on the server, and the recordings are kept for the teacher. The browser's speech recognition is no longer used.
- Sessions abandoned by the student are closed and evaluated by a scheduled task.
- AI-generated values are escaped wherever they are shown; manual grades are validated against the maximum grade.
- Teachers restricted to their own groups only see their groups' attempts.

### Evaluation

- The evaluator receives the original PDF and all evidence in full; nothing is truncated.
- Per-step weights are configurable; the final grade is computed by the plugin, not by the model.
- Analyses and feedback are written in the student's language, also when produced by cron or a teacher.

### Grading

- Draft, publish and withdraw now behave as expected; edits made before publishing are kept.
- The AI's proposed grade is stored separately from the final grade; regenerating never overwrites a teacher's edit nor notifies the student again.

### Now functional

- Multiple attempts (best released grade counts), user and group overrides, opening and closing dates, custom avatars, the "complete all three steps" completion rule, the extra safety prompt, the per-activity retention period, and a PDF size limit of its own.
- Screenshots of the presentation are captured and analysed; a separate audio track keeps long recordings transcribable.

### Privacy

- The privacy notice, the admin texts and the Privacy API metadata now state exactly what is sent to OpenAI.
- Student pseudonyms use a secret generated per site.
- Data export includes analyses, transcripts and all files; recordings of abandoned attempts are purged too.

### Removed

- Group submission, the disk-space warning, the FFmpeg path and the anonymisation salt settings (none had any effect), and the use of `shell_exec`/`exec`.

### Added

- PHPUnit and Behat tests.

### Changed

- Model catalogue updated to GPT-6.1 Sol (default), GPT-6 Astra and GPT-6 Luna; activities still set to GPT-4o / GPT-4o mini are migrated on upgrade.
- Transcription now uses `gpt-transcribe`; tribunal voices use `gpt-4o-mini-tts` with 13 voices.
- The two per-model admin checkboxes are replaced by a single "Models available to teachers" setting.
- Requests send `max_completion_tokens` and a low reasoning effort, as required by the current models.

### Fixed

- The OpenAI API key entered in the admin settings was discarded (treated as a failed decryption), so no API call could authenticate.
- PDF analysis via the Responses API read the wrong output item for reasoning models and always fell back to raw text extraction.

## [1.0.0] — 2024-03-22

### Added

- **Core activity structure**: Moodle 4.5 compliant activity module with full Plugin API integration.
- **Three-step examination workflow**:
  - Step 1: PDF document upload with GPT-4o analysis.
  - Step 2: Screen-recorded video presentation with Whisper transcription and GPT-4o Vision analysis.
  - Step 3: Live AI viva tribunal with 3 configurable AI examiners, OpenAI TTS voices, and Web Speech API push-to-talk.
- **AI integration**:
  - Centralised `openai_client` singleton with retry/backoff, rate limiting, and optional content moderation.
  - `pdf_analyzer`, `video_analyzer`, `tribunal_conductor`, and `evaluator` classes.
  - Support for GPT-4o and GPT-4o mini.
  - Whisper transcription with optional FFmpeg server-side frame extraction.
  - OpenAI TTS with 6 voice options (alloy, echo, fable, onyx, nova, shimmer).
- **Student anonymisation**: SHA-256 hashed student IDs in all API prompts — real names never sent to OpenAI.
- **GDPR compliance**: Full `core_privacy\local\metadata\provider` implementation with data export and deletion.
- **Grading**: Moodle Gradebook integration, AI-generated structured feedback with per-step breakdown, optional teacher review workflow.
- **Notifications**: Student grade release notification, teacher submission review notification.
- **Automated file purge**: Scheduled task purges video/audio files older than the configured retention period.
- **Backup/Restore**: Full Moodle Course Backup 2 compatibility (API keys excluded for security).
- **Internationalisation**: Complete string files for English (`en`), Spanish (`es`), and Brazilian Portuguese (`pt_br`).
- **Responsive UI**: Animated stepper, immersive tribunal room with avatar lip-sync animation, countdown timer, REC indicator.
- **3 SVG avatars**: Neutral, feminine, and masculine professional academic avatars with lip-sync CSS hooks.
- **Global admin settings**: API keys, model selection, cost estimates, security filters, storage limits, GDPR notice customisation.
- **Capabilities**: `view`, `submit`, `grade`, `viewallsubmissions`, `manageplugin`.
- **Activity completion rules**: Submit, receive grade, achieve minimum grade.
- **Security**: CSRF protection, MIME validation, server-side size checks, capability checks on all endpoints.
