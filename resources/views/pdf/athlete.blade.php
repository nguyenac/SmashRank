<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>SmashRank — Hồ sơ {{ $athlete->full_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 20px; border-bottom: 3px solid #10b981; padding-bottom: 6px; }
        h2 { font-size: 13px; color: #059669; margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        td, th { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .stat { display: inline-block; width: 23%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px; margin-right: 1.5%; text-align: center; }
        .stat b { display: block; font-size: 16px; color: #059669; }
        .muted { color: #777; font-size: 10px; }
    </style>
</head>
<body>
    <h1>🏸 {{ $athlete->full_name }} {{ $athlete->verified ? '✔' : '' }}</h1>
    <p class="muted">
        {{ $athlete->nationality }} ({{ $athlete->country_code }}) · {{ $athlete->category }}
        · {{ $athlete->skill_level }} · {{ $athlete->dominant_hand === 'right' ? 'Tay phải' : 'Tay trái' }}
        @if($athlete->club) · CLB: {{ $athlete->club }} @endif
        @if($athlete->association) · Hiệp hội: {{ $athlete->association }} @endif
        @if($athlete->grassroots_rank) · Hạng phong trào: {{ $athlete->grassroots_rank }} @endif
    </p>

    <div>
        <span class="stat"><b>#{{ $athlete->world_rank ?? '—' }}</b>Thứ hạng TG</span>
        <span class="stat"><b>{{ number_format($athlete->ranking_points) }}</b>Điểm BWF</span>
        <span class="stat"><b>{{ $athlete->elo_rating }}</b>Điểm Elo</span>
        <span class="stat"><b>{{ $athlete->win_rate }}%</b>Tỷ lệ thắng</span>
    </div>

    <h2>Chuỗi trận thắng & GOAT</h2>
    @if($athlete->details)
        <table>
            <tr><th>Chuỗi hiện tại</th><th>Kỷ lục sự nghiệp (W.O. ngắt)</th><th>Kỷ lục sự nghiệp (W.O. tính thắng)</th><th>Chuỗi Super 1000→100</th></tr>
            <tr>
                <td>{{ $athlete->details->win_streak_current }}</td>
                <td>{{ $athlete->details->win_streak_career_excl_wo }}</td>
                <td>{{ $athlete->details->win_streak_career }}</td>
                <td>{{ $athlete->details->super_streak }}</td>
            </tr>
            <tr><th>Điểm GOAT</th><th>Danh hiệu</th><th>Chung kết</th><th>Tổng trận / Thắng</th></tr>
            <tr>
                <td>{{ $athlete->details->goat_points }}</td>
                <td>{{ $athlete->details->titles }}</td>
                <td>{{ $athlete->details->finals }}</td>
                <td>{{ $athlete->details->total_matches }} / {{ $athlete->details->total_wins }}</td>
            </tr>
        </table>
    @endif

    <h2>Chỉ số kỹ năng (Radar)</h2>
    <table>
        <tr><th>Nhanh nhẹn</th><th>Sức mạnh</th><th>Sức bền</th><th>Kỹ thuật</th><th>Phòng thủ</th><th>Tâm lý</th></tr>
        <tr>
            <td>{{ $athlete->skill_agility }}</td><td>{{ $athlete->skill_power }}</td>
            <td>{{ $athlete->skill_stamina }}</td><td>{{ $athlete->skill_technique }}</td>
            <td>{{ $athlete->skill_defense }}</td><td>{{ $athlete->skill_mentality }}</td>
        </tr>
    </table>

    <h2>Trang bị</h2>
    <p>Vợt: {{ $athlete->racket?->name ?? '—' }} · Giày: {{ $athlete->shoes?->name ?? '—' }}</p>

    <h2>Xu hướng điểm 12 tháng</h2>
    <table>
        <tr><th>Tháng</th><th>Điểm BWF</th><th>Elo</th><th>Tỷ lệ thắng</th></tr>
        @foreach($athlete->histories as $h)
            <tr>
                <td>{{ $h->recorded_month->format('Y-m') }}</td>
                <td>{{ number_format($h->points) }}</td>
                <td>{{ $h->elo_rating }}</td>
                <td>{{ $h->win_rate }}%</td>
            </tr>
        @endforeach
    </table>

    <p class="muted">Xuất từ SmashRank — {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
