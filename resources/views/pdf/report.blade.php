<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }} · {{ $report['period'] }}</title>
    <style>
        @page { margin: 118px 30px 48px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #0b3b38; font-size: 9px; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #007d71; }
        h2 { font-size: 10.5px; margin: 12px 0 4px; color: #007d71; text-transform: uppercase; letter-spacing: 0.5px; }
        .meta { margin: 0; color: #33504d; }
        .meta td { padding: 0 10px 1px 0; border: 0; }
        .ringkasan { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin: 8px -4px 0; }
        .ringkasan td { border: 1px solid #c5eee9; border-radius: 4px; padding: 4px 6px; background: #f3fbfa; }
        .ringkasan .label { color: #5b7472; font-size: 8px; }
        .ringkasan .nilai { font-size: 12px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #e6f7f5; font-size: 8px; text-transform: uppercase; text-align: left; padding: 4px 4px; border: 1px solid #c5e6e2; }
        table.data td { padding: 3px 4px; border: 1px solid #dceeec; vertical-align: top; }
        table.data tr { page-break-inside: avoid; }
        table.data tbody tr:nth-child(even) td { background: #fafdfd; }
        .r { text-align: right; }
        .kosong { color: #5b7472; font-style: italic; padding: 6px 0; }
    </style>
</head>
<body>
    @include('pdf.partials.letterhead')

    <h1>{{ $report['title'] }}</h1>
    <table class="meta">
        <tr><td>Periode</td><td>: <strong>{{ $report['period'] }}</strong></td></tr>
        <tr><td>Cakupan</td><td>: {{ $scope ? implode(' · ', $scope) : 'Semua' }}</td></tr>
        <tr><td>Dicetak</td><td>: {{ tanggal($printedAt, 'l, j F Y') }} pukul {{ jam($printedAt) }} WITA · {{ setting('program.organizer') }}</td></tr>
    </table>

    @if ($report['summary'])
        <table class="ringkasan">
            <tr>
                @foreach ($report['summary'] as $item)
                    <td><div class="label">{{ $item['label'] }}</div><div class="nilai">{{ $item['value'] }}</div></td>
                @endforeach
            </tr>
        </table>
    @endif

    @foreach ($report['sheets'] as $sheet)
        @php
            // Kolom tautan (mis. ke halaman sesi) tidak berguna di kertas.
            $hidden = $sheet['links'] ?? [];
            $columns = array_values(array_diff(array_keys($sheet['headings']), $hidden));
        @endphp
        <h2>{{ $sheet['title'] }} <span style="font-weight: normal; text-transform: none;">({{ count($sheet['rows']) }} baris)</span></h2>
        @if ($sheet['rows'] === [])
            <p class="kosong">Belum ada data pada periode ini.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($columns as $c)
                            <th>{{ $sheet['headings'][$c] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sheet['rows'] as $row)
                        <tr>
                            @foreach ($columns as $c)
                                @php
                                    $cell = $row[$c] ?? null;
                                @endphp
                                <td @class(['r' => is_int($cell) || is_float($cell)])>{{ $cell === null || $cell === '' ? '–' : (is_float($cell) ? angka($cell, 1) : $cell) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
