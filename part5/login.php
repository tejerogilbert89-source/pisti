<?php
session_start();
include "db.php"; // must define $conn (mysqli)

$error = "";

/* ===============================
   HANDLE LOGIN
================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ===============================
       ADMIN LOGIN
    ================================ */
    if (isset($_POST['admin_login'])) {

        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if ($username === "" || $password === "") {
            $error = "Please enter username and password.";
        } else {

            $stmt = $conn->prepare("SELECT username, password FROM users WHERE username = ?");
            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();

                if (password_verify($password, $admin['password'])) {
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['role'] = 'admin';

                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Incorrect password!";
                }
            } else {
                $error = "Admin account not found!";
            }
        }
    }

    /* ===============================
       STUDENT ACCESS (AUTOMATIC)
    ================================ */
    if (isset($_POST['student_login'])) {

        $_SESSION['username']   = "Guest Student";
        $_SESSION['role']       = "student";
        $_SESSION['student_id'] = 0; // guest access

        header("Location: student_transaction.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Page</title>
<link rel="stylesheet" href="Untitled-1.css">
</head>
<body>

<header>De La Salle John Bosco College</header>

<main>
    <div class="logo">
        <img src="image.png.jpg" alt="logo">
        <h2>Higher Education's </h2>
         <h1>Library</h1>
        <h1>Book Borrowing</h1>
        <h1>System</h1>
    </div>

    <div class="login-wrapper">
        <div class="login-box">
            <h1>Log In</h1>

            <?php if ($error !== ""): ?>
                <p style="color:red; text-align:center;">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <form method="post" class="login-form">

                <!-- ADMIN LOGIN -->
                <input type="text" name="username" placeholder="Admin Username">
                <input type="password" name="password" placeholder="Password">

                <button type="submit" name="admin_login">
                    Admin Login
                </button>

                <!-- STUDENT AUTO LOGIN -->
                <button type="submit" name="student_login" formnovalidate>
                    Student Access
                </button>

            </form>
        </div>
    </div>
</main>

</body>
</html>
