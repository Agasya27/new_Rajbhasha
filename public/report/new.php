<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_role(['officer','super_admin']);
app_session_start();
$pdo = db();
$user = current_user();

// Autosave endpoint
if (isset($_POST['action']) && $_POST['action'] === 'autosave') {
  verify_csrf();
  $data = $_POST;
  unset($data['action'], $data['_csrf']);
  $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
  // Create draft if none exists (per unit/user for current quarter-year)
  $q = (int)($_POST['period_quarter'] ?? 0) ?: (int)ceil(date('n')/3);
  $y = (int)($_POST['period_year'] ?? 0) ?: (int)date('Y');
  // Upsert: try update existing draft first
  $st = $pdo->prepare('SELECT id FROM reports WHERE user_id=? AND unit_id=? AND period_quarter=? AND period_year=? AND status="draft" ORDER BY id DESC LIMIT 1');
  $st->execute([$user['id'],$user['unit_id'],$q,$y]);
  $existing = (int)$st->fetchColumn();
  if ($existing) {
    $pdo->prepare('UPDATE reports SET data_json=?, updated_at=NOW() WHERE id=?')->execute([$payload,$existing]);
    json_response(['ok'=>true,'report_id'=>$existing]);
  } else {
    $pdo->prepare('INSERT INTO reports(user_id, unit_id, status, period_quarter, period_year, data_json, updated_at) VALUES(?,?,?,?,?,?,NOW())')
        ->execute([$user['id'],$user['unit_id'],'draft',$q,$y,$payload]);
    $id = (int)$pdo->lastInsertId();
    json_response(['ok'=>true,'report_id'=>$id]);
  }
}

$message = '';
if (is_post() && isset($_POST['save'])) {
  verify_csrf();
  $q = (int)($_POST['period_quarter'] ?? 0);
  $y = (int)($_POST['period_year'] ?? 0);
  if ($q<1||$q>4) $q = (int)ceil(date('n')/3);
  if ($y<2000) $y = (int)date('Y');

  // Validate numeric fields
  $intFields = ['sec1_total_issued','sec1_issued_in_hindi','sec1_issued_english_only','sec1_issued_hindi_only','sec2_received_in_hindi','sec2_replied_in_hindi','sec2_not_replied_in_hindi'];
  foreach ($intFields as $f) { $_POST[$f] = (int)($_POST[$f] ?? 0); }

  // Cross-field validations
  $total = (int)$_POST['sec1_total_issued'];
  $h = (int)$_POST['sec1_issued_in_hindi'];
  $he = (int)$_POST['sec1_issued_english_only'];
  $ho = (int)$_POST['sec1_issued_hindi_only'];
  if ($ho > $h) {
    flash_set('danger','Section 1: "केवल हिंदी (Hindi-only)" cannot exceed "हिंदी में निर्गत (Issued in Hindi)".');
    redirect('report/new.php');
  }
  if (($h + $he) > $total) {
    flash_set('danger','Section 1: Hindi (total) + English-only cannot exceed Total issued.');
    redirect('report/new.php');
  }
  if ((int)$_POST['sec2_not_replied_in_hindi'] > 0 && empty(trim((string)($_POST['sec2_reason_not_replied'] ?? '')))) {
    flash_set('danger','Please provide reason for items not replied in Hindi (Section 2).');
    redirect('report/new.php');
  }

  $data = $_POST;
  unset($data['_csrf'],$data['save']);
  $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
  $status = $_POST['save'] === 'final' ? 'submitted' : 'draft';
  $pdo->prepare('INSERT INTO reports(user_id, unit_id, status, period_quarter, period_year, data_json, updated_at) VALUES(?,?,?,?,?,?,NOW())')
      ->execute([$user['id'],$user['unit_id'],$status,$q,$y,$payload]);
  $reportId = (int)$pdo->lastInsertId();

  // Warning: Hindi usage % drop >=10% vs previous quarter (non-blocking)
  try {
    $curPct = $total>0 ? (($_POST['sec1_issued_in_hindi']/$total)*100.0) : 0.0;
    $pq = $q>1 ? $q-1 : 4; $py = $q>1 ? $y : ($y-1);
    $stp = $pdo->prepare('SELECT data_json FROM reports WHERE unit_id=? AND status IN ("submitted","approved") AND (period_year<? OR (period_year=? AND period_quarter<?)) ORDER BY period_year DESC, period_quarter DESC LIMIT 1');
    $stp->execute([$user['unit_id'],$y,$y,$q]);
    $prev = $stp->fetchColumn();
    if ($prev) { $pj=json_decode($prev,true)?:[]; $pt=(int)($pj['sec1_total_issued']??0); $ph=(int)($pj['sec1_issued_in_hindi']??0); $pp=$pt>0?($ph/$pt*100.0):0.0; if ($pp - $curPct >= 10.0) { flash_set('warning','Hindi usage % dropped more than 10 points vs previous period.'); }}
  } catch (Throwable $e) { /* ignore */ }

  // Handle attachments
  if (!empty($_FILES['attachments']['name'][0])) {
    $count = count($_FILES['attachments']['name']);
    for ($i=0; $i<$count; $i++) {
      if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
      if ($_FILES['attachments']['size'][$i] > 10*1024*1024) continue; // 10 MB
      $name = $_FILES['attachments']['name'][$i];
      $tmp = $_FILES['attachments']['tmp_name'][$i];
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, ['pdf','png','jpg','jpeg'], true)) continue;
      $safeName = time().'_'.preg_replace('/[^A-Za-z0-9_\.-]/','_', $name);
      $dest = UPLOAD_DIR . DIRECTORY_SEPARATOR . $safeName;
      if (move_uploaded_file($tmp, $dest)) {
        $pdo->prepare('INSERT INTO attachments(report_id,file_name,file_path,mime_type,file_size) VALUES(?,?,?,?,?)')
            ->execute([$reportId,$name,$safeName,mime_content_type($dest) ?: null, filesize($dest)]);
      }
    }
  }

  flash_set('success', $status==='submitted' ? 'रिपोर्ट जमा / Report submitted' : 'ड्राफ्ट सुरक्षित / Draft saved');
  redirect('report/view.php?id='.$reportId);
}

include __DIR__ . '/../../templates/header.php';
echo '<link rel="stylesheet" href="'.app_base_url('assets/css/report.css').'" />';
$reportJsPath = BASE_PATH . '/public/assets/js/report.js';
$reportJsVersion = is_file($reportJsPath) ? (string)filemtime($reportJsPath) : (string)time();
?>
<div class="row">
  <div class="col-lg-8">
    <h3 class="mb-3">रिपोर्ट</h3>
    <form method="post" enctype="multipart/form-data" id="reportForm" data-report-id="">
      <?= csrf_input() ?>
      <!-- Wizard Progress + View Toggle -->
      <div class="mb-3">
        <div class="progress" style="height: 8px;">
          <div class="progress-bar" id="wizProgress" role="progressbar" style="width: 20%"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted mt-1">
          <span>Step 1</span><span>Step 2</span><span>Step 3</span><span>Step 4</span><span>Step 5</span>
        </div>
        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" role="switch" id="toggleFullView">
          <label class="form-check-label" for="toggleFullView">Show full form (all sections)</label>
        </div>
      </div>

      <!-- Step 1: Period -->
      <div class="card mb-3 wizard-step" data-step="1"><div class="card-body">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label required">तिमाही / Quarter</label>
            <select name="period_quarter" class="form-select" required>
              <option value="1">Q1</option><option value="2">Q2</option><option value="3">Q3</option><option value="4">Q4</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label required">वर्ष / Year</label>
            <input type="number" class="form-control" name="period_year" value="<?= esc(date('Y')) ?>" required>
          </div>
        </div>
      </div></div>

      <!-- Step 2: Section 1 -->
      <div class="card mb-3 wizard-step report-section" data-step="2"><div class="card-body">
        <h5 class="card-title">अनुभाग 1 – राजभाषा अधिनियम, 1963 (धारा 3(3))</h5>
        <div class="row g-2">
          <div class="col-md-6 col-lg-3"><label class="form-label" for="total_issued">(क) जारी दस्तावेज़ों की कुल संख्या</label><input id="total_issued" title="Total issued" type="text" class="form-control" name="sec1_total_issued" placeholder="संख्या या पाठ"></div>
          <div class="col-md-6 col-lg-3"><label class="form-label" for="issued_in_hindi">(ख) हिंदी में जारी दस्तावेज़</label><input id="issued_in_hindi" title="Issued in Hindi (total)" type="text" class="form-control" name="sec1_issued_in_hindi" placeholder="संख्या या पाठ"></div>
          <div class="col-md-6 col-lg-3"><label class="form-label" for="issued_english_only">(ग) केवल अंग्रेज़ी में जारी किए गए दस्तावेज़</label><input id="issued_english_only" title="English-only" type="text" class="form-control" name="sec1_issued_english_only" placeholder="संख्या या पाठ"></div>
          <div class="col-md-6 col-lg-3"><label class="form-label" for="issued_hindi_only">(घ) केवल हिन्दी में जारी किए गए दस्तावेज़</label><input id="issued_hindi_only" title="Hindi-only" type="text" class="form-control" name="sec1_issued_hindi_only" placeholder="संख्या या पाठ"></div>
        </div>
        <div class="small text-muted mt-2" id="liveHindiPct"></div>
        <div class="small mt-1" id="sec1Help"></div>
      </div></div>

      <!-- Step 3: Section 2 -->
      <div class="card mb-3 wizard-step" data-step="3"><div class="card-body">
        <h5 class="card-title">अनुभाग 2 – हिंदी में प्राप्त पत्र (नियम-5)</h5>
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label" title="Letters received in Hindi">प्राप्त हिंदी पत्र</label><input type="text" class="form-control" name="sec2_received_in_hindi" placeholder="संख्या या पाठ"></div>
          <div class="col-md-3"><label class="form-label" title="Replied in Hindi">हिंदी में उत्तर</label><input type="text" class="form-control" name="sec2_replied_in_hindi" placeholder="संख्या या पाठ"></div>
          <div class="col-md-3"><label class="form-label" title="Not replied in Hindi">हिंदी में उत्तर नहीं</label><input type="text" class="form-control" name="sec2_not_replied_in_hindi" placeholder="संख्या या पाठ"></div>
          <div class="col-md-3"><label class="form-label" title="Reason for not replying in Hindi">कारण</label><input type="text" class="form-control" name="sec2_reason_not_replied" placeholder="कारण लिखें"></div>
        </div>
        <div class="mt-2">
          <label class="form-label" for="sec2_note" title="Additional note">💬 छोटा नोट</label>
          <input type="text" id="sec2_note" name="sec2_note" class="form-control" placeholder="💬 नोट लिखें...">
        </div>
      </div></div>

      <!-- Step 4: Section 3 (dynamic rows) -->
      <div class="card mb-3 wizard-step" data-step="4"><div class="card-body">
        <h5 class="card-title">अनुभाग 3 – अंग्रेजी में प्राप्त पत्रों के उत्तर हिंदी में</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>क्रम</th><th title="Total received">कुल</th><th title="Replied in Hindi">हिंदी में उत्तर</th><th title="Replied in English">अंग्रेजी में उत्तर</th><th title="Remarks">टिप्पणी</th><th></th></tr></thead>
            <tbody id="sec3Rows"></tbody>
          </table>
        </div>
        <input type="hidden" name="sec3_rows_json" id="sec3_rows_json" value="[]">
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddSec3Row">+ Add Row</button>
      </div></div>

      <!-- Step 5: Section 4,5,6 and attachments -->
      <div class="card mb-3 wizard-step" data-step="5"><div class="card-body">
        <h5 class="card-title">अनुभाग 4 – मूल रूप से भेजे गए पत्र</h5>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>श्रेणी</th><th title="Hindi">हिंदी</th><th title="English">अंग्रेजी</th><th title="Bilingual">द्विभाषी</th></tr></thead>
            <tbody>
              <?php $cats=['क) केंद्र/राज्य','ख) विभाग/क्षेत्र','ग) अन्य']; foreach($cats as $idx=>$label): $k=$idx+1; ?>
              <tr>
                <td><?= esc($label) ?></td>
                <td><input type="text" inputmode="numeric" pattern="[0-9,\.\s-]*" class="form-control" name="sec4_hindi_<?= $k ?>" title="Hindi" placeholder="संख्या या पाठ"></td>
                <td><input type="text" inputmode="numeric" pattern="[0-9,\.\s-]*" class="form-control" name="sec4_english_<?= $k ?>" title="English" placeholder="संख्या या पाठ"></td>
                <td><input type="text" inputmode="numeric" pattern="[0-9,\.\s-]*" class="form-control" name="sec4_bilingual_<?= $k ?>" title="Bilingual" placeholder="संख्या या पाठ"></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div></div>

      <div class="card mb-3 wizard-step" data-step="5"><div class="card-body">
        <h5 class="card-title">अनुभाग 5 – फाइलें / ई-ऑफिस</h5>
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label" title="Files in Hindi">हिंदी फाइलें</label><input type="text" inputmode="numeric" pattern="[0-9,\.\s-]*" class="form-control" name="sec5_files_hindi" placeholder="संख्या या पाठ"></div>
          <div class="col-md-3"><label class="form-label" title="Pending files">लंबित</label><input type="text" inputmode="numeric" pattern="[0-9,\.\s-]*" class="form-control" name="sec5_files_pending" placeholder="संख्या या पाठ"></div>
          <div class="col-md-3"><label class="form-label" title="Delayed files">विलंबित</label><input type="text" inputmode="numeric" pattern="[0-9,\.\s-]*" class="form-control" name="sec5_files_delayed" placeholder="संख्या या पाठ"></div>
          <div class="col-md-3"><label class="form-label" title="Remarks">टिप्पणी</label><input type="text" class="form-control" name="sec5_remarks"></div>
        </div>
      </div></div>

      <div class="card mb-3 wizard-step report-section" data-step="5"><div class="card-body">
        <h5 class="card-title">अनुभाग 6 – प्रशिक्षण / कार्यक्रम</h5>
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label">कार्यक्रम</label><input type="text" inputmode="numeric" pattern="[0-9,\.\s-]*" class="form-control" name="sec6_events" placeholder="संख्या या पाठ"></div>
          <div class="col-md-3"><label class="form-label">प्रतिभागी</label><input type="text" inputmode="numeric" pattern="[0-9,\.\s-]*" class="form-control" name="sec6_participants" placeholder="संख्या या पाठ"></div>
          <div class="col-md-6"><label class="form-label">फोटो अपलोड (PDF/JPG/PNG)</label><input type="file" id="fileUpload" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple><div class="small-muted mt-1">Max 10MB each. Allowed: PDF/JPG/PNG</div><ul id="uploadList" class="mt-2"></ul></div>
        </div>
      </div></div>

      <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="me-auto">
          <button type="button" class="btn btn-outline-secondary" id="btnPrev">⟵ Previous</button>
          <button type="button" class="btn btn-outline-primary" id="btnNext">Next ⟶</button>
        </div>
        <span class="text-muted small me-2">अंतिम सुरक्षित: <span id="lastSavedAt">—</span></span>
        <button type="button" class="btn btn-light" onclick="window.print()">Print Preview</button>
        <button type="button" id="btnSaveDraft" class="btn btn-secondary">ड्राफ्ट सुरक्षित / Save Draft</button>
        <button type="button" id="btnSubmitFinal" class="btn btn-primary">अंतिम जमा / Submit Final</button>
      </div>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="assistant-sidebar">
      <div class="card mb-3"><div class="card-body">
        <h6 class="card-title">Smart Rajbhasha Sahayak (स्मार्ट राजभाषा सहायक)</h6>
        <button class="btn btn-sm btn-outline-success mb-2" id="btnSuggest">स्मार्ट सुझाव / Smart Suggestions</button>
        <div id="suggestions" class="small text-muted"></div>
        <hr>
        <div class="input-group input-group-sm">
          <input type="text" class="form-control" id="txtTranslate" placeholder="पाठ लिखें / Enter text">
          <button class="btn btn-outline-secondary" id="btnHiEn">Hi→En</button>
          <button class="btn btn-outline-secondary" id="btnEnHi">En→Hi</button>
        </div>
        <div id="translateOut" class="small mt-2"></div>
      </div></div>
    </div>
  </div>
</div>

<script>
// Wizard controller + Full view toggle
let currentStep = 1; const totalSteps = 5; const FULL_KEY='report_full_view';
const btnPrev = document.getElementById('btnPrev');
const btnNext = document.getElementById('btnNext');
const prog = document.getElementById('wizProgress');
const fullSwitch = document.getElementById('toggleFullView');

function applyFullView(full){
  const steps = document.querySelectorAll('.wizard-step');
  steps.forEach(s=> s.style.display = full ? '' : 'none');
  if (!full) {
    steps.forEach(s=>{ if (parseInt(s.getAttribute('data-step'))===currentStep) s.style.display=''; });
  }
  if (btnPrev && btnNext) {
    btnPrev.style.display = full ? 'none' : '';
    btnNext.style.display = full ? 'none' : '';
  }
  if (prog) prog.style.width = full ? '100%' : (currentStep*100/totalSteps)+'%';
}

function showStep(n){
  currentStep = Math.min(Math.max(1,n), totalSteps);
  const full = !!(fullSwitch && fullSwitch.checked);
  applyFullView(full);
}

if (fullSwitch){
  // initialize from URL (?view=full) or localStorage
  const urlFull = new URLSearchParams(location.search).get('view')==='full';
  const savedFull = localStorage.getItem(FULL_KEY)==='1';
  fullSwitch.checked = urlFull || savedFull;
  fullSwitch.addEventListener('change', ()=>{
    try { localStorage.setItem(FULL_KEY, fullSwitch.checked ? '1':'0'); } catch(e){}
    applyFullView(fullSwitch.checked);
  });
}

document.getElementById('btnPrev').addEventListener('click', ()=>showStep(currentStep-1));
document.getElementById('btnNext').addEventListener('click', ()=>showStep(currentStep+1));
showStep(1);

// Live Hindi % in Section 1 (parse from free text)
function parseNum(val){
  const m = String(val||'').match(/-?\d+(?:[\.,]\d+)?/);
  return m ? Number(m[0].replace(',', '.')) : 0;
}
function updateHindiPct(){
  const t = parseNum(document.querySelector('[name="sec1_total_issued"]').value);
  const h = parseNum(document.querySelector('[name="sec1_issued_in_hindi"]').value);
  const he = parseNum(document.querySelector('[name="sec1_issued_english_only"]').value);
  const ho = parseNum(document.querySelector('[name="sec1_issued_hindi_only"]').value);
  const pct = (t>0)? ((h/t)*100).toFixed(1): '0.0';
  document.getElementById('liveHindiPct').textContent = `Hindi Usage: ${pct}%`;
  const help = document.getElementById('sec1Help');
  let ok = true; let msg = '';
  if (ho > h) { ok=false; msg = 'Hindi-only should not exceed Hindi (total).'; }
  else if ((h+he) > t) { ok=false; msg = 'Hindi (total) + English-only should not exceed Total issued.'; }
  help.className = 'small mt-1 ' + (ok ? 'text-success':'text-danger');
  help.textContent = ok ? 'OK: Hindi (total) includes only-Hindi + bilingual. Keep Hindi-only ≤ Hindi (total) and Hindi (total) + English-only ≤ Total.' : `Fix: ${msg}`;
  // Toggle submit buttons
  document.querySelectorAll('button[type="submit"]').forEach(b=> b.disabled = !ok);
}
['sec1_total_issued','sec1_issued_in_hindi','sec1_issued_english_only','sec1_issued_hindi_only'].forEach(n=>{
  const el = document.querySelector(`[name="${n}"]`); if (el) el.addEventListener('input', updateHindiPct);
});
updateHindiPct();

// autosave now handled by assets/js/report.js


// Removed numeric-only mask and number-only validation to allow free text input as requested

// Offline backup to localStorage
const LS_KEY = 'rajbhasha_report_backup';
function saveBackup(){
  const form = document.getElementById('reportForm');
  const data = {};
  new FormData(form).forEach((v,k)=>{ if (k !== '_csrf') data[k]=v; });
  try { localStorage.setItem(LS_KEY, JSON.stringify({ts: Date.now(), data})); } catch(e) {}
}
document.getElementById('reportForm').addEventListener('input', saveBackup);
// Restore if present
try {
  const raw = localStorage.getItem(LS_KEY);
  if (raw){ const obj = JSON.parse(raw); const d = obj.data||{}; for (const k in d){ const el = document.querySelector(`[name="${k}"]`); if (el && !el.value){ el.value = d[k]; } }
  }
} catch(e) {}
</script>
<script src="<?= app_base_url('assets/js/report.js') ?>?v=<?= esc($reportJsVersion) ?>"></script>
<!-- Suggestions Modal -->
<div class="modal fade" id="suggestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">सुझाव</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><div id="suggestModalBody" class="small"></div></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="applySuggestionsBtn">Apply</button>
      </div>
    </div>
  </div>
  </div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
