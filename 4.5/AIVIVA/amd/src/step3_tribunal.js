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
 * Step 3 — AI Tribunal UI: TTS playback, STT capture, avatar animations.
 *
 * @module     mod_aiviva/step3_tribunal
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ajaxPost, showStatus, formatTime, playBeep} from './utils';
import {get_string as getString} from 'core/str';

let cfg = {};
let sessionActive    = false;
let currentTurn      = 0;
let sessionTimer     = null;
let remainingSeconds = 0;
let recognition      = null;   // Web Speech API SpeechRecognition.
let isRecognising    = false;
let currentTranscript = '';
let audioCtx         = null;   // Shared AudioContext for lip sync.
let pttSetupDone     = false;  // Guard against duplicate PTT listeners.
let pendingSubmit    = false;  // Flag: submit transcript when recognition ends.

/**
 * Initialises the tribunal module.
 *
 * @param {Object} config
 * @param {number} config.cmid          - Course module id.
 * @param {string} config.sesskey       - Moodle session key.
 * @param {number} config.submissionid  - Submission id.
 * @param {number} config.durationmins  - Tribunal duration in minutes.
 * @param {string} config.member1voice  - TTS voice for member 1.
 * @param {string} config.member2voice  - TTS voice for member 2.
 * @param {string} config.member3voice  - TTS voice for member 3.
 */
export const init = (config) => {
    cfg = config;

    const step3Panel = document.getElementById('aiviva_step3_panel');
    if (!step3Panel) {
        return;
    }

    // Only show the ready screen when the panel becomes visible.
    const observer = new MutationObserver(() => {
        if (!step3Panel.classList.contains('d-none') && !sessionActive) {
            observer.disconnect();
            showReadyScreen(step3Panel);
        }
    });
    observer.observe(step3Panel, {attributes: true, attributeFilter: ['class']});

    // If already visible on load.
    if (!step3Panel.classList.contains('d-none') && !sessionActive) {
        showReadyScreen(step3Panel);
    }

    // If evaluation is already pending (page reload after tribunal), start polling.
    const pendingStatuses = ['submitted', 'grading'];
    if (pendingStatuses.includes(cfg.submissionstatus)) {
        const evalStatusEl = document.getElementById('aiviva_eval_status');
        pollEvaluationStatus(evalStatusEl);
    }
};

// ------------------------------------------------------------------
// Ready screen (shown before session starts)
// ------------------------------------------------------------------

/**
 * Injects a full-panel "ready" screen with a start button.
 * The tribunal room is hidden until the user clicks Start.
 *
 * @param {HTMLElement} step3Panel - The step 3 container element.
 */
const showReadyScreen = async (step3Panel) => {
    const tribunalRoom = step3Panel.querySelector('.aiviva-tribunal-room');
    if (tribunalRoom) {
        tribunalRoom.style.display = 'none';
    }

    const [title, notice, btnLabel] = await Promise.all([
        getString('tribunal_ready_title', 'mod_aiviva'),
        getString('tribunal_ready_notice', 'mod_aiviva'),
        getString('tribunal_start_btn', 'mod_aiviva'),
    ]);

    const screen = document.createElement('div');
    screen.id = 'aiviva_tribunal_ready';
    screen.className = 'text-center p-4 my-4';
    screen.innerHTML = `
        <h3 class="mb-3">${title}</h3>
        <div class="alert alert-warning d-inline-block text-start mb-4" style="max-width:600px">
            <strong>⚠️</strong> ${notice}
        </div>
        <br>
        <button type="button" class="btn btn-primary btn-lg" id="aiviva_tribunal_start_btn">
            🎓 ${btnLabel}
        </button>
    `;

    step3Panel.insertBefore(screen, tribunalRoom ?? step3Panel.firstChild);

    document.getElementById('aiviva_tribunal_start_btn').addEventListener('click', () => {
        screen.remove();
        if (tribunalRoom) {
            tribunalRoom.style.display = '';
        }
        // Create AudioContext on user gesture (browser autoplay policy).
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        startSession();
    });
};

// ------------------------------------------------------------------
// Session lifecycle
// ------------------------------------------------------------------

/**
 * Starts the tribunal session by requesting the opening statement.
 */
const startSession = async () => {
    sessionActive    = true;
    currentTurn      = 0;
    remainingSeconds = cfg.durationmins * 60;

    startSessionTimer();
    setupSpeechRecognition();

    const statusEl = document.getElementById('aiviva_tribunal_status');
    const loadingMsg = await getString('tribunal_loading', 'mod_aiviva');
    showStatus(statusEl, loadingMsg, 'info');

    try {
        const data = await ajaxPost(
            M.cfg.wwwroot + '/mod/aiviva/ajax.php',
            {action: 'tribunal_opening', submissionid: cfg.submissionid, cmid: cfg.cmid},
            cfg.sesskey
        );

        if (data.success) {
            await deliverTribunalTurn(data.member, data.text, data.audio_base64);
            showPushToTalk();
        } else {
            showStatus(statusEl, data.error || 'Failed to start tribunal', 'danger');
        }
    } catch (e) {
        showStatus(document.getElementById('aiviva_tribunal_status'), e.message, 'danger');
    }
};

/**
 * Starts the countdown timer for the session.
 */
const startSessionTimer = () => {
    const timerEl = document.getElementById('aiviva_tribunal_timer');
    updateTimerDisplay(timerEl);

    sessionTimer = setInterval(async () => {
        remainingSeconds--;
        updateTimerDisplay(timerEl);

        if (remainingSeconds <= 0) {
            clearInterval(sessionTimer);
            endSession();
        } else if (remainingSeconds === 60) {
            playBeep(660, 400, 0.3);
            const msg = await getString('warning_1min', 'mod_aiviva');
            showStatus(document.getElementById('aiviva_tribunal_status'), msg, 'warning');
        }
    }, 1000);
};

/**
 * Updates the timer display element.
 *
 * @param {HTMLElement} timerEl - Timer element.
 */
const updateTimerDisplay = (timerEl) => {
    if (timerEl) {
        timerEl.textContent = formatTime(remainingSeconds);
        timerEl.className   = 'tribunal-timer' + (remainingSeconds <= 60 ? ' urgent' : '');
    }
};

/**
 * Gracefully ends the tribunal session.
 */
const endSession = async () => {
    sessionActive = false;
    hidePushToTalk();

    const statusEl = document.getElementById('aiviva_tribunal_status');
    const closingMsg = await getString('tribunal_ending', 'mod_aiviva');
    showStatus(statusEl, closingMsg, 'info');

    try {
        const data = await ajaxPost(
            M.cfg.wwwroot + '/mod/aiviva/ajax.php',
            {action: 'tribunal_closing', submissionid: cfg.submissionid, cmid: cfg.cmid, turn: currentTurn},
            cfg.sesskey
        );

        if (data.success) {
            await deliverTribunalTurn(data.member, data.text, data.audio_base64);
            // If evaluation already completed synchronously, reload now.
            if (data.evaluation_status === 'graded') {
                const doneMsg = await getString('evaluation_complete', 'mod_aiviva');
                showStatus(statusEl, doneMsg, 'success');
                setTimeout(() => window.location.reload(), 2000);
                return;
            }
            if (data.evaluation_error) {
                showStatus(statusEl, 'Evaluation error: ' + data.evaluation_error, 'danger');
            }
        }
    } catch (e) {
        // Ignore closing errors.
    }

    const finishedMsg = await getString('tribunal_finished', 'mod_aiviva');
    showStatus(statusEl, finishedMsg, 'info');
    pollEvaluationStatus(statusEl);
};

// ------------------------------------------------------------------
// Tribunal turn delivery (TTS + avatar animation)
// ------------------------------------------------------------------

/**
 * Switches a member's avatar video between idle and talking states.
 *
 * @param {number}  memberNum - Tribunal member number (1-3).
 * @param {boolean} talking   - True to play talking loop, false for idle.
 */
const setAvatarTalking = (memberNum, talking) => {
    const video = document.getElementById(`avatar_video_${memberNum}`);
    if (!video) {
        return;
    }
    const newSrc = talking ? video.dataset.talking : video.dataset.idle;
    if (video.getAttribute('src') !== newSrc) {
        video.src  = newSrc;
        video.loop = true;
        video.play().catch(() => {});
    }
};

/**
 * Delivers a tribunal member's message: shows text, plays audio, animates avatar.
 *
 * @param {number} member      - Tribunal member number (1-3).
 * @param {string} text        - Spoken text.
 * @param {string} audioBase64 - Base64-encoded MP3 audio (may be empty).
 */
const deliverTribunalTurn = async (member, text, audioBase64) => {
    currentTurn++;
    appendToTranscript(`tribunal_${member}`, text, member);
    setActiveMember(member);

    if (audioBase64) {
        await playAudioBase64(audioBase64, member);
    } else {
        // No audio — switch to talking for the wait duration, then back to idle.
        setAvatarTalking(member, true);
        await new Promise(r => setTimeout(r, Math.min(5000, text.length * 60)));
        setAvatarTalking(member, false);
    }

    clearActiveMember();
};

/**
 * Plays base64-encoded MP3 audio using Web Audio API with real-time lip sync.
 *
 * @param {string} base64 - Base64 MP3 data.
 * @param {number} member - Member number for avatar lip-sync.
 * @returns {Promise<void>}
 */
const playAudioBase64 = (base64, member) => {
    return new Promise((resolve) => {
        try {
            const binary = atob(base64);
            const bytes  = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }

            const memberEl = document.getElementById(`tribunal_member_${member}`);
            const mouthEl  = document.getElementById(`mouth_open_${member}`);
            memberEl?.classList.add('speaking');
            setAvatarTalking(member, true);

            // Fallback if AudioContext unavailable.
            if (!audioCtx) {
                const blob  = new Blob([bytes], {type: 'audio/mpeg'});
                const url   = URL.createObjectURL(blob);
                const audio = new Audio(url);
                audio.onended = audio.onerror = () => {
                    memberEl?.classList.remove('speaking');
                    setAvatarTalking(member, false);
                    URL.revokeObjectURL(url);
                    resolve();
                };
                audio.play().catch(() => resolve());
                return;
            }

            audioCtx.decodeAudioData(bytes.buffer.slice(0), (audioBuffer) => {
                const source   = audioCtx.createBufferSource();
                const analyser = audioCtx.createAnalyser();
                analyser.fftSize = 256;

                source.buffer = audioBuffer;
                source.connect(analyser);
                analyser.connect(audioCtx.destination);

                // Lip-sync animation loop.
                const dataArray = new Uint8Array(analyser.frequencyBinCount);
                let animFrame   = null;

                const animateMouth = () => {
                    analyser.getByteTimeDomainData(dataArray);
                    let sum = 0;
                    for (let i = 0; i < dataArray.length; i++) {
                        const v = (dataArray[i] - 128) / 128;
                        sum += v * v;
                    }
                    const rms = Math.sqrt(sum / dataArray.length);
                    const ry  = Math.min(6, rms * 50);
                    if (mouthEl) {
                        mouthEl.setAttribute('ry', ry.toFixed(1));
                    }
                    animFrame = requestAnimationFrame(animateMouth);
                };
                animFrame = requestAnimationFrame(animateMouth);

                source.onended = () => {
                    cancelAnimationFrame(animFrame);
                    if (mouthEl) {
                        mouthEl.setAttribute('ry', '0');
                    }
                    memberEl?.classList.remove('speaking');
                    setAvatarTalking(member, false);
                    resolve();
                };

                source.start(0);
            }, () => {
                // Decode failed — resolve without audio.
                memberEl?.classList.remove('speaking');
                setAvatarTalking(member, false);
                resolve();
            });
        } catch (e) {
            resolve();
        }
    });
};

/**
 * Sets a tribunal member's avatar as the active speaker.
 *
 * @param {number} member - Member number.
 */
const setActiveMember = (member) => {
    document.querySelectorAll('.tribunal-member').forEach((el, i) => {
        el.classList.toggle('active-speaker', i + 1 === member);
        el.classList.toggle('inactive-speaker', i + 1 !== member);
    });
};

/** Clears all speaker highlights. */
const clearActiveMember = () => {
    document.querySelectorAll('.tribunal-member').forEach(el => {
        el.classList.remove('active-speaker', 'inactive-speaker');
    });
};

// ------------------------------------------------------------------
// Speech recognition (STT)
// ------------------------------------------------------------------

/**
 * Initialises the Web Speech API recognition object.
 */
const setupSpeechRecognition = () => {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
        return; // Will fall back to server-side Whisper transcription.
    }

    recognition = new SR();
    recognition.continuous     = true;
    recognition.interimResults = true;
    recognition.lang           = cfg.speechlang || document.documentElement.lang || 'en-US';

    recognition.onresult = (e) => {
        let interim = '';
        let final   = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
            const t = e.results[i][0].transcript;
            if (e.results[i].isFinal) {
                final += t;
            } else {
                interim += t;
            }
        }
        currentTranscript += final;

        // Show live transcript.
        const transcriptEl = document.getElementById('aiviva_tribunal_transcript');
        if (transcriptEl) {
            const liveEl = transcriptEl.querySelector('.live-transcript') ||
                (() => {
                    const el = document.createElement('div');
                    el.className = 'live-transcript participant-turn';
                    transcriptEl.appendChild(el);
                    return el;
                })();
            liveEl.textContent = currentTranscript + (interim ? ' ' + interim : '');
        }
    };

    recognition.onerror = (e) => {
        if (e.error !== 'no-speech') {
            // Speech recognition error — non-critical, ignore.
        }
    };

    // Submit the transcript once the engine has finalised all results.
    recognition.onend = () => {
        if (pendingSubmit) {
            pendingSubmit = false;
            // If no final results accumulated, fall back to whatever is visible
            // in the live-transcript div (may contain interim text).
            let transcript = currentTranscript;
            if (!transcript.trim()) {
                const liveEl = document.querySelector('#aiviva_tribunal_transcript .live-transcript');
                transcript = liveEl ? liveEl.textContent.trim() : '';
            }
            submitParticipantResponse(transcript);
        }
    };
};

/** Shows the push-to-talk button. */
const showPushToTalk = () => {
    document.getElementById('aiviva_ptt_btn')?.classList.remove('d-none');
    if (!pttSetupDone) {
        pttSetupDone = true;
        setupPTT();
    }
};

/** Hides the push-to-talk button. */
const hidePushToTalk = () => {
    document.getElementById('aiviva_ptt_btn')?.classList.add('d-none');
    if (recognition && isRecognising) {
        recognition.stop();
    }
};

/**
 * Sets up push-to-talk mouse/touch events on the PTT button.
 */
const setupPTT = () => {
    const pttBtn = document.getElementById('aiviva_ptt_btn');
    if (!pttBtn) {
        return;
    }

    const startListening = () => {
        if (!sessionActive) {
            return;
        }
        currentTranscript = '';
        isRecognising     = true;
        pttBtn.classList.add('active');

        if (recognition) {
            try {
                recognition.start();
            } catch (e) {
                // Already started.
            }
        }
    };

    const stopListening = () => {
        if (!isRecognising) {
            return;
        }
        isRecognising = false;
        pttBtn.classList.remove('active');

        if (recognition) {
            pendingSubmit = true;
            recognition.stop();
            // Fallback: if onend doesn't fire within 1 s, submit anyway.
            setTimeout(() => {
                if (pendingSubmit) {
                    pendingSubmit = false;
                    let transcript = currentTranscript;
                    if (!transcript.trim()) {
                        const liveEl = document.querySelector('#aiviva_tribunal_transcript .live-transcript');
                        transcript = liveEl ? liveEl.textContent.trim() : '';
                    }
                    submitParticipantResponse(transcript);
                }
            }, 1000);
        } else {
            submitParticipantResponse(currentTranscript);
        }
    };

    pttBtn.addEventListener('mousedown', startListening);
    pttBtn.addEventListener('mouseup', stopListening);
    pttBtn.addEventListener('touchstart', e => { e.preventDefault(); startListening(); }, {passive: false});
    pttBtn.addEventListener('touchend', e => { e.preventDefault(); stopListening(); }, {passive: false});
};

// ------------------------------------------------------------------
// Sending participant response and getting next question
// ------------------------------------------------------------------

/**
 * Sends the participant's transcribed response to the server and delivers the reply.
 *
 * @param {string} transcript - The transcribed response text.
 */
const submitParticipantResponse = async (transcript) => {
    if (!sessionActive || !transcript.trim()) {
        return;
    }

    hidePushToTalk();
    appendToTranscript('participant', transcript, 0);
    const statusEl = document.getElementById('aiviva_tribunal_status');
    const thinkingMsg = await getString('tribunal_thinking', 'mod_aiviva');
    showStatus(statusEl, thinkingMsg, 'info');

    // Determine which member speaks next (rotate 1 → 2 → 3 → 1 ...).
    const nextMember = (currentTurn % 3) + 1;

    try {
        const data = await ajaxPost(
            M.cfg.wwwroot + '/mod/aiviva/ajax.php',
            {
                action:       'tribunal_turn',
                submissionid: cfg.submissionid,
                cmid:         cfg.cmid,
                response:     transcript,
                next_member:  nextMember,
                turn:         currentTurn + 1,
            },
            cfg.sesskey
        );

        if (data.success) {
            await deliverTribunalTurn(data.member, data.text, data.audio_base64);

            if (sessionActive) {
                showPushToTalk();
            }
        } else {
            showStatus(statusEl, data.error || 'Error processing response', 'danger');
        }
    } catch (e) {
        showStatus(statusEl, 'Error: ' + e.message, 'danger');
        if (sessionActive) {
            showPushToTalk();
        }
    }
};

// ------------------------------------------------------------------
// Conversation log & transcript UI
// ------------------------------------------------------------------

/**
 * Appends a turn to the conversation log and transcript area.
 *
 * @param {string} speaker - Speaker identifier ('tribunal_1', 'tribunal_2', 'tribunal_3', 'participant').
 * @param {string} text    - Message text.
 * @param {number} member  - Member number (for colour coding).
 */
const appendToTranscript = (speaker, text, member) => {
    const transcriptEl = document.getElementById('aiviva_tribunal_transcript');
    const logEl        = document.getElementById('aiviva_conversation_log');

    const isParticipant = speaker === 'participant';
    const itemClass     = isParticipant ? 'participant-turn' : `tribunal-turn member-${member}`;
    const label         = isParticipant ? '🎓 You' : `⚖️ Member ${member}`;

    [transcriptEl, logEl].forEach(container => {
        if (!container) {
            return;
        }
        // Remove live transcript div.
        container.querySelector('.live-transcript')?.remove();

        const item = document.createElement('div');
        item.className = 'transcript-item ' + itemClass;
        item.innerHTML = `<span class="speaker-label">${label}</span><span class="message-text">${escapeHtml(text)}</span>`;
        container.appendChild(item);
        container.scrollTop = container.scrollHeight;
    });
};

// ------------------------------------------------------------------
// Evaluation polling
// ------------------------------------------------------------------

/**
 * Polls the server until the final evaluation is ready, then reloads.
 *
 * @param {HTMLElement} statusEl - Status element.
 */
const pollEvaluationStatus = (statusEl) => {
    const pollUrl = M.cfg.wwwroot + '/mod/aiviva/ajax.php';
    let attempts  = 0;

    const poll = async () => {
        attempts++;
        if (attempts > 60) {
            return;
        }

        try {
            const url  = `${pollUrl}?action=check_evaluation&submissionid=${cfg.submissionid}` +
                `&sesskey=${cfg.sesskey}&cmid=${cfg.cmid}`;
            const res  = await fetch(url);
            const data = await res.json();

            if (data.status === 'graded') {
                const doneMsg = await getString('evaluation_complete', 'mod_aiviva');
                showStatus(statusEl, doneMsg, 'success');
                setTimeout(() => window.location.reload(), 2000);
            } else if (data.status === 'error') {
                showStatus(statusEl, data.error || 'Evaluation failed', 'danger');
            } else {
                setTimeout(poll, 5000);
            }
        } catch (e) {
            setTimeout(poll, 5000);
        }
    };

    setTimeout(poll, 5000);
};

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------

/**
 * Escapes HTML special characters to prevent XSS.
 *
 * @param {string} str - Raw string.
 * @returns {string} Escaped string.
 */
const escapeHtml = (str) => String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
