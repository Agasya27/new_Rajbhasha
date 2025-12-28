<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_role(['super_admin','reviewer']);
$pdo = db();

// Approve/Reject actions
if (is_post()) {
  verify_csrf();
  $id = (int)($_POST['id'] ?? 0);
  $action = $_POST['action'] ?? '';
  $comments = trim((string)($_POST['comments'] ?? ''));
  if (in_array($action, ['approve','reject'])) {
    $status = $action === 'approve' ? 'approved' : 'rejected';
    // Update report status
    $pdo->prepare('UPDATE reports SET status=?, updated_at=NOW() WHERE id=?')->execute([$status,$id]);
    // Insert review record
    $pdo->prepare('INSERT INTO report_reviews(report_id, reviewer_id, decision, comments) VALUES(?,?,?,?)')
        ->execute([$id, current_user()['id'], $status, $comments ?: null]);
    // Audit log
    $pdo->prepare('INSERT INTO audit_logs(user_id, action, details) VALUES(?,?,?)')
        ->execute([current_user()['id'], $action, 'report_id='.$id.'; comments='.($comments?:'-')]);
    // Email log to report owner (best-effort)
    $own = $pdo->prepare('SELECT u.email FROM reports r JOIN users u ON u.id=r.user_id WHERE r.id=?');
    $own->execute([$id]);
    $to = (string)$own->fetchColumn();
    if ($to) {
      $subject = 'Report '.$status.' (ID #'.$id.')';
      $body = 'Your report #'.$id.' has been '.$status.( $comments? (". Comments: ".$comments) : '' );
      $pdo->prepare('INSERT INTO email_logs(to_email,subject,body) VALUES(?,?,?)')->execute([$to,$subject,$body]);
    }
    flash_set('success', 'Report '.$status);
    redirect('settings.php');
  }
}

$pending = $pdo->query("SELECT r.*, u.name unit_name, us.name user_name, us.email user_email FROM reports r JOIN units u ON u.id=r.unit_id JOIN users us ON us.id=r.user_id WHERE r.status IN ('submitted','pending') ORDER BY r.id DESC")->fetchAll();

// Helper to check overdue based on current quarter/year
$curQ = (int)ceil(date('n')/3);
$curY = (int)date('Y');

include __DIR__ . '/../../templates/header.php';
?>
<h4>रिपोर्ट अनुमोदन / Report Approval</h4>
<div class="table-responsive">
  <table class="table table-striped">
    <thead><tr><th>ID</th><th>Unit</th><th>Period</th><th>By</th><th>Status</th><th>Reviews</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($pending as $r): ?>
      <?php 
        $isOverdue = ($r['period_year'] < $curY) || ($r['period_year'] == $curY && (int)$r['period_quarter'] < $curQ);
        $rowClass = $isOverdue ? 'table-warning' : '';
        $revSt = $pdo->prepare('SELECT rr.*,u.name reviewer_name FROM report_reviews rr JOIN users u ON u.id=rr.reviewer_id WHERE rr.report_id=? ORDER BY rr.id DESC');
        $revSt->execute([(int)$r['id']]);
        $reviews = $revSt->fetchAll();
      ?>
      <tr class="<?= $rowClass ?>">
        <td>#<?= (int)$r['id'] ?></td>
        <td><?= esc($r['unit_name']) ?></td>
        <td><?= esc('Q'.$r['period_quarter'].' '.$r['period_year']) ?></td>
        <td><?= esc($r['user_name']) ?></td>
        <td>
          <span class="badge bg-secondary"><?= esc($r['status']) ?></span>
          <?php if ($isOverdue): ?><span class="badge bg-danger ms-1">Overdue</span><?php endif; ?>
        </td>
        <td class="small">
          <?php if ($reviews): foreach($reviews as $rv): ?>
            <div>
              <strong><?= esc($rv['reviewer_name']) ?>:</strong>
              <span class="badge bg-<?= $rv['decision']==='approved'?'success':'danger' ?>"><?= esc($rv['decision']) ?></span>
              <em class="text-muted"><?= esc(date('Y-m-d H:i', strtotime($rv['created_at']))) ?></em>
              <?php if (!empty($rv['comments'])): ?>
                <div class="text-muted">“<?= esc($rv['comments']) ?>”</div>
              <?php endif; ?>
            </div>
          <?php endforeach; else: ?>
            <em class="text-muted">No past reviews</em>
          <?php endif; ?>
        </td>
        <td>
          <a class="btn btn-sm btn-outline-primary" href="<?= app_base_url('report/view.php?id='.(int)$r['id']) ?>">View</a>
          <form method="post" class="d-inline">
            <?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="text" class="form-control form-control-sm d-inline-block" style="width:200px" name="comments" placeholder="Comments">
            <button class="btn btn-sm btn-success" name="action" value="approve">Approve</button>
            <button class="btn btn-sm btn-danger" name="action" value="reject">Reject</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$pending): ?><div class="alert alert-info">No reports pending review.</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
