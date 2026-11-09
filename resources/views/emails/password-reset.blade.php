<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your password</title>
</head>
<body style="margin:0;padding:24px;background:#F3F8F4;color:#1F2933;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border-radius:16px;">
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 8px;color:#1F8A3D;font-size:13px;font-weight:bold;letter-spacing:1px;">CPSU MOTOR POOL</p>
                            <h1 style="margin:0 0 16px;font-size:24px;">Reset your password</h1>
                            <p style="line-height:1.6;">Hello {{ $name }}, we received a request to reset the password for your driver account.</p>
                            <p style="margin:28px 0;">
                                <a href="{{ $url }}" style="display:inline-block;padding:14px 22px;border-radius:10px;background:#1F8A3D;color:#fff;text-decoration:none;font-weight:bold;">Choose a new password</a>
                            </p>
                            <p style="line-height:1.6;">This link expires in 60 minutes and can only be used once. If the button does not work, copy and paste this link into your browser:</p>
                            <p style="overflow-wrap:anywhere;"><a href="{{ $url }}" style="color:#176E30;">{{ $url }}</a></p>
                            <p style="margin-top:24px;color:#6B7280;font-size:13px;line-height:1.6;">If you did not request a password reset, you can ignore this email. Your password will not change unless the link is used.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
