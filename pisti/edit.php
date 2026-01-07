<?php
session_start();
include "db.php";

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// REQUIRE book_id
if (!isset($_GET['book_id'])) {
    header("Location: index.php");
    exit();
}

$book_id = intval($_GET['book_id']);

/*
=================================
 GET BOOK INFO
=================================
*/
$stmt = $conn->prepare("SELECT * FROM books WHERE book_id=? LIMIT 1");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    die("Book not found.");
}

/*
=================================
 UPDATE BOOK
=================================
*/
if (isset($_POST['updateItem'])) {

    $name     = trim($_POST['itemName']);
    $category = trim($_POST['itemCategory']);
    $author   = trim($_POST['itemAuthor']);
    $isbn     = trim($_POST['itemISBN']);
    $status   = trim($_POST['itemStatus']);
    $quantity = (int)$_POST['itemQuantity'];

    // Prevent negative quantity
    if ($quantity < 0) {
        $quantity = 0;
    }

    // Auto-fix status based on quantity
    if ($quantity == 0) {
        $status = 'Out of Stock';
    }

    $update = $conn->prepare("
        UPDATE books 
        SET book_name=?, category=?, author=?, isbn=?, status=?, volume=?
        WHERE book_id=?
    ");
    $update->bind_param(
        "sssssii",
        $name,
        $category,
        $author,
        $isbn,
        $status,
        $quantity,
        $book_id
    );
    $update->execute();

    header("Location: index.php?updated=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Book</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <aside class="sidebar">
        <h2>ADMIN</h2>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="borrow.php">Borrow / Return</a></li>
            <li><a href="transaction.php">Transaction History</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="main">

        <header class="topbar">
            <h1>Edit Book</h1>
        </header>

        <section class="page-inner">

            <form method="POST" class="item-form">

                <label>Book Name</label>
                <input type="text" name="itemName"
                       value="<?= htmlspecialchars($book['book_name']) ?>" required>

                <label>Category</label>
                <input type="text" name="itemCategory"
                       value="<?= htmlspecialchars($book['category']) ?>" required>

                <label>Author</label>
                <input type="text" name="itemAuthor"
                       value="<?= htmlspecialchars($book['Author']) ?>" required>

                <label>ISBN</label>
                <input type="text" name="itemISBN"
                       value="<?= htmlspecialchars($book['ISBN']) ?>" required>

                <label>Status</label>
                <select name="itemStatus">
                    <option value="Available" <?= $book['status']=="Available" ? "selected" : "" ?>>Available</option>
                    <option value="Borrowed" <?= $book['status']=="Borrowed" ? "selected" : "" ?>>Borrowed</option>
                    <option value="Defective" <?= $book['status']=="Defective" ? "selected" : "" ?>>Defective</option>
                    <option value="Out of Stock" <?= $book['status']=="Out of Stock" ? "selected" : "" ?>>Out of Stock</option>
                </select>

                <label>Quantity</label>
                <input type="number" name="itemQuantity" min="0"
                       value="<?= (int)$book['volume'] ?>" required>

                <button type="submit" name="updateItem">Save Changes</button>
                <a href="index.php">Cancel</a>

            </form>

        </section>
    </main>

</div>

</body>
</html>
