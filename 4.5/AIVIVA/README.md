# AI Viva — Moodle Activity Plugin (`mod_aiviva`)

An AI-powered oral examination simulation plugin for Moodle 4.5 that guides students through a three-step academic defence: PDF document submission, screen-recorded presentation, and a live viva session with a virtual AI tribunal panel.

---

## Requirements

| Requirement | Minimum |
|---|---|
| Moodle | 4.5 (build 2024042200) |
| PHP | 8.1+ |
| Database | MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 13+ |
| Browser | Chrome 90+, Edge 90+, Firefox 85+ (screen capture requires HTTPS) |
| Server | HTTPS mandatory (Web Speech API and `getDisplayMedia` require a secure origin) |
| OpenAI API key | Required — GPT-4o, Whisper, TTS endpoints |
| FFmpeg (optional) | Recommended for server-side frame extraction from videos |
| Moodle cron | Must be running regularly (used for background AI processing) |

---

## Installation

### Method 1: Moodle Plugin Directory (recommended)

1. Download the plugin zip from the Moodle Plugin Directory.
2. In Moodle: **Site administration → Plugins → Install plugins**.
3. Upload the zip and follow the on-screen prompts.
4. Complete the database upgrade steps.

### Method 2: Manual installation

```bash
# Unzip into the Moodle mod directory
unzip mod_aiviva.zip -d /path/to/moodle/mod/aiviva

# Fix permissions (Linux)
chown -R www-data:www-data /path/to/moodle/mod/aiviva
chmod -R 755 /path/to/moodle/mod/aiviva
```

Then visit **Site administration → Notifications** to run the database installer.

---

## Post-Installation Configuration

1. **Configure the OpenAI API Key**
   Go to **Site administration → Plugins → Activity modules → AI Viva**.
   Enter your OpenAI API key in the "Primary OpenAI API Key" field.
   The key is stored encrypted using Moodle's built-in encryption.

2. **Select available AI models**
   Enable GPT-4o and/or GPT-4o mini based on your institution's budget.

3. **Set storage limits**
   Configure the maximum video file size and automatic purge schedule to manage disk usage.

4. **Customise the GDPR notice**
   Edit the privacy notice text to match your institution's data processing policies.

5. **Configure the anonymisation salt** (optional)
   A random salt is used when hashing student IDs before sending to OpenAI. You may want to set this to a long random string specific to your installation.

6. **Verify cron is running**
   AI analysis jobs run as Moodle adhoc tasks. Ensure `cron.php` or `cli/cron.php` runs at least every minute.

---

## Creating an Activity

1. In a course, **Add an activity → AI Viva**.
2. Configure the three steps:
   - **Step 1**: What PDF the student should submit and your analysis prompt.
   - **Step 2**: Presentation duration and video analysis prompt.
   - **Step 3**: Configure the three tribunal members (name, role, personality, voice, avatar).
3. Set grading options (maximum grade, workflow, notifications).
4. Save and return to course.

---

## Student Workflow

1. Student opens the activity and accepts the GDPR/privacy notice.
2. **Step 1**: Uploads their PDF document. The AI analyses it in the background.
3. **Step 2**: Records a screen presentation (10-second countdown, timer, auto-stop). The AI transcribes and analyses the video.
4. **Step 3**: Participates in a live viva with the three AI tribunal members via TTS speech and push-to-talk voice responses.
5. After the tribunal session ends, the AI generates a comprehensive grade and feedback.

---

## Teacher Workflow

1. Navigate to **AI Viva → Submissions** to see all student submissions.
2. Review each submission's PDF, video, and tribunal transcript.
3. If grading workflow is enabled:
   - Review the AI-generated grade and feedback.
   - Adjust if needed and click **Publish grade** to release it to the student.
4. If workflow is disabled, grades are published automatically.

---

## Browser Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---|---|---|---|---|
| Screen recording (`getDisplayMedia`) | ✅ | ✅ | ✅ 13+ | ✅ |
| Web Speech API (STT) | ✅ | ⚠️ Partial | ✅ | ✅ |
| Audio playback (TTS) | ✅ | ✅ | ✅ | ✅ |

> **Note**: Web Speech API recognition may not be available in Firefox. In that case, student responses are captured as audio and transcribed server-side via Whisper.

---

## Security Notes

- Student names are **never** sent to OpenAI. They are replaced with `STUDENT-<sha256hash>`.
- API keys are stored encrypted using `\core\encryption`.
- All file uploads are MIME-type and size validated on both client and server.
- CSRF protection (`sesskey`) is enforced on all write operations.
- Rate limiting prevents API abuse per user.

---

## Estimated OpenAI Costs (per student session)

| Component | GPT-4o | GPT-4o mini |
|---|---|---|
| PDF analysis | ~$0.05 | ~$0.01 |
| Video analysis (10 min) | ~$0.10–$0.20 | ~$0.02–$0.05 |
| Tribunal (10 min, ~8 turns) | ~$0.10–$0.15 | ~$0.02–$0.04 |
| Whisper transcription (20 min) | ~$0.02 | ~$0.02 |
| TTS (~8 responses, ~100 words each) | ~$0.02 | ~$0.02 |
| **Total per student** | **~$0.30–$0.45** | **~$0.07–$0.14** |

---

## Frequently Asked Questions

**Q: Can I use this without HTTPS?**
A: No. Screen recording (`getDisplayMedia`) and Web Speech API require a secure (HTTPS) origin. You must have a valid SSL certificate.

**Q: What happens to student data sent to OpenAI?**
A: Content is anonymised before sending (student name replaced with a hash). Per OpenAI's API terms, data is not used to train models. Files uploaded to the OpenAI Files API are deleted immediately after analysis.

**Q: The tribunal session is in English — can it be in another language?**
A: Yes. Set the tribunal member prompts in the desired language and configure the Web Speech API language attribute (`lang`) to match. The AI will respond in the language of your prompts.

**Q: Can I add more than 3 tribunal members?**
A: The current version supports exactly 3 members. This is a planned enhancement for a future release.

**Q: Videos are large — how do I manage disk space?**
A: Configure the **video retention period** in the global settings. Videos are automatically purged from the server after the specified number of days (default: 15). Database records and transcripts are retained.

**Q: Why does cron need to run every minute?**
A: AI analysis (PDF analysis, video analysis, evaluation) runs as background adhoc tasks to avoid browser timeouts. If cron runs infrequently, students may experience long waits between steps.

---

## License

GNU General Public License v3 or later — see [LICENSE](https://www.gnu.org/copyleft/gpl.html).

---

## Support & Bug Reports

Please report issues at the [Moodle Plugin Directory](https://moodle.org/plugins) tracker or open a GitHub issue.
