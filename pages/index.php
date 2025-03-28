<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accommodation Listings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@latest/dist/full.css" rel="stylesheet">
</head>
<body class="p-5">
    <h1 class="text-3xl font-bold">Welcome to Accommodation Listings</h1>

    <div class="mt-5">
        <form id="loginForm" class="flex flex-col gap-3 w-1/3">
            <input type="email" name="email" placeholder="Email" class="input input-bordered w-full" required>
            <input type="password" name="password" placeholder="Password" class="input input-bordered w-full" required>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const response = await fetch('auth.php', { method: 'POST', body: formData });
            const result = await response.json();
            console.log(result);
        });
    </script>
</body>
</html>

