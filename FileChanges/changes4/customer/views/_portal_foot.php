<?php $portalLayout = $portalLayout ?? 'sidebar'; ?>
</div>
<?php if ($portalLayout === 'sidebar'): ?>
    </div>
</div>
<?php endif; ?>
<?php if (!empty($print)): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
</body>
</html>
