<?php
require_once 'includes/auth.php';
checkAuth();

$user = getCurrentUser($GLOBALS['pdo']);
if ($user['user_type'] !== 'student') {
    header("Location: " . ($user['user_type'] === 'teacher' ? "teacher/teacher_dashboard.php" : "login.php"));
    exit();
}

// Get student details
$stmt = $GLOBALS['pdo']->prepare("SELECT s.*, u.first_name, u.last_name, u.email, u.profile_image 
                                 FROM students s JOIN users u ON s.user_id = u.user_id 
                                 WHERE s.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get attendance summary
$stmt = $GLOBALS['pdo']->prepare("SELECT semester, SUM(total_classes) as total, SUM(attended_classes) as attended 
                                 FROM attendance WHERE student_id = ? GROUP BY semester");
$stmt->execute([$_SESSION['user_id']]);
$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get contest performance
$stmt = $GLOBALS['pdo']->prepare("SELECT COUNT(*) as total_contests, AVG(score) as avg_score, MAX(score) as max_score
                                 FROM contest_submissions WHERE student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$contest = $stmt->fetch(PDO::FETCH_ASSOC);

// Get reminders
$stmt = $GLOBALS['pdo']->prepare("SELECT * FROM reminders 
                                 WHERE user_id = ? AND due_date >= CURDATE()
                                 ORDER BY due_date ASC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notices for student's semester/section
$stmt = $pdo->prepare("SELECT * FROM notices 
                      WHERE (semester = ? OR semester IS NULL OR semester = '') 
                      AND (section = ? OR section IS NULL OR section = '')
                      ORDER BY created_at DESC");
$stmt->execute([$student['semester'], $student['section']]);
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate CGPA (same method as in result.php)
$results = $pdo->prepare("SELECT * FROM results 
                         WHERE student_id = ? AND published IN (0, 1)
                         ORDER BY semester, course_code");
$results->execute([$_SESSION['user_id']]);
$results = $results->fetchAll(PDO::FETCH_ASSOC);

$total_credits = 0;
$total_grade_points = 0;
$credit_per_course = 3; // Assuming each course has 3 credits

foreach ($results as $result) {
    $grade_point = 0;
    switch ($result['grade']) {
        case 'A+': $grade_point = 4.0; break;
        case 'A':  $grade_point = 3.75; break;
        case 'A-': $grade_point = 3.5; break;
        case 'B+': $grade_point = 3.25; break;
        case 'B':  $grade_point = 3.0; break;
        case 'B-': $grade_point = 2.75; break;
        case 'C+': $grade_point = 2.5; break;
        case 'C':  $grade_point = 2.25; break;
        case 'D':  $grade_point = 2.0; break;
        case 'F':  $grade_point = 0.0; break;
    }
    
    $total_grade_points += $grade_point * $credit_per_course;
    $total_credits += $credit_per_course;
}

$cgpa = $total_credits > 0 ? $total_grade_points / $total_credits : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
    <link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Student Dashboard | PU</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Section -->
            <aside class="col-md-2">
                <div class="toggle d-flex justify-content-between align-items-center mt-3">
                    <div class="logo d-flex align-items-center gap-2">
                        <img src="assets/image/Logo_of_Premier_University_(PU).png" class="img-fluid" width="64" height="64">
                        <h2 class="m-0">PU<span class="danger">C</span></h2>
                    </div>
                    <div class="close" id="close-btn">
                        <span class="material-icons-sharp">close</span>
                    </div>
                </div>

                <div class="sidebar bg-white shadow rounded-3 p-3 mt-4 h-88vh position-relative">
                    <a href="index.php" class="active d-flex align-items-center text-decoration-none text-dark py-3 px-3 rounded">
                        <span class="material-icons-sharp">dashboard</span>
                        <h3 class="m-0 ms-2">Home</h3>
                    </a>
                    <a href="student/contest.php" class="d-flex align-items-center text-decoration-none text-dark py-3 px-3 rounded">
                        <span class="material-icons-sharp">insights</span>
                        <h3 class="m-0 ms-2">Contest</h3>
                    </a>
                    <a href="student/attendance.php" class="d-flex align-items-center text-decoration-none text-dark py-3 px-3 rounded">
                        <span class="material-icons-sharp">calendar_today</span>
                        <h3>Attendance</h3>
                    </a>
                    <a href="student/result.php" class="d-flex align-items-center text-decoration-none text-dark py-3 px-3 rounded">
                        <span class="material-icons-sharp">inventory</span>
                        <h3 class="m-0 ms-2">Result</h3>
                    </a>
                    <a href="student/notice.php" class="d-flex align-items-center text-decoration-none text-dark py-3 px-3 rounded">
                        <span class="material-icons-sharp">mail_outline</span>
                        <h3>Notice</h3>
                    </a>
                    <a href="student/profile.php" class="d-flex align-items-center text-decoration-none text-dark py-3 px-3 rounded">
                        <span class="material-icons-sharp">settings</span>
                        <h3 class="m-0 ms-2">Edit Profile</h3>
                    </a>
                    <a href="logout.php" class="d-flex align-items-center text-decoration-none text-dark py-3 px-3 rounded position-absolute bottom-0 start-0 w-100">
                        <span class="material-icons-sharp">logout</span>
                        <h3 class="m-0 ms-2">Logout</h3>
                    </a>
                </div>
            </aside>
            <!-- End of Sidebar Section -->

            <!-- Main Content -->
            <main class="col-md-8">
                <!-- Student Profile Section -->
                <div class="student-profile d-flex align-items-center p-4 border-bottom border-2 border-secondary position-relative">
                    <img src="uploads/profile_images/<?php echo $student['profile_image'] ?: 'profile-2.jpg'; ?>" alt="Student Photo" class="rounded me-4" width="120" height="140">
                    <div class="student-info">
                        <h2 class="student-name fw-bold fs-4 text-dark"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                        <p class="m-0"><strong>Department of <span>Computer Science & Engineering</span></strong></p>
                        <p class="m-0"><strong>Advisor:</strong> Mohammad Hasan</p>
                        <p class="roll m-0">Roll: <span><?php echo htmlspecialchars($student['roll_number']); ?></span></p>
                    </div>
                    <a href="profile.php" class="edit-profile-link position-absolute top-0 end-0 text-primary fw-bold text-decoration-none">Edit Profile</a>
                </div>
                <!-- End of Student Profile Section -->

                <h1 class="fw-bold fs-3 mt-4">Analytics</h1>
                <!-- Analyses -->
                <div class="analyse row g-4 mt-2">
                    <div class="attendance col-md-12">
                        <div class="status bg-white p-4 rounded-3 shadow-sm d-flex justify-content-between align-items-center">
                            <div class="info">
                                <h3 class="m-0 ms-2 fs-6">Attendance</h3>
                                <h1 id="attendance" class="m-0 fs-2">
                                    <?php 
                                    $total = 0;
                                    $attended = 0;
                                    foreach ($attendance as $a) {
                                        $total += $a['total'];
                                        $attended += $a['attended'];
                                    }
                                    echo $total > 0 ? round(($attended/$total)*100) : 0;
                                    ?><span>%</span>
                                </h1>
                                <p class="m-0 text-muted"><?php echo $attended; ?> of <?php echo $total; ?> classes</p>
                            </div>
                            <div class="progresss position-relative" style="width: 92px; height: 92px;">
                                <svg width="7rem" height="7rem">
                                    <circle cx="38" cy="38" r="36" data-num="<?php echo $total > 0 ? round(($attended/$total)*100) : 0; ?>"></circle>
                                </svg>
                                <div class="percentage position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                    <p class="m-0">+<?php echo $total > 0 ? round(($attended/$total)*100) : 0; ?>%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="cgpa col-md-12">
                        <div class="status bg-white p-4 rounded-3 shadow-sm d-flex justify-content-between align-items-center">
                            <div class="info">
                                <h3 class="m-0 ms-2 fs-6">CGPA</h3>
                                <h1 id="cgpa" class="m-0 fs-2">
                                    <?php echo number_format($cgpa, 2); ?>
                                </h1>
                                <p class="m-0 text-muted"><?php echo $total_credits; ?> credits</p>
                            </div>
                            <div class="progresss position-relative" style="width: 92px; height: 92px;">
                                <svg width="7rem" height="7rem">
                                    <circle cx="38" cy="38" r="36" data-num="<?php echo number_format($cgpa * 25, 2); ?>"></circle>
                                </svg>
                                <div class="percentage position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                    <p class="m-0">+<?php echo number_format($cgpa * 25, 2); ?>%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="contest col-md-11">
                        <div class="status bg-white p-4 rounded-3 shadow-sm d-flex justify-content-between align-items-center">
                            <div class="info">
                                <h3 class="m-0 ms-2 fs-6">Contest Score</h3>
                                <h1 id="contest-score" class="m-0 fs-2">
                                    <?php echo $contest['total_contests'] > 0 ? round($contest['avg_score']) : '0'; ?>/100
                                </h1>
                                <p class="m-0 text-muted"><?php echo $contest['total_contests'] ?: '0'; ?> contests</p>
                            </div>
                            <div class="progresss position-relative" style="width: 92px; height: 92px;">
                                <svg width="7rem" height="7rem">
                                    <circle cx="38" cy="38" r="36" data-num="<?php echo $contest['total_contests'] > 0 ? round($contest['avg_score']) : '0'; ?>"></circle>
                                </svg>
                                <div class="percentage position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                    <p class="m-0">+<?php echo $contest['total_contests'] > 0 ? round($contest['avg_score']) : '0'; ?>%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notices Section -->
                <div class="notices mt-4">
                    <h2 class="fw-bold fs-4 mb-3">University Notices</h2>
                    <div class="notice-list bg-white shadow-sm rounded-3 overflow-hidden">
                        <?php foreach ($notices as $notice): ?>
                        <div class="notice-item p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="m-0 fs-5"><?php echo htmlspecialchars($notice['title']); ?></h4>
                                <p class="m-0 text-muted">Published: <?php echo date('d M Y', strtotime($notice['created_at'])); ?></p>
                            </div>
                            <?php if ($notice['pdf_path']): ?>
                            <a href="assets/notices/<?php echo htmlspecialchars($notice['pdf_path']); ?>" download>
                                <span class="material-icons-sharp text-primary">download</span>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="student/notice.php" class="d-block text-center my-3 text-primary text-decoration-none">View All Notices</a>
                </div>
                <!-- End of Notices Section -->
            </main>
            <!-- End of Main Content -->

            <!-- Right Section -->
            <div class="right-section col-md-2 mt-4">
                <div class="nav d-flex justify-content-end gap-4">
                    <button id="menu-btn" class="btn p-0">
                        <span class="material-icons-sharp">menu</span>
                    </button>
                </div>
                <!-- End of Nav -->

                <!-- Total Progress Section -->
                <h2 class="section-heading fw-bold fs-3 text-start mb-3">Total Progress</h2>
                <div class="total-progress bg-white p-4 rounded-3 shadow-sm">
                    <div class="progress-container position-relative mx-auto" style="width: 140px; height: 140px;">
                        <svg width="9rem" height="9rem">
                            <circle cx="54" cy="54" r="49" id="total-progress-circle"></circle>
                        </svg>
                        <div class="progress-text position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                            <h1 id="total-progress" class="m-0">0%</h1>
                        </div>
                    </div>
                </div>
                <!-- End of Total Progress Section -->

                <!-- Reminders Section -->
                <div class="reminders mt-5">
                    <div class="header d-flex justify-content-between align-items-center mb-3">
                        <h2 class="m-0 fw-bold fs-4">My Reminders</h2>
                        <span class="material-icons-sharp p-2 bg-white shadow-sm rounded-circle">notifications_none</span>
                    </div>

                    <div class="reminders-list">
                        <?php foreach ($reminders as $reminder): 
                            $reminderDate = new DateTime($reminder['due_date']);
                            $now = new DateTime();
                            $interval = $now->diff($reminderDate);
                            $daysDiff = $interval->days;
                        ?>
                        <div class="notification bg-white p-3 rounded-3 shadow-sm d-flex align-items-center gap-3 mb-3">
                            <div class="icon p-2 <?php echo $daysDiff <= 1 ? 'bg-danger' : ($daysDiff <= 3 ? 'bg-warning' : 'bg-success'); ?> rounded-2 d-flex align-items-center justify-content-center">
                                <span class="material-icons-sharp text-white">notifications</span>
                            </div>
                            <div class="content w-100 d-flex justify-content-between align-items-center">
                                <div class="info">
                                    <h3 class="m-0 fs-6"><?php echo htmlspecialchars($reminder['title']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo date('d M Y, h:i A', strtotime($reminder['due_date'])); ?>
                                        (in <?php echo $daysDiff == 0 ? 'today' : ($daysDiff == 1 ? '1 day' : $daysDiff . ' days'); ?>)
                                    </small>
                                </div>
                                <span class="material-icons-sharp delete-reminder" data-id="<?php echo $reminder['reminder_id']; ?>">delete</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Add Reminder Form (Hidden by default) -->
                    <div class="add-reminder-form bg-white p-3 rounded-3 shadow-sm mb-3" style="display: none;">
                        <div class="mb-3">
                            <label for="reminder-title" class="form-label">Title*</label>
                            <input type="text" class="form-control" id="reminder-title" placeholder="Enter reminder title" required>
                        </div>
                        <div class="mb-3">
                            <label for="reminder-description" class="form-label">Description</label>
                            <textarea class="form-control" id="reminder-description" placeholder="Optional description"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="reminder-date" class="form-label">Due Date & Time*</label>
                            <input type="datetime-local" class="form-control" id="reminder-date" required>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-sm btn-outline-secondary cancel-reminder">Cancel</button>
                            <button class="btn btn-sm btn-primary save-reminder">Save</button>
                        </div>
                    </div>

                    <div class="add-reminder-btn bg-white p-3 rounded-3 border-2 border-dashed border-primary text-primary d-flex align-items-center justify-content-center cursor-pointer">
                        <div class="d-flex align-items-center gap-2">
                            <span class="material-icons-sharp">add</span>
                            <h3 class="m-0 fs-6">Add Reminder</h3>
                        </div>
                    </div>
                </div>
                <!-- End of Reminders Section -->
            </div>
        </div>
    </div>

    <!-- Notice Modal -->
    <div class="modal fade" id="noticeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="noticeModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3"><small id="noticeModalDate"></small></p>
                    <div id="noticeModalContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="noticeModalDownload">Download PDF</a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Progress circle animations
            const numbers = document.querySelectorAll('.progresss .percentage p');
            const circles = document.querySelectorAll('.progresss svg circle');

            numbers.forEach((number, index) => {
                let target = parseFloat(circles[index].getAttribute('data-num')) || 100;
                let count = 0;
                let interval = setInterval(() => {
                    if (count >= target) {
                        clearInterval(interval);
                    } else {
                        count++;
                        number.innerHTML = `+${count}%`;
                        circles[index].style.strokeDashoffset = Math.floor(200 - (200 * count) / 100);
                    }
                }, 20);
            });

            // Total progress calculation
            let attendance = parseFloat(document.getElementById("attendance").innerText);
            let cgpa = parseFloat(document.getElementById("cgpa").innerText) * 25;
            let contestScore = parseFloat(document.getElementById("contest-score").innerText.split('/')[0]);
            let totalProgress = Math.round((attendance + cgpa + contestScore) / 3);
            
            let totalProgressEl = document.getElementById("total-progress");
            let circle = document.getElementById("total-progress-circle");
            let count = 0;

            let interval = setInterval(() => {
                if (count >= totalProgress) {
                    clearInterval(interval);
                } else {
                    count++;
                    totalProgressEl.innerText = count + "%";
                    circle.style.strokeDashoffset = Math.floor(280 - (280 * count) / 100);
                }
            }, 20);

            // Sidebar toggle
            const sideMenu = document.querySelector('aside');
            const menuBtn = document.getElementById('menu-btn');
            const closeBtn = document.getElementById('close-btn');

            menuBtn.addEventListener('click', () => {
                sideMenu.style.display = 'block';
            });

            closeBtn.addEventListener('click', () => {
                sideMenu.style.display = 'none';
            });

            // Notice modal functionality
            const noticeModal = new bootstrap.Modal(document.getElementById('noticeModal'));
            document.querySelectorAll('.notice-item').forEach(item => {
                item.addEventListener('click', () => {
                    const title = item.querySelector('h4').innerText;
                    const date = item.querySelector('p').innerText.replace('Published: ', '');
                    
                    document.getElementById('noticeModalTitle').innerText = title;
                    document.getElementById('noticeModalDate').innerText = 'Published: ' + date;
                    document.getElementById('noticeModalContent').innerHTML = '<p>Notice content would appear here...</p>';
                    
                    const downloadBtn = item.querySelector('[download]');
                    if (downloadBtn) {
                        document.getElementById('noticeModalDownload').href = downloadBtn.href;
                        document.getElementById('noticeModalDownload').style.display = 'inline-block';
                    } else {
                        document.getElementById('noticeModalDownload').style.display = 'none';
                    }
                    
                    noticeModal.show();
                });
            });

            // Reminders functionality
            const addReminderBtn = document.querySelector('.add-reminder-btn');
            const addReminderForm = document.querySelector('.add-reminder-form');
            const saveReminderBtn = document.querySelector('.save-reminder');
            const cancelReminderBtn = document.querySelector('.cancel-reminder');
            const remindersList = document.querySelector('.reminders-list');

            // Show add reminder form
            addReminderBtn.addEventListener('click', () => {
                addReminderForm.style.display = 'block';
                addReminderBtn.style.display = 'none';
                
                // Set min date/time to now
                const now = new Date();
                const timezoneOffset = now.getTimezoneOffset() * 60000;
                const localISOTime = new Date(now - timezoneOffset).toISOString().slice(0, 16);
                document.getElementById('reminder-date').min = localISOTime;
            });

            // Cancel adding reminder
            cancelReminderBtn.addEventListener('click', () => {
                addReminderForm.style.display = 'none';
                addReminderBtn.style.display = 'flex';
                document.getElementById('reminder-title').value = '';
                document.getElementById('reminder-date').value = '';
            });

            // Save reminder function
            async function saveReminder() {
                const title = document.getElementById('reminder-title').value.trim();
                const description = document.getElementById('reminder-description').value.trim();
                const dueDate = document.getElementById('reminder-date').value;
                const userId = <?php echo $_SESSION['user_id']; ?>;

                // Validate required fields
                if (!title || !dueDate) {
                    alert('Please fill in all required fields (Title and Due Date)');
                    return false;
                }

                try {
                    const response = await fetch('includes/save_reminder.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            title: title,
                            description: description,
                            due_date: dueDate
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Refresh reminders list
                        await loadReminders();
                        // Reset form
                        document.getElementById('reminder-title').value = '';
                        document.getElementById('reminder-description').value = '';
                        document.getElementById('reminder-date').value = '';
                        // Hide form
                        document.querySelector('.add-reminder-form').style.display = 'none';
                        document.querySelector('.add-reminder-btn').style.display = 'flex';
                        return true;
                    } else {
                        throw new Error(result.message || 'Failed to save reminder');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert(error.message);
                    return false;
                }
            }

            // Load reminders function
            async function loadReminders() {
                try {
                    const response = await fetch('includes/get_reminders.php?user_id=<?php echo $_SESSION['user_id']; ?>');
                    const reminders = await response.json();
                    
                    if (reminders.length > 0) {
                        let html = '';
                        reminders.forEach(reminder => {
                            const dueDate = new Date(reminder.due_date);
                            const now = new Date();
                            const diffTime = dueDate - now;
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            
                            html += `
                                <div class="notification bg-white p-3 rounded-3 shadow-sm d-flex align-items-center gap-3 mb-3">
                                    <div class="icon p-2 ${diffDays <= 1 ? 'bg-danger' : (diffDays <= 3 ? 'bg-warning' : 'bg-success')} rounded-2 d-flex align-items-center justify-content-center">
                                        <span class="material-icons-sharp text-white">notifications</span>
                                    </div>
                                    <div class="content w-100 d-flex justify-content-between align-items-center">
                                        <div class="info">
                                            <h3 class="m-0 fs-6">${reminder.title}</h3>
                                            <small class="text-muted">
                                                ${dueDate.toLocaleString('en-US', { 
                                                    month: 'short', 
                                                    day: 'numeric', 
                                                    year: 'numeric',
                                                    hour: 'numeric',
                                                    minute: '2-digit'
                                                })}
                                                (in ${diffDays === 0 ? 'today' : (diffDays === 1 ? '1 day' : `${diffDays} days`)})
                                            </small>
                                        </div>
                                        <span class="material-icons-sharp delete-reminder" data-id="${reminder.reminder_id}">delete</span>
                                    </div>
                                </div>
                            `;
                        });
                        
                        remindersList.innerHTML = html;
                        attachDeleteHandlers();
                    } else {
                        remindersList.innerHTML = '<div class="text-center text-muted p-3">No upcoming reminders</div>';
                    }
                } catch (error) {
                    console.error('Error loading reminders:', error);
                }
            }

            // Attach delete handlers to all delete buttons
            function attachDeleteHandlers() {
                document.querySelectorAll('.delete-reminder').forEach(btn => {
                    btn.addEventListener('click', async function() {
                        const reminderId = this.getAttribute('data-id');
                        if (confirm('Are you sure you want to delete this reminder?')) {
                            try {
                                const response = await fetch('includes/delete_reminder.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        id: reminderId
                                    })
                                });
                                
                                const result = await response.json();
                                
                                if (result.success) {
                                    await loadReminders();
                                } else {
                                    alert('Error deleting reminder: ' + (result.message || 'Unknown error'));
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                alert('Failed to delete reminder');
                            }
                        }
                    });
                });
            }

            // Attach save handler
            saveReminderBtn.addEventListener('click', saveReminder);

            // Contest details modal (if implemented)
            const contestElement = document.querySelector('.contest');
            if (contestElement) {
                contestElement.addEventListener('click', function() {
                    // In a real implementation, this would open a modal with contest details
                    alert('Contest Performance:\n\nAverage Score: <?php echo $contest['avg_score'] ?: '0'; ?>/100\nTotal Contests: <?php echo $contest['total_contests'] ?: '0'; ?>\nTop Score: <?php echo $contest['max_score'] ?: '0'; ?>/100');
                });
            }
        });
    </script>
</body>
</html>