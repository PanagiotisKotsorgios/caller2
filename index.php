<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

redirect(current_user() ? 'dashboard.php' : 'login.php');
