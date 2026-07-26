<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Pasti della settimana</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1f2937;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }
        .subtitle {
            color: #6b7280;
            margin: 0 0 20px;
        }
        .day {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        .day-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: capitalize;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        table.slots {
            width: 100%;
            border-collapse: collapse;
        }
        table.slots td {
            width: 50%;
            vertical-align: top;
            padding: 4px 10px 4px 0;
        }
        .slot-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .meal {
            margin-bottom: 6px;
        }
        .meal-title {
            font-weight: bold;
        }
        .meal-category {
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
        }
        .meal-description {
            font-size: 10px;
            color: #4b5563;
        }
        .empty {
            color: #9ca3af;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>Pasti della settimana</h1>
    <p class="subtitle">
        {{ ucfirst($weekStart->locale('it')->translatedFormat('d F Y')) }} - {{ ucfirst($weekEnd->locale('it')->translatedFormat('d F Y')) }}
    </p>

    @foreach ($days as $day)
        <div class="day">
            <div class="day-title">{{ ucfirst($day['date']->locale('it')->translatedFormat('l d F')) }}</div>
            <table class="slots">
                <tr>
                    <td>
                        <div class="slot-label">Pranzo</div>
                        @forelse ($day['lunch'] as $meal)
                            <div class="meal">
                                <div class="meal-title">{{ $meal->title }}</div>
                                @if ($meal->category)
                                    <div class="meal-category">{{ $dishCategories[$meal->category] ?? $meal->category }}</div>
                                @endif
                                @if ($meal->description)
                                    <div class="meal-description">{{ $meal->description }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="empty">Nessun pasto</div>
                        @endforelse
                    </td>
                    <td>
                        <div class="slot-label">Cena</div>
                        @forelse ($day['dinner'] as $meal)
                            <div class="meal">
                                <div class="meal-title">{{ $meal->title }}</div>
                                @if ($meal->category)
                                    <div class="meal-category">{{ $dishCategories[$meal->category] ?? $meal->category }}</div>
                                @endif
                                @if ($meal->description)
                                    <div class="meal-description">{{ $meal->description }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="empty">Nessun pasto</div>
                        @endforelse
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
