<?php
session_start();
date_default_timezone_set('Asia/Manila');

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
    $borrower_type = $_POST['borrower_type'] ?? '';
    $first_name    = strtoupper(trim($_POST['first_name'] ?? ''));
    $middle_name   = strtoupper(trim($_POST['middle_name'] ?? ''));
    $last_name     = strtoupper(trim($_POST['last_name'] ?? ''));
    $course        = $_POST['course'] ?? '';
    $year          = $_POST['year'] ?? '';
    $book_id       = intval($_POST['book_id']);
    $borrow_period = $_POST['borrow_period'] ?? '7 days';

    if (empty($borrower_id)) {
        $_SESSION['error'] = "Borrower ID is required.";
        header("Location: borrow.php"); exit();
    }

    /* CHECK IF BORROWER EXISTS */
    $stmt = $conn->prepare("SELECT * FROM borrower WHERE borrower_id=?");
    $stmt->bind_param("s", $borrower_id);
    $stmt->execute();
    $existingBorrower = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existingBorrower) {

        $borrower_type = $existingBorrower['borrower_type'];
        $course        = $existingBorrower['course'];
        $year          = $existingBorrower['year'];

    } else {

        if (empty($first_name) || empty($last_name) || empty($course) || empty($year)) {
            $_SESSION['error'] = "New borrower must complete full information.";
            header("Location: borrow.php"); exit();
        }

        $borrower_name = trim("$first_name $middle_name $last_name");

        $stmt = $conn->prepare("
            INSERT INTO borrower
            (borrower_id, borrower_name, course, year, borrower_type,
             first_name, middle_name, last_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssss",
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

    /* BORROW PERIOD */
    switch ($borrow_period) {
        case '1 day':   $due_date = date('Y-m-d H:i:s', strtotime('+1 day')); break;
        case '3 days':  $due_date = date('Y-m-d H:i:s', strtotime('+3 days')); break;
        case '1 month': $due_date = date('Y-m-d H:i:s', strtotime('+1 month')); break;
        default:        $due_date = date('Y-m-d H:i:s', strtotime('+7 days'));
    }

    /* CHECK BOOK */
    $stmt = $conn->prepare("SELECT available FROM books WHERE book_id=?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$book || $book['available'] <= 0) {
        $_SESSION['error'] = "Book unavailable.";
        header("Location: borrow.php"); exit();
    }

    /* INSERT TRANSACTION */
    $stmt = $conn->prepare("
        INSERT INTO transactions (book_id, borrower_id, date_borrowed, due_date, late_fee)
        VALUES (?, ?, NOW(), ?, 0)
    ");
    $stmt->bind_param("iss", $book_id, $borrower_id, $due_date);
    $stmt->execute();
    $stmt->close();

    /* UPDATE BOOK STOCK */
    $stmt = $conn->prepare("
        UPDATE books
        SET available = available - 1,
            borrowed  = borrowed + 1,
            status = IF(available - 1 > 0, 'Available', 'Out of Stock')
        WHERE book_id = ?
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

    $stmt = $conn->prepare("SELECT due_date FROM transactions WHERE transaction_id=?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $lateFee = 0;
    if ($row) {
        $today = new DateTime();
        $dueDate = new DateTime($row['due_date']);
        if ($today > $dueDate) {
            $daysLate = $dueDate->diff($today)->days;
            $lateFee = $daysLate * 5;
        }
    }

    $stmt = $conn->prepare("
        UPDATE transactions
        SET date_returned = NOW(), late_fee = ?
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("ii", $lateFee, $transaction_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE books b
        JOIN transactions t ON b.book_id = t.book_id
        SET b.available = b.available + 1,
            b.borrowed  = b.borrowed - 1,
            b.status = 'Available'
        WHERE t.transaction_id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book returned successfully!";
    header("Location: borrow.php");
    exit();
}

/* FETCH BORROWED BOOKS */
$borrowed = $conn->query("
    SELECT t.transaction_id, t.date_borrowed, t.due_date,
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
    <title>Admin Panel</title>
    <link rel="stylesheet" href="borrow.css">
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
h1 { margin-bottom: 20px; }
input[name="first_name"], input[name="middle_name"], input[name="last_name"] { text-transform: uppercase; }
.borrow-form .form-group small { display: block; margin-top: 2px; }
.grid-row { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px; }
.grid-row .form-group { flex: 1; min-width: 150px; }
button { padding: 6px 12px; cursor: pointer; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
table, th, td { border: 1px solid #333; }
th, td { padding: 8px; text-align: left; }
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
    <h1>Dashboard</h1>
</div>

</body>

<form method="POST" class="borrow-form">

    <!-- Borrower Type -->
    <div class="form-row single">
        <div class="form-group">
            <label>BORROWER TYPE</label>
            <div class="type-options">
                <input type="radio" name="borrower_type" id="student" value="Student" required>
                <label for="student" class="type-btn">Student</label>

                <input type="radio" name="borrower_type" id="teacher" value="Teacher">
                <label for="teacher" class="type-btn">Teacher</label>
            </div>
        </div>
    </div>

    <!-- Borrower ID -->
    <div class="form-row single">
        <div class="form-group" style="width:400px;">
            <label>BORROWER ID</label>
            <input type="text" name="borrower_id" required>
        </div>
    </div>

    <!-- Name Row -->
    <div class="form-row triple">
        <div class="form-group">
            <label>FIRST NAME</label>
            <input type="text" name="first_name" required>
        </div>

        <div class="form-group">
            <label>MIDDLE NAME</label>
            <input type="text" name="middle_name">
        </div>

        <div class="form-group">
            <label>LAST NAME</label>
            <input type="text" name="last_name" required>
        </div>
    </div>

    <!-- Course & Year -->
    <div class="form-row double">
        <div class="form-group">
            <label>COURSE</label>
            <input type="text" name="course">
        </div>

        <div class="form-group">
            <label>YEAR</label>
            <select name="year">
                <option value="">Select Year</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
            </select>
        </div>
    </div>

    <!-- Book ID & Borrow Period -->
    <div class="form-row double">
        <div class="form-group">
            <label>BOOK ID</label>
            <input type="text" name="book_id" required>
        </div>

        <div class="form-group">
            <label>BORROW PERIOD (Days)</label>
            <input type="number" name="borrow_period" min="1" max="30" required>
        </div>
    </div>

    <button type="submit" name="borrow">Borrow Book</button>

</form>


<h2>Currently Borrowed</h2>
<table>
<tr>
<th>Title</th>
<th>Borrower</th>
<th>Type</th>
<th>Course-Year</th>
<th>Date Borrowed</th>
<th>Due Date</th>
<th>Late Fee (₱)</th>
<th>Action</th>
</tr>

<?php while($row = $borrowed->fetch_assoc()): 
$today = new DateTime();
$dueDate = new DateTime($row['due_date']);
$lateFee = 0;
$overdueClass = '';
if($today > $dueDate){
    $daysLate = $dueDate->diff($today)->days;
    $lateFee = $daysLate * 5;
    $overdueClass = 'overdue';
}
?>
<tr>
<td><?= htmlspecialchars($row['Title']) ?></td>
<td><?= htmlspecialchars($row['borrower_name']) ?></td>
<td><?= $row['borrower_type'] ?></td>
<td><?= $row['course'].'-'.$row['year'] ?></td>
<td><?= $row['date_borrowed'] ?></td>
<td class="<?= $overdueClass ?>">
<?= $row['due_date'] ?> <?= $lateFee > 0 ? '(OVERDUE)' : '' ?>
</td>
<td class="overdue"><?= number_format($lateFee, 2) ?></td>
<td>
<form method="POST">
<input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
<button name="return">Return</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>

</body>
</html>










