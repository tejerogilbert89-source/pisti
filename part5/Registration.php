<?php
session_start();
date_default_timezone_set('Asia/Manila'); // Philippine Time

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
    $first_name    = strtoupper(trim($_POST['first_name']));
    $middle_name   = strtoupper(trim($_POST['middle_name']));
    $last_name     = strtoupper(trim($_POST['last_name']));
    $course        = $_POST['course'] ?? '';
    $year          = $_POST['year'] ?? '';
    $book_id       = intval($_POST['book_id']);
    $borrow_period = $_POST['borrow_period'] ?? '7 days';
    $borrower_name = trim("$first_name $middle_name $last_name");

    // Validation
    if (!preg_match("/^[A-Z]+$/", $first_name) ||
        (!empty($middle_name) && !preg_match("/^[A-Z]+$/", $middle_name)) ||
        !preg_match("/^[A-Z]+$/", $last_name)) {
        $_SESSION['error'] = "Names must contain letters only.";
        header("Location: borrow.php"); exit();
    }

    if (!preg_match("/^[0-9]+$/", $borrower_id)) {
        $_SESSION['error'] = "Borrower ID must contain numbers only.";
        header("Location: borrow.php"); exit();
    }

    if ($borrower_type === "Student" && strlen($borrower_id) != 7) {
        $_SESSION['error'] = "Student ID must be exactly 7 digits.";
        header("Location: borrow.php"); exit();
    }

    if ($borrower_type === "Teacher" && strlen($borrower_id) != 5) {
        $_SESSION['error'] = "Teacher ID must be exactly 5 digits.";
        header("Location: borrow.php"); exit();
    }

    // Borrow period
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
        header("Location: borrow.php"); exit();
    }

    // Check or insert borrower
    $stmt = $conn->prepare("SELECT borrower_id FROM borrower WHERE borrower_id=?");
    $stmt->bind_param("s", $borrower_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows;
    $stmt->close();

    if (!$exists) {
        $stmt = $conn->prepare("
            INSERT INTO borrower
            (borrower_id, borrower_name, course, year, borrower_type,
             first_name, middle_name, last_name, Borrow_Period)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $period_days = str_contains($borrow_period,'day') ? intval($borrow_period) : ($borrow_period === '1 month' ? 30 : 7);
        $stmt->bind_param(
            "sssssssss",
            $borrower_id,
            $borrower_name,
            $course,
            $year,
            $borrower_type,
            $first_name,
            $middle_name,
            $last_name,
            $period_days
        );
        $stmt->execute();
        $stmt->close();
    }

    // Insert transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions (book_id, borrower_id, date_borrowed, due_date, late_fee)
        VALUES (?, ?, NOW(), ?, 0)
    ");
    $stmt->bind_param("iss", $book_id, $borrower_id, $due_date);
    $stmt->execute();
    $stmt->close();

    // Update book counts
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
    header("Location: borrow.php"); exit();
}

/* ===============================
   RETURN BOOK
================================ */
if (isset($_POST['return'])) {
    $transaction_id = intval($_POST['transaction_id']);

    $stmt = $conn->prepare("SELECT due_date FROM transactions WHERE transaction_id = ?");
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
            b.status   = 'Available'
        WHERE t.transaction_id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = "Book returned successfully!";
    header("Location: borrow.php"); exit();
}

/* ===============================
   FETCH BORROWED BOOKS
================================ */
$filter_status = $_GET['filter_status'] ?? '';
$search_name   = trim($_GET['search_name'] ?? '');

$sql = "
    SELECT t.transaction_id, t.date_borrowed, t.due_date, t.date_returned, t.late_fee,
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

if ($search_name !== '') {
    $sql .= " AND br.borrower_name LIKE ?";
    $stmt = $conn->prepare($sql);
    $like_name = "%$search_name%";
    $stmt->bind_param("s", $like_name);
    $stmt->execute();
    $borrowed = $stmt->get_result();
} else {
    $sql .= " ORDER BY (t.due_date < NOW()) DESC, t.due_date ASC";
    $borrowed = $conn->query($sql);
}

/* Overdue counter */
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
body { font-family: Arial, sans-serif; padding: 20px; }
h1 { margin-bottom: 20px; }
input[name="first_name"], input[name="middle_name"], input[name="last_name"] { text-transform: uppercase; }
.borrow-form .form-group small { display: block; margin-top: 2px; }
.grid-row { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px; }
.grid-row .form-group { flex: 1; min-width: 150px; transition: all 0.3s ease; }
button {
    padding: 6px 12px;
    cursor: pointer;
    background-color: green;
    color: white;
    border: none;
    border-radius: 5px;
    display: block;
    margin: 10px auto; /* centers the button */
}
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
table, th, td { border: 1px solid #333; }
th, td { padding: 8px; text-align: left; }
.overdue-row { background-color: #ffe6e6; }
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
if(isset($_SESSION['message'])) { echo "<p style='color:green'>".$_SESSION['message']."</p>"; unset($_SESSION['message']); }
if(isset($_SESSION['error']))   { echo "<p style='color:red'>".$_SESSION['error']."</p>"; unset($_SESSION['error']); }
?>

<form method="POST" class="borrow-form">
    <div class="form-group">
        <label>Borrower Type</label>
        <select name="borrower_type" required>
            <option value="Student">Student</option>
            <option value="Teacher">Teacher/Staff</option>
        </select>
    </div>

    <div class="grid-row">
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first_name" required pattern="[A-Za-z]+" title="Letters only">
        </div>
        <div class="form-group">
            <label>Middle Name</label>
            <input type="text" name="middle_name" pattern="[A-Za-z]*" title="Letters only">
        </div>
        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last_name" required pattern="[A-Za-z]+" title="Letters only">
        </div>
    </div>

    <div class="grid-row">
        <div class="form-group">
            <label>Borrower ID</label>
            <input type="text" id="borrower_id" name="borrower_id" required>
            <small id="borrower_id_hint">Enter your ID</small>
        </div>
        <div class="form-group">
            <label>Course</label>
            <select name="course">
                <option value="">--Select--</option>
                <option value="BSIT">BSIT</option>
                <option value="BSHM">BSHM</option>
                <option value="BSBA">BSBA</option>
                <option value="BSA">BSA</option>
                <option value="BSTM">BSTM</option>
                <option value="TEED">TEED</option>
            </select>
        </div>
        <div class="form-group">
            <label>Year</label>
            <select name="year">
                <option value="">--Select--</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select>
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

    <button type="submit" name="borrow">Register & Borrow</button>
</form>

<p><strong>Overdue Books:</strong> <?= $countOverdue ?></p>

<form method="GET">
    <label>Filter:</label>
    <select name="filter_status">
        <option value="">All Borrowed</option>
        <option value="overdue" <?= $filter_status==='overdue'?'selected':'' ?>>Overdue Only</option>
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
    <th>COURSE/YEAR</th>
    <th>DATE BORROWED</th>
    <th>DUE DATE</th>
    <th>LATE FEE(₱)</th>
</tr>

<?php while($row = $borrowed->fetch_assoc()):
    $dateBorrowed = new DateTime($row['date_borrowed']);
    $dueDateObj   = new DateTime($row['due_date']);
    $today        = new DateTime();
    $lateFee      = ($today > $dueDateObj) ? $dueDateObj->diff($today)->days * 5 : $row['late_fee'];
?>
<tr class="<?= $lateFee>0?'overdue-row':'' ?>">
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['borrower_name']) ?></td>
    <td><?= $row['borrower_type'] ?></td>
    <td><?= $row['course'].'-'.$row['year'] ?></td>
    <td><?= $dateBorrowed->format('F d, Y h:i A') ?></td>
    <td style="color:<?= $lateFee>0?'red':'inherit' ?>">
        <?= $dueDateObj->format('F d, Y h:i A') ?>
        <?= $lateFee>0 ? '<br><strong>OVERDUE</strong>' : '' ?>
    </td>
    <td style="color:red;font-weight:bold;">
        <?= number_format($lateFee,2) ?>
    </td>
</tr>
<?php endwhile; ?>
</table>

<script>
// Instant client-side table search
const searchInput = document.getElementById('search');
searchInput.addEventListener('keyup', function() {
    const filter = this.value.toUpperCase();
    const table = document.querySelector('table');
    const trs = table.getElementsByTagName('tr');
    for (let i = 1; i < trs.length; i++) {
        const nameTd = trs[i].getElementsByTagName('td')[1];
        trs[i].style.display = nameTd && nameTd.textContent.toUpperCase().includes(filter) ? '' : 'none';
    }
});

// Show/hide Course & Year fields based on Borrower Type
const borrowerType = document.querySelector('select[name="borrower_type"]');
const courseField = document.querySelector('select[name="course"]').parentElement;
const yearField = document.querySelector('select[name="year"]').parentElement;

function toggleStudentFields() {
    if (borrowerType.value === 'Student') {
        courseField.style.display = 'block';
        yearField.style.display = 'block';
    } else {
        courseField.style.display = 'none';
        yearField.style.display = 'none';
    }
}

// Initialize on page load
toggleStudentFields();
borrowerType.addEventListener('change', toggleStudentFields);
</script>

</body>
</html>