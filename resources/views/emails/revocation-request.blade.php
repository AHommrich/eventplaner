<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Rücknahme-Anfrage</title>
</head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1a1a1a;">
    <h2 style="margin-bottom: 8px;">Neue Rücknahme-Anfrage</h2>
    <p style="color: #555;">Ein Gast möchte seine Absage zurücknehmen.</p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 8px 0; font-weight: bold; width: 140px;">Gast:</td>
            <td>{{ $guest->firstname }} {{ $guest->lastname }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold;">Event:</td>
            <td>{{ $event->name }}</td>
        </tr>
    </table>

    <p style="margin-top: 24px;">
        Bitte melde dich bei dem Gast und gib die Anfrage frei oder lehne sie ab.
        Nach der Freigabe kann der Gast erneut zusagen.
    </p>

    <p style="margin-top: 32px; color: #888; font-size: 12px;">
        Diese E-Mail wurde automatisch von {{ config('app.name') }} gesendet.
    </p>
</body>
</html>
