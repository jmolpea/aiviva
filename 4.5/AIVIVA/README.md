# AI Viva — Moodle Activity Plugin (`mod_aiviva`)

An AI-powered oral examination for Moodle 4.5 to 5.3. Students go through a three-step academic defence: they submit a PDF document, record a presentation of it, and then defend it live before a panel of three AI examiners who speak to them and listen to their spoken answers. The AI proposes a grade with feedback; a teacher can review it before it is released.

---

## Requirements

| Requirement | Minimum |
|---|---|
| Moodle | 4.5, 5.0, 5.1, 5.2 or 5.3 |
| PHP | 8.1+ (as required by your Moodle version) |
| Browser | Current Chrome, Edge, Firefox or Safari |
| Server | HTTPS (browsers only allow screen and microphone capture on a secure origin) |
| OpenAI API key | Required. Used for the language models, speech-to-text and text-to-speech |
| Moodle cron | Running every minute (finishes AI jobs and closes abandoned sessions) |

No server-side tools (FFmpeg, pdftotext) are needed.

---

## Installation

1. Unzip into `mod/aiviva` or install the zip from **Site administration → Plugins → Install plugins**.
2. Visit **Site administration → Notifications** to run the installer.
3. Go to **Site administration → Plugins → Activity modules → AI Viva** and:
   - enter the licence key (the plugin runs without one for a 15-day evaluation period);
   - enter your OpenAI API key;
   - choose which models teachers may select;
   - review the storage limits and, if you wish, write your own privacy notice.

---

## Models

| Use | Model |
|---|---|
| Document analysis, presentation analysis, tribunal, evaluation | GPT-6.1 Sol (default), GPT-6 Astra or GPT-6 Luna, chosen per activity and per step |
| Speech-to-text | `gpt-transcribe` |
| Examiner voices | `gpt-4o-mini-tts` (13 voices) |

Cost depends on the model and on the size of each student's work, because documents and transcripts are always sent in full. See the price list on the settings page and OpenAI's pricing page.

---

## Creating an activity

1. In a course, **Add an activity → AI Viva**.
2. Set the number of attempts and, optionally, the opening and closing dates.
3. Configure the three steps:
   - **Step 1**: instructions, maximum PDF size and your analysis prompt.
   - **Step 2**: instructions, maximum duration and your analysis prompt.
   - **Step 3**: session duration, evaluation prompt and the three examiners (name, role, personality, voice, avatar or your own image).
4. Set the grading options: maximum grade, the weight of each step in the final grade, whether grades are held for teacher review, and notifications.
5. Optionally add extra safety rules (applied to every prompt) and the retention period for recordings.

**Overrides** (in the activity's menu) change the attempt limit and dates for a single user or a group.

---

## Student workflow

1. Read and accept the privacy notice. This starts an attempt.
2. **Step 1**: upload the PDF. The page moves on by itself when the analysis is ready.
3. **Step 2**: share the screen and microphone, record the presentation, review it, and submit it or record again.
4. **Step 3**: test the microphone and start the session. Examiners speak in turn; to answer, the student presses a button, speaks, and presses it again. The session clock is kept by the server: it does not stop if the page is closed, and reloading the page resumes the same session.
5. When the time is up the session closes and the evaluation is produced. If the student left, cron closes the session.

With several attempts allowed, a new attempt can be started once the previous one has been graded and released. The best released grade goes to the gradebook.

---

## Teacher workflow

**AI Viva → View submissions** lists every attempt. For each one a teacher can:

- read the PDF, watch the recording, read the presentation transcript and listen to each tribunal answer next to its transcript;
- see the AI's score and feedback per step, and any academic-integrity concerns it raised;
- edit the grade and feedback, **save** them as a draft, **publish** them to the student and the gradebook, or **withdraw** a published grade;
- **regenerate** the AI analyses or the evaluation, for example after changing a prompt. A grade or feedback edited by a teacher is never overwritten by a regeneration, and the student is not notified again.

"Hold grades for teacher review" is on by default. If a teacher switches it off, the AI grade is released automatically, except when the AI could not read part of the work or raised an academic-integrity concern: those grades always wait for a teacher.

---

## What the evaluation takes into account

The evaluator receives, in full and without truncation: the assignment as you wrote it (activity description, the instructions of each step and your prompts for each step), the original PDF, its analysis, the complete presentation transcript and analysis, and the complete tribunal conversation. It scores each step from 0 to 100; the final grade is the weighted average computed by the plugin with the weights set on the activity.

Safeguards against partial evaluations:

- The presentation audio is recorded in consecutive four-minute parts and each part is transcribed separately, because speech-to-text services limit the size and length of a request and may cut long transcripts short.
- An AI answer that stops because it reached its length limit is never stored: the request is repeated with more room, and fails visibly if it is still cut.
- If an analysis is missing when the evaluation runs (for example, the AI service failed when the student uploaded the work), it is attempted again first.
- If part of the student's work still could not be read, the grade is not released automatically, whatever the activity's setting: it is held for a teacher, who is notified and sees a warning on the attempt.

Limits of the AI service to bear in mind: OpenAI limits the number of pages and the size of the PDFs it reads in one request (see its file-input documentation for the current figures). A document over those limits falls back to a plain-text extraction, which is less reliable; review those attempts by hand.

---

## Privacy and security

- Sent to OpenAI: the PDF as submitted, the audio of the presentation and of each tribunal answer, screenshots of the presentation, and the resulting transcripts. The student's Moodle name and email are not sent; prompts use a pseudonymous code. The content itself may still identify the student, and the built-in privacy notice says so.
- Students must accept the privacy notice before each attempt.
- Recordings are deleted after the retention period set on the activity; the PDF, transcripts and grades are kept.
- The Privacy API is implemented: export and deletion cover attempts, conversations, files, per-user overrides, and the grades a teacher edited.
- Every upload is checked by real content type and size; every state-changing request requires the session key; students can only act on their own latest attempt and only in the state that allows it.
- The API keys are stored encrypted with Moodle's encryption API and are never shown again once saved.
- Teachers restricted to their own groups can only see, download and regenerate their groups' attempts.

---

## Development

```bash
# From the Moodle root
npx grunt amd --root=mod/aiviva
vendor/bin/phpunit --testsuite mod_aiviva_testsuite
vendor/bin/behat --tags=@mod_aiviva
```

---

## License

GNU General Public License v3 or later — see `LICENSE`.

### Media

The avatar images and videos in `pix/avatars/` were generated with AI tools for this plugin. They do not depict any real person and are distributed under the same licence as the rest of the plugin.
