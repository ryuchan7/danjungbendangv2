<?php
session_start();
// NOTE: Assuming 'db_connect.php' properly establishes $pdo connection.
require_once 'db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    // Generate a secure, random token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set('Asia/Kuala_Lumpur');

// Handle messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
// Clear messages after display
unset($_SESSION['success_message'], $_SESSION['error_message']);

// --- Handle salary edit (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_salary') {
    // CSRF Protection Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $bookingID = intval($_POST['bookingID']);
        // Ensure salary is treated as a float and sanitized for database
        $worker_salary = floatval($_POST['worker_salary']);

        try {
            $stmt = $pdo->prepare("UPDATE booking SET worker_salary = :worker_salary WHERE bookingID = :bookingID");
            $stmt->execute(['worker_salary' => $worker_salary, 'bookingID' => $bookingID]);

            // Log the action in audit_logs
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, created_at)
                VALUES (:user_id, 'Edit Worker Salary', :details, NOW())
            ");
            $details = json_encode(['booking_id' => $bookingID, 'new_salary' => $worker_salary]);
            $stmt->execute(['user_id' => $_SESSION['user_id'], 'details' => $details]);

            // Set success message and redirect to prevent form resubmission and clear $_POST
            $_SESSION['success_message'] = 'Salary updated successfully.';
            header('Location: worker_salary.php');
            exit;
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// --- Fetch and Process Bookings (GET/Initial Load) ---
try {
    // Fetch all relevant bookings
    $stmt = $pdo->prepare("
        SELECT 
            b.bookingID, 
            b.userID,
            h.name AS homestay_name, 
            b.book_date,
            b.checkIN, 
            b.checkOUT, 
            b.number_of_guests, 
            b.payment_status, 
            b.payment_method, 
            b.amount_paid, 
            b.booking_status, 
            b.payment_proof,
            b.worker_salary,
            COALESCE(u.full_name, b.full_name) AS customer_name, 
            COALESCE(u.email, b.email) AS customer_email, 
            COALESCE(u.phone, b.phone) AS customer_phone, 
            COALESCE(u.ic_no, b.ic_no) AS customer_ic_no,
            b.created_at
        FROM booking b
        INNER JOIN homestaylist h ON b.hslistID = h.hslistID
        LEFT JOIN users u ON b.userID = u.userID
        WHERE b.booking_status NOT IN ('Cancelled', 'Rejected') AND b.deleted_at IS NULL
        ORDER BY b.checkIN ASC
    ");
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process bookings for salaries
    $today = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
    $completed_bookings = [];
    $upcoming_bookings = [];
    $monthly_salaries = [];
    $estimated_monthly_salaries = [];
    $total_salary_completed = 0;
    $estimated_total_salary_upcoming = 0;

    foreach ($bookings as $booking) {
        $checkInDate = new DateTime($booking['checkIN'], new DateTimeZone('Asia/Kuala_Lumpur'));
        $checkOutDate = new DateTime($booking['checkOUT'], new DateTimeZone('Asia/Kuala_Lumpur'));
        $duration = $checkOutDate->diff($checkInDate)->days;

        // Calculate salary: use stored value or default (50 for 1 day, 100 for >1 day)
        $default_salary = (($duration == 1) ? 50.00 : 100.00);
        // Ensure worker_salary is a float and use default if NULL/empty/zero
        $salary = (floatval($booking['worker_salary']) > 0) ? floatval($booking['worker_salary']) : $default_salary;

        $booking['duration'] = $duration;
        $booking['salary'] = $salary;

        if ($checkOutDate < $today) {
            // Completed
            $total_salary_completed += $salary;
            $completed_bookings[] = $booking;

            // Group by month/year based on check-out
            $month_year = $checkOutDate->format('Y-m');
            if (!isset($monthly_salaries[$month_year])) {
                $monthly_salaries[$month_year] = ['count' => 0, 'total' => 0];
            }
            $monthly_salaries[$month_year]['count']++;
            $monthly_salaries[$month_year]['total'] += $salary;
        } else {
            // Upcoming
            $estimated_total_salary_upcoming += $salary;
            $upcoming_bookings[] = $booking;

            // Group by month/year based on check-out for estimated
            $month_year = $checkOutDate->format('Y-m');
            if (!isset($estimated_monthly_salaries[$month_year])) {
                $estimated_monthly_salaries[$month_year] = ['count' => 0, 'total' => 0];
            }
            $estimated_monthly_salaries[$month_year]['count']++;
            $estimated_monthly_salaries[$month_year]['total'] += $salary;
        }
    }
    // Sort monthly arrays for chronological display/chart
    ksort($monthly_salaries);
    ksort($estimated_monthly_salaries);

} catch (PDOException $e) {
    // If a database error occurs during fetch, set an error message
    $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Worker Salary - Cozy Homestay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.21/jspdf.plugin.autotable.min.js"></script>
    <style>
        /* Existing CSS... */
        body { font-family: 'Poppins', sans-serif; background-color: #f5f7fa; color: #333; margin: 0; padding: 0; }
        .wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #fff; padding: 20px; border-right: 1px solid #e0e0e0; transition: all 0.3s ease; position: fixed; height: 100%; overflow-y: auto; }
        .sidebar .logo { font-size: 1.8rem; font-weight: 600; color: #26a69a; margin-bottom: 30px; text-align: center; }
        .sidebar .nav-link { display: flex; align-items: center; padding: 12px 15px; color: #6c757d; text-decoration: none; border-radius: 8px; margin-bottom: 10px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #26a69a; color: #fff; }
        .sidebar .nav-link i { margin-right: 12px; }
        .main-content { margin-left: 250px; padding: 30px; flex: 1; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); z-index: 1000; } .sidebar.active { transform: translateX(0); } .main-content { margin-left: 0; } }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .header h2 { color: #26a69a; font-weight: 600; margin: 0; }
        .btn-logout { border: none; border-radius: 20px; padding: 8px 16px; color: #fff; font-weight: 500; background: linear-gradient(135deg, #dc3545, #a71d2a); }
        .btn-logout:hover { background: linear-gradient(135deg, #a71d2a, #dc3545); }
        .section { margin-bottom: 40px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .section h3 { color: #26a69a; font-weight: 600; margin-bottom: 20px; }
        .table-responsive { margin-bottom: 20px; max-height: 600px; overflow-y: auto; }
        .table { border-collapse: separate; border-spacing: 0; }
        .table th { background: #26a69a; color: #fff; position: sticky; top: 0; z-index: 10; border-bottom: 2px solid #00695c; }
        .table td, .table th { padding: 12px; vertical-align: middle; }
        .table tbody tr:hover { background-color: #f8f9fa; }
        .total-row { font-weight: bold; background-color: #e9ecef; }
        .status-circle { display: inline-flex; align-items: center; justify-content: center; min-width: 100px; padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500; text-align: center; }
        .status-completed { background-color: #17a2b8; color: #fff; }
        .status-upcoming { background-color: #ffc107; color: #333; }
        .edit-salary-form { display: flex; align-items: center; gap: 10px; }
        .edit-salary-input { width: 100px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="sidebar">
            <div class="logo">D'Anjung Bendang Homestay</div>
            <a href="admin_dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="user_management.php" class="nav-link">
                <i class="fas fa-users"></i> User Management
            </a>
            <a href="bookings.php" class="nav-link">
                <i class="fas fa-book"></i> All Bookings
            </a>
            <a href="reports.php" class="nav-link">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <a href="audit_logs.php" class="nav-link">
                <i class="fas fa-history"></i> Audit Logs
            </a>
            <a href="feedback_management.php" class="nav-link">
                <i class="fas fa-comment-dots"></i> Feedback Customer
            </a>
            <a href="worker_salary.php" class="nav-link active">
                <i class="fas fa-money-bill-wave"></i> Worker Salary
            </a>
        </div>

        <div class="main-content">
            <div class="header">
                <h2>Worker Salary</h2>
                <a href="logout.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="section">
                <h3>Monthly Salary Summary (Completed)</h3>
                <div class="table-responsive">
                    <table class="table table-hover" id="monthlySummaryCompleted">
                        <thead>
                            <tr>
                                <th>Month/Year</th>
                                <th>Number of Completed Bookings</th>
                                <th>Total Salary (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($monthly_salaries)): ?>
                                <tr><td colspan="3" class="text-center">No completed bookings found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($monthly_salaries as $month_year => $data): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($month_year . '-01')); ?></td>
                                        <td><?php echo $data['count']; ?></td>
                                        <td>RM<?php echo number_format($data['total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td>Total:</td>
                                    <td><?php echo count($completed_bookings); ?></td>
                                    <td>RM<?php echo number_format($total_salary_completed, 2); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary" onclick="exportToPDF('completed')">Export Completed Summary to PDF</button>
            </div>

            <div class="section">
                <h3>Estimated Monthly Salary Summary (Upcoming)</h3>
                <div class="table-responsive">
                    <table class="table table-hover" id="monthlySummaryUpcoming">
                        <thead>
                            <tr>
                                <th>Month/Year</th>
                                <th>Number of Upcoming Bookings</th>
                                <th>Estimated Total Salary (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($estimated_monthly_salaries)): ?>
                                <tr><td colspan="3" class="text-center">No upcoming bookings found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($estimated_monthly_salaries as $month_year => $data): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($month_year . '-01')); ?></td>
                                        <td><?php echo $data['count']; ?></td>
                                        <td>RM<?php echo number_format($data['total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td>Total:</td>
                                    <td><?php echo count($upcoming_bookings); ?></td>
                                    <td>RM<?php echo number_format($estimated_total_salary_upcoming, 2); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary" onclick="exportToPDF('upcoming')">Export Upcoming Summary to PDF</button>
            </div>

            <div class="section">
                <h3>Detailed Salaries by Booking (Completed)</h3>
                <div class="search-container d-flex gap-3 mb-3 align-items-center">
                    <input type="text" id="searchInputCompleted" class="form-control" placeholder="Search Booking ID or Customer Name...">
                    <h4>Total Completed Salary: <span class="text-success">RM<?php echo number_format($total_salary_completed, 2); ?></span></h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="salaryTableCompleted">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Booking ID</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Duration (Days)</th>
                                <th>Salary (RM)</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="salaryTableBodyCompleted">
                            <?php if (empty($completed_bookings)): ?>
                                <tr><td colspan="9" class="text-center">No completed bookings found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($completed_bookings as $index => $booking): ?>
                                    <tr class="salary-row-completed">
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($booking['bookingID']); ?></td>
                                        <td><?php echo htmlspecialchars((new DateTime($booking['checkIN']))->format('d-m-Y')); ?></td>
                                        <td><?php echo htmlspecialchars((new DateTime($booking['checkOUT']))->format('d-m-Y')); ?></td>
                                        <td><?php echo $booking['duration']; ?></td>
                                        <td>
                                            <form class="edit-salary-form" method="POST" action="worker_salary.php">
                                                <input type="hidden" name="action" value="edit_salary">
                                                <input type="hidden" name="bookingID" value="<?php echo $booking['bookingID']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="number" name="worker_salary" class="form-control edit-salary-input" step="0.01" value="<?php echo number_format($booking['salary'], 2, '.', ''); ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Save Salary"><i class="fas fa-save"></i></button>
                                            </form>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?></td>
                                        <td>
                                            <span class="status-circle status-completed">Completed</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container" id="paginationContainerCompleted"></div>
                <button class="btn btn-primary" onclick="exportToPDF('detailed_completed')">Export Detailed Completed to PDF</button>
            </div>

            <div class="section">
                <h3>Detailed Salaries by Booking (Upcoming)</h3>
                <div class="search-container d-flex gap-3 mb-3 align-items-center">
                    <input type="text" id="searchInputUpcoming" class="form-control" placeholder="Search Booking ID or Customer Name...">
                    <h4>Estimated Total Upcoming Salary: <span class="text-warning">RM<?php echo number_format($estimated_total_salary_upcoming, 2); ?></span></h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="salaryTableUpcoming">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Booking ID</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Duration (Days)</th>
                                <th>Estimated Salary (RM)</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="salaryTableBodyUpcoming">
                            <?php if (empty($upcoming_bookings)): ?>
                                <tr><td colspan="9" class="text-center">No upcoming bookings found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($upcoming_bookings as $index => $booking): ?>
                                    <tr class="salary-row-upcoming">
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($booking['bookingID']); ?></td>
                                        <td><?php echo htmlspecialchars((new DateTime($booking['checkIN']))->format('d-m-Y')); ?></td>
                                        <td><?php echo htmlspecialchars((new DateTime($booking['checkOUT']))->format('d-m-Y')); ?></td>
                                        <td><?php echo $booking['duration']; ?></td>
                                        <td>
                                            <form class="edit-salary-form" method="POST" action="worker_salary.php">
                                                <input type="hidden" name="action" value="edit_salary">
                                                <input type="hidden" name="bookingID" value="<?php echo $booking['bookingID']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="number" name="worker_salary" class="form-control edit-salary-input" step="0.01" value="<?php echo number_format($booking['salary'], 2, '.', ''); ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Save Salary"><i class="fas fa-save"></i></button>
                                            </form>
                                        </td>
                                        <td><?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?></td>
                                        <td>
                                            <span class="status-circle status-upcoming">Upcoming</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-container" id="paginationContainerUpcoming"></div>
                <button class="btn btn-primary" onclick="exportToPDF('detailed_upcoming')">Export Detailed Upcoming to PDF</button>
            </div>
            
            <div class="section">
                <h3>Monthly Salary Chart (Completed)</h3>
                <canvas id="monthlySalaryChart" width="400" height="150"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Chart.js for Monthly Salary ---
            const monthlyData = <?php echo json_encode(array_values($monthly_salaries)); ?>;
            const labels = <?php echo json_encode(array_map(function($my) { return date('F Y', strtotime($my . '-01')); }, array_keys($monthly_salaries))); ?>;
            const ctx = document.getElementById('monthlySalaryChart').getContext('2d');
            
            if (labels.length > 0) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Salary (RM)',
                            data: monthlyData.map(d => d.total),
                            backgroundColor: '#26a69a',
                            borderColor: '#004d40',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Salary (RM)' } },
                            x: { title: { display: true, text: 'Month/Year' } }
                        }
                    }
                });
            } else {
                ctx.canvas.parentNode.innerHTML = '<p class="text-center text-muted">No data available to generate a chart.</p>';
            }

            // --- Search and Pagination function ---
            function setupTable(tableId, searchId, paginationId, rowClass) {
                const tableBody = document.getElementById(tableId + 'Body');
                if (!tableBody) return; // Exit if table body not found
                
                const searchInput = document.getElementById(searchId);
                const paginationContainer = document.getElementById(paginationId);
                // Collect all original rows for pagination/filtering
                const allRows = Array.from(tableBody.querySelectorAll('.' + rowClass)); 
                const rowsPerPage = 20;
                let currentPage = 1;
                let filteredRows = allRows;

                function filterRows() {
                    const searchTerm = searchInput.value.toLowerCase().trim();
                    filteredRows = allRows.filter(row => {
                        // Check Booking ID (cells[1]) and Customer Name (cells[6])
                        const bookingId = row.cells[1].textContent.toLowerCase();
                        const name = row.cells[6].textContent.toLowerCase();
                        return bookingId.includes(searchTerm) || name.includes(searchTerm);
                    });
                    currentPage = 1;
                    renderTable();
                    renderPagination();
                }

                function renderTable() {
                    const start = (currentPage - 1) * rowsPerPage;
                    const end = start + rowsPerPage;
                    const paginatedRows = filteredRows.slice(start, end);
                    
                    // Clear and re-append only the visible rows to the tbody
                    tableBody.innerHTML = '';
                    if (paginatedRows.length === 0) {
                        tableBody.innerHTML = `<tr><td colspan="9" class="text-center">No matching records found.</td></tr>`;
                    }
                    
                    paginatedRows.forEach((row, index) => {
                        row.cells[0].textContent = start + index + 1; // Update No
                        tableBody.appendChild(row);
                    });
                }

                function renderPagination() {
                    const pageCount = Math.ceil(filteredRows.length / rowsPerPage);
                    paginationContainer.innerHTML = '';
                    if (pageCount <= 1) return;
                    
                    const pagination = document.createElement('nav');
                    pagination.innerHTML = `
                        <ul class="pagination justify-content-center">
                            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                                <a class="page-link" href="#" data-page="prev" aria-label="Previous"><span aria-hidden="true">«</span></a>
                            </li>
                            ${Array.from({ length: pageCount }, (_, i) => i + 1).map(page => `
                                <li class="page-item ${currentPage === page ? 'active' : ''}">
                                    <a class="page-link" href="#" data-page="${page}">${page}</a>
                                </li>
                            `).join('')}
                            <li class="page-item ${currentPage === pageCount ? 'disabled' : ''}">
                                <a class="page-link" href="#" data-page="next" aria-label="Next"><span aria-hidden="true">»</span></a>
                            </li>
                        </ul>
                    `;
                    paginationContainer.appendChild(pagination);
                    
                    // Event listeners for pagination links
                    pagination.querySelectorAll('.page-link').forEach(link => {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            const parentLi = this.parentElement;
                            if (parentLi.classList.contains('disabled')) return;
                            
                            const pageAttr = this.getAttribute('data-page');
                            if (pageAttr === 'prev') {
                                currentPage--;
                            } else if (pageAttr === 'next') {
                                currentPage++;
                            } else {
                                currentPage = parseInt(pageAttr);
                            }
                            
                            renderTable();
                            renderPagination();
                            // Scroll to the top of the table
                            window.scrollTo({ top: document.getElementById(tableId).offsetTop - 100, behavior: 'smooth' });
                        });
                    });
                }

                searchInput.addEventListener('input', filterRows);
                
                // Initial render
                renderTable();
                renderPagination();
            }

            setupTable('salaryTableCompleted', 'searchInputCompleted', 'paginationContainerCompleted', 'salary-row-completed');
            setupTable('salaryTableUpcoming', 'searchInputUpcoming', 'paginationContainerUpcoming', 'salary-row-upcoming');

            // --- Export to PDF function (using jspdf-autotable) ---
            window.exportToPDF = function(type) {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                let title = '';
                let tableSelector = '';
                let fileName = '';

                // Determine table and title based on type
                if (type === 'completed') {
                    title = 'Worker Salary: Monthly Summary (Completed)';
                    tableSelector = '#monthlySummaryCompleted';
                    fileName = 'completed_monthly_summary.pdf';
                } else if (type === 'upcoming') {
                    title = 'Worker Salary: Estimated Monthly Summary (Upcoming)';
                    tableSelector = '#monthlySummaryUpcoming';
                    fileName = 'upcoming_monthly_summary.pdf';
                } else if (type === 'detailed_completed') {
                    title = 'Worker Salary: Detailed Bookings (Completed)';
                    tableSelector = '#salaryTableCompleted';
                    fileName = 'completed_detailed_salaries.pdf';
                    // Need to capture ALL rows, not just paginated ones, for detailed report
                    // We'll export the whole table's data, not just what's currently displayed.
                } else if (type === 'detailed_upcoming') {
                    title = 'Worker Salary: Detailed Bookings (Upcoming)';
                    tableSelector = '#salaryTableUpcoming';
                    fileName = 'upcoming_detailed_salaries.pdf';
                }

                doc.setFontSize(14);
                doc.text(title, 14, 20);
                
                // Use autoTable to convert HTML table to PDF
                // For detailed tables, we need to hide the 'Action' column from the PDF
                if (type.startsWith('detailed')) {
                    doc.autoTable({ 
                        html: tableSelector, 
                        startY: 30,
                        theme: 'grid',
                        headStyles: { fillColor: [38, 166, 154] }, // #26a69a
                        columnStyles: { 
                            // 8 is the index of the 'Action' column, hide it.
                            8: { cellWidth: 0, minCellWidth: 0, maxCellWidth: 0 } 
                        },
                        didParseCell: function(data) {
                            // Fix for salary column to only show the value, not the form elements
                            if (data.column.index === 5 && data.cell.section === 'body') {
                                // Find the input field's value inside the form
                                const input = data.cell.raw.querySelector('input[name="worker_salary"]');
                                if (input) {
                                    data.cell.text = ['RM' + input.value];
                                }
                            }
                             // Fix for Status column to just show the text
                            if (data.column.index === 7 && data.cell.section === 'body') {
                                const statusText = data.cell.raw.querySelector('.status-circle');
                                if (statusText) {
                                    data.cell.text = [statusText.textContent];
                                }
                            }
                        }
                    });
                } else {
                    // For monthly summaries, use the table directly
                    doc.autoTable({ 
                        html: tableSelector, 
                        startY: 30,
                        theme: 'grid',
                        headStyles: { fillColor: [38, 166, 154] } // #26a69a
                    });
                }

                doc.save(fileName);
            };
        });
    </script>
</body>
</html>