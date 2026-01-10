<?php
session_start();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
}

header("Location: login.php");
exit;