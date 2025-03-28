<?php
include("../api/auth.php");

$auth = new Auth();

if (!$auth->isAuthenticated()) {
    header("Location: login.php");
    exit();
}
?>

<h1>Welcome to your Dashboard</h1>
<p>You are logged in as <?= $_SESSION["user"]["user"]["email"] ?>.</p>
<a href="logout.php">Logout</a>
