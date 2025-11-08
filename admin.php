<?php
// =======================================================
// admin_cashouts.php — Full Admin + JSON API + View Info Modal
// =======================================================
ob_start();
require 'config.php';
session_start();

require_once 'payout_lib.php';

// Limits (hard rule)
const MIN_CASHOUT = 0.00;
const MAX_CASHOUT = 100.00;

// --- API Endpoint handling first ---
if (isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
        echo json_encode(['ok'=>false,'error'=>'Access denied']); exit;
    }
    if (!isset($pdo) || !$pdo instanceof PDO) {
        echo json_encode(['ok'=>false,'error'=>'Database not connected']); exit;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    try {
        switch ($action) {

            case 'list': {
                // --- Filters ---
                $status     = trim($_GET['status']     ?? '');
                $method     = trim($_GET['method']     ?? '');
                $search     = trim($_GET['search']     ?? '');
                $date_from  = trim($_GET['date_from']  ?? '');
                $date_to    = trim($_GET['date_to']    ?? '');
                $min_amount = trim($_GET['min_amount'] ?? '');
                $max_amount = trim($_GET['max_amount'] ?? '');

                $page = max(1, intval($_GET['page'] ?? 1));
                $per  = max(1, intval($_GET['per_page'] ?? 25));
                $off  = ($page-1)*$per;

                $where = '1';
                $args  = [];

                if ($status !== '') { $where .= ' AND c.status = ?'; $args[] = $status; }
                if ($method !== '') { $where .= ' AND (c.payout_method = ? OR c.paid_via = ?)'; $args[]=$method; $args[]=$method; }
                if ($search !== '') { $where .= ' AND (u.username LIKE ? OR u.id LIKE ?)'; $args[]="%$search%"; $args[]="%$search%"; }
                if ($date_from !== '') { $where .= ' AND c.requested_at >= ?'; $args[] = $date_from.' 00:00:00'; }
                if ($date_to   !== '') { $where .= ' AND c.requested_at <= ?'; $args[] = $date_to.' 23:59:59'; }
                if (is_numeric($min_amount)) { $where .= ' AND c.amount >= ?'; $args[] = (float)$min_amount; }
                if (is_numeric($max_amount)) { $where .= ' AND c.amount <= ?'; $args[] = (float)$max_amount; }

                // Count total
                $csql = $pdo->prepare("
                    SELECT COUNT(*) FROM cashout_requests c
                    LEFT JOIN users u ON c.user_id=u.id
                    WHERE $where
                ");
                $csql->execute($args);
                $total = (int)$csql->fetchColumn();

// Page rows (safe LIMIT/OFFSET binding)
$sql = $pdo->prepare("
    SELECT c.*, u.username, pm.method AS paid_method_type,
           pm.paypal_email, pm.stripe_customer_id, pm.stripe_card_last4, pm.stripe_card_brand
    FROM cashout_requests c
    LEFT JOIN users u ON c.user_id=u.id
    LEFT JOIN payout_methods pm ON pm.id = c.paid_method_id
    WHERE $where
    ORDER BY c.requested_at DESC
    LIMIT :limit OFFSET :offset
");

// Bind all dynamic filters first
$idx = 1;
foreach ($args as $k => $v) {
    $sql->bindValue($idx++, $v);
}

// Explicitly bind LIMIT and OFFSET as integers
$sql->bindValue(':limit', (int)$per, PDO::PARAM_INT);
$sql->bindValue(':offset', (int)$off, PDO::PARAM_INT);

$sql->execute();
$rows = $sql->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['ok'=>true,'rows'=>$rows,'total'=>$total,'page'=>$page,'per_page'=>$per]);
                break;
            }

            case 'get_payout_methods': {
                $uid = (int)($_GET['user_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id=? ORDER BY created_at DESC");
                $stmt->execute([$uid]);
                echo json_encode(['ok'=>true,'methods'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }

            case 'mark_paid': {
                // Manual mark (no real API call)
                $id   = (int)($_POST['id'] ?? 0);
                $via  = trim($_POST['method'] ?? 'manual');
                $pmid = (int)($_POST['payout_method_id'] ?? 0);

                // optional: enforce limits on manual too
                $amt = (float)($pdo->query("SELECT amount FROM cashout_requests WHERE id={$id}")->fetchColumn() ?: 0);
                if ($amt < MIN_CASHOUT || $amt > MAX_CASHOUT) {
                    echo json_encode(['ok'=>false,'error'=>'Amount outside allowed limits']); exit;
                }

                $pdo->prepare("UPDATE cashout_requests SET status='paid',paid_via=?,paid_method_id=?,paid_at=NOW() WHERE id=?")
                    ->execute([$via, $pmid ?: null, $id]);

                echo json_encode(['ok'=>true]);
                break;
            }

            case 'pay_paypal': {
                // Real PayPal payout
                $id   = (int)($_POST['id'] ?? 0);
                $pmid = (int)($_POST['payout_method_id'] ?? 0);

                // Load request
                $q = $pdo->prepare("SELECT user_id, amount, status FROM cashout_requests WHERE id=?");
                $q->execute([$id]);
                $req = $q->fetch(PDO::FETCH_ASSOC);
                if (!$req) { echo json_encode(['ok'=>false,'error'=>'Request not found']); break; }
                if ($req['status'] !== 'pending') { echo json_encode(['ok'=>false,'error'=>'Request is not pending']); break; }

                $amount = (float)$req['amount'];
                if ($amount < MIN_CASHOUT || $amount > MAX_CASHOUT) {
                    echo json_encode(['ok'=>false,'error'=>'Amount outside allowed limits']); break;
                }

                // Load payout method
                $pm = $pdo->prepare("SELECT method, paypal_email FROM payout_methods WHERE id=? AND user_id=?");
                $pm->execute([$pmid, $req['user_id']]);
                $method = $pm->fetch(PDO::FETCH_ASSOC);
                if (!$method || $method['method']!=='paypal' || empty($method['paypal_email'])) {
                    echo json_encode(['ok'=>false,'error'=>'Invalid PayPal payout method']); break;
                }

                $res = payout_via_paypal($method['paypal_email'], $amount, PAYPAL_CURRENCY);
                if (!$res['ok']) { echo json_encode(['ok'=>false,'error'=>'PayPal error: '.$res['error']]); break; }

                $pdo->prepare("UPDATE cashout_requests SET status='paid', paid_via='paypal', paid_method_id=?, paid_at=NOW() WHERE id=?")
                    ->execute([$pmid, $id]);
                echo json_encode(['ok'=>true,'txn_id'=>$res['id']??null]);
                break;
            }

            case 'pay_stripe': {
                // Real Stripe payout
                $id   = (int)($_POST['id'] ?? 0);
                $pmid = (int)($_POST['payout_method_id'] ?? 0);
                $via  = trim($_POST['via'] ?? 'stripe_debit'); // 'stripe_debit' or 'stripe_credit'

                $q = $pdo->prepare("SELECT user_id, amount, status FROM cashout_requests WHERE id=?");
                $q->execute([$id]);
                $req = $q->fetch(PDO::FETCH_ASSOC);
                if (!$req) { echo json_encode(['ok'=>false,'error'=>'Request not found']); break; }
                if ($req['status'] !== 'pending') { echo json_encode(['ok'=>false,'error'=>'Request is not pending']); break; }

                $amount = (float)$req['amount'];
                if ($amount < MIN_CASHOUT || $amount > MAX_CASHOUT) {
                    echo json_encode(['ok'=>false,'error'=>'Amount outside allowed limits']); break;
                }

                $pm = $pdo->prepare("SELECT method, stripe_customer_id FROM payout_methods WHERE id=? AND user_id=?");
                $pm->execute([$pmid, $req['user_id']]);
                $method = $pm->fetch(PDO::FETCH_ASSOC);
                if (!$method || strpos($method['method'],'stripe')!==0 || empty($method['stripe_customer_id'])) {
                    echo json_encode(['ok'=>false,'error'=>'Invalid Stripe payout method']); break;
                }

                $res = payout_via_stripe($method['stripe_customer_id'], $amount, STRIPE_DEFAULT_CURRENCY);
                if (!$res['ok']) { echo json_encode(['ok'=>false,'error'=>'Stripe error: '.$res['error']]); break; }

                $pdo->prepare("UPDATE cashout_requests SET status='paid', paid_via=?, paid_method_id=?, paid_at=NOW() WHERE id=?")
                    ->execute([$via, $pmid, $id]);
                echo json_encode(['ok'=>true,'txn_id'=>$res['id']??null]);
                break;
            }

            case 'decline': {
                $id     = (int)($_POST['id'] ?? 0);
                $reason = trim($_POST['reason'] ?? '');
                $pdo->prepare("UPDATE cashout_requests SET status='declined', decline_reason=? WHERE id=?")
                    ->execute([$reason, $id]);
                echo json_encode(['ok'=>true]);
                break;
            }

            case 'view_info': {
                $id = (int)($_GET['id'] ?? 0);
                $stmt=$pdo->prepare("
                    SELECT c.*, u.username, pm.*
                    FROM cashout_requests c
                    LEFT JOIN users u ON c.user_id=u.id
                    LEFT JOIN payout_methods pm ON pm.id=c.paid_method_id
                    WHERE c.id=?
                ");
                $stmt->execute([$id]);
                echo json_encode(['ok'=>true,'info'=>$stmt->fetch(PDO::FETCH_ASSOC)]);
                break;
            }

            default: echo json_encode(['ok'=>false,'error'=>'Invalid action']);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// --- HTML page (admin only) ---
if ($_SESSION['user_id'] != 1) { http_response_code(403); exit('Access denied.'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Cashouts</title>
<style>
body{font-family:system-ui,Segoe UI,Roboto;background:#0b0f1a;color:#e2e8f0;margin:0;padding:20px}
h1{color:#f6c84b;margin-bottom:1rem}
.controls,.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px}
input,select,button{padding:8px 12px;border-radius:6px;border:1px solid #1e293b;background:#16203b;color:#e2e8f0}
button.btn{background:#1e40af;border:none;color:#fff;cursor:pointer}
button.btn-danger{background:#b91c1c}
#flash{margin-bottom:12px;font-weight:600}
table{width:100%;border-collapse:collapse;background:#111827;border-radius:8px;overflow:hidden}
th,td{padding:10px 12px;border-bottom:1px solid #1e293b;text-align:left}
th{background:#1e293b}
tr:nth-child(even){background:#0f172a}
.muted{color:#64748b}
.status-pending{color:#fbbf24}
.status-paid{color:#6ee7b7}
.status-declined{color:#f87171}
#modal,#payModal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);z-index:999}
.box{background:#1e293b;padding:20px;border-radius:10px;box-shadow:0 0 20px rgba(0,0,0,0.5)}
.box h3{margin-top:0}
.small{font-size:.85em;color:#94a3b8}
select,button{margin-top:6px}
.limit{background:#0f172a;border:1px solid #334155;padding:10px;border-radius:8px;margin:10px 0}
</style>
</head>
<body>
<h1>Cashout Manager</h1>

<div class="limit">
  <b>Limits:</b> Min <b>$<?= number_format(MIN_CASHOUT,2) ?></b> · Max <b>$<?= number_format(MAX_CASHOUT,2) ?></b> per cashout.
</div>

<!-- Filters -->
<div class="filters">
  <select id="status">
    <option value="">All Status</option><option value="pending">Pending</option>
    <option value="paid">Paid</option><option value="declined">Declined</option>
  </select>
  <select id="method">
    <option value="">All Methods</option>
    <option value="paypal">PayPal</option>
    <option value="stripe_debit">Stripe Debit</option>
    <option value="stripe_credit">Stripe Credit</option>
    <option value="manual">Manual</option>
  </select>
  <input type="date" id="date_from" placeholder="From">
  <input type="date" id="date_to" placeholder="To">
  <input type="number" step="0.01" id="min_amount" placeholder="Min $">
  <input type="number" step="0.01" id="max_amount" placeholder="Max $">
  <input type="text" id="search" placeholder="Search user or id">
  <select id="perPage"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option></select>
  <button id="refreshBtn" class="btn">Apply</button>
</div>

<div id="flash"></div>

<table id="grid">
  <thead>
    <tr><th>ID</th><th>User</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th><th>Actions</th></tr>
  </thead>
  <tbody><tr><td colspan="7" class="muted">Loading…</td></tr></tbody>
</table>

<!-- PAYOUT MODAL -->
<div id="payModal"><div class="box" style="width:420px">
  <h3>Pay User</h3>
  <div id="modalUser" class="small"></div>
  <select id="payoutMethodsSelect"></select>
  <pre id="payoutMethodDetails" class="small"></pre>
  <button id="payPaypal" class="btn">Pay via PayPal</button>
  <button id="payStripeDebit" class="btn">Pay via Stripe Debit</button>
  <button id="payStripeCredit" class="btn">Pay via Stripe Credit</button>
  <button id="markManual" class="btn">Mark Paid (Manual)</button>
  <button id="cancelPayBtn" class="btn btn-danger">Cancel</button>
</div></div>

<!-- VIEW INFO MODAL -->
<div id="modal"><div class="box" style="width:420px">
  <h3>Payout Info</h3>
  <pre id="infoText" class="small"></pre>
  <button id="closeInfo" class="btn btn-danger">Close</button>
</div></div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const API=location.pathname,$=s=>document.querySelector(s);
 let currentPayId=0,currentUserId=0,methodsCache=[];

 function setFlash(m,ok=1){$('#flash').textContent=m;$('#flash').style.color=ok?'#6ee7b7':'#f87171';}

 async function fetchList(page=1){
   const q=new URLSearchParams({
     action:'list',
     status:$('#status').value,
     method:$('#method').value,
     search:$('#search').value.trim(),
     date_from:$('#date_from').value,
     date_to:$('#date_to').value,
     min_amount:$('#min_amount').value,
     max_amount:$('#max_amount').value,
     page,
     per_page:$('#perPage').value
   });
   const r=await fetch(API+'?'+q,{cache:'no-store'});
   const j=await r.json();
   if(j.ok) renderTable(j.rows); else setFlash(j.error,false);
 }

 function renderTable(rows){
   const tb=$('#grid tbody'); tb.innerHTML='';
   if(!rows?.length){tb.innerHTML='<tr><td colspan="7" class="muted">No results</td></tr>'; return;}
   rows.forEach(r=>{
     const methodLabel = r.paid_via || r.payout_method || '—';
     const tr=document.createElement('tr');
     tr.innerHTML = `
       <td>${r.id}</td>
       <td>${r.username} (#${r.user_id})</td>
       <td>$${Number(r.amount).toFixed(2)}</td>
       <td>${methodLabel}</td>
       <td class="status-${r.status}">${r.status}</td>
       <td>${r.requested_at}</td>
       <td>
         ${r.status==='pending'
           ? `<button class='btn' onclick='openPay(${r.id},${r.user_id},"${(r.username||'').replace(/"/g,'&quot;')}")'>Pay</button>
              <button class='btn btn-danger' onclick='decline(${r.id})'>Decline</button>`
           : `<button class='btn' onclick='viewInfo(${r.id})'>View Info</button>`}
       </td>`;
     tb.appendChild(tr);
   });
 }

 window.openPay = async function(id,uid,user){
   currentPayId=id; currentUserId=uid;
   $('#modalUser').textContent = `User: ${user} (#${uid})`;
   $('#payoutMethodsSelect').innerHTML = '<option>Loading…</option>';
   $('#payoutMethodDetails').textContent = '';
   $('#payModal').style.display='flex';

   const r=await fetch(API+'?action=get_payout_methods&user_id='+uid,{cache:'no-store'});
   const j=await r.json();
   methodsCache = j.ok ? (j.methods||[]) : [];
   if(!methodsCache.length){
     $('#payoutMethodsSelect').innerHTML = '<option value="">No methods</option>';
     $('#payoutMethodDetails').textContent = 'User has no saved payout methods.';
     return;
   }
   $('#payoutMethodsSelect').innerHTML = methodsCache.map(m=>{
     let t = m.method;
     if(m.method==='paypal' && m.paypal_email) t += ' — '+m.paypal_email;
     if(m.stripe_card_last4) t += ' — '+(m.stripe_card_brand||'card')+' ****'+m.stripe_card_last4;
     return `<option value="${m.id}">${t}</option>`;
   }).join('');
   showDetails();
 };

 function getSelectedMethod(){
   const sel=$('#payoutMethodsSelect'); const id=sel.value;
   return methodsCache.find(x => String(x.id)===String(id));
 }
 function showDetails(){
   const m = getSelectedMethod();
   if(!m){ $('#payoutMethodDetails').textContent='No details'; return; }
   let txt = `Method: ${m.method}\nCreated: ${m.created_at}`;
   if(m.method==='paypal') txt += `\nPayPal: ${m.paypal_email||'N/A'}`;
   if(m.stripe_card_last4) txt += `\nStripe: ${m.stripe_card_brand||''} ****${m.stripe_card_last4}\nCustomer ID: ${m.stripe_customer_id||''}`;
   $('#payoutMethodDetails').textContent = txt;
 }
 document.addEventListener('change', e => { if(e.target.id==='payoutMethodsSelect') showDetails(); });

 async function payPaypal(){
   const m=getSelectedMethod(); if(!m){ return setFlash('Select a payout method',0); }
   if(m.method!=='paypal') return setFlash('Selected method is not PayPal',0);
   const body = new URLSearchParams({action:'pay_paypal', id: currentPayId, payout_method_id: m.id});
   const r = await fetch(API,{method:'POST', body}); const j=await r.json();
   if(j.ok){ setFlash('Paid via PayPal'); closePay(); fetchList(); } else setFlash(j.error||'PayPal error',0);
 }
 async function payStripe(via){
   const m=getSelectedMethod(); if(!m){ return setFlash('Select a payout method',0); }
   if(!m.method.startsWith('stripe')) return setFlash('Selected method is not Stripe',0);
   const body = new URLSearchParams({action:'pay_stripe', id: currentPayId, payout_method_id: m.id, via});
   const r = await fetch(API,{method:'POST', body}); const j=await r.json();
   if(j.ok){ setFlash('Paid via Stripe'); closePay(); fetchList(); } else setFlash(j.error||'Stripe error',0);
 }
 async function markManual(){
   const m=getSelectedMethod(); // optional
   const body=new URLSearchParams({action:'mark_paid', id: currentPayId, method:'manual', payout_method_id: m?.id||''});
   const r=await fetch(API,{method:'POST', body}); const j=await r.json();
   if(j.ok){ setFlash('Marked paid (manual)'); closePay(); fetchList(); } else setFlash(j.error||'Error',0);
 }

 $('#payPaypal').onclick = payPaypal;
 $('#payStripeDebit').onclick = () => payStripe('stripe_debit');
 $('#payStripeCredit').onclick = () => payStripe('stripe_credit');
 $('#markManual').onclick = markManual;
 $('#cancelPayBtn').onclick = ()=>$('#payModal').style.display='none';

 window.viewInfo = async function(id){
   const r=await fetch(API+'?action=view_info&id='+id); const j=await r.json();
   if(!j.ok) return setFlash(j.error,0);
   const i=j.info||{};
   let t=`User: ${i.username} (#${i.user_id})\nStatus: ${i.status}\nAmount: $${Number(i.amount||0).toFixed(2)}\nRequested: ${i.requested_at}\nPaid via: ${i.paid_via||'—'}\nPaid at: ${i.paid_at||'—'}`;
   if(i.paid_via==='paypal'&&i.paypal_email) t+=`\nPayPal: ${i.paypal_email}`;
   if((i.paid_via||'').startsWith('stripe')) t+=`\nStripe: ${i.stripe_card_brand||''} ****${i.stripe_card_last4||''}\nCustomer ID: ${i.stripe_customer_id||''}`;
   $('#infoText').textContent=t; $('#modal').style.display='flex';
 };
 $('#closeInfo').onclick=()=>$('#modal').style.display='none';

 window.decline = async function(id){
   const reason=prompt('Reason for decline?'); if(!reason) return;
   const r=await fetch(API,{method:'POST',body:new URLSearchParams({action:'decline',id,reason})});
   const j=await r.json(); if(j.ok){ setFlash('Declined'); fetchList(); } else setFlash(j.error||'Error',0);
 };

 // ✅ Add this right below
function closePay(){
  document.getElementById('payModal').style.display = 'none';
}

 $('#refreshBtn').onclick=()=>fetchList(1);
 fetchList(1);
});
</script>
</body>
</html>