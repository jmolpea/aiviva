# Changelog — mod_aiviva

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project uses [Semantic Versioning](https://semver.org/).

---

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
