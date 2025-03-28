<?php
include("api/auth.php");

$auth = new Auth();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $result = $auth->register($email, $password);

    if (isset($result["error"])) {
        $message = "Error: " . $result["error"]["message"];
    } else {
        $message = "Registration successful! Please check your email for confirmation.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>
    <?php if (isset($message)) echo "<p>$message</p>"; ?>
    <form method="POST">
        <input type="email" name="email" placeholder="Enter Email" required>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type="submit">Register</button>
    </form>
</body>
</html>
