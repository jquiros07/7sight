<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>You've been invited to {{ $workspace->name }}</title>
</head>
<body style="margin:0; padding:0; background-color:#fafafa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <span style="display:none; font-size:1px; color:#fafafa; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
        {{ $inviter->name }} invited you to join {{ $workspace->name }} on 7Sight.
    </span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border:1px solid #e4e4e7; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:32px 32px 0 32px;">
                            <img src="{{ $message->embed(resource_path('images/7sight.png')) }}" width="56" height="56" alt="7Sight" style="display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 32px 0 32px;">
                            <h1 style="margin:0; font-size:20px; font-weight:600; color:#18181b;">You've been invited to {{ $workspace->name }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0 32px; font-size:15px; line-height:1.6; color:#52525b;">
                            <p style="margin:0;">
                                {{ $inviter->name }} invited you to join <strong>{{ $workspace->name }}</strong> on
                                7Sight as a{{ $role === 'admin' ? 'n' : '' }} {{ $role }}. Create an account with this
                                email address to accept.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:28px 32px 0 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="border-radius:8px; background-color:#0891b2;">
                                        <a href="{{ $registerUrl }}"
                                           style="display:inline-block; padding:12px 28px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;">
                                            Create your account
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 0 32px; font-size:13px; line-height:1.6; color:#a1a1aa;">
                            You'll be added to {{ $workspace->name }} automatically once you verify this email
                            address.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0 32px; font-size:13px; line-height:1.6; color:#a1a1aa;">
                            Button not working? Paste this link into your browser:<br>
                            <a href="{{ $registerUrl }}" style="color:#0e7490; word-break:break-all;">{{ $registerUrl }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px 32px 32px;">
                            <hr style="border:none; border-top:1px solid #e4e4e7; margin:0 0 20px 0;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#a1a1aa;">
                                If you weren't expecting this invite, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:20px 0 0 0; font-size:12px; color:#a1a1aa;">
                    &copy; {{ date('Y') }} Video Intelligence Platform
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
