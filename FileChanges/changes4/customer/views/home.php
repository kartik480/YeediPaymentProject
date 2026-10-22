<?php include __DIR__.'/_head.php'; ?>
<h1>YEEDIPAY</h1>
<p>Open a customer payment link.</p>
<form method="get" action="<?=e(url('/'))?>">
    <label>Customer code<br><input name="code" value="CUST0003" required style="padding:10px;font-size:1rem"></label>
    <p><button type="submit">Open payment page</button></p>
</form>
<p><a href="<?=e(url('/p/CUST0003'))?>">Pay as CUST0003</a></p>
<?php include __DIR__.'/_foot.php'; ?>
