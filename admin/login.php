<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - YukiMartBD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 antialiased h-screen flex flex-col justify-center items-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
        <div class="flex items-center justify-center gap-2 mb-8">
            <img src="../assets/images/logo.png" alt="Logo" class="w-12 h-12 object-contain mr-2">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">YukiMart<span class="text-accent-500">BD</span></h1>
        </div>

        <h2 class="text-center text-gray-500 font-semibold mb-6 uppercase tracking-wider text-sm">Admin Portal</h2>

        <form id="login-form" class="flex flex-col gap-4">
            <input type="tel" id="phone" required placeholder="Admin Phone Number" class="modern-input">
            <input type="password" id="password" required placeholder="Password" class="modern-input">
            
            <button type="submit" id="login-btn" class="w-full bg-accent-500 text-gray-900 font-extrabold rounded-xl py-3 shadow-md hover:bg-accent-600 transition-all mt-2">
                Sign In to Admin
            </button>
            <div id="error-msg" class="text-red-500 text-sm text-center hidden mt-2"></div>
        </form>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('login-btn');
            const errorMsg = document.getElementById('error-msg');
            
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
            btn.disabled = true;
            errorMsg.classList.add('hidden');

            try {
                const res = await fetch('../api/auth.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        phone: document.getElementById('phone').value,
                        password: document.getElementById('password').value
                    })
                });
                
                const data = await res.json();
                if (data.success) {
                    if (data.role === 'admin') {
                        window.location.href = 'dashboard.php';
                    } else {
                        errorMsg.textContent = "Unauthorized access. Admins only.";
                        errorMsg.classList.remove('hidden');
                    }
                } else {
                    errorMsg.textContent = data.message;
                    errorMsg.classList.remove('hidden');
                }
            } catch (err) {
                errorMsg.textContent = "Network error.";
                errorMsg.classList.remove('hidden');
            } finally {
                btn.innerHTML = 'Sign In to Admin';
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
