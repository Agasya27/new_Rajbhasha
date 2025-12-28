(function(){
	const form = document.getElementById('reportForm');
	if (!form) return;
		const saveInfo = document.getElementById('lastSavedAt');
		const metaCsrf = document.querySelector('meta[name="csrf-token"]');
		const csrf = (metaCsrf && metaCsrf.content) || (window.CSRF_TOKEN || '');
	// Parse a number out of free text (supports commas/decimals)
	const parseNum = (v)=>{ const m = String(v||'').match(/-?\d+(?:[\.,]\d+)?/); return m ? Number(m[0].replace(',', '.')) : 0; };
	let dirty = false;
	let reportId = Number(form.dataset.reportId || 0) || null;
	// Section 3 dynamic rows state
	let sec3 = [];

	// Mark dirty on input change
	form.addEventListener('input', ()=>{ dirty = true; });

	async function autosave(){
		if (!dirty) return;
		const data = Object.fromEntries(new FormData(form).entries());
		data._csrf = csrf;
		if (reportId) data.report_id = reportId;
		try{
			const res = await fetch('save_ajax.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf}, body: JSON.stringify(data)});
			const j = await res.json();
			if (j.status === 'ok') {
				reportId = j.data.report_id;
				form.dataset.reportId = reportId;
				dirty = false;
				if (saveInfo) saveInfo.textContent = j.data.saved_at;
			}
		}catch(e){ /* ignore transient errors */ }
	}
	setInterval(autosave, 30000);

	// Save Draft button
	const btnDraft = document.getElementById('btnSaveDraft');
	if (btnDraft){
		btnDraft.addEventListener('click', async (e)=>{
			e.preventDefault();
			await autosave();
			alert('ड्राफ्ट सुरक्षित');
		});
	}

	// Submit Final: do simple client-side validation then POST to submit.php
	const btnSubmit = document.getElementById('btnSubmitFinal');
	if (btnSubmit){
		btnSubmit.addEventListener('click', async (e)=>{
			e.preventDefault();
			// Basic checks (parse from free text)
			const parseNum = (v)=>{ const m = String(v||'').match(/-?\d+(?:[\.,]\d+)?/); return m ? Number(m[0].replace(',', '.')) : 0; };
			const total = parseNum(form.sec1_total_issued?.value);
			const h = parseNum(form.sec1_issued_in_hindi?.value);
			const he = parseNum(form.sec1_issued_english_only?.value);
			const ho = parseNum(form.sec1_issued_hindi_only?.value);
			if (ho > h) { alert('केवल हिंदी, हिंदी में निर्गत से अधिक नहीं हो सकती।'); return; }
			if ((h+he) > total) { alert('हिंदी + केवल अंग्रेज़ी, कुल निर्गत से अधिक नहीं हो सकते।'); return; }
			if (!reportId) { await autosave(); }
			if (!reportId) { alert('Report not created yet. Try again.'); return; }
			const fd = new FormData(form);
			fd.append('_csrf', csrf);
			fd.append('report_id', String(reportId));
			try{
				const res = await fetch('submit.php', { method:'POST', body: fd });
				if (res.redirected) { window.location = res.url; return; }
				const j = await res.json().catch(()=>null);
				if (!j || j.status==='error') alert(j?.message || 'Submit failed');
			}catch(e){ alert('Network error'); }
		});
	}

	// Upload attachments via AJAX
	const up = document.getElementById('fileUpload');
	const upList = document.getElementById('uploadList');
	if (up){
		up.addEventListener('change', async ()=>{
			if (!reportId) { await autosave(); }
			if (!reportId) { alert('Please save draft first.'); return; }
			const files = up.files; if (!files || !files.length) return;
			for (const f of files){
				const fd = new FormData();
				fd.append('_csrf', csrf);
				fd.append('report_id', String(reportId));
				fd.append('file', f);
				try{
					const res = await fetch('upload_attachment.php', { method:'POST', body: fd });
					const j = await res.json();
					if (j.status==='ok' && upList){
						const li = document.createElement('li');
						li.dataset.attId = j.data.attachment_id;
						li.innerHTML = `${j.data.file_name} (${Math.round(j.data.size/1024)} KB)
							<button type="button" class="btn btn-sm btn-link text-danger ms-2" data-action="del-att">Delete</button>`;
						upList.appendChild(li);
					} else { alert(j.message || 'Upload failed'); }
				}catch(e){ alert('Upload error'); }
			}
			up.value = '';
		});
	}

	// Handle delete attachment clicks
	if (upList){
		upList.addEventListener('click', async (e)=>{
			const btn = e.target.closest('[data-action="del-att"]');
			if (!btn) return;
			const li = btn.closest('li');
			const attId = li?.dataset?.attId;
			if (!attId) return;
			if (!confirm('Delete this attachment?')) return;
			const fd = new FormData();
			fd.append('_csrf', csrf);
			fd.append('attachment_id', attId);
			try{
				const res = await fetch('delete_attachment.php', { method:'POST', body: fd });
				const j = await res.json();
				if (j.status === 'ok') { li.remove(); }
				else { alert(j.message || 'Delete failed'); }
			}catch(err){ alert('Network error'); }
		});
	}

	// Smart Assistant buttons
	const btnSuggest = document.getElementById('btnSuggest');
	if (btnSuggest){
		btnSuggest.addEventListener('click', async ()=>{
			const snap = Object.fromEntries(new FormData(form).entries());
			const fd = new FormData(); fd.append('_csrf', csrf); fd.append('snapshot', JSON.stringify(snap));
			try{ const res = await fetch('../assistant/suggest.php', { method:'POST', body: fd }); const j = await res.json();
				if (j){
					const body = document.getElementById('suggestModalBody');
					body.innerHTML = '';
					if (j.tips) j.tips.forEach(t=> body.innerHTML += '<div>• '+t+'</div>');
					if (j.suggestions){ body.innerHTML += '<hr><div class="text-muted">Suggested values will be applied on Apply.</div>'; }
					const modal = new bootstrap.Modal(document.getElementById('suggestModal'));
					const applyBtn = document.getElementById('applySuggestionsBtn');
					applyBtn.onclick = ()=>{
						if (j.suggestions){ for (const k in j.suggestions){ if (form[k] && j.suggestions[k]!==null && j.suggestions[k]!==undefined) { form[k].value = j.suggestions[k]; form.dispatchEvent(new Event('input', {bubbles:true})); } } }
						modal.hide();
					};
					modal.show();
				}
			}catch(e){ alert('Assistant error'); }
		});
	}

	const btnHiEn = document.getElementById('btnHiEn');
	const btnEnHi = document.getElementById('btnEnHi');
	const txtTranslate = document.getElementById('txtTranslate');
	const translateOut = document.getElementById('translateOut');
	async function doTrans(to){
		try{ const res = await fetch('../assistant/translate.php?to='+encodeURIComponent(to)+'&text='+encodeURIComponent(txtTranslate.value)); const j = await res.json(); translateOut.textContent = j.translated || ''; }catch(e){}
	}
	if (btnHiEn) btnHiEn.onclick = ()=>doTrans('en');
	if (btnEnHi) btnEnHi.onclick = ()=>doTrans('hi');

	// ----- Section 3 dynamic rows handling -----
	let sec3Rows = document.getElementById('sec3Rows');
	let sec3Hidden = document.getElementById('sec3_rows_json');
	const btnAddSec3Row = document.getElementById('btnAddSec3Row');

	function ensureSec3Ready(){
		if (!sec3Rows) sec3Rows = document.getElementById('sec3Rows');
		if (!sec3Hidden) sec3Hidden = document.getElementById('sec3_rows_json');
		return !!(sec3Rows && sec3Hidden);
	}

	function syncSec3Hidden(){ if (!ensureSec3Ready()) return; sec3Hidden.value = JSON.stringify(sec3); dirty = true; }
	function renderSec3(){
		if (!ensureSec3Ready()) return;
		sec3Rows.innerHTML = '';
		sec3.forEach((row, idx)=>{
			const tr = document.createElement('tr');
			tr.innerHTML = `<td>${idx+1}</td>
				<td><input type="text" class="form-control" inputmode="numeric" pattern="[0-9,\.\s-]*" value="${row.total||''}"></td>
				<td><input type="text" class="form-control" inputmode="numeric" pattern="[0-9,\.\s-]*" value="${row.hi||''}"></td>
				<td><input type="text" class="form-control" inputmode="numeric" pattern="[0-9,\.\s-]*" value="${row.en||''}"></td>
				<td><input type="text" class="form-control" value="${row.rem||''}"></td>
				<td><button type="button" class="btn btn-sm btn-outline-danger">✕</button></td>`;
			// Append row first to avoid any exception preventing visible rendering
			sec3Rows.appendChild(tr);
			const iTotal = tr.children[1]?.querySelector('input');
			const iHi    = tr.children[2]?.querySelector('input');
			const iEn    = tr.children[3]?.querySelector('input');
			const iRem   = tr.children[4]?.querySelector('input');
			const btnDel = tr.children[5]?.querySelector('button');
			if (iTotal) iTotal.addEventListener('input', ()=>{ sec3[idx].total = parseNum(iTotal.value); syncSec3Hidden(); });
			if (iHi)    iHi.addEventListener('input',    ()=>{ sec3[idx].hi = parseNum(iHi.value); syncSec3Hidden(); });
			if (iEn)    iEn.addEventListener('input',    ()=>{ sec3[idx].en = parseNum(iEn.value); syncSec3Hidden(); });
			if (iRem)   iRem.addEventListener('input',   ()=>{ sec3[idx].rem = iRem.value; syncSec3Hidden(); });
			if (btnDel) btnDel.addEventListener('click', ()=>{ sec3.splice(idx,1); renderSec3(); syncSec3Hidden(); });
		});
	}
	function addSec3Row(){ sec3.push({total:0,hi:0,en:0,rem:''}); renderSec3(); syncSec3Hidden(); }
	// Direct binding if present
	if (btnAddSec3Row){ btnAddSec3Row.addEventListener('click', (e)=>{ e.preventDefault(); addSec3Row(); }); }
	// Delegated binding as fallback (robust if DOM changes)
	document.addEventListener('click', (e)=>{
		const trg = e.target.closest('#btnAddSec3Row');
		if (!trg) return;
		e.preventDefault();
		addSec3Row();
	});
	// Initialize once DOM is ready and elements are present
	function initSec3(){
		ensureSec3Ready();
		try{ const parsed = JSON.parse((sec3Hidden && sec3Hidden.value) || '[]'); if (Array.isArray(parsed) && parsed.length) sec3 = parsed; }catch(e){}
		if (sec3.length===0){ addSec3Row(); }
		else { renderSec3(); syncSec3Hidden(); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initSec3); }
	else { initSec3(); }

	// Expose a safe global fallback the inline button can call
	window.__addSec3Row = function(){ addSec3Row(); };
})();