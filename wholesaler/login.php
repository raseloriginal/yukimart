<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wholesaler Login - YukiMartBD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../assets/js/tailwind.config.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .modern-input { width: 100%; padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; outline: none; transition: all 0.2s; }
        .modern-input:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased h-screen flex flex-col justify-center items-center p-4">

    <div class="w-full max-w-md bg-white rounded border border-gray-150 shadow-sm p-8">
        <div class="flex items-center justify-center gap-2 mb-8">
            <img src="../assets/images/logo.png" alt="Logo" class="w-12 h-12 object-contain mr-2">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Yuki<span class="text-accent-500">Wholesale</span></h1>
        </div>

        <h2 class="text-center text-gray-400 font-extrabold mb-6 uppercase tracking-wider text-xs">Wholesaler Portal</h2>

        <form id="login-form" class="flex flex-col gap-4">
            <input type="tel" id="phone" required placeholder="Phone Number" class="modern-input focus:border-brand-500 focus:shadow-[0_0_0_1px_#a855f7]">
            <input type="password" id="password" required placeholder="Password" class="modern-input focus:border-brand-500 focus:shadow-[0_0_0_1px_#a855f7]">
            
            <button type="submit" id="login-btn" class="w-full bg-accent-500 text-gray-900 font-extrabold text-sm rounded py-3 shadow transition-all hover:bg-accent-600 active:bg-accent-700 mt-2">
                Sign In to Portal
            </button>
            <div id="error-msg" class="text-red-600 bg-red-50 border border-red-200 rounded p-2 text-xs font-bold text-center hidden mt-2"></div>
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
                    if (data.role === 'wholesaler') {
                        window.location.href = 'dashboard.php';
                    } else {
                        errorMsg.textContent = "Unauthorized access. Wholesalers only.";
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
                btn.innerHTML = 'Sign In to Portal';
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
