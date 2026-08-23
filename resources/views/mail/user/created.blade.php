@extends('mail.layout')

@section('title', config('app.name') . ' — Account Created')

@section('heading', 'Your B2B Portal Account Has Been Created')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">
        Dear {{ $userName }},
    </p>

    <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">
        An account has been created for you under <strong>{{ $agencyName }}</strong>@if ($createdByName) by {{ $createdByName }}@endif.
        You can sign in to the B2B portal using the credentials below.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
        <tr>
            <td style="padding:12px 16px;background-color:#f8fafc;font-size:13px;font-weight:700;color:#475569;width:38%;">Agency</td>
            <td style="padding:12px 16px;font-size:14px;color:#0f172a;">{{ $agencyName }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background-color:#f8fafc;font-size:13px;font-weight:700;color:#475569;border-top:1px solid #e2e8f0;">Username</td>
            <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-top:1px solid #e2e8f0;">{{ $username }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background-color:#f8fafc;font-size:13px;font-weight:700;color:#475569;border-top:1px solid #e2e8f0;">Mobile Number</td>
            <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-top:1px solid #e2e8f0;">{{ $phone ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background-color:#f8fafc;font-size:13px;font-weight:700;color:#475569;border-top:1px solid #e2e8f0;">Department</td>
            <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-top:1px solid #e2e8f0;">{{ $department ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background-color:#f8fafc;font-size:13px;font-weight:700;color:#475569;border-top:1px solid #e2e8f0;">Designation</td>
            <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-top:1px solid #e2e8f0;">{{ $designation ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background-color:#f8fafc;font-size:13px;font-weight:700;color:#475569;border-top:1px solid #e2e8f0;">Temporary Password</td>
            <td style="padding:12px 16px;font-size:14px;color:#0f172a;border-top:1px solid #e2e8f0;font-family:Consolas,Monaco,monospace;">{{ $defaultPassword }}</td>
        </tr>
    </table>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
        <tr>
            <td style="border-radius:8px;background-color:#027de2;">
                <a href="{{ $portalUrl }}" style="display:inline-block;padding:12px 22px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">
                    Sign In to B2B Portal
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#b45309;background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;">
        <strong>Important:</strong> For your account security, please change your password immediately after your first login.
    </p>

    <p style="margin:0;font-size:14px;line-height:1.6;color:#64748b;">
        If you were not expecting this account, please contact your agency administrator.
    </p>
@endsection
