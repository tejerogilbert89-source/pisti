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

/* =========================================================
   AJAX: CHECK BORROWER
========================================================= */
if (isset($_GET['checkBorrower'])) {
    $id = $_GET['checkBorrower'];

    $stmt = $conn->prepare("SELECT borrower_name FROM borrower WHERE borrower_id=?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode($res ? $res : []);
    exit();
}

/* ===============================
   BORROW BOOK
================================ */
if (isset($_POST['borrow'])) {

    $borrower_id   = trim($_POST['borrower_id']);
    $book_id       = intval($_POST['book_id']);
    $borrow_period = $_POST['borrow_period'] ?? '7 days';

    // Check borrower
    $stmt = $conn->prepare("SELECT * FROM borrower WHERE borrower_id=?");
    $stmt->bind_param("s", $borrower_id);
    $stmt->execute();
    $borrowerData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$borrowerData) {
        $_SESSION['error'] = "Borrower not registered.";
        header("Location: borrow.php");
        exit();
    }

    // Due date
    switch ($borrow_period) {
        case '1 day':   $due_date = date('Y-m-d H:i:s', strtotime('+1 day')); break;
        case '3 days':  $due_date = date('Y-m-d H:i:s', strtotime('+3 days')); break;
        case '7 days':  $due_date = date('Y-m-d H:i:s', strtotime('+7 days')); break;
        case '1 month': $due_date = date('Y-m-d H:i:s', strtotime('+1 month')); break;
        default:        $due_date = date('Y-m-d H:i:s', strtotime('+7 days'));
    }

    // Check book availability
    $stmt = $conn->prepare("SELECT available FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$book || $book['available'] <= 0) {
        $_SESSION['error'] = "Book unavailable.";
        header("Location: borrow.php");
        exit();
    }

    // Insert transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions (book_id, borrower_id, date_borrowed, due_date, late_fee)
        VALUES (?, ?, NOW(), ?, 0)
    ");
    $stmt->bind_param("iss", $book_id, $borrower_id, $due_date);
    $stmt->execute();
    $stmt->close();

    // Update books
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

    $stmt = $conn->prepare("
        SELECT book_id, due_date
        FROM transactions 
        WHERE transaction_id = ? AND date_returned IS NULL
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $trans = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($trans) {

        $book_id = $trans['book_id'];

        // compute late fee
        $lateFee = 0;
        $today = new DateTime();
        $dueDate = new DateTime($trans['due_date']);
        if ($today > $dueDate) {
            $lateFee = $dueDate->diff($today)->days * 5;
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
            UPDATE books
            SET available = available + 1,
                borrowed  = borrowed - 1,
                status = 'Available'
            WHERE book_id = ?
        ");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['message'] = "Book returned successfully!";
    } else {
        $_SESSION['error'] = "Invalid return transaction.";
    }

    header("Location: borrow.php");
    exit();
}

/* ===============================
   FETCH BORROWED (FILTER + SORT)
================================ */
$filter_status = $_GET['filter_status'] ?? '';

$sql = "
    SELECT t.transaction_id, t.date_borrowed, t.due_date, t.late_fee,
           br.borrower_name, br.borrower_type, br.course, br.year,
           bo.Title
    FROM transactions t
    JOIN borrower br ON t.borrower_id = br.borrower_id
    JOIN books bo ON t.book_id = bo.book_id
    WHERE t.date_returned IS NULL
";

if ($filter_status === 'overdue') {
    $sql .= " AND t.due_date < NOW()";
}

$sql .= " ORDER BY (t.due_date < NOW()) DESC, t.due_date ASC";

$borrowed = $conn->query($sql);

/* overdue counter */
$countOverdue = $conn->query("
    SELECT COUNT(*) AS total
    FROM transactions
    WHERE date_returned IS NULL
    AND due_date < NOW()
")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Borrow Books</title>
<link rel="stylesheet" href="borrow.css">

<style>
h1{ text-align:center; margin-bottom:25px; }
.form-center{ display:flex; justify-content:center; }
.grid-row{ display:flex; gap:18px; flex-wrap:wrap; justify-content:center; }
.form-group{ display:flex; flex-direction:column; min-width:200px; }
.center-btn{ display:flex; justify-content:center; margin-top:25px; }
.center-btn button{
    padding:12px 42px;
    font-size:16px;
    font-weight:bold;
    border:none;
    border-radius:10px;
    background:linear-gradient(135deg,#0d8f55,#0a6b40);
    color:white;
    cursor:pointer;
}
table{ border-collapse:collapse; width:100%; margin-top:30px; background:white; }
table,th,td{ border:1px solid #333; }
th{ background:#0d8f55; color:white; }
th,td{ padding:8px; }
.overdue-row{ background:#ffe6e6; }
#search{ margin-bottom:15px; padding:6px; width:50%; display:block; margin-left:auto; margin-right:auto; }
</style>
</head>
<body>

<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="index.php">Books</a></li>
        <li><a href="Registration.php">Register Borrower</a></li>
        <li><a href="borrow.php">Borrow / Return</a></li>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<h1>Borrow / Return Books</h1>

<?php
if(isset($_SESSION['message'])){
    echo "<p style='color:green;text-align:center'>".$_SESSION['message']."</p>";
    unset($_SESSION['message']);
}
if(isset($_SESSION['error'])){
    echo "<p style='color:red;text-align:center'>".$_SESSION['error']."</p>";
    unset($_SESSION['error']);
}
?>

<form method="POST">
<div class="form-center">
    <div class="grid-row">
        <div class="form-group">
            <label>Borrower ID</label>
            <input type="text" id="borrower_id" name="borrower_id" required>
        </div>

        <div class="form-group">
            <label>Book ID</label>
            <input type="number" name="book_id" required>
        </div>

        <div class="form-group">
            <label>Borrow Period</label>
            <select name="borrow_period">
                <option value="1 day">1 Day</option>
                <option value="3 days">3 Days</option>
                <option value="7 days">7 Days</option>
                <option value="1 month">1 Month</option>
            </select>
        </div>
    </div>
</div>

<div class="center-btn">
    <button name="borrow">Borrow</button>
</div>
</form>

<p style="text-align:center;font-weight:bold;">
Overdue Books: <?= $countOverdue ?>
</p>

<form method="GET" style="text-align:center;margin-bottom:15px;">
    <label>Filter:</label>
    <select name="filter_status">
        <option value="">All Borrowed</option>
        <option value="overdue" <?= $filter_status==='overdue'?'selected':'' ?>>
            Overdue Only
        </option>
    </select>
    <button type="submit">Apply</button>
</form>

<h2>Borrowed Books</h2>
<input type="text" id="search" placeholder="Search borrower name...">

<table>
<tr>
    <th>TITLE</th>
    <th>NAME</th>
    <th>TYPE</th>
    <th>COURSE / YEAR</th>
    <th>DATE BORROWED</th>
    <th>DUE DATE</th>
    <th>LATE FEE (₱)</th>
    <th>ACTION</th>
</tr>

<?php while($row = $borrowed->fetch_assoc()):
    $today = new DateTime();
    $dueDate = new DateTime($row['due_date']);
    $lateFee = ($today > $dueDate) ? $dueDate->diff($today)->days * 5 : 0;
?>
<tr class="<?= $lateFee>0 ? 'overdue-row' : '' ?>">
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td title="<?= htmlspecialchars($row['borrower_name']) ?>">
        <?= strlen($row['borrower_name'])>30
            ? htmlspecialchars(substr($row['borrower_name'],0,30).'...')
            : htmlspecialchars($row['borrower_name']) ?>
    </td>
    <td><?= htmlspecialchars($row['borrower_type']) ?></td>
    <td><?= htmlspecialchars($row['course'].' - '.$row['year']) ?></td>
    <td><?= date('F d, Y h:i A', strtotime($row['date_borrowed'])) ?></td>
    <td style="color:<?= $lateFee>0?'red':'inherit' ?>">
        <?= date('F d, Y h:i A', strtotime($row['due_date'])) ?> <?= $lateFee>0?'<br><strong>OVERDUE</strong>':'' ?>
    </td>
    <td style="color:red;font-weight:bold;"><?= number_format($lateFee,2) ?></td>
    <td>
        <form method="POST">
            <input type="hidden" name="transaction_id" value="<?= $row['transaction_id'] ?>">
            <button name="return">Return</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>
</table>

<script>
const borrowerIdInput = document.getElementById('borrower_id');
borrowerIdInput.addEventListener('input', () => {
    borrowerIdInput.value = borrowerIdInput.value.replace(/\D/g, '');
});

// Live search for Borrower Name
const searchInput = document.getElementById('search');
searchInput.addEventListener('input', () => {
    const filter = searchInput.value.toLowerCase();
    const table = document.querySelector('table');
    const rows = table.querySelectorAll('tbody tr, tr'); // include all rows
    
    rows.forEach((row, index) => {
        if(index === 0) return; // skip header row
        const nameCell = row.cells[1]; // borrower name is in 2nd column
        const nameText = nameCell.textContent.toLowerCase();
        if(nameText.includes(filter)){
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

</body>
</html>