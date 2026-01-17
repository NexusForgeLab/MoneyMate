<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
render_header('Reports', $user);
?>
<div class="card">
  <h1>Reports</h1>
  <div class="muted">Pie charts for selected date range.</div>
</div>

<div class="card">
  <form id="rangeForm" class="grid">
    <div class="col-3">
      <div class="muted">From</div>
      <input type="date" id="from" value="<?php echo h(date('Y-m-01')); ?>"/>
    </div>
    <div class="col-3">
      <div class="muted">To</div>
      <input type="date" id="to" value="<?php echo h(date('Y-m-t')); ?>"/>
    </div>
    <div class="col-6" style="display:flex;align-items:flex-end;gap:10px">
      <button class="btn" type="button" onclick="loadRange()">Load</button>
      <a class="btn" href="/tx_list.php">Transactions</a>
    </div>
  </form>
</div>

<div class="grid">
  <div class="col-6">
    <div class="card">
      <h2>Income vs Expense (range)</h2>
      <canvas id="pieIE" height="220"></canvas>
    </div>
  </div>
  <div class="col-6">
    <div class="card">
      <h2>Expense by Category (range)</h2>
      <canvas id="pieCat" height="220"></canvas>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
let pie1=null, pie2=null;
async function j(url){ const r=await fetch(url); return await r.json(); }

async function loadRange(){
  const from=document.getElementById('from').value;
  const to=document.getElementById('to').value;
  const ie = await j(`/api/range_income_expense.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
  const cat = await j(`/api/range_expense_by_category.php?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);

  if(pie1) pie1.destroy();
  pie1 = new Chart(document.getElementById('pieIE'), {
    type:'pie',
    data:{ labels:['Income','Expense'], datasets:[{ data:[ie.income, ie.expense] }] },
    options:{ plugins:{legend:{labels:{color:'#e7eefc'}}} }
  });

  if(pie2) pie2.destroy();
  pie2 = new Chart(document.getElementById('pieCat'), {
    type:'pie',
    data:{ labels: cat.map(x=>x.label), datasets:[{ data: cat.map(x=>x.total) }] },
    options:{ plugins:{legend:{labels:{color:'#e7eefc'}}} }
  });
}
loadRange();
</script>

<?php render_footer(); ?>
