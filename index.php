<?php
session_start();

// Set PWA manifest in header before redirect
header('Link: </manifest.json>; rel="manifest"');
header('Location: includes/auth/login.php');
exit();
?>