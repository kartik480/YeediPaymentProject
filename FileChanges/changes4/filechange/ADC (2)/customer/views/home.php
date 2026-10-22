<?php include __DIR__.'/_head.php'; ?>
<div class="card center">
  <h1>YEEDIPAY</h1>
  <p>Admin creates customers and bank QRs. Customers pay on their permanent link.</p>
  <p><a class="btn" href="<?=e(admin_url('/admin/login'))?>">Admin Login</a></p>
</div>
<div class="card" style="max-width:520px;margin:0 auto">
  <h2>Open a customer payment link</h2>
  <p class="muted">Use the Customer ID created in Admin (for example CUST0001).</p>
  <form method="get" action="<?=e(url('/'))?>" class="row">
    <input class="grow" name="code" placeholder="Customer ID e.g. CUST0001" required>
    <button>Open Payment</button>
  </form>
</div>
<?php include __DIR__.'/_foot.php'; ?>
