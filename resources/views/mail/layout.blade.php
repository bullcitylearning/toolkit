@php
    /** @var \Bcl\Toolkit\Brand\Brand $brand */
    $theme = $brand->theme();
    $brandConfig = $brand->config();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f0f0;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f0f0;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        @hasSection('header')
                            <td style="background-color:{{ $theme['primary'] }};padding:16px 32px;">
                                @yield('header')
                            </td>
                        @else
                            <td style="background-color:{{ $theme['primary'] }};padding:20px 32px;">
                                <img src="{{ $brand->url($brandConfig['logo_white_png']) }}" alt="{{ $brandConfig['name'] }}" height="36" style="height:36px;display:block;border:0;">
                            </td>
                        @endif
                    </tr>
                    <tr>
                        <td style="padding:@yield('content-padding', '32px');">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9fafb;padding:20px 32px;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.6;">
                                {{ $brandConfig['name'] }} · {{ $brandConfig['address'] }}<br>
                                <a href="{{ $brandConfig['website'] }}" style="color:{{ $theme['link'] }};">{{ parse_url($brandConfig['website'], PHP_URL_HOST) }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
