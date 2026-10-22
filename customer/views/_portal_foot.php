<?php $portalLayout = $portalLayout ?? 'sidebar'; ?>
</div>
<?php if ($portalLayout === 'sidebar'): ?>
    </div>
</div>
<?php endif; ?>
<?php if (!empty($print)): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
<script>
(function(){
  var app=document.querySelector('.yp-app');
  if(!app) return;
  var btn=app.querySelector('.yp-menu-btn');
  var backdrop=app.querySelector('.yp-nav-backdrop');
  var side=app.querySelector('.yp-side');
  function closeNav(){app.classList.remove('yp-nav-open');if(btn)btn.setAttribute('aria-expanded','false');}
  function openNav(){app.classList.add('yp-nav-open');if(btn)btn.setAttribute('aria-expanded','true');}
  if(btn){btn.addEventListener('click',function(){app.classList.contains('yp-nav-open')?closeNav():openNav();});}
  if(backdrop){backdrop.addEventListener('click',closeNav);}
  if(side){side.querySelectorAll('a').forEach(function(a){a.addEventListener('click',closeNav);});}
})();
</script>
</body>
</html>
