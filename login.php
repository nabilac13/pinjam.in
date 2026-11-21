<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

if (empty($_SESSION['public_csrf'])) {
    $_SESSION['public_csrf'] = bin2hex(random_bytes(32));
}

$next = ($_GET['next'] ?? '') === 'admin' ? 'admin' : 'home';
if (!empty($_SESSION['admin_user'])) {
    header('Location: admin.php');
    exit;
}
if (!empty($_SESSION['public_user']) && $next !== 'admin') {
    header('Location: index.php');
    exit;
}

function escapeLoginHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f0ede4">
    <title>Masuk — Pinjam.in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>window.tailwind = window.tailwind || {}; window.tailwind.config = { theme: { extend: { colors: { blue: { 50: "#eaf4f2", 100: "#d3e8e4", 200: "#a9d1c9", 300: "#7bb5aa", 400: "#4b9185", 500: "#267266", 600: "#004741", 700: "#003d38", 800: "#00332f", 900: "#002824" } } } } };</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: "DM Sans", ui-sans-serif, system-ui, sans-serif; }
        .bg-blue-600 { background-color: #004741 !important; }
        .text-blue-600 { color: #004741 !important; }
        .text-blue-700 { color: #003d38 !important; }
        .hover\:bg-blue-700:hover { background-color: #003d38 !important; }
    </style>
</head>
<body class="grid min-h-screen place-items-center bg-[#f0ede4] px-5 py-10 text-slate-800 antialiased">
    <main class="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white p-7 shadow-xl shadow-slate-900/[.04] sm:p-9">
        <a href="index.php" class="mb-8 inline-flex items-center gap-3" aria-label="Kembali ke Pinjam.in">
            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-blue-600 text-white"><span class="text-xl">▣</span></span>
            <span class="text-xl font-extrabold tracking-tight text-slate-900">pinjam<span class="text-blue-600">.in</span></span>
        </a>
        <p class="text-xs font-bold uppercase tracking-[.16em] text-blue-600">Akun Pinjam.in</p>
        <h1 id="form-title" class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">Masuk ke akun</h1>
        <p id="form-description" class="mt-2 text-sm leading-6 text-slate-500">Masuk sebagai admin untuk membuka dashboard, atau sebagai peminjam untuk mengajukan perangkat.</p>
        <p id="form-error" role="alert" class="mt-5 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></p>

        <form id="login-form" class="mt-6 space-y-4">
            <label class="block text-sm font-semibold text-slate-700">Username
                <input name="username" type="text" autocomplete="username" required class="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
            </label>
            <div>
                <label for="login-password" class="block text-sm font-semibold text-slate-700">Password</label>
                <div class="relative">
                    <input id="login-password" name="password" type="password" autocomplete="current-password" required class="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 pr-12 text-sm font-normal outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                    <button id="toggle-login-password" type="button" aria-label="Lihat password" aria-pressed="false" class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <g id="login-password-eye-open"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g>
                            <g id="login-password-eye-closed" class="hidden"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.9M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 2.1-.2"/></g>
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="h-11 w-full rounded-xl bg-blue-600 px-4 text-sm font-bold text-white transition hover:bg-blue-700">Masuk</button>
        </form>

        <form id="register-form" class="mt-6 hidden space-y-4">
            <label class="block text-sm font-semibold text-slate-700">Nama lengkap
                <input name="nama" type="text" maxlength="100" autocomplete="name" required class="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
            </label>
            <label class="block text-sm font-semibold text-slate-700">Username
                <input name="username" type="text" minlength="3" maxlength="50" autocomplete="username" required class="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
            </label>
            <label class="block text-sm font-semibold text-slate-700">Email <span class="font-normal text-slate-400">(opsional)</span>
                <input name="email" type="email" maxlength="120" autocomplete="email" class="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
            </label>
            <div>
                <label for="register-password" class="block text-sm font-semibold text-slate-700">Password</label>
                <div class="relative">
                    <input id="register-password" name="password" type="password" minlength="8" autocomplete="new-password" required class="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 pr-12 text-sm font-normal outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                    <button id="toggle-register-password" type="button" aria-label="Lihat password" aria-pressed="false" class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <g id="register-password-eye-open"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g>
                            <g id="register-password-eye-closed" class="hidden"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.9M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 2.1-.2"/></g>
                        </svg>
                    </button>
                </div>
                <span class="mt-1 block text-xs font-normal text-slate-400">Minimal 8 karakter.</span>
            </div>
            <button type="submit" class="h-11 w-full rounded-xl bg-blue-600 px-4 text-sm font-bold text-white transition hover:bg-blue-700">Buat akun peminjam</button>
        </form>

        <p class="mt-5 text-center text-sm text-slate-500">
            <span id="switch-prompt">Belum punya akun?</span>
            <button id="switch-mode" type="button" class="font-bold text-blue-600 hover:underline">Daftar sebagai peminjam</button>
        </p>
        <a href="index.php" class="mt-5 block text-center text-sm font-semibold text-slate-500 hover:text-blue-600">Kembali ke halaman utama</a>
    </main>
    <script>
        const csrfToken = <?= json_encode($_SESSION['public_csrf'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const loginForm = document.getElementById("login-form");
        const registerForm = document.getElementById("register-form");
        const loginPassword = document.getElementById("login-password");
        const toggleLoginPassword = document.getElementById("toggle-login-password");
        const registerPassword = document.getElementById("register-password");
        const toggleRegisterPassword = document.getElementById("toggle-register-password");
        function attachPasswordVisibilityToggle(input, button, openEyeId, closedEyeId) {
            button.addEventListener("click", () => {
                const visible = input.type === "password";
                input.type = visible ? "text" : "password";
                button.setAttribute("aria-pressed", String(visible));
                button.setAttribute("aria-label", visible ? "Sembunyikan password" : "Lihat password");
                document.getElementById(openEyeId).classList.toggle("hidden", visible);
                document.getElementById(closedEyeId).classList.toggle("hidden", !visible);
            });
        }
        attachPasswordVisibilityToggle(loginPassword, toggleLoginPassword, "login-password-eye-open", "login-password-eye-closed");
        attachPasswordVisibilityToggle(registerPassword, toggleRegisterPassword, "register-password-eye-open", "register-password-eye-closed");
        const formTitle = document.getElementById("form-title");
        const formDescription = document.getElementById("form-description");
        const switchPrompt = document.getElementById("switch-prompt");
        const switchMode = document.getElementById("switch-mode");
        const errorMessage = document.getElementById("form-error");
        let isRegistering = false;

        switchMode.addEventListener("click", () => {
            isRegistering = !isRegistering;
            loginForm.classList.toggle("hidden", isRegistering);
            registerForm.classList.toggle("hidden", !isRegistering);
            errorMessage.classList.add("hidden");
            formTitle.textContent = isRegistering ? "Buat akun peminjam" : "Masuk ke akun";
            formDescription.textContent = isRegistering
                ? "Daftar akun peminjam untuk mengajukan dan memantau peminjaman perangkat."
                : "Masuk sebagai admin untuk membuka dashboard, atau sebagai peminjam untuk mengajukan perangkat.";
            switchPrompt.textContent = isRegistering ? "Sudah punya akun?" : "Belum punya akun?";
            switchMode.textContent = isRegistering ? "Masuk" : "Daftar sebagai peminjam";
        });

        async function sendAuthRequest(event, action) {
            event.preventDefault();
            errorMessage.classList.add("hidden");
            const form = event.currentTarget;
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = "Memproses...";

            try {
                const response = await fetch("api.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({
                        action,
                        csrfToken,
                        ...Object.fromEntries(new FormData(form)),
                    }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || "Permintaan gagal diproses.");
                window.location.href = result.redirect;
            } catch (error) {
                errorMessage.textContent = error.message || "Terjadi kesalahan. Silakan coba lagi.";
                errorMessage.classList.remove("hidden");
            } finally {
                button.disabled = false;
                button.textContent = action === "login" ? "Masuk" : "Buat akun peminjam";
            }
        }

        loginForm.addEventListener("submit", event => sendAuthRequest(event, "login"));
        registerForm.addEventListener("submit", event => sendAuthRequest(event, "register"));
    </script>
</body>
</html>
