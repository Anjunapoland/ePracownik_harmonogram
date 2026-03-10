<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . (current_user() ? 'schedule.php' : 'login.php'));
exit;
