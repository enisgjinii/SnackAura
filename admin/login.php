<?php
session_start();
require_once 'includes/db_connect.php';

$response = [
    'success' => false,
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $response['success'] = true;
        $response['message'] = 'Login successful!';
    } else {
        $response['message'] = 'Invalid username or password.';
    }

    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.12.0/toastify.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="w-full max-w-md bg-white rounded-lg shadow-lg p-6">
        <h4 class="text-2xl font-bold text-center mb-6 text-gray-800">Admin Login</h4>
        <form id="loginForm" method="POST" novalidate>
            <!-- Username Field -->
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <div class="relative mt-1">
                    <input type="text" id="username" name="username" required placeholder="Enter your username"
                        class="pl-10 pr-3 py-2 w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 focus:outline-none bg-gray-50 text-gray-700">
                    <div class="absolute inset-y-0 left-3 flex items-center text-gray-500">
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>
            </div>

            <!-- Password Field -->
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <div class="relative mt-1">
                    <input type="password" id="password" name="password" required placeholder="Enter your password"
                        class="pl-10 pr-3 py-2 w-full border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 focus:outline-none bg-gray-50 text-gray-700">
                    <div class="absolute inset-y-0 left-3 flex items-center text-gray-500">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>
            </div>

            <!-- Login Button -->
            <button type="submit" id="loginBtn"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg focus:outline-none focus:ring focus:ring-blue-300 flex justify-center items-center">
                <span id="btnText">Login</span>
                <i id="btnSpinner" class="hidden fas fa-spinner fa-spin ml-2"></i>
            </button>
        </form>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Show spinner and disable button
            btnText.classList.add('hidden');
            btnSpinner.classList.remove('hidden');
            loginBtn.setAttribute('disabled', true);

            const formData = new FormData(loginForm);

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
                loginBtn.removeAttribute('disabled');

                // Display toast notification
                Toastify({
                    text: result.message,
                    duration: 3000,
                    gravity: "top",
                    position: "center",
                    backgroundColor: result.success ? "#48BB78" : "#F56565",
                    stopOnFocus: true,
                }).showToast();

                if (result.success) {
                    setTimeout(() => {
                        window.location.href = "dashboard.php";
                    }, 2000);
                }
            } catch (error) {
                btnText.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
                loginBtn.removeAttribute('disabled');

                Toastify({
                    text: "An error occurred. Please try again.",
                    duration: 3000,
                    gravity: "top",
                    position: "center",
                    backgroundColor: "#F56565",
                    stopOnFocus: true,
                }).showToast();
            }
        });
    </script>
</body>

</html>