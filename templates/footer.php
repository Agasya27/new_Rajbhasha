  </main>
  <!-- Use local Bootstrap bundle to avoid CDN tracking prevention issues -->
  <script src="<?= app_base_url('assets/vendor/bootstrap.bundle.min.js') ?>"></script>
  <script>
    // Fallback to CDN if local bootstrap failed to load
    (function(){
      try {
        // Bootstrap 5 exposes window.bootstrap when bundle loads
        if (typeof window.bootstrap === 'undefined') {
          var s = document.createElement('script');
          s.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
          s.crossOrigin = 'anonymous';
          document.head.appendChild(s);
        }
      } catch(e){}
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="<?= app_base_url('assets/js/main.js') ?>"></script>
  <!-- Floating Assistant Widget -->
  <style>
    .assistant-fab { position: fixed; right: 20px; bottom: 20px; z-index: 1055; }
    .assistant-modal .dropzone { border: 2px dashed #adb5bd; padding: 16px; text-align:center; border-radius:8px; color:#6c757d; }
    .chat-box { border: 1px solid #dee2e6; border-radius: 8px; height: 260px; overflow-y: auto; padding: 8px; background: #f8f9fa; }
    .chat-msg { margin: 6px 0; max-width: 80%; padding: 6px 10px; border-radius: 10px; white-space: pre-wrap; }
    .chat-user { background: #d1e7dd; margin-left: auto; }
    .chat-assistant { background: #e9ecef; margin-right: auto; }
    .chat-input { resize: none; }
  </style>
  <button type="button" class="btn btn-primary rounded-circle assistant-fab" title="Smart Sahayak" data-bs-toggle="modal" data-bs-target="#assistantModal">💬</button>

  <div class="modal fade assistant-modal" id="assistantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Smart Rajbhasha Sahayak</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <h6 class="mb-2">Chat</h6>
              <div id="chatBox" class="chat-box small">
                <div class="chat-msg chat-assistant">नमस्ते! How can I help you?</div>
              </div>
              <div class="input-group input-group-sm mt-2">
                <textarea id="chatPrompt" class="form-control chat-input" rows="2" placeholder="Type your question..."></textarea>
                <button class="btn btn-primary" id="chatSend">Send</button>
              </div>
              <div id="chatErr" class="text-danger small mt-1" style="display:none"></div>
              <hr>
            </div>
            <div class="col-md-6">
              <h6>Translate</h6>
              <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control" id="asstTxt" placeholder="Enter text / पाठ लिखें">
                <button class="btn btn-outline-secondary" id="asstHiEn">Hi→En</button>
                <button class="btn btn-outline-secondary" id="asstEnHi">En→Hi</button>
              </div>
              <div id="asstOut" class="small text-muted"></div>
            </div>
            <div class="col-md-6">
              <h6>OCR</h6>
              <div class="d-flex align-items-center gap-2 mb-2">
                <div id="ocrDrop" class="dropzone flex-fill">Drag & drop image/PDF here or click to choose</div>
              </div>
              <div class="input-group input-group-sm mt-2" style="max-width: 280px;">
                <label class="input-group-text" for="ocrLang">Lang</label>
                <select id="ocrLang" class="form-select">
                  <option value="hin+eng" selected>Hindi+English</option>
                  <option value="hin">Hindi</option>
                  <option value="eng">English</option>
                </select>
              </div>
              <input type="file" id="ocrFile" accept=".png,.jpg,.jpeg,.pdf" class="form-control form-control-sm mt-2" style="display:none">
              <div class="d-flex gap-2 mt-2">
                <button type="button" id="ocrCopy" class="btn btn-sm btn-outline-secondary">Copy Text</button>
                <button type="button" id="ocrDownload" class="btn btn-sm btn-outline-secondary">Download .txt</button>
                <div id="ocrBusy" class="small text-muted" style="display:none">Processing…</div>
              </div>
              <pre id="ocrOut" class="small mt-2" style="white-space: pre-wrap;"></pre>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // --- Chat (OpenRouter) ---
    (function(){
      const box = document.getElementById('chatBox');
      const promptEl = document.getElementById('chatPrompt');
      const sendBtn = document.getElementById('chatSend');
      const errEl = document.getElementById('chatErr');
      if (!box || !promptEl || !sendBtn) return;
      function appendMsg(text, who){
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (who==='user' ? 'chat-user' : 'chat-assistant');
        div.textContent = text;
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
      }
      async function doSend(){
        const t = (promptEl.value || '').trim();
        if (!t) return;
        errEl.style.display = 'none';
        appendMsg(t, 'user');
        promptEl.value='';
        const fd = new FormData();
        fd.append('_csrf', window.CSRF_TOKEN || '');
        fd.append('prompt', t);
        try{
          const res = await fetch('<?= app_base_url('assistant/openrouter_chat.php') ?>', {
            method:'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' }
          });
          const ct = res.headers.get('content-type') || '';
          if (!ct.includes('application/json')) {
            const txt = await res.text();
            if (res.status === 401 || /login/i.test(txt)) { errEl.textContent = 'Please log in and try again.'; }
            else { errEl.textContent = 'Server error ('+res.status+').'; }
            errEl.style.display = '';
            return;
          }
          const j = await res.json();
          if (j.status === 'ok') { appendMsg(j.reply || '(no reply)', 'assistant'); }
          else { errEl.textContent = j.message || 'Assistant error'; errEl.style.display = ''; }
        }catch(e){ errEl.textContent = 'Network error'; errEl.style.display = ''; }
      }
      sendBtn.addEventListener('click', doSend);
      promptEl.addEventListener('keydown', (e)=>{ if (e.key==='Enter' && !e.shiftKey){ e.preventDefault(); doSend(); } });
    })();

    async function asstTranslate(dir){
      const fd = new FormData();
      fd.append('_csrf', window.CSRF_TOKEN);
      fd.append('text', document.getElementById('asstTxt').value);
      fd.append('direction', dir);
      const res = await fetch('<?= app_base_url('assistant/translate.php') ?>', { method:'POST', body: fd });
      const j = await res.json();
      document.getElementById('asstOut').textContent = j.translated || '';
    }
    const hiEnBtn = document.getElementById('asstHiEn');
    const enHiBtn = document.getElementById('asstEnHi');
    if (hiEnBtn && enHiBtn){
      hiEnBtn.onclick = ()=>asstTranslate('hi-en');
      enHiBtn.onclick = ()=>asstTranslate('en-hi');
    }
    const dz = document.getElementById('ocrDrop');
    const fileInput = document.getElementById('ocrFile');
    if (dz && fileInput){
      function uploadFile(f){
        const fd = new FormData(); fd.append('_csrf', window.CSRF_TOKEN); fd.append('file', f);
        const langSel = document.getElementById('ocrLang'); if (langSel) fd.append('lang', langSel.value || 'hin+eng');
        const busy = document.getElementById('ocrBusy'); if (busy) busy.style.display='';
        fetch('<?= app_base_url('assistant/ocr.php') ?>', {
          method:'POST',
          body: fd,
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' }
        }).then(async r=>{
          if (busy) busy.style.display='none';
          const ct = r.headers.get('content-type')||'';
          if (!ct.includes('application/json')){
            const txt = await r.text();
            let s = 'OCR Error\n';
            if (r.status === 401 || /login/i.test(txt)) s += 'Please log in and try again.'; else s += 'Server error ('+r.status+').';
            document.getElementById('ocrOut').textContent = s;
            return null;
          }
          return r.json();
        }).then(j=>{
          if (!j) return;
          let s = j.text || '';
          if (j.stats) { s = `Words: ${j.stats.words} | Chars: ${j.stats.chars} | Lines: ${j.stats.lines}` + (j.meta? ` | Method: ${j.meta.method||''} | Lang: ${j.meta.lang||''}`:'') + `\n\n` + s; }
          if (!j.ok && j.error) { s = 'OCR Error:\n' + j.error; }
          document.getElementById('ocrOut').textContent = s || JSON.stringify(j);
        }).catch(()=>{ document.getElementById('ocrOut').textContent = 'Error performing OCR.'; });
      }
      dz.addEventListener('click', ()=>fileInput.click());
      dz.addEventListener('dragover', e=>{ e.preventDefault(); dz.classList.add('bg-light'); });
      dz.addEventListener('dragleave', e=>{ dz.classList.remove('bg-light'); });
      dz.addEventListener('drop', e=>{ e.preventDefault(); dz.classList.remove('bg-light'); if (e.dataTransfer.files.length) uploadFile(e.dataTransfer.files[0]); });
      fileInput.addEventListener('change', e=>{ if (e.target.files.length) uploadFile(e.target.files[0]); });
    }

    // Copy/Download actions
    const ocrCopy = document.getElementById('ocrCopy');
    const ocrDownload = document.getElementById('ocrDownload');
    if (ocrCopy) ocrCopy.addEventListener('click', async ()=>{
      const txt = document.getElementById('ocrOut').textContent || '';
      try { await navigator.clipboard.writeText(txt); ocrCopy.textContent='Copied!'; setTimeout(()=>ocrCopy.textContent='Copy Text', 1200); } catch {}
    });
    if (ocrDownload) ocrDownload.addEventListener('click', ()=>{
      const txt = document.getElementById('ocrOut').textContent || '';
      const blob = new Blob([txt], {type:'text/plain;charset=utf-8'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = 'ocr_result.txt'; a.click(); setTimeout(()=>URL.revokeObjectURL(url), 1000);
    });
  </script>
</body>
</html>
