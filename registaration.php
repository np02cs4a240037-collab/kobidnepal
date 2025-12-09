<?php
session_start();
define('USERS_FILE', 'users.json');
define('MIN_PASSWORD_LENGTH', 8);
$errors = [];
$success_message = '';
$form_data = [];
function loadUsers() {
    if (!file_exists(USERS_FILE)) {
        file_put_contents(USERS_FILE, json_encode([]));
        return [];
    }
    
    $json_data = file_get_contents(USERS_FILE);
    if ($json_data === false) {
        throw new Exception("Unable to read users file. Please check file permissions.");
    }
    
    $users = json_decode($json_data, true);
    if ($users === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error reading user data. Invalid JSON format.");
    }
    
    return $users ?: [];
}
function saveUsers($users) {
    $json_data = json_encode($users, JSON_PRETTY_PRINT);
    if ($json_data === false) {
        throw new Exception("Error encoding user data to JSON.");
    }
    
    $result = file_put_contents(USERS_FILE, $json_data);
    if ($result === false) {
        throw new Exception("Unable to save user data. Please check file permissions.");
    }
    
    return true;
}
function validateName($name) {
    if (empty(trim($name))) {
        return "Name is required.";
    }
    
    if (strlen(trim($name)) < 2) {
        return "Name must be at least 2 characters long.";
    }
    
    if (!preg_match('/^[a-zA-Z\s]+$/', trim($name))) {
        return "Name can only contain letters and spaces.";
    }
    
    return null;
}

function validateEmail($email, $users) {
    if (empty(trim($email))) {
        return "Email is required.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email address.";
    }
    foreach ($users as $user) {
        if (strtolower($user['email']) === strtolower(trim($email))) {
            return "This email is already registered.";
        }
    }
    
    return null;
}

function validatePassword($password, $confirm_password) {
    if (empty($password)) {
        return "Password is required.";
    }
    
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        return "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long.";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return "Password must contain at least one lowercase letter.";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least one number.";
    }
    
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
        return "Password must contain at least one special character.";
    }
    
    if ($password !== $confirm_password) {
        return "Passwords do not match.";
    }
    
    return null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    try {
        $users = loadUsers();
        
        $name_error = validateName($form_data['name']);
        if ($name_error) $errors['name'] = $name_error;
        
        $email_error = validateEmail($form_data['email'], $users);
        if ($email_error) $errors['email'] = $email_error;
        
        $password_error = validatePassword($form_data['password'], $form_data['confirm_password']);
        if ($password_error) $errors['password'] = $password_error;
        if (empty($errors)) {
            $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
            
            $new_user = [
                'id' => uniqid('user_', true),
                'name' => trim($form_data['name']),
                'email' => trim($form_data['email']),
                'password' => $hashed_password,
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => null
            ];
            $users[] = $new_user;
            saveUsers($users);
            $form_data = [];
            $success_message = "Registration successful! You can now login with your credentials.";
            
            unset($_SESSION['form_data']);
        } else {
            $_SESSION['form_data'] = $form_data;
        }
        
    } catch (Exception $e) {
        $errors['system'] = "System Error: " . $e->getMessage();
        $_SESSION['form_data'] = $form_data;
    }
} else {
    if (isset($_SESSION['form_data'])) {
        $form_data = $_SESSION['form_data'];
        unset($_SESSION['form_data']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
        }
        
        .registration-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input.error {
            border-color: #ff3860;
        }
        
        .error-message {
            color: #ff3860;
            font-size: 12px;
            margin-top: 5px;
            display: block;
            font-weight: 500;
        }
        
        .success-message {
            background: #48c774;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        .system-error {
            background: #ff3860;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        .password-requirements {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .password-requirements ul {
            list-style: none;
            padding-left: 5px;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .password-requirements li:before {
            content: "•";
            color: #667eea;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #eee;
        }
        
        .registered-users {
            margin-top: 30px;
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .registered-users h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .user-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .user-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-name {
            font-weight: 500;
            color: #333;
        }
        
        .user-email {
            font-size: 12px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="registration-card">
            <div class="header">
                <h1>User Registration</h1>
                <p>Create your account to get started</p>
            </div>
            
            <div class="form-container">
                <?php if ($success_message): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors['system'])): ?>
                    <div class="system-error">
                        <?php echo htmlspecialchars($errors['system']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" novalidate>
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                            placeholder="Enter your full name"
                            class="<?php echo isset($errors['name']) ? 'error' : ''; ?>"
                            required
                        >
                        <?php if (isset($errors['name'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['name']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                            placeholder="Enter your email address"
                            class="<?php echo isset($errors['email']) ? 'error' : ''; ?>"
                            required
                        >
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Create a secure password"
                            class="<?php echo isset($errors['password']) ? 'error' : ''; ?>"
                            required
                        >
                        <?php if (isset($errors['password'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($errors['password']); ?></span>
                        <?php endif; ?>
                        
                        <div class="password-requirements">
                            <p><strong>Password must contain:</strong></p>
                            <ul>
                                <li>Minimum <?php echo MIN_PASSWORD_LENGTH; ?> characters</li>
                                <li>At least one uppercase letter</li>
                                <li>At least one lowercase letter</li>
                                <li>At least one number</li>
                                <li>At least one special character</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Re-enter your password"
                            required
                        >
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        Create Account
                    </button>
                </form>
            </div>
            
            <div class="footer">
                <p>Already have an account? <a href="#" style="color: #667eea; text-decoration: none;">Sign In</a></p>
            </div>
        </div>
        
        <?php
        try {
            $users = loadUsers();
            if (!empty($users)): 
        ?>
            <div class="registered-users">
                <h3>Registered Users (Total: <?php echo count($users); ?>)</h3>
                <div class="user-list">
                    <?php foreach ($users as $user): ?>
                        <div class="user-item">
                            <div>
                                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div style="font-size: 12px; color: #888;">
                                <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php 
            endif;
        } catch (Exception $e) {
        }
        ?>
    </div>

    <script>
        document.getElementById('password')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const requirements = {
                length: password.length >= <?php echo MIN_PASSWORD_LENGTH; ?>,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
            };
            
            const indicators = document.querySelectorAll('.password-requirements li');
            indicators.forEach((li, index) => {
                const requirementMet = Object.values(requirements)[index];
                li.style.color = requirementMet ? '#48c774' : '#666';
            });
        });
        
        document.querySelector('form')?.addEventListener('submit', function(e) {

            console.log('Form submitted');
        });
    </script>
</body>
</html>