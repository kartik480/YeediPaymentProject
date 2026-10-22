<?php
include __DIR__.'/_dash_head.php';
$form_action = url('/c/'.$c['code'].'/start');
$show_top_dashboard = false;
$pay_shell = true;
$footer_link = null;
include __DIR__.'/_payment_start.php';
include __DIR__.'/_dash_foot.php';
