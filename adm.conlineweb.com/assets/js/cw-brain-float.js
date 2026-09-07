/**
 * Cerebro flotante: voz bidireccional + chat IA del sistema (prompt/execute).
 */
(function () {
  'use strict';

  var cfg = window.CW_BRAIN_CFG || null;
  if (!cfg || !cfg.apiUrl) return;

  var root = document.getElementById('cwBrainRoot');
  if (!root) return;

  var fab = document.getElementById('cwBrainFab');
  var panel = document.getElementById('cwBrainPanel');
  var logEl = document.getElementById('cwBrainLog');
  var statusEl = document.getElementById('cwBrainStatus');
  var input = document.getElementById('cwBrainInput');
  var sendBtn = document.getElementById('cwBrainSend');
  var execBtn = document.getElementById('cwBrainExec');
  var micBtn = document.getElementById('cwBrainMic');
  var speakBtn = document.getElementById('cwBrainSpeakToggle');
  var closeBtn = document.getElementById('cwBrainClose');
  var badge = document.getElementById('cwBrainBadge');
  var voiceSel = document.getElementById('cwBrainVoice');
  var voicePrevBtn = document.getElementById('cwBrainVoicePreview');
  var speakBubble = document.getElementById('cwBrainSpeakBubble');
  var speaking = false;

  var sessionId = 0;
  var busy = false;
  var speakOn = true;
  var listening = false;
  var recognition = null;
  var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition || null;
  var VOICE_KEY = 'cw_brain_voice_uri_v2';
  var preferredVoice = null;
  var humanVoices = [];
  var speechJob = null;
  var speechChunkTimer = null;
  var speechStartTimer = null;
  var speechVoicesPumpRetries = 0;
  var speechPrimed = false;

  function isIOSDevice() {
    var ua = navigator.userAgent || '';
    return /iPad|iPhone|iPod/.test(ua) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function isMacOSDevice() {
    if (isIOSDevice()) return false;
    var ua = navigator.userAgent || '';
    var platform = navigator.platform || '';
    return /Macintosh|Mac OS X/i.test(ua) || platform === 'MacIntel';
  }

  function isAppleSpeechPlatform() {
    return isIOSDevice() || isMacOSDevice();
  }

  function clearSpeechWatchdogs() {
    if (speechChunkTimer) {
      window.clearTimeout(speechChunkTimer);
      speechChunkTimer = null;
    }
    if (speechStartTimer) {
      window.clearTimeout(speechStartTimer);
      speechStartTimer = null;
    }
  }

  function estimateChunkSpeakMs(text, rate) {
    var len = String(text || '').length;
    var cps = 13 * (rate || 0.9);
    return Math.min(28000, Math.max(1600, (len / cps) * 1000 + 700));
  }

  function abortSpeechChunkAdvance(delayMs) {
    clearSpeechWatchdogs();
    try {
      if (window.speechSynthesis) window.speechSynthesis.cancel();
    } catch (e) { /* ignore */ }
    window.setTimeout(function () {
      if (!speechJob) {
        setSpeaking(false);
        return;
      }
      speakNextChunk();
    }, delayMs || 60);
  }

  function armSpeechChunkEndWatchdog(part, rate) {
    clearSpeechWatchdogs();
    if (!isAppleSpeechPlatform()) return;
    var chunkMs = estimateChunkSpeakMs(part, rate);
    speechChunkTimer = window.setTimeout(function () {
      if (!speechJob) return;
      abortSpeechChunkAdvance(60);
    }, chunkMs);
  }

  function primeSpeechForApple() {
    if (!window.speechSynthesis) return;
    try {
      window.speechSynthesis.cancel();
      window.speechSynthesis.resume();
    } catch (e) { /* ignore */ }
    pickHumanVoice();
    speechPrimed = true;
  }

  function setStatus(msg, isErr) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.classList.toggle('is-err', !!isErr);
  }

  function appendMsg(role, text) {
    if (!logEl || !text) return;
    var div = document.createElement('div');
    var cls = role === 'assistant' ? 'is-assistant' : (role === 'system' ? 'is-system' : 'is-user');
    var who = role === 'assistant' ? 'Cerebro' : (role === 'system' ? 'Sistema' : 'Tú');
    div.className = 'cw-brain-msg ' + cls;
    div.innerHTML = '<small>' + who + '</small>' + escapeHtml(String(text));
    logEl.appendChild(div);
    logEl.scrollTop = logEl.scrollHeight;
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function scoreVoice(v) {
    if (!v) return -1;
    var name = String(v.name || '');
    var lang = String(v.lang || '');
    var score = 0;
    if (!/^es/i.test(lang)) return -1;
    if (/es[-_]MX/i.test(lang)) score += 80;
    else if (/es[-_](US|419)/i.test(lang)) score += 60;
    else score += 40;

    var natural = /natural|neural|online\s*\(natural\)|premium|enhanced|studio|google|microsoft/i.test(name);
    var namedHuman = /sabina|dalia|paulina|elena|lupe|lucia|mónica|monica|jorge|carlos|spanish\s*mexico|méxico|mexico/i.test(name);
    if (natural) score += 55;
    if (namedHuman) score += 25;
    if (/female|mujer|woman/i.test(name)) score += 8;
    // Penalizar voces robóticas / compactas
    if (/desktop|compact|mobile|espeak|festival/i.test(name)) score -= 40;
    if (/microsoft\s+server/i.test(name)) score -= 20;
    return score;
  }

  function isHumanEnough(v) {
    var s = scoreVoice(v);
    // Solo las más humanas: natural/neural/google/microsoft buenas o es-MX decentes
    if (s < 90) return false;
    var name = String(v.name || '');
    var natural = /natural|neural|online\s*\(natural\)|premium|enhanced|studio|google/i.test(name);
    var mx = /es[-_]MX/i.test(String(v.lang || ''));
    return natural || (mx && s >= 100) || s >= 120;
  }

  function voiceId(v) {
    return String(v.voiceURI || (v.name + '|' + v.lang));
  }

  function listHumanVoices() {
    if (!window.speechSynthesis) return [];
    var all = window.speechSynthesis.getVoices() || [];
    var list = all.filter(isHumanEnough);
    list.sort(function (a, b) { return scoreVoice(b) - scoreVoice(a); });
    if (isIOSDevice()) {
      var localOnly = list.filter(function (v) { return !!v.localService; });
      if (localOnly.length) list = localOnly;
    }
    if (list.length === 0) {
      list = all
        .filter(function (v) { return scoreVoice(v) > 0; })
        .sort(function (a, b) { return scoreVoice(b) - scoreVoice(a); });
      if (isIOSDevice()) {
        var localFallback = list.filter(function (v) { return !!v.localService; });
        if (localFallback.length) list = localFallback;
      }
      list = list.slice(0, 5);
    }
    return list.slice(0, 8);
  }

  function findVoiceById(id) {
    if (!id) return null;
    var all = (window.speechSynthesis && window.speechSynthesis.getVoices()) || [];
    for (var i = 0; i < all.length; i++) {
      if (voiceId(all[i]) === id) return all[i];
    }
    return null;
  }

  function pickHumanVoice() {
    var saved = '';
    try { saved = localStorage.getItem(VOICE_KEY) || ''; } catch (e) {}
    if (saved) {
      var chosen = findVoiceById(saved);
      if (chosen && !(isIOSDevice() && !chosen.localService)) {
        preferredVoice = chosen;
        return preferredVoice;
      }
    }
    humanVoices = listHumanVoices();
    preferredVoice = humanVoices[0] || null;
    return preferredVoice;
  }

  function fillVoiceSelect() {
    if (!voiceSel) return;
    humanVoices = listHumanVoices();
    var saved = '';
    try { saved = localStorage.getItem(VOICE_KEY) || ''; } catch (e) {}
    voiceSel.innerHTML = '';
    if (humanVoices.length === 0) {
      var opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = 'No hay voces humanas en español';
      voiceSel.appendChild(opt0);
      return;
    }
    humanVoices.forEach(function (v, i) {
      var opt = document.createElement('option');
      opt.value = voiceId(v);
      var tag = /natural|neural|online/i.test(v.name) ? ' · natural' : '';
      opt.textContent = v.name.replace(/^Microsoft\s+/i, '').replace(/\s+Online\s*\(Natural\)/i, ' Natural') + tag
        + ' (' + v.lang + ')';
      voiceSel.appendChild(opt);
      if ((saved && voiceId(v) === saved) || (!saved && i === 0)) {
        opt.selected = true;
      }
    });
    preferredVoice = findVoiceById(voiceSel.value) || humanVoices[0];
  }

  function setSpeaking(on) {
    speaking = !!on;
    if (fab) fab.classList.toggle('is-speaking', speaking);
    if (root) root.classList.toggle('is-speaking', speaking);
    if (speakBubble) {
      speakBubble.hidden = !speaking;
      speakBubble.classList.toggle('is-visible', speaking);
    }
  }

  function stopSpeak() {
    speechJob = null;
    speechVoicesPumpRetries = 0;
    clearSpeechWatchdogs();
    try {
      if (window.speechSynthesis) window.speechSynthesis.cancel();
    } catch (e) {}
    setSpeaking(false);
  }

  function speakNextChunk() {
    if (!speechJob || !speakOn || !window.speechSynthesis) {
      stopSpeak();
      return;
    }
    if (isAppleSpeechPlatform() && window.speechSynthesis.getVoices().length === 0 && speechVoicesPumpRetries < 8) {
      speechVoicesPumpRetries += 1;
      window.setTimeout(function () {
        preferredVoice = null;
        pickHumanVoice();
        speakNextChunk();
      }, 120);
      return;
    }
    speechVoicesPumpRetries = 0;
    if (speechJob.idx >= speechJob.chunks.length) {
      stopSpeak();
      return;
    }

    var voice = pickHumanVoice();
    var useVoice = !!(voice && !speechJob.noVoice);
    if (isIOSDevice() && voice && !voice.localService) useVoice = false;

    var part = speechJob.chunks[speechJob.idx++].slice(0, isAppleSpeechPlatform() ? 200 : 280);
    part = String(part || '').trim();
    if (!part) {
      speakNextChunk();
      return;
    }

    var rate = 0.92;
    var u = new SpeechSynthesisUtterance(part);
    u.lang = (voice && voice.lang) ? voice.lang : 'es-MX';
    if (!/^es/i.test(u.lang)) u.lang = 'es-MX';
    if (useVoice) u.voice = voice;
    u.rate = rate;
    u.pitch = 0.96;
    u.volume = 1;

    var chunkStarted = false;
    u.onstart = function () {
      chunkStarted = true;
      clearSpeechWatchdogs();
      armSpeechChunkEndWatchdog(part, rate);
      setSpeaking(true);
    };
    u.onend = function () {
      clearSpeechWatchdogs();
      window.setTimeout(speakNextChunk, 140);
    };
    u.onerror = function () {
      clearSpeechWatchdogs();
      if (speechJob && useVoice && !speechJob.noVoice) {
        speechJob.noVoice = true;
        speechJob.idx = Math.max(0, speechJob.idx - 1);
        preferredVoice = null;
        pickHumanVoice();
        window.setTimeout(speakNextChunk, 80);
        return;
      }
      if (speechJob && !speechJob.voiceRetried) {
        speechJob.voiceRetried = true;
        speechJob.idx = Math.max(0, speechJob.idx - 1);
        preferredVoice = null;
        pickHumanVoice();
        window.setTimeout(speakNextChunk, 60);
        return;
      }
      abortSpeechChunkAdvance(80);
    };

    try {
      if (isAppleSpeechPlatform()) {
        if (!speechJob.applePrimed) {
          speechJob.applePrimed = true;
          try { window.speechSynthesis.cancel(); } catch (eCancel) { /* ignore */ }
        }
        window.speechSynthesis.resume();
      } else {
        window.speechSynthesis.resume();
      }
      window.speechSynthesis.speak(u);
      if (isAppleSpeechPlatform()) {
        speechStartTimer = window.setTimeout(function () {
          if (chunkStarted || !speechJob) return;
          if (useVoice && !speechJob.noVoice) {
            speechJob.noVoice = true;
            speechJob.idx = Math.max(0, speechJob.idx - 1);
            abortSpeechChunkAdvance(80);
            return;
          }
          abortSpeechChunkAdvance(60);
        }, 1500);
        armSpeechChunkEndWatchdog(part, rate);
      }
    } catch (e) {
      abortSpeechChunkAdvance(60);
    }
  }

  function speak(text) {
    if (!speakOn || !text || !window.speechSynthesis) return;
    stopSpeak();
    var clean = String(text)
      .replace(/[#*_`>]+/g, ' ')
      .replace(/https?:\/\/\S+/gi, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    if (!clean) return;

    var chunks = clean
      .replace(/([.!?…])\s+/g, '$1|')
      .split('|')
      .map(function (p) { return p.trim(); })
      .filter(Boolean);
    if (chunks.length === 0) chunks = [clean.slice(0, 500)];

    speechJob = {
      chunks: chunks,
      idx: 0,
      noVoice: false,
      voiceRetried: false,
      applePrimed: false
    };
    setSpeaking(true);
    speakNextChunk();
  }

  function openPanel() {
    root.classList.add('is-open');
    if (badge) badge.classList.remove('is-on');
    primeSpeechForApple();
    if (input) setTimeout(function () { input.focus(); }, 80);
    if (logEl && logEl.children.length === 0) {
      appendMsg(
        'system',
        'Hola, soy el cerebro de ConlineWeb. Háblame o escribe: puedo ayudarte con SEO, GEO, diseño, mantenimiento, contenidos y el panel. Cuando quieras formalizar una orden, pulsa «Ejecutar orden».'
      );
      speak('Hola, soy el cerebro de ConlineWeb. ¿En qué te ayudo?');
    }
  }

  function closePanel() {
    root.classList.remove('is-open');
    stopListening();
  }

  function togglePanel() {
    if (root.classList.contains('is-open')) closePanel();
    else openPanel();
  }

  function wantsExecute(text) {
    var t = String(text || '').toLowerCase();
    return /(ejecuta|ejecutar|hazlo|crea(r)?\s+propuesta|encola|genera(r)?\s+solicitud|aplica(r)?\s+orden|formaliza)/i.test(t);
  }

  function api(body) {
    return fetch(cfg.apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
  }

  function setBusy(on) {
    busy = !!on;
    if (sendBtn) sendBtn.disabled = busy;
    if (execBtn) execBtn.disabled = busy || !(sessionId > 0);
    if (micBtn) micBtn.disabled = busy;
  }

  function runPrompt(text) {
    var prompt = String(text || '').trim();
    if (prompt.length < 2) {
      setStatus('Dime algo con al menos un par de palabras.', true);
      return;
    }
    if (busy) return;
    setBusy(true);
    appendMsg('user', prompt);
    if (input) input.value = '';
    setStatus('Pensando con la base de conocimiento…');
    api({
      action: 'run',
      prompt: prompt,
      mode: 'brain',
      session_id: sessionId || 0
    }).then(function (data) {
      if (!data || !data.ok) {
        setBusy(false);
        var err = (data && data.error) || 'No pude responder ahora';
        setStatus(err, true);
        appendMsg('assistant', err);
        speak('Perdón, no pude completar eso. ' + err);
        return;
      }
      if (data.session_id) sessionId = data.session_id;
      var reply = data.reply || '';
      if (!reply && Array.isArray(data.messages)) {
        for (var i = data.messages.length - 1; i >= 0; i--) {
          if (data.messages[i].role === 'assistant') {
            reply = data.messages[i].content || '';
            break;
          }
        }
      }
      if (reply) {
        appendMsg('assistant', reply);
        speak(reply);
      }
      setStatus(data.message || 'Listo. Si quieres formalizar la orden, pulsa Ejecutar.');
      setBusy(false);
      if (execBtn) execBtn.disabled = !(sessionId > 0);

      if (wantsExecute(prompt)) {
        executeOrder('');
      }
    }).catch(function () {
      setBusy(false);
      setStatus('Error de red al hablar con el cerebro', true);
      appendMsg('assistant', 'Se me cortó la conexión. ¿Lo intentamos de nuevo?');
      speak('Se me cortó la conexión. ¿Lo intentamos de nuevo?');
    });
  }

  function executeOrder(instruction) {
    if (!(sessionId > 0)) {
      setStatus('Primero conversemos un poco la orden.', true);
      return;
    }
    if (busy) return;
    setBusy(true);
    setStatus('Formalizando orden en la cola de propuestas…');
    appendMsg('system', 'Ejecutando orden → generando solicitud en Monitor…');
    api({
      action: 'execute_request',
      session_id: sessionId,
      instruction: instruction || ''
    }).then(function (data) {
      setBusy(false);
      if (!data || !data.ok) {
        var err = (data && data.error) || 'No se pudo crear la solicitud';
        setStatus(err, true);
        appendMsg('assistant', err);
        speak('No pude crear la solicitud. ' + err);
        return;
      }
      var msg = data.message || 'Solicitud creada en la cola.';
      if (data.proposal_id) {
        msg += ' Propuesta #' + data.proposal_id + '.';
      }
      msg += ' Revísala en Monitor antes de publicar.';
      appendMsg('assistant', msg);
      setStatus(msg);
      speak(msg);
      if (badge) {
        badge.textContent = '!';
        badge.classList.add('is-on');
      }
    }).catch(function () {
      setBusy(false);
      setStatus('Error de red al ejecutar la orden', true);
    });
  }

  function stopListening() {
    listening = false;
    if (fab) fab.classList.remove('is-listening');
    if (micBtn) {
      micBtn.classList.remove('is-on');
      micBtn.innerHTML = '<i class="fas fa-microphone"></i> Hablar';
    }
    try {
      if (recognition) recognition.stop();
    } catch (e) {}
  }

  function startListening() {
    if (!SpeechRec) {
      setStatus('Tu navegador no soporta dictado por voz. Puedes escribirme.', true);
      speak('Tu navegador no soporta dictado por voz. Puedes escribirme.');
      return;
    }
    if (listening) {
      stopListening();
      return;
    }
    recognition = new SpeechRec();
    recognition.lang = 'es-MX';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    recognition.continuous = false;
    recognition.onstart = function () {
      listening = true;
      if (fab) fab.classList.add('is-listening');
      if (micBtn) {
        micBtn.classList.add('is-on');
        micBtn.innerHTML = '<i class="fas fa-stop"></i> Escuchando…';
      }
      setStatus('Te escucho…');
      stopSpeak();
    };
    recognition.onresult = function (ev) {
      var text = '';
      try {
        text = ev.results[0][0].transcript || '';
      } catch (e) {}
      stopListening();
      if (text) {
        if (input) input.value = text;
        runPrompt(text);
      }
    };
    recognition.onerror = function () {
      stopListening();
      setStatus('No alcancé a escucharte bien. Prueba otra vez.', true);
    };
    recognition.onend = function () {
      stopListening();
    };
    try {
      recognition.start();
    } catch (e) {
      stopListening();
      setStatus('No pude abrir el micrófono.', true);
    }
  }

  if (fab) fab.addEventListener('click', function () {
    primeSpeechForApple();
    togglePanel();
  });
  if (closeBtn) closeBtn.addEventListener('click', closePanel);
  if (sendBtn) {
    sendBtn.addEventListener('click', function () {
      runPrompt(input ? input.value : '');
    });
  }
  if (input) {
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey) {
        ev.preventDefault();
        runPrompt(input.value);
      }
    });
  }
  if (execBtn) {
    execBtn.addEventListener('click', function () {
      executeOrder('');
    });
  }
  if (micBtn) micBtn.addEventListener('click', startListening);
  if (speakBtn) {
    speakBtn.addEventListener('click', function () {
      speakOn = !speakOn;
      speakBtn.classList.toggle('is-on', speakOn);
      speakBtn.title = speakOn ? 'Voz del cerebro: activada' : 'Voz del cerebro: silenciada';
      speakBtn.innerHTML = speakOn
        ? '<i class="fas fa-volume-up"></i>'
        : '<i class="fas fa-volume-mute"></i>';
      if (!speakOn) stopSpeak();
      else {
        primeSpeechForApple();
        speak('Listo, te sigo hablando.');
      }
    });
    speakBtn.classList.add('is-on');
  }

  if (voiceSel) {
    voiceSel.addEventListener('change', function () {
      try { localStorage.setItem(VOICE_KEY, voiceSel.value || ''); } catch (e) {}
      preferredVoice = findVoiceById(voiceSel.value);
      setStatus('Voz guardada: ' + ((preferredVoice && preferredVoice.name) || 'automática'));
    });
  }
  if (voicePrevBtn) {
    voicePrevBtn.addEventListener('click', function () {
      primeSpeechForApple();
      preferredVoice = findVoiceById(voiceSel && voiceSel.value) || pickHumanVoice();
      speak('Hola, soy el cerebro de ConlineWeb. Así suena mi voz contigo.');
    });
  }

  // Precargar voces humanas
  if (window.speechSynthesis) {
    fillVoiceSelect();
    pickHumanVoice();
    window.speechSynthesis.onvoiceschanged = function () {
      fillVoiceSelect();
      pickHumanVoice();
    };
    window.setTimeout(function () { fillVoiceSelect(); pickHumanVoice(); }, 350);
    window.setTimeout(function () { fillVoiceSelect(); pickHumanVoice(); }, 1200);
  }
})();
