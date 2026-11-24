<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tanda terima konsumsi · {{ $period }}</title>
    <style>
        @page { margin: 118px 34px 50px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #0b3b38; font-size: 8.5px; }
        .jadwal { page-break-before: always; }
        .jadwal.pertama { page-break-before: auto; }
        h1 { font-size: 13px; margin: 0; text-align: center; color: #007d71; letter-spacing: 0.4px; }
        .sub { text-align: center; margin: 1px 0 8px; color: #33504d; font-size: 9px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .meta td { padding: 1px 0; border: 0; vertical-align: top; }
        .meta .k { width: 74px; color: #5b7472; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #e6f7f5; font-size: 7.5px; text-transform: uppercase; text-align: left; padding: 4px; border: 1px solid #c5e6e2; }
        table.data td { padding: 2px 4px; border: 1px solid #dceeec; vertical-align: middle; height: 26px; }
        table.data tr { page-break-inside: avoid; }
        .no { width: 18px; text-align: right; }
        .jam { width: 34px; text-align: center; }
        .ttd { width: 104px; text-align: center; padding: 1px !important; }
        .ttd img { height: 24px; max-width: 100px; }
        .tidak td { background: #fff8f1; }
        .alasan { color: #b45309; font-style: italic; }
        .wakil { color: #33504d; }
        .ringkas { margin-top: 6px; color: #33504d; }
        .pengesahan { width: 100%; margin-top: 14px; page-break-inside: avoid; }
        .pengesahan td { border: 0; vertical-align: top; width: 50%; }
        .garis-ttd { margin-top: 44px; }
        .kosong { color: #5b7472; font-style: italic; padding: 6px 0; }
    </style>
</head>
<body>
    @include('pdf.partials.letterhead')

    @forelse ($items as $item)
        @php
            $schedule = $item['schedule'];
        @endphp
        <div @class(['jadwal', 'pertama' => $loop->first])>
            <h1>DAFTAR TANDA TERIMA KONSUMSI</h1>
            <div class="sub">{{ setting('program.name') }} · {{ setting('program.organizer') }}</div>

            <table class="meta">
                <tr>
                    <td class="k">Hari / tanggal</td><td>: <strong>{{ tanggal($schedule->date, 'l, j F Y') }}</strong></td>
                    <td class="k">Vendor</td><td>: {{ $schedule->vendor?->name ?? '–' }}</td>
                </tr>
                <tr>
                    <td class="k">Waktu makan</td><td>: <strong>{{ $schedule->meal_type->label() }}</strong> ({{ $schedule->windowLabel() }} WITA)</td>
                    <td class="k">Menu</td><td>: {{ $schedule->menu ?: '–' }}</td>
                </tr>
                <tr>
                    <td class="k">Cakupan</td><td>: {{ $scope ? implode(' · ', $scope) : 'Semua' }}</td>
                    <td class="k">Porsi</td><td>: dipesan {{ angka($schedule->portions_ordered) }}{{ $schedule->delivery ? ' · diterima dari vendor '.$schedule->delivery->quantityLabel() : '' }}</td>
                </tr>
            </table>

            <table class="data">
                <thead>
                    <tr>
                        <th class="no">No</th>
                        <th>Nama</th>
                        <th>Kelas / tim</th>
                        <th class="jam">Jam</th>
                        <th class="ttd">Tanda tangan</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item['rows'] as $row)
                        @php
                            $receipt = $row['receipt'];
                            $signature = $receipt?->signatureThumbnail();
                        @endphp
                        <tr @class(['tidak' => ! $receipt])>
                            <td class="no">{{ $loop->iteration }}</td>
                            <td>{{ $row['user']->name }}</td>
                            <td>{{ $row['group'] }}</td>
                            <td class="jam">{{ $receipt ? jam($receipt->received_at) : '–' }}</td>
                            <td class="ttd">
                                @if ($signature)
                                    <img src="{{ $signature }}" alt="">
                                @elseif ($receipt)
                                    <span class="wakil">(berkas tidak ada)</span>
                                @endif
                            </td>
                            <td>
                                @if ($receipt)
                                    @if ($receipt->proxy_by)
                                        <span class="wakil">Diwakilkan oleh {{ $receipt->proxy?->name }}{{ $receipt->note ? ' – '.$receipt->note : '' }}</span>
                                    @elseif ($receipt->note)
                                        <span class="wakil">{{ $receipt->note }}</span>
                                    @endif
                                @else
                                    <span class="alasan">{{ implode('; ', $row['reasons']) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="ringkas">
                Berhak <strong>{{ angka($item['eligible']) }}</strong> ·
                menerima <strong>{{ angka($item['received']) }}</strong>{{ $item['proxied'] ? ' (diwakilkan '.angka($item['proxied']).')' : '' }} ·
                tidak menerima <strong>{{ angka($item['missed']) }}</strong>
            </div>

            <table class="pengesahan">
                <tr>
                    <td>
                        Penyedia konsumsi,<br>{{ $schedule->vendor?->name ?? '' }}
                        <div class="garis-ttd">( ........................................ )</div>
                    </td>
                    <td style="text-align: right;">
                        Manado, {{ tanggal($schedule->date, 'j F Y') }}<br>Penanggung jawab konsumsi,
                        <div class="garis-ttd">( ........................................ )</div>
                    </td>
                </tr>
            </table>
        </div>
    @empty
        <h1>DAFTAR TANDA TERIMA KONSUMSI</h1>
        <p class="kosong">Belum ada jadwal makan pada {{ $period }}.</p>
    @endforelse
</body>
</html>
