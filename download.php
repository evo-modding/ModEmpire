<?php
$title = 'Download File';
require 'header.php';

// Get file ID from URL
$file_id = intval($_GET['id'] ?? 0);
if (!$file_id) {
    http_response_code(400);
    echo '<h1>Error 400</h1><p>Invalid file ID.</p>';
    require 'footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT f.*, u.username FROM files f JOIN users u ON u.id = f.user_id WHERE f.id = ? LIMIT 1");
$stmt->execute([$file_id]);
$f = $stmt->fetch();

if (!$f) {
    http_response_code(404);
    echo '<h1>Error 404</h1><p>File not found.</p>';
    require 'footer.php';
    exit;
}

$is_public = ($f['visibility'] === 'public');
$is_owner = (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $f['user_id']);
$user_is_logged_in = !empty($_SESSION['user_id']);

$user_plan = null;
if ($user_is_logged_in) {
    $stmt = $pdo->prepare("SELECT plan FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_plan = $stmt->fetchColumn();
}

// ⏩ VIP users skip ads & countdown entirely
if ($user_plan === 'vip') {
    header("Location: get_link.php?id={$f['id']}");
    exit;
}

if (!$is_public && !$is_owner) {
    http_response_code(403);
    echo '<h1>Error 403</h1><p>Access Denied. Private file.</p>';
    require 'footer.php';
    exit;
}

$file_size_kb = number_format($f['size'] / 1024, 2);
$file_size_mb = number_format($f['size'] / 1024 / 1024, 2);
$display_size = $f['size'] > (1024 * 1024) ? "$file_size_mb MB" : "$file_size_kb KB";
?>

<style>
.download-page-wrapper{display:flex;justify-content:center;align-items:flex-start;padding-top:5vh;}
.download-box{background:rgba(255,255,255,0.02);padding:28px;border-radius:12px;border:1px solid rgba(255,255,255,0.06);width:100%;max-width:600px;text-align:center;}
.download-box h2{margin:0 0 16px;word-wrap:break-word;}
.file-info{color:rgba(255,255,255,0.7);font-size:15px;margin-bottom:24px;}
.ad-placeholder,.ad-placeholder-leaderboard,.ad-placeholder-footer{
  width:100%;
  min-height:100px;
  display:flex;
  align-items:center;
  justify-content:center;
  margin:24px 0;
  padding:20px;
  box-sizing:border-box;
  color:rgba(255,255,255,0.5);
}
.btn-download{width:100%;padding:14px;font-size:18px;font-weight:700;text-decoration:none;margin-top:16px;box-sizing:border-box;}
.small-note{font-size:14px;opacity:.75;margin-top:8px}

#parent {
    text-align:center;
    height:400px;
    min-width:100%;
}
.block {
    height:100px;
    width:200px;
    text-align:left;
}
.center {
    margin:auto;
}
.left {
    margin:auto auto auto 0;
}
.right {
    margin:auto 0 auto auto;
}
</style>
<!-- Ads (unchanged) -->
<script async src="//www.ezojs.com/ezoic/sa.min.js"></script>
<script>window.ezstandalone=window.ezstandalone||{};ezstandalone.cmd=ezstandalone.cmd||[];</script>
<div id="parent">
<div class="ad-placeholder-leaderboard block center" id="ad-top">
<script type="text/javascript">
  atOptions={'key':'fd77fbf7812c3261a99c11cbc010ac65','format':'iframe','height':90,'width':728,'params':{}};
</script>
<script type="text/javascript" src="//www.highperformanceformat.com/fd77fbf7812c3261a99c11cbc010ac65/invoke.js"></script>
</div>
<div class="download-page-wrapper">
  <div class="download-box">
    <h2>Download File</h2>
    <p style="font-size:1.2rem;margin-bottom:8px;word-wrap:break-word;">
      <?= htmlspecialchars($f['original_name']) ?>
    </p>
    <div class="file-info">
      Uploaded by: <strong><?= htmlspecialchars($f['username']) ?></strong>
      &nbsp;&bull;&nbsp;
      Size: <strong><?= $display_size ?></strong>
    </div>

    <div class="ad-placeholder" id="ad-mid">
<script type="text/javascript">
  atOptions={'key':'c760278533c5c245059b602b490ee020','format':'iframe','height':300,'width':160,'params':{}};
</script>
<script type="text/javascript" src="//www.highperformanceformat.com/c760278533c5c245059b602b490ee020/invoke.js"></script>
    </div>

    <p id="timer-message" style="font-size:16px;margin-top:16px;color:#fff;">
      Checking ads...
    </p>

    <a href="get_link.php?id=<?= $f['id'] ?>" class="btn btn-download" id="download-button" style="display:none;">
      Download Now
    </a>

    <button class="btn btn-download" id="download-button-disabled" disabled>
      Please wait...
    </button>
    
    <div class="small-note" id="ad-warning" style="display:none;color:#ffb4b4">
      Ad-block detected — please allow ads to continue.
    </div>
  </div>
</div>
    <p></p>
    <?php if($user_is_logged_in): ?>
  <a href="instant.php?id=<?= $f['id'] ?>" class="btn btn-secondary" style="margin-top:10px;">
    ⏩ Skip Wait (1 Credit)
  </a>
<?php endif; ?>
<P></P>
<div class="ad-placeholder-footer block center" id="ad-bottom">
<script type="text/javascript">
  atOptions={'key':'b233078aa2e24f0c6e983ae60b1e9b20','format':'iframe','height':60,'width':468,'params':{}};
</script>
<script type="text/javascript" src="//www.highperformanceformat.com/b233078aa2e24f0c6e983ae60b1e9b20/invoke.js"></script>
</div>
</div>
<!-- Keep all vendor scripts -->
<script>(function(s){s.dataset.zone='10102975',s.src='https://al5sm.com/tag.min.js'})([document.documentElement,document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<script>(function(s){s.dataset.zone='10103009',s.src='https://gizokraijaw.net/vignette.min.js'})([document.documentElement,document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<script>(function(s){s.dataset.zone='10103018',s.src='https://groleegni.net/vignette.min.js'})([document.documentElement,document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<script src="https://cmp.gatekeeperconsent.com/min.js" data-cfasync="false"></script>
<script src="https://the.gatekeeperconsent.com/cmp.min.js" data-cfasync="false"></script>

<script>
function detectAdBlock(containers=['#ad-top','#ad-mid','#ad-bottom']) {
  return new Promise((resolve)=>{
    const bait=document.createElement('div');
    bait.className='adsbox banner ad ad-container';
    bait.style.cssText='position:absolute;left:-9999px;width:1px;height:1px;';
    document.body.appendChild(bait);

    const isHidden = ()=> {
      const cs=window.getComputedStyle(bait);
      return cs.display==='none'||cs.visibility==='hidden'||bait.offsetHeight===0;
    };

    let found=false;
    setTimeout(()=>{
      for(const sel of containers){
        const el=document.querySelector(sel);
        if(el && el.querySelector('iframe')) found=true;
      }
      const blocked=isHidden() || !found;
      bait.remove();
      resolve(!blocked);
    },3000); // wait a bit for ads to load
  });
}

function startCountdown(){
  let sec=30;
  const cd=document.createElement('strong');
  cd.id='countdown';
  cd.style.color='#6ee7b7';
  cd.textContent=sec;
  const msg=document.getElementById('timer-message');
  msg.innerHTML='Your download will be ready in ';
  msg.appendChild(cd);
  msg.append(' seconds...');
  const btn=document.getElementById('download-button');
  const dis=document.getElementById('download-button-disabled');
  const timer=setInterval(()=>{
    sec--;
    cd.textContent=sec;
    if(sec<=0){
      clearInterval(timer);
      msg.textContent='Your download is ready!';
      btn.style.display='inline-block';
      dis.style.display='none';
    }
  },1000);
}

window.addEventListener('load',async()=>{
  const allowed=await detectAdBlock();
  const msg=document.getElementById('timer-message');
  const warn=document.getElementById('ad-warning');
  const btn=document.getElementById('download-button');
  const dis=document.getElementById('download-button-disabled');
  if(!allowed){
    msg.textContent='Ad-block detected — ads must be enabled to continue.';
    warn.style.display='block';
    btn.style.display='none';
    dis.disabled=true;
    dis.textContent='Ads blocked';
  }else{
    msg.textContent='Ads detected — preparing download...';
    startCountdown();
  }
});
</script>

<?php require 'footer.php'; ?>
