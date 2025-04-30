<?php
require_once 'includes/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';
$default_user_type = 'student'; // Default user type

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_id']) && isset($_POST['password']) && isset($_POST['user_type'])) {
        // LOGIN FORM SUBMISSION
        $login_id = trim($_POST['login_id']);
        $password = trim($_POST['password']);
        $user_type = $_POST['user_type'];
        $default_user_type = $user_type; // Remember user type for form
        
        try {
            if ($user_type === 'teacher') {
                $stmt = $pdo->prepare("SELECT u.*, t.teacher_id 
                                     FROM users u 
                                     JOIN teachers t ON u.user_id = t.user_id 
                                     WHERE t.teacher_id = ? AND u.user_type = 'teacher'");
            } else {
                $stmt = $pdo->prepare("SELECT u.*, s.roll_number 
                                     FROM users u 
                                     JOIN students s ON u.user_id = s.user_id 
                                     WHERE s.roll_number = ? AND u.user_type = 'student'");
            }
            
            $stmt->execute([$login_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Redirect based on user type
                header("Location: " . ($user_type === 'teacher' ? "teacher/teacher_dashboard.php" : "index.php"));
                exit();
            } else {
                $error = "Invalid credentials!";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
        
    } elseif (isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['user_id']) && 
              isset($_POST['email']) && isset($_POST['password']) && isset($_POST['confirm_password'])) {
        // SIGNUP FORM SUBMISSION
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $user_id = trim($_POST['user_id']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        $user_type = $_POST['user_type'];
        $default_user_type = $user_type; // Remember user type for form
        
        // Validate inputs
        if (empty($first_name) || empty($last_name) || empty($user_id) || empty($email) || empty($password)) {
            $error = "All fields are required!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long!";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Check if user_id or email already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? OR email = ?");
                $stmt->execute([$user_id, $email]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "User ID or Email already exists!";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert into users table
                    $stmt = $pdo->prepare("INSERT INTO users (user_id, first_name, last_name, email, password, user_type) 
                                         VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $first_name, $last_name, $email, $hashed_password, $user_type]);
                    
                    // Insert into specific table
                    if ($user_type === 'teacher') {
                        $teacher_id = $user_id;
                        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_id, position, department) 
                                             VALUES (?, ?, 'Lecturer', 'Computer Science')");
                        $stmt->execute([$user_id, $teacher_id]);
                    } else {
                        $batch_year = date('Y');
                        $roll_number = $user_id;
                        $stmt = $pdo->prepare("INSERT INTO students (user_id, roll_number, batch, semester, section) 
                                             VALUES (?, ?, ?, 1, 'A')");
                        $stmt->execute([$user_id, $roll_number, $batch_year]);
                    }
                    
                    $pdo->commit();
                    $success = "Registration successful! Please login.";
                    
                    // Clear form values
                    $_POST = array();
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome@6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>
    <div class="container" id="container">
        <!-- Sign Up Form -->
        <div class="form-container sign-up">
            <form action="login.php" method="POST" class="d-flex flex-column align-items-center w-100">
                <h1 class="mb-4">Create Account</h1>
                
                <!-- Error/Success Messages -->
                <?php if ($error && isset($_POST['first_name'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <span class="mb-3">Fill in your details to register</span>
                
                <!-- Form Fields -->
                <input type="text" name="first_name" class="form-control mb-3" placeholder="First Name" required 
                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                       
                <input type="text" name="last_name" class="form-control mb-3" placeholder="Last Name" required
                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                       
                <input type="text" class="form-control mb-3" id="signupId" name="user_id" 
                       placeholder="<?php echo ($_POST['user_type'] ?? $default_user_type) === 'teacher' ? 'Teacher ID' : 'Student ID'; ?>" 
                       required value="<?php echo htmlspecialchars($_POST['user_id'] ?? ''); ?>">
                       
                <input type="email" name="email" class="form-control mb-3" placeholder="Email Address" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                       
                <input type="password" name="password" class="form-control mb-3" placeholder="Password" 
                       pattern=".{6,}" title="Password must be at least 6 characters" required>
                       
                <input type="password" name="confirm_password" class="form-control mb-3" placeholder="Confirm Password" 
                       pattern=".{6,}" title="Password must be at least 6 characters" required>
                       
                <button type="submit" class="btn btn-primary w-100">Sign Up</button>

                <!-- User Type Switch -->
                <div class="user-switch text-center mt-3">
                    <a href="#" id="switchToStudentSignup" 
                       class="<?php echo ($_POST['user_type'] ?? $default_user_type) === 'student' ? 'active' : ''; ?>">
                       Sign Up as Student
                    </a>
                    <span class="mx-2">|</span>
                    <a href="#" id="switchToTeacherSignup" 
                       class="<?php echo ($_POST['user_type'] ?? $default_user_type) === 'teacher' ? 'active' : ''; ?>">
                       Sign Up as Teacher
                    </a>
                </div>
                  
                <input type="hidden" name="user_type" id="signupUserType" 
                       value="<?php echo htmlspecialchars($_POST['user_type'] ?? $default_user_type); ?>">
            </form>
        </div>

        <!-- Sign In Form -->
        <div class="form-container sign-in">
            <form class="d-flex flex-column align-items-center w-100" action="login.php" method="POST">
                <h1 class="mb-4">Log in</h1>
                
                <!-- Error Message -->
                <?php if ($error && isset($_POST['login_id'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <span class="mb-3">Use your ID and password to log in</span>
                
                <input type="text" class="form-control mb-3" id="loginId" name="login_id" 
                       placeholder="<?php echo ($_POST['user_type'] ?? $default_user_type) === 'teacher' ? 'Teacher ID' : 'Student ID'; ?>" 
                       required value="<?php echo htmlspecialchars($_POST['login_id'] ?? ''); ?>">
                       
                <input type="password" class="form-control mb-3" name="password" placeholder="Password" 
                       pattern=".{6,}" title="Password must be at least 6 characters" required>
                       
                <a href="#" class="mb-3 text-decoration-none">Forgot Your Password?</a>
                
                <button type="submit" class="btn btn-primary w-100">Log in</button>

                <!-- User Type Switch -->
                <div class="user-switch text-center mt-3">
                    <a href="#" id="switchToStudentLogin" 
                       class="<?php echo ($_POST['user_type'] ?? $default_user_type) === 'student' ? 'active' : ''; ?>">
                       Login as Student
                    </a>
                    <span class="mx-2">|</span>
                    <a href="#" id="switchToTeacherLogin" 
                       class="<?php echo ($_POST['user_type'] ?? $default_user_type) === 'teacher' ? 'active' : ''; ?>">
                       Login as Teacher
                    </a>
                </div>
  
                <input type="hidden" name="user_type" id="loginUserType" 
                       value="<?php echo htmlspecialchars($_POST['user_type'] ?? $default_user_type); ?>">
            </form>
        </div>

        <!-- Toggle Container -->
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <h1>Welcome Back!</h1>
                    <p>Enter your personal details to use all site features</p>
                    <button class="btn btn-light" id="login">Log in</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <h1>Hello!</h1>
                    <p>Register with your personal details to use all site features</p>
                    <button class="btn btn-light" id="register">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // DOM Elements
        const container = document.getElementById('container');
        const registerBtn = document.getElementById('register');
        const loginBtn = document.getElementById('login');
        const signupUserType = document.getElementById('signupUserType');
        const loginUserType = document.getElementById('loginUserType');
        const signupId = document.getElementById('signupId');
        const loginId = document.getElementById('loginId');

        // Toggle between Sign In and Sign Up forms
        registerBtn.addEventListener('click', () => {
            container.classList.add("active");
        });

        loginBtn.addEventListener('click', () => {
            container.classList.remove("active");
        });

        // Function to handle user type switching
        function switchUserType(type, formType) {
            const isLoginForm = formType === 'Login';
            const teacherSwitches = document.querySelectorAll(isLoginForm ? '#switchToTeacherLogin' : '#switchToTeacherSignup');
            const studentSwitches = document.querySelectorAll(isLoginForm ? '#switchToStudentLogin' : '#switchToStudentSignup');
            const userTypeInput = isLoginForm ? loginUserType : signupUserType;
            const idField = isLoginForm ? loginId : signupId;
            
            // Update UI
            if (type === 'teacher') {
                teacherSwitches.forEach(el => el.classList.add('active'));
                studentSwitches.forEach(el => el.classList.remove('active'));
                idField.placeholder = "Teacher ID";
            } else {
                studentSwitches.forEach(el => el.classList.add('active'));
                teacherSwitches.forEach(el => el.classList.remove('active'));
                idField.placeholder = "Student ID";
            }
            
            // Update hidden field
            userTypeInput.value = type;
        }

        // Add event listeners for teacher/student switches
        document.querySelectorAll('#switchToTeacherLogin, #switchToTeacherSignup').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const formType = this.id.includes('Login') ? 'Login' : 'Signup';
                switchUserType('teacher', formType);
            });
        });

        document.querySelectorAll('#switchToStudentLogin, #switchToStudentSignup').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const formType = this.id.includes('Login') ? 'Login' : 'Signup';
                switchUserType('student', formType);
            });
        });

        // Form validation
        function validatePassword(password) {
            return password.length >= 6;
        }

        // Sign Up form validation
        document.querySelector('.sign-up form').addEventListener('submit', function(e) {
            const password = this.querySelector('input[name="password"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            
            if (!validatePassword(password)) {
                alert('Password must be at least 6 characters long');
                e.preventDefault();
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                e.preventDefault();
            }
        });

        // Sign In form validation
        document.querySelector('.sign-in form').addEventListener('submit', function(e) {
            const password = this.querySelector('input[name="password"]').value;
            
            if (!validatePassword(password)) {
                alert('Password must be at least 6 characters long');
                e.preventDefault();
            }
        });

        // Initialize based on current user type
        document.addEventListener('DOMContentLoaded', function() {
            const currentUserType = "<?php echo $default_user_type; ?>";
            switchUserType(currentUserType, 'Login');
            switchUserType(currentUserType, 'Signup');
            
            // Show success message if redirected from successful signup
            <?php if ($success): ?>
                container.classList.remove("active");
            <?php endif; ?>
        });
    </script>
</body>
</html>