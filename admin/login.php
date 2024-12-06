<?php
// admin/login.php
session_start();
require_once 'includes/db_connect.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php'); // Change to your desired landing page
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Fetch user from the database
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Authentication successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        header('Location: dashboard.php');
        exit();
    } else {
        $message = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Admin Login - Restaurant Delivery</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Fade-in animation for the card */
        @keyframes fadeIn {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in-out forwards;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-sm bg-white shadow p-6 rounded-md fade-in">
        <div class="text-center mb-6">
            <div class="text-4xl text-gray-700 mb-4"><i class="fa fa-utensils"></i></div>
            <h3 class="text-xl font-semibold text-gray-800">Admin Login</h3>
            <p class="text-sm text-gray-500 mt-1">Please enter your credentials to continue.</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 text-center text-red-600 text-sm font-medium">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-5" novalidate>
            <div>
                <label for="username" class="block mb-1 text-gray-700 font-semibold">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa fa-user"></i>
                    </span>
                    <input type="text"
                        id="username"
                        name="username"
                        required
                        class="block w-full border border-gray-300 rounded px-3 py-2 pl-10 focus:outline-none focus:ring-1 focus:ring-gray-400"
                        placeholder="Enter your admin username">
                </div>
            </div>

            <div>
                <label for="password" class="block mb-1 text-gray-700 font-semibold">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <i class="fa fa-lock"></i>
                    </span>
                    <input type="password"
                        id="password"
                        name="password"
                        required
                        class="block w-full border border-gray-300 rounded px-3 py-2 pl-10 pr-8 focus:outline-none focus:ring-1 focus:ring-gray-400"
                        placeholder="Enter your password">
                    <button type="button" id="togglePassword"
                        class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600"
                        tabindex="-1">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between text-sm text-gray-600">
                <label class="flex items-center">
                    <input type="checkbox" class="mr-1 border-gray-300 rounded" /> Remember me
                </label>
                <a href="#" class="text-blue-600 hover:underline">Forgot password?</a>
            </div>

            <button type="submit"
                class="relative w-full py-2 rounded bg-gray-800 text-white font-medium hover:bg-gray-900 transition-colors">
                <span class="button-text">Login</span>
                <span class="absolute inset-0 flex items-center justify-center hidden spinner">
                    <i class="fa fa-spinner fa-spin"></i>
                    <span class="ml-2">Logging in...</span>
                </span>
            </button>
        </form>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');
        const form = document.querySelector('form');
        const submitButton = form.querySelector('button[type="submit"]');
        const buttonText = submitButton.querySelector('.button-text');
        const spinner = submitButton.querySelector('.spinner');

        togglePassword.addEventListener('click', () => {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            togglePassword.innerHTML =
                type === 'password' ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>';
        });

        form.addEventListener('submit', (e) => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('was-validated');
            } else {
                // Show spinner on submit
                buttonText.classList.add('hidden');
                spinner.classList.remove('hidden');
            }
        });
    </script>
</body>

</html>