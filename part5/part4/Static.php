<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ===============================
   DATABASE CONNECTION
================================ */
$conn = new mysqli("localhost", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
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
   COURSE FILTER
================================ */
$selectedCourse = isset($_GET['course']) ? $_GET['course'] : "";
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m'); // default current month
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');      // default current year

/* ===============================
   COURSE LIST
================================ */
$coursesList = ['BSIT', 'BSHM', 'BSBA', 'BSA', 'TEED', 'BSTM'];

/* ===============================
   FETCH COURSE BORROW COUNT (for chart)
================================ */
$courseData = [];
$coursePlaceholders = "'" . implode("','", $coursesList) . "'";

$courseResult = $conn->query("
    SELECT b.course, COUNT(t.transaction_id) as total_books
    FROM borrower b
    LEFT JOIN transactions t ON b.borrower_id = t.borrower_id
    WHERE b.course IN ($coursePlaceholders)
    GROUP BY b.course
");
while ($row = $courseResult->fetch_assoc()) {
    $courseData[$row['course']] = $row['total_books'];
}
// Ensure all courses appear
foreach ($coursesList as $c) {
    if (!isset($courseData[$c])) $courseData[$c] = 0;
}

/* ===============================
   FETCH TRANSACTIONS (WITH FILTER)
================================ */
$sql = "
    SELECT 
        t.transaction_id,
        t.date_borrowed,
        t.date_returned,
        b.borrower_name,
        b.borrower_type,
        b.borrower_id,
        b.course,
        b.year,
        bo.Title
    FROM transactions t
    LEFT JOIN borrower b ON t.borrower_id = b.borrower_id
    LEFT JOIN books bo ON t.book_id = bo.book_id
    WHERE 1=1
";

if (!empty($selectedCourse)) {
    $sql .= " AND b.course = '" . $conn->real_escape_string($selectedCourse) . "'";
}
if (!empty($selectedMonth) && !empty($selectedYear)) {
    $sql .= " AND MONTH(t.date_borrowed) = '" . intval($selectedMonth) . "' AND YEAR(t.date_borrowed) = '" . intval($selectedYear) . "'";
}

$sql .= " ORDER BY t.transaction_id DESC";
$transactions = $conn->query($sql);
if (!$transactions) die("Query Error: " . $conn->error);

/* ===============================
   MONTHLY BORROWERS COUNT
================================ */
$thisMonthBorrowers = $conn->query("
    SELECT COUNT(DISTINCT t.borrower_id) as total
    FROM transactions t
    WHERE MONTH(t.date_borrowed) = '".intval($selectedMonth)."' AND YEAR(t.date_borrowed) = '".intval($selectedYear)."'
")->fetch_assoc()['total'] ?? 0;

/* ===============================
   MONTH LIST
================================ */
$months = [
    1=>"January",2=>"February",3=>"March",4=>"April",5=>"May",6=>"June",
    7=>"July",8=>"August",9=>"September",10=>"October",11=>"November",12=>"December"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Transaction History</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { margin:0; font-family:Arial; background:#f4f6f9; }
.sidebar { width:220px; height:100vh; background:#2c3e50; color:white; position:fixed; padding:20px; }
.sidebar ul { list-style:none; padding:0; }
.sidebar ul li { margin:15px 0; }
.sidebar ul li a { color:white; text-decoration:none; }
.main { margin-left:240px; padding:20px; }

select { padding:8px; margin-bottom:15px; margin-right:10px; }
.course-box { background:white; padding:15px; margin-bottom:15px; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.1); }

table { width:100%; border-collapse:collapse; background:white; }
table th, table td { padding:10px; border:1px solid #ddd; text-align:center; }
table th { background:#34495e; color:white; }

#printBtn {
    padding:10px 20px;
    margin-bottom:15px;
    background:#2980b9;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}
#printBtn:hover { background:#1c5980; }

.stat-box { display:inline-block; background:#fff; padding:15px; border-radius:8px; margin-right:10px; margin-bottom:10px; box-shadow:0 4px 8px rgba(0,0,0,0.1); text-align:center; min-width:150px;}
.stat-box h3 { margin:0 0 5px; font-size:14px; color:#555; }
.stat-box p { margin:0; font-size:18px; font-weight:bold; color:#2c3e50; }
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

<div class="main">
    <h1>Transaction History</h1>

    <!-- STATISTICS -->
    <div class="stat-box">
        <h3>Borrowers in <?= $months[intval($selectedMonth)] ?> <?= $selectedYear ?></h3>
        <p><?= $thisMonthBorrowers ?></p>
    </div>

    <!-- FILTER FORM -->
    <form method="GET">
        <label>Course:</label>
        <select name="course">
            <option value="">-- All Courses --</option>
            <?php foreach ($coursesList as $courseOption): ?>
                <option value="<?= $courseOption ?>" <?= ($selectedCourse == $courseOption) ? 'selected' : '' ?>><?= $courseOption ?></option>
            <?php endforeach; ?>
        </select>

        <label>Month:</label>
        <select name="month">
            <?php foreach ($months as $num=>$name): ?>
                <option value="<?= $num ?>" <?= ($selectedMonth == $num) ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
        </select>

        <label>Year:</label>
        <select name="year">
            <?php 
            for($y=date('Y'); $y>=2020; $y--): ?>
                <option value="<?= $y ?>" <?= ($selectedYear == $y) ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit">Filter</button>
    </form>

    <!-- PRINT BUTTON -->
    <button id="printBtn" onclick="printRecords()">Print Records</button>

    <!-- BAR CHART -->
    <div class="course-box">
        <canvas id="courseChart" height="150"></canvas>
    </div>

    <!-- TABLE -->
    <table id="transactionTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>BOOK</th>
                <th>NAME</th>
                <th>BORROWER ID</th>
                <th>TYPE</th>
                <th>COURSE</th>
                <th>YEAR</th>
                <th>DATE BORROWED</th>
                <th>DATE RETURNED</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($transactions->num_rows > 0): ?>
            <?php while ($row = $transactions->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                    <td><?= htmlspecialchars($row['Title']) ?></td>
                    <td><?= htmlspecialchars($row['borrower_name']) ?></td>
                    <td><?= htmlspecialchars($row['borrower_id']) ?></td>
                    <td><?= htmlspecialchars($row['borrower_type']) ?></td>
                    <td><?= htmlspecialchars($row['course']) ?></td>
                    <td><?= htmlspecialchars($row['year']) ?></td>
                    <td><?= $row['date_borrowed'] ?></td>
                    <td><?= !empty($row['date_returned']) ? $row['date_returned'] : '<span style="color:red;">Not Returned</span>' ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="9">No transactions found</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Chart.js for books per course
const ctx = document.getElementById('courseChart').getContext('2d');
const courseChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($courseData)) ?>,
        datasets: [{
            label: 'Books Borrowed',
            data: <?= json_encode(array_values($courseData)) ?>,
            backgroundColor: 'rgba(52, 152, 219, 0.7)',
            borderColor: 'rgba(52, 152, 219, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            title: { display: true, text: 'Books Borrowed Per Course' }
        },
        scales: {
            y: { beginAtZero: true, stepSize: 1 }
        }
    }
});

// Print function
function printRecords() {
    const printContents = document.getElementById('transactionTable').outerHTML;
    const originalContents = document.body.innerHTML;

    document.body.innerHTML = "<h2>Transaction Records</h2>" + printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}
</script>

</body>
</html>