<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>SmashRank — Báo cáo tổng hợp</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 20px; border-bottom: 3px solid #10b981; padding-bottom: 6px; }
        h2 { font-size: 13px; color: #059669; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        td, th { border: 1px solid #ddd; padding: 4px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #777; font-size: 10px; }
    </style>
</head>
<body>
    <h1>📊 SmashRank — Báo cáo tổng hợp</h1>
    <p class="muted">Xuất ngày {{ now()->format('d/m/Y H:i') }}</p>

    <h2>Tổng quan</h2>
    <p>Tổng số vận động viên: <b>{{ $totalAthletes }}</b> · Tài khoản người dùng: <b>{{ $totalUsers }}</b></p>

    <h2>Top 10 VĐV theo Elo</h2>
    <table>
        <tr><th>#</th><th>Họ tên</th><th>Quốc gia</th><th>Elo</th><th>Thắng/Thua</th></tr>
        @foreach($topAthletes as $i => $a)
            <tr>
                <td>{{ $i + 1 }}</td><td>{{ $a->full_name }}</td><td>{{ $a->country_code }}</td>
                <td>{{ $a->elo_rating }}</td><td>{{ $a->win_count }}/{{ $a->loss_count }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Thiết bị theo loại & thương hiệu</h2>
    <table>
        <tr><th>Loại</th><th>Số lượng</th></tr>
        @foreach($equipmentByType as $type => $total)
            <tr><td>{{ $type }}</td><td>{{ $total }}</td></tr>
        @endforeach
    </table>
    <table>
        <tr><th>Thương hiệu</th><th>Số sản phẩm</th></tr>
        @foreach($equipmentByBrand as $b)
            <tr><td>{{ $b->name }}</td><td>{{ $b->products_count }}</td></tr>
        @endforeach
    </table>

    <h2>Giải đấu gần đây</h2>
    <table>
        <tr><th>Giải</th><th>Cấp độ</th><th>Thời gian</th><th>Live</th></tr>
        @foreach($tournaments as $t)
            <tr>
                <td>{{ $t->name }}</td><td>{{ $t->level }}</td>
                <td>{{ $t->start_date?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $t->has_live_scores ? 'Có' : 'Không' }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
