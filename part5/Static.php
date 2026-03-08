<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ===============================
   DATABASE CONNECTION
=============================== */
$conn = new mysqli("localhost", "root", "", "school_inventory");
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===============================
   ADMIN CHECK
=============================== */
if (!isset($_SESSION['username']) || $_SESSION['username'] !== "admin") {
    header("Location: login.php");
    exit();
}

/* ===============================
   FILTERS
=============================== */
$selectedCourse = $_GET['course'] ?? "";
$selectedMonth  = $_GET['month'] ?? date('m');
$selectedYear   = $_GET['year'] ?? date('Y');

$coursesList = ['BSIT', 'BSHM', 'BSBA', 'BSA', 'TEED', 'BSTM'];

/* ===============================
   FETCH COURSE BORROW COUNT (BAR GRAPH)
=============================== */
$courseData = [];

$sqlCourse = "
    SELECT b.course, COUNT(t.transaction_id) as total_books
    FROM borrower b
    LEFT JOIN transactions t ON b.borrower_id = t.borrower_id
    WHERE b.course IN ('" . implode("','", $coursesList) . "')
";

if (!empty($selectedMonth) && !empty($selectedYear)) {
    $sqlCourse .= " AND MONTH(t.date_borrowed) = '".intval($selectedMonth)."'
                    AND YEAR(t.date_borrowed) = '".intval($selectedYear)."'";
}

$sqlCourse .= " GROUP BY b.course";

$courseResult = $conn->query($sqlCourse);

while ($row = $courseResult->fetch_assoc()) {
    $courseData[$row['course']] = $row['total_books'];
}

foreach ($coursesList as $c) {
    if (!isset($courseData[$c])) $courseData[$c] = 0;
}

/* ===============================
   FETCH UNIQUE BORROWERS
=============================== */
$sql = "
    SELECT 
        b.borrower_name,
        b.borrower_type,
        b.borrower_id,
        b.course,
        b.year,
        COUNT(t.transaction_id) as total_borrows
    FROM transactions t
    LEFT JOIN borrower b ON t.borrower_id = b.borrower_id
    WHERE 1=1
";

if (!empty($selectedCourse)) {
    $sql .= " AND b.course = '" . $conn->real_escape_string($selectedCourse) . "'";
}

if (!empty($selectedMonth) && !empty($selectedYear)) {
    $sql .= " AND MONTH(t.date_borrowed) = '" . intval($selectedMonth) . "' 
              AND YEAR(t.date_borrowed) = '" . intval($selectedYear) . "'";
}

$sql .= " GROUP BY b.borrower_id ORDER BY MAX(t.transaction_id) DESC";

$transactions = $conn->query($sql);
if (!$transactions) die("Query Error: " . $conn->error);

/* ===============================
   MONTHLY BORROWERS COUNT
=============================== */
$thisMonthBorrowers = $conn->query("
    SELECT COUNT(DISTINCT borrower_id) as total
    FROM transactions
    WHERE MONTH(date_borrowed) = '".intval($selectedMonth)."'
    AND YEAR(date_borrowed) = '".intval($selectedYear)."'
")->fetch_assoc()['total'] ?? 0;

$months = [
    1=>"January",2=>"February",3=>"March",4=>"April",5=>"May",6=>"June",
    7=>"July",8=>"August",9=>"September",10=>"October",11=>"November",12=>"December"
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Library Statistics</title>
<link rel="stylesheet" href="static.css">
<style>
/* ===========================
   ADDITIONAL STYLES
=========================== */

/* White chart background with shadow */
#courseChart {
    border-radius: 14px;
    background-color: #ffffff;
    padding: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,.1);
}

/* STAT BOX */
.stat-box {
    background:#2c7be5;
    border-radius:16px;
    padding:14px 20px;
    font-weight:700;
    color:white;
    margin-bottom:20px;
    text-align:center;
    font-size:1.2rem;
}

/* FILTER FORM */
form[method="GET"] { display:flex; flex-wrap:wrap; gap:12px; justify-content:center; margin-bottom:20px; }
form[method="GET"] select, form[method="GET"] button { padding:8px 12px; border-radius:12px; border:none; font-size:14px; }
form[method="GET"] button { background:#0b7a4f; color:#fff; cursor:pointer; font-weight:700; transition:.25s; }
form[method="GET"] button:hover { background:#075f3c; }

/* PRINT BUTTON */
#printBtn { display:block; margin:10px auto 30px; padding:10px 28px; border-radius:999px; border:none; background:#0b7a4f; color:#fff; font-weight:700; cursor:pointer; }
#printBtn:hover { background:#075f3c; }

/* TABLE WRAPPER */
.table-wrapper { overflow-x:auto; margin-bottom:40px; }

/* PRINT STYLES */
@media print {
    /* Hide sidebar, buttons, and filter */
    .sidebar, #printBtn, form[method="GET"] { display:none !important; }

    /* Main content full width */
    .main { margin:0; padding:0; }

    /* Ensure stat box and table visible */
    .stat-box { display:block !important; }
    .table-wrapper table { width:100%; }

    body { margin:0; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <h2>ADMIN</h2>
    <ul>
        <li><a href="Transaction.php">Transaction History</a></li>
        <li><a href="Static.php">Books History</a></li>
        <li><a href="masterlist.php">Masterlist</a></li>
    </ul>
</aside>

<div class="main">

<h1 style="text-align:center; margin-bottom:20px;">Library Statistics</h1>

<div class="stat-box">
    Borrowers in <?= $months[intval($selectedMonth)] ?> <?= $selectedYear ?>: <?= $thisMonthBorrowers ?>
</div>

<form method="GET">
    <select name="course">
        <option value="">All Courses</option>
        <?php foreach ($coursesList as $c): ?>
        <option value="<?= $c ?>" <?= ($selectedCourse==$c)?'selected':'' ?>><?= $c ?></option>
        <?php endforeach; ?>
    </select>

    <select name="month">
        <?php foreach ($months as $num=>$name): ?>
        <option value="<?= $num ?>" <?= ($selectedMonth==$num)?'selected':'' ?>><?= $name ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filter</button>
</form>

<button id="printBtn">Print PDF</button>

<canvas id="courseChart" height="150"></canvas>

<div class="table-wrapper">
<table>
<thead>
<tr>
    <th>Total Borrows</th>
    <th>Name</th>
    <th>Borrower ID</th>
    <th>Type</th>
    <th>Course</th>
    <th>Year</th>
</tr>
</thead>
<tbody>
<?php if ($transactions->num_rows > 0): ?>
<?php while ($row = $transactions->fetch_assoc()): ?>
<tr>
    <td><?= $row['total_borrows'] ?></td>
    <td><?= htmlspecialchars($row['borrower_name']) ?></td>
    <td><?= htmlspecialchars($row['borrower_id']) ?></td>
    <td><?= htmlspecialchars($row['borrower_type']) ?></td>
    <td><?= htmlspecialchars($row['course']) ?></td>
    <td><?= htmlspecialchars($row['year']) ?></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6" style="text-align:center;">No transactions found</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('courseChart').getContext('2d');

// White background for chart
ctx.canvas.style.backgroundColor = '#ffffff';

const chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($courseData)) ?>,
        datasets: [{
            label: 'Total Borrows per Course',
            data: <?= json_encode(array_values($courseData)) ?>,
            backgroundColor: [
                'rgba(52, 219, 122, 0.7)',
                'rgba(52, 122, 219, 0.7)',
                'rgba(219, 52, 122, 0.7)',
                'rgba(219, 219, 52, 0.7)',
                'rgba(122, 52, 219, 0.7)',
                'rgba(52, 219, 219, 0.7)'
            ],
            borderColor: 'rgba(0,0,0,0.1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: "#e0e0e0" }, ticks: { color: "#1f3723" } },
            y: { beginAtZero: true, precision: 0, grid: { color: "#e0e0e0" }, ticks: { color: "#1f3723" } }
        }
    }
});

// -------------------------------
// PRINT BUTTON FUNCTION
// -------------------------------
document.getElementById('printBtn').addEventListener('click', function() {
    const chartCanvas = document.getElementById('courseChart');
    const imgData = chartCanvas.toDataURL("image/png");

    const img = document.createElement('img');
    img.src = imgData;
    img.style.width = '100%';

    chartCanvas.parentNode.replaceChild(img, chartCanvas);

    window.print();

    // Restore page after print
    setTimeout(() => { location.reload(); }, 1000);
});
</script>

</body>
</html>