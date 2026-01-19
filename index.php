<?php
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/finance.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/backup.php';

$user = require_login();
$pdo = db();

// This runs once per day when you visit the dashboard
run_auto_backup();

// Fetch Net Worth Data
$nw = get_net_worth($user['id']);
$total_nw = $nw['total_net_worth'];

// Fetch Savings Rate (Avg last 3 months)
$avg_savings = get_average_savings($user['id']);

// Fetch Milestones
$stmt = $pdo->prepare("SELECT * FROM milestones WHERE user_id=? ORDER BY amount ASC");
$stmt->execute([$user['id']]);
$milestones = $stmt->fetchAll();

// Calculate Milestone Status
$next_ms = null;
$prev_ms_amount = 0;
foreach($milestones as $m) {
    if($total_nw < $m['amount']) {
        $next_ms = $m;
        break;
    }
    $prev_ms_amount = $m['amount'];
}

function format_time_needed(float $gap, float $rate): string {
    if($gap <= 0) return "Achieved";
    if($rate <= 0) return "Infinity (Save more!)";
    
    $months = ceil($gap / $rate);
    if($months < 12) return $months . " Months";
    
    $y = floor($months / 12);
    $m = $months % 12;
    return $y . " Years " . ($m > 0 ? $m . " Months" : "");
}

render_header('Dashboard', $user);
?>
<div class="card">
  <h1>Total Net Worth: ₹<?php echo number_format($total_nw, 2); ?></h1>
  <div class="muted">
    Avg. Monthly Savings: <span class="<?php echo $avg_savings>=0?'good':'bad';?>">₹<?php echo number_format($avg_savings); ?></span>
  </div>
</div>

<?php if($milestones): ?>
<div class="card" style="background: linear-gradient(to right, #ffffff, #f9f9f9); border-top: 4px solid #8e44ad;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:10px; flex-wrap:wrap; gap:10px;">
        <h2>🚀 Roadmap</h2>
        <?php if($next_ms): 
            $gap = $next_ms['amount'] - $total_nw;
            $range = $next_ms['amount'] - $prev_ms_amount;
            $progress = ($range > 0) ? (($total_nw - $prev_ms_amount) / $range) * 100 : 0;
            if($progress < 0) $progress = 0;
        ?>
            <div style="text-align:right">
                <div class="muted">Next: <b><?php echo h($next_ms['name']); ?></b></div>
                <div style="color:#8e44ad; font-weight:bold; font-size:1.1em;">
                    ₹<?php echo number_format($gap); ?> to go
                </div>
            </div>
        <?php else: ?>
            <div style="color:#34c759; font-weight:bold;">🎉 All Milestones Achieved!</div>
        <?php endif; ?>
    </div>

    <div style="overflow-x: auto; padding-bottom: 20px; margin: 0 -10px;">
        <div style="min-width: 600px; padding: 0 20px;">
            <div style="position:relative; height:12px; background:#e0e0e0; border-radius:6px; margin: 30px 0;">
                <?php 
                    $max_val = end($milestones)['amount'];
                    if($total_nw > $max_val) $max_val = $total_nw;
                    if($max_val <= 0) $max_val = 1;

                    $fill_width = ($total_nw / $max_val) * 100;
                    if($fill_width > 100) $fill_width = 100;
                ?>
                <div style="height:100%; background:#34c759; border-radius:6px; width:<?php echo $fill_width; ?>%; transition: width 1s;"></div>
                
                <div style="position:absolute; top:-8px; left:<?php echo $fill_width; ?>%; transform:translateX(-50%); z-index:2;">
                    <div style="background:#2b2b2b; color:white; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold; box-shadow:0 2px 4px rgba(0,0,0,0.2);">YOU</div>
                    <div style="width:2px; height:8px; background:#2b2b2b; margin:0 auto;"></div>
                </div>

                <?php foreach($milestones as $m): 
                     $pos = ($m['amount'] / $max_val) * 100;
                     if($pos > 100) $pos = 100;
                     $passed = $total_nw >= $m['amount'];
                ?>
                <div style="position:absolute; top:-6px; left:<?php echo $pos; ?>%; transform:translateX(-50%); display:flex; flex-direction:column; align-items:center;">
                    <div style="width:12px; height:12px; border-radius:50%; background:<?php echo $passed ? '#34c759' : '#ccc'; ?>; border:2px solid white; box-shadow:0 1px 3px rgba(0,0,0,0.2);"></div>
                    <div style="margin-top:8px; font-size:11px; color:#555; text-align:center; white-space:nowrap;">
                        <b><?php echo h($m['name']); ?></b><br>
                        <span class="muted">₹<?php echo number_format($m['amount']/1000); ?>k</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div style="margin-top:20px; border-top:1px dashed #eee; padding-top:15px;">
        <h3 style="font-size:1rem; margin-bottom:10px;">⏱️ Time to Reach (at current savings)</h3>
        <div class="grid">
            <?php foreach($milestones as $m): ?>
            <?php if($total_nw >= $m['amount']) continue; ?>
            <div class="col-4">
                <div style="background:#f4f4f4; padding:10px; border-radius:6px;">
                    <div style="font-weight:bold; font-size:0.9rem;"><?php echo h($m['name']); ?></div>
                    <div style="color:#8e44ad; font-weight:bold; font-size:0.85rem;">
                        <?php echo format_time_needed($m['amount'] - $total_nw, $avg_savings); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="kpi">
  <div class="card box" style="border-top:4px solid #007aff">
    <div class="muted">Bank Balance</div>
    <div class="val" style="color:#007aff">₹<?php echo number_format($nw['cash_balance'], 2); ?></div>
  </div>
  <div class="card box" style="border-top:4px solid #34c759">
    <div class="muted">Stocks Value</div>
    <div class="val good">₹<?php echo number_format($nw['stock_value'], 2); ?></div>
  </div>
  <div class="card box" style="border-top:4px solid #ff9500">
    <div class="muted">Mutual Funds</div>
    <div class="val warn">₹<?php echo number_format($nw['mf_value'], 2); ?></div>
  </div>
</div>

<div class="grid">
  <div class="col-8">
    <div class="card">
      <h2>Monthly Trend</h2>
      <div style="position: relative; height: 250px; width:100%">
        <canvas id="lineChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-4">
    <div class="card">
      <h2>Investments</h2>
      <div id="invList" class="muted">Loading...</div>
      <div style="margin-top:10px">
         <a class="btn" href="/investments.php" style="width:100%;justify-content:center">Portfolio</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
async function j(url){ const r=await fetch(url); return await r.json(); }

(async ()=>{
  const trend = await j('/api/trend.php');
  new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
      labels: trend.map(x=>x.month),
      datasets: [
        { label:'Income', data: trend.map(x=>x.income), borderColor:'#34c759', backgroundColor:'#34c759', tension: 0.3 },
        { label:'Expense', data: trend.map(x=>x.expense), borderColor:'#ff3b30', backgroundColor:'#ff3b30', tension: 0.3 }
      ]
    },
    options: { responsive:true, maintainAspectRatio:false }
  });

  const inv = await j('/api/investments.php');
  const el = document.getElementById('invList');
  if(!inv.length){ el.textContent = 'No investments.'; return; }
  el.innerHTML = inv.slice(0,5).map(r=>{
    const pl = r.unrealized_pl;
    return `<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee">
      <div style="font-size:0.9em"><b>${r.symbol}</b></div>
      <div class="${pl>=0?'good':'bad'}" style="font-size:0.9em">₹${pl.toFixed(0)}</div>
    </div>`;
  }).join('');
})();
</script>
<?php render_footer(); ?>