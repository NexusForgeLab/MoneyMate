<?php
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/finance.php';

$user = require_login();
// Fetch Net Worth Data
$nw = get_net_worth($user['id']);

render_header('Dashboard', $user);
?>
<div class="card">
  <h1>Total Net Worth: ₹<?php echo number_format($nw['total_net_worth'], 2); ?></h1>
  <div class="muted">Live calculation of Bank Balance + Stocks + Mutual Funds</div>
</div>

<div class="kpi">
  <div class="card box" style="border-top:4px solid #007aff">
    <div class="muted">Bank Balance (Liquid)</div>
    <div class="val" style="color:#007aff">₹<?php echo number_format($nw['cash_balance'], 2); ?></div>
    <div class="muted" style="font-size:0.8em">Total Income - Expenses</div>
  </div>
  <div class="card box" style="border-top:4px solid #34c759">
    <div class="muted">Stocks Value</div>
    <div class="val good">₹<?php echo number_format($nw['stock_value'], 2); ?></div>
    <div class="muted" style="font-size:0.8em">Current Holdings</div>
  </div>
  <div class="card box" style="border-top:4px solid #ff9500">
    <div class="muted">Mutual Funds Value</div>
    <div class="val warn">₹<?php echo number_format($nw['mf_value'], 2); ?></div>
    <div class="muted" style="font-size:0.8em">Current Holdings</div>
  </div>
</div>

<div class="grid">
  <div class="col-8">
    <div class="card">
      <h2>Monthly Trend (Last 12 Months)</h2>
      <canvas id="lineChart" height="110"></canvas>
    </div>
  </div>
  <div class="col-4">
    <div class="card">
      <h2>Investments</h2>
      <div id="invList" class="muted">Loading...</div>
      <div style="margin-top:10px">
         <a class="btn" href="/investments.php" style="width:100%;justify-content:center">Manage Portfolio</a>
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
        { label:'Income', data: trend.map(x=>x.income), borderColor:'#34c759', backgroundColor:'#34c759' },
        { label:'Expense', data: trend.map(x=>x.expense), borderColor:'#ff3b30', backgroundColor:'#ff3b30' }
      ]
    },
    options: { responsive:true }
  });

  const inv = await j('/api/investments.php');
  const el = document.getElementById('invList');
  if(!inv.length){ el.textContent = 'No investments.'; return; }
  el.innerHTML = inv.slice(0,5).map(r=>{
    const pl = r.unrealized_pl;
    return `<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee">
      <div><b>${r.symbol}</b></div>
      <div class="${pl>=0?'good':'bad'}">₹${pl.toFixed(0)}</div>
    </div>`;
  }).join('');
})();
</script>
<?php render_footer(); ?>