<?php
include("../api/auth.php");

$auth = new Auth();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $loginResult = $auth->login($email, $password);

    if ($loginResult === true) {
        header("Location: dashboard.php");
        exit();
    } else {
        $message = "Login Failed: " . $loginResult;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <?php if (isset($message)) echo "<p>$message</p>"; ?>
    <form method="POST">
        <input type="email" name="email" placeholder="Enter Email" required>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type="submit">Login</button>
    </form>
</body>
</html>
