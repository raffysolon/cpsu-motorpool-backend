<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | CPSU Motor Pool</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 32px 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #0F3D24, #1F8A3D);
            color: #1F2933;
            font-family: Arial, sans-serif;
        }
        main {
            width: min(100%, 440px);
            padding: 32px;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 20px 55px #0F172A40;
        }
        h1 { margin: 0 0 8px; font-size: 25px; }
        p { color: #6B7280; line-height: 1.5; }
        label { display: block; margin: 18px 0 7px; font-weight: 700; }
        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            font: inherit;
        }
        input:focus { outline: 2px solid #1F8A3D; border-color: transparent; }
        input[readonly] { background: #F8FAFC; color: #64748B; }
        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: 0;
            border-radius: 10px;
            background: #1F8A3D;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .message { margin: 16px 0; color: #176E30; }
        .error { margin: 12px 0 0; color: #B42318; font-size: 14px; }
        @media (max-width: 480px) { main { padding: 24px; } }
    </style>
</head>
<body>
<main>
    <h1>Set a new password</h1>
    <p>Choose a new password for your CPSU Motor Pool driver account.</p>

    @if (session('status'))
        <p class="message" role="status">{{ session('status') }}</p>
        <p>You can now sign in to the CPSU Motor Pool app with your new password.</p>
    @else
        <form method="POST" action="{{ secure_url(route('password.update', [], false)) }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" readonly required>
            @error('email') <p class="error">{{ $message }}</p> @enderror

            <label for="password">New password</label>
            <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
            @error('password') <p class="error">{{ $message }}</p> @enderror

            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
            <input type="hidden" name="token" value="{{ old('token', $token) }}">
            @error('token') <p class="error">{{ $message }}</p> @enderror

            <button type="submit">Reset password</button>
        </form>
    @endif
</main>
</body>
</html>
