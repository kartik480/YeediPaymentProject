</div>
<script>
(function(){
  var btn=document.querySelector('.nav-menu-btn');
  var nav=document.getElementById('adminNav');
  if(!btn||!nav) return;
  btn.addEventListener('click',function(){
    var open=nav.classList.toggle('is-open');
    btn.setAttribute('aria-expanded',open?'true':'false');
  });
})();
</script>
</body></html>