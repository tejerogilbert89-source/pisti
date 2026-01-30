<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===============================
   ADMIN CHECK
================================ */
if (!isset($_SESSION['username']) || $_SESSION['username'] !== "admin") {
    header("Location: login.php");
    exit();
}

/* ===============================
   BORROW BOOK
================================ */
if (isset($_POST['borrow'])) {

    $borrower_id   = trim($_POST['borrower_id']);
    $borrower_type = $_POST['borrower_type'];

    // Convert names to uppercase
    $first_name  = strtoupper(trim($_POST['first_name']));
    $middle_name = strtoupper(trim($_POST['middle_name']));
    $last_name   = strtoupper(trim($_POST['last_name']));

    $course = $_POST['course'] ?? '';
    $year   = intval($_POST['year'] ?? 0);
    $book_id = intval($_POST['book_id']);

    $borrower_name = trim("$first_name $middle_name $last_name");

    /* ===============================
       VALIDATE INPUT
    ================================ */
    if (!preg_match("/^[A-Z ]+$/", $first_name)) {
        $_SESSION['error'] = "First name must contain letters only.";
        header("Location: borrow.php");
        exit();
    }
    if (!empty($middle_name) && !preg_match("/^[A-Z ]+$/", $middle_name)) {
        $_SESSION['error'] = "Middle name must contain letters only.";
        header("Location: borrow.php");
        exit();
    }
    if (!preg_match("/^[A-Z ]+$/", $last_name)) {
        $_SESSION['error'] = "Last name must contain letters only.";
        header("Location: borrow.php");
        exit();
    }
    if (!preg_match("/^[0-9]+$/", $borrower_id)) {
        $_SESSION['error'] = "Borrower ID must contain numbers only.";
        header("Location: borrow.php");
        exit();
    }
    if ($borrower_type === "Student" && strlen($borrower_id) != 7) {
        $_SESSION['error'] = "Student ID must be 7 digits.";
        header("Location: borrow.php");
        exit();
    }
    if ($borrower_type === "Teacher" && strlen($borrower_id) != 5) {
        $_SESSION['error'] = "Teacher ID must be 5 digits.";
        header("Location: borrow.php");
        exit();
    }

    /* ===============================
       CHECK BOOK AVAILABILITY
    ================================ */
    $stmt = $conn->prepare("SELECT Title, volume FROM books WHERE book_id=?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$book) {
        $_SESSION['error'] = "Book not found.";
        header("Location: borrow.php");
        exit();
    }

    if ($book['volume'] <= 0) {
        $_SESSION['error'] = "Book is out of stock.";
        header("Location: borrow.php");
        exit();
    }

    /* ===============================
       CHECK BORROWER EXISTENCE
    ================================ */
    $stmt = $conn->prepare("SELECT borrower_id FROM borrower WHERE borrower_id=?");
    $stmt->bind_param("i", $borrower_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows;
    $stmt->close();

    /* ===============================
       INSERT BORROWER IF NEW
    ================================ */
    if (!$exists) {
        $stmt = $conn->prepare("
            INSERT INTO borrower
            (borrower_id, borrower_name, course, year, borrower_type,
             first_name, middle_name, last_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ississss",
            $borrower_id,
            $borrower_name,
            $course,
            $year,
            $borrower_type,
            $first_name,
            $middle_name,
            $last_name
        );
        $stmt->execute();
        $stmt->close();
    }

    /* ===============================
       INSERT TRANSACTION
    ================================ */
    $stmt = $conn->prepare("
        INSERT INTO transactions (book_id, borrower_id, date_borrowed)
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ii", $book_id, $borrower_id);
    $stmt->execute();
    $stmt->close();

    /* ===============================
       UPDATE BOOK VOLUME
    ================================ */
    $stmt = $conn->prepare("
        UPDATE books
        SET volume = volume - 1,
            status = IF(volume - 1 > 0, 'Available', 'Out of Stock')
        WHERE book_id=?
    ");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book borrowed successfully!";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   RETURN BOOK
================================ */
if (isset($_POST['return'])) {
    $transaction_id = intval($_POST['transaction_id']);

    $stmt = $conn->prepare("
        SELECT book_id FROM transactions
        WHERE transaction_id=? AND date_returned IS NULL
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $conn->query("UPDATE transactions SET date_returned=NOW() WHERE transaction_id=$transaction_id");
        $conn->query("UPDATE books SET volume=volume+1, status='Available' WHERE book_id=".$row['book_id']);
        $_SESSION['message'] = "Book returned successfully!";
    } else {
        $_SESSION['error'] = "Transaction not found or already returned.";
    }

    header("Location: borrow.php");
    exit();
}

/* ===============================
   DELETE TRANSACTION
================================ */
if (isset($_POST['delete'])) {
    $transaction_id = intval($_POST['transaction_id']);
    $conn->query("DELETE FROM transactions WHERE transaction_id=$transaction_id");

    $_SESSION['message'] = "Transaction deleted.";
    header("Location: borrow.php");
    exit();
}

/* ===============================
   FETCH BORROWED BOOKS
================================ */
$borrowed = $conn->query("
    SELECT t.transaction_id, t.date_borrowed,
           br.borrower_name, br.borrower_type, br.course, br.year,
           bo.Title
    FROM transactions t
    JOIN borrower br ON t.borrower_id = br.borrower_id
    JOIN books bo ON t.book_id = bo.book_id
    WHERE t.date_returned IS NULL
    ORDER BY t.date_borrowed ASC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Borrow Books</title>
<link rel="stylesheet" href="borrow.css">
<style>
input[name="first_name"],
input[name="middle_name"],
input[name="last_name"] {
    text-transform: uppercase;
}
</style>
</head>
<body>

<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="index.php">Books</a></li>
        <li><a href="borrow.php">Borrow / Return</a></li>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<div class="main-content">
<h1>Borrow / Return Books</h1>

<?php
if (isset($_SESSION['message'])) {
    echo "<p style='color:green'>".$_SESSION['message']."</p>";
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    echo "<p style='color:red'>".$_SESSION['error']."</p>";
    unset($_SESSION['error']);
}
?>

<form method="POST" class="borrow-form">

    <div class="borrower-type-section">
        <label for="borrower_type">Borrower Type:</label>
        <select name="borrower_type" id="borrower_type" onchange="toggleFields()" required>
            <option value="Student">Student</option>
            <option value="Teacher">Teacher</option>
        </select>
    </div>

    <div class="name-fields">
        <div class="form-group">
            <label>First Name:</label>
            <input type="text" name="first_name" required pattern="[A-Za-z ]+" title="Letters only" oninput="this.value = this.value.toUpperCase()">
        </div>

        <div class="form-group">
            <label>Middle Name:</label>
            <input type="text" name="middle_name" pattern="[A-Za-z ]*" title="Letters only" oninput="this.value = this.value.toUpperCase()">
        </div>

        <div class="form-group">
            <label>Last Name:</label>
            <input type="text" name="last_name" required pattern="[A-Za-z ]+" title="Letters only" oninput="this.value = this.value.toUpperCase()">
        </div>
    </div>

    <div class="form-group">
        <label>Borrower ID:</label>
        <input type="text" name="borrower_id" id="borrower_id" required pattern="[0-9]+" title="Numbers only">
        <small id="borrowerIdHint" style="color:gray; display:block; margin-top:4px;">
            Enter 7-digit Student ID or 5-digit Teacher ID.
        </small>
    </div>

    <div class="form-group" id="courseYear">
        <div>
            <label>Course:</label>
            <select name="course">
                <option value="">Select Course</option>
                <option value="BSIT">BSIT</option>
                <option value="BSTM">BSTM</option>
                <option value="BSHM">BSHM</option>
                <option value="BSBA">BSBA</option>
                <option value="BSA">BSA</option>
            </select>
        </div>

        <div>
            <label>Year:</label>
            <input type="number" name="year">
        </div>
    </div>

    <div class="form-group">
        <label>Book ID:</label>
        <input type="number" name="book_id" required>
    </div>

    <button type="submit" name="borrow">Borrow</button>
</form>

<h2>Borrowed Books</h2>
<input type="text" id="search" placeholder="Search books..." onkeyup="searchTable()">

<table border="1" cellpadding="6">
<tr>
    <th>TITLE</th>
    <th>FIRST NAME / MIDDLE NAME / LAST NAME</th>
    <th>TYPE</th>
    <th>COURSE / YEAR</th>
    <th>DATE</th>
    <th>ACTION</th>
</tr>

<?php while ($row = $borrowed->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['borrower_name']) ?></td>
    <td><?= htmlspecialchars($row['borrower_type']) ?></td>
    <td><?= htmlspecialchars($row['course']) ?> <?= htmlspecialchars($row['year']) ?></td>
    <td><?= htmlspecialchars($row['date_borrowed']) ?></td>
    <td>
        <form method="POST" style="display:inline">
            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
            <button name="return">Return</button>
        </form>
        <form method="POST" style="display:inline">
            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
            <button name="delete">Delete</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>
</table>
</div>

<script>
function toggleFields() {
    const type = document.getElementById("borrower_type").value;
    document.getElementById("courseYear").style.display =
        type === "Teacher" ? "none" : "block";

    const hint = document.getElementById("borrowerIdHint");
    hint.textContent = type === "Student" ? 
        "Enter 7-digit Student ID." : "Enter 5-digit Teacher ID.";
}
window.onload = toggleFields;

function searchTable() {
    const input = document.getElementById("search").value.toLowerCase();
    const rows = document.querySelectorAll("table tr:not(:first-child)");
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>
