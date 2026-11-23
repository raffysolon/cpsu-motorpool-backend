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
        .password-field { position: relative; }
        .password-field input { padding-right: 52px; }
        .password-toggle { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; margin: 0; padding: 10px; background: transparent; color: #475569; display: grid; place-items: center; }
        .password-toggle:focus-visible { outline: 2px solid #1F8A3D; }
        .password-toggle svg { width: 24px; height: 24px; }
        .password-toggle .eye-slash { display: none; }
        .password-toggle[aria-pressed="true"] .eye-slash { display: block; }
        .message { margin: 16px 0; color: #176E30; }
        .error { margin: 12px 0 0; color: #B42318; font-size: 14px; }
        @media (max-width: 480px) { main { padding: 24px; } }
    </style>
</head>
<body>
<main>
    <h1>Set a new password</h1>
    <p>Choose a new password for your CPSU Motor Pool account.</p>

    <p>Use at least 8 characters, including uppercase and lowercase letters, a number and a special character.</p>

    @if (session('status'))
        <p class="message" role="status">{{ session('status') }}</p>
        <p>Your previous sessions have been signed out. Return to the CPSU Motor Pool app and sign in with your new password.</p>
    @else
        <form method="POST" action="{{ secure_url(route('password.update', [], false)) }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" readonly required>
            @error('email') <p class="error">{{ $message }}</p> @enderror

            <label for="password">New password</label>
            <div class="password-field">
                <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
                <button type="button" class="password-toggle" aria-controls="password" aria-pressed="false" aria-label="Show new password" title="Show new password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                        <path class="eye-slash" d="m3 3 18 18"/>
                    </svg>
                </button>
            </div>
            @error('password') <p class="error">{{ $message }}</p> @enderror

            <label for="password_confirmation">Confirm new password</label>
            <div class="password-field">
                <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                <button type="button" class="password-toggle" aria-controls="password_confirmation" aria-pressed="false" aria-label="Show password confirmation" title="Show password confirmation">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                        <path class="eye-slash" d="m3 3 18 18"/>
                    </svg>
                </button>
            </div>
            <input type="hidden" name="token" value="{{ old('token', $token) }}">
            @error('token') <p class="error">{{ $message }}</p> @enderror

            <button type="submit">Reset password</button>
        </form>
    @endif
</main>
<script>
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            const label = button.getAttribute('aria-label').replace(visible ? 'Show' : 'Hide', visible ? 'Hide' : 'Show');
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
        });
    });
</script>
</body>
</html>
