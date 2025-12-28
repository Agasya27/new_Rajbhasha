<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_login();
include __DIR__ . '/../../templates/header.php';
?>
<div class="row">
  <div class="col-12 col-lg-8">
    <div class="card mb-3">
      <div class="card-header">Chat (OpenRouter)</div>
      <div class="card-body">
        <div id="assistant-chat" class="border rounded p-2" style="min-height:180px; white-space:pre-wrap;"></div>
        <div class="input-group mt-2">
          <input id="assistant-input" class="form-control" placeholder="Ask the assistant..." />
          <button id="assistant-send" class="btn btn-primary">Send</button>
        </div>
        <small class="text-muted d-block mt-2">Requires login. Uses your local OpenRouter configuration; answers are contextual to this portal.</small>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">OCR (Hindi + English)</div>
      <div class="card-body">
        <form id="ocr-form">
          <div class="row g-2">
            <div class="col-md-8">
              <input type="file" id="ocr-file" name="file" accept=".png,.jpg,.jpeg,.tif,.tiff,.pdf" class="form-control" />
            </div>
            <div class="col-md-3">
              <select name="lang" id="ocr-lang" class="form-select">
                <option value="hin+eng">Hindi + English</option>
                <option value="hin">Hindi</option>
                <option value="eng">English</option>
              </select>
            </div>
            <div class="col-md-1">
              <button class="btn btn-success w-100" id="ocr-run" type="submit">Run</button>
            </div>
          </div>
        </form>
        <div id="ocr-status" class="form-text mt-2"></div>
        <pre id="ocr-out" class="p-2 border rounded mt-2" style="min-height:120px;"></pre>
        <small class="text-muted">Images → Tesseract; Digital PDFs → pdftotext; Scanned PDFs → ImageMagick → Tesseract.</small>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const csrf = window.CSRF_TOKEN || '';
  const base = '<?= esc(APP_BASE_URL) ?>';
  // Chat
  const chatEl = document.getElementById('assistant-chat');
  const inputEl = document.getElementById('assistant-input');
  document.getElementById('assistant-send').addEventListener('click', async ()=>{
    const prompt = inputEl.value.trim(); if(!prompt) return;
    chatEl.textContent += (chatEl.textContent?"\n\n":"") + "You: " + prompt;
    try{
      const resp = await fetch(base+'assistant/openrouter_chat.php', {
        method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, credentials:'include',
        body:new URLSearchParams({ prompt, _csrf: csrf })
      });
      const ct = resp.headers.get('content-type')||'';
      if(!ct.includes('application/json')){
        const raw = await resp.text();
        chatEl.textContent += "\n\nAssistant: " + (raw||'Please log in or check server.');
        return;
      }
      const data = await resp.json();
      if (data && data.status === 'ok') {
        chatEl.textContent += "\n\nAssistant: " + (data.reply || '');
      } else {
        chatEl.textContent += "\n\nAssistant: " + (data?.error || data?.message || 'Error');
      }
    }catch(e){ chatEl.textContent += "\n\nAssistant: Network error."; }
  });

  // OCR
  document.getElementById('ocr-form').addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    const fEl = document.getElementById('ocr-file');
    const lang = document.getElementById('ocr-lang').value;
    const status = document.getElementById('ocr-status');
    const out = document.getElementById('ocr-out');
    out.textContent='';
    if(!fEl.files || !fEl.files[0]){ status.textContent='Please choose a file.'; return; }
    status.textContent='Running...';
    const fd = new FormData(); fd.append('file', fEl.files[0]); fd.append('lang', lang); fd.append('_csrf', csrf);
    try{
      const resp = await fetch(base+'assistant/ocr.php', { method:'POST', body: fd, credentials:'include' });
      const ct = resp.headers.get('content-type')||'';
      if(!ct.includes('application/json')){ out.textContent = await resp.text(); status.textContent='Server response'; return; }
      const data = await resp.json();
      if(data.ok){ out.textContent = data.text || ''; status.textContent = `${data.meta?.method||'ocr'} (${data.meta?.lang||lang})`; }
      else { out.textContent = data.error||'Error'; status.textContent='Error'; }
    }catch(e){ status.textContent='Network error'; }
  });
})();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
