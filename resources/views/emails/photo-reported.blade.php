<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Foto-Meldung</title>
</head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1a1a1a;">
    <h2 style="margin-bottom: 8px;">Neue Foto-Meldung</h2>
    <p style="color: #555;">Ein Gast hat ein Foto gemeldet. Der Name des meldenden Gasts wird bewusst nicht mitgeschickt.</p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 8px 0; font-weight: bold; width: 140px;">Event:</td>
            <td>{{ $event->name }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; font-weight: bold;">Grund:</td>
            <td>{{ $report->reason }}</td>
        </tr>
        @if ($report->message)
        <tr>
            <td style="padding: 8px 0; font-weight: bold; vertical-align: top;">Nachricht:</td>
            <td style="white-space: pre-wrap;">{{ $report->message }}</td>
        </tr>
        @endif
    </table>

    @if ($report->photo?->url)
    <p style="margin-top: 24px;">
        <a href="{{ $report->photo->url }}" style="display: inline-block;">
            <img src="{{ $report->photo->url }}" alt="Gemeldetes Foto" style="max-width: 100%; border-radius: 8px; border: 1px solid #ddd;">
        </a>
    </p>
    @endif

    <p style="margin-top: 24px;">
        Bearbeite die Meldung im Bereich <strong>Anfragen</strong> in {{ config('app.name') }}.
    </p>

    <p style="margin-top: 32px; color: #888; font-size: 12px;">
        Diese E-Mail wurde automatisch von {{ config('app.name') }} gesendet.
    </p>
</body>
</html>
