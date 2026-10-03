import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import type { RankingHistoryPoint } from '../types';

interface Props {
  data: RankingHistoryPoint[];
  metric?: 'elo_rating' | 'points' | 'win_rate';
  height?: number;
}

const METRIC_CONFIG = {
  elo_rating: { label: 'Điểm Elo', color: '#34d399', unit: '' },
  points: { label: 'Điểm BWF', color: '#fbbf24', unit: '' },
  win_rate: { label: 'Tỷ lệ thắng (%)', color: '#fb7185', unit: '%' },
} as const;

/**
 * Biểu đồ đường thể hiện sự thay đổi điểm xếp hạng của vận động viên
 * theo thời gian (12 tháng gần nhất) + tỷ lệ thắng.
 */
export default function RankTrendChart({ data, metric = 'elo_rating', height = 280 }: Props) {
  const cfg = METRIC_CONFIG[metric];

  return (
    <div style={{ width: '100%', height }}>
      <ResponsiveContainer>
        <LineChart data={data} margin={{ top: 8, right: 16, bottom: 8, left: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
          <XAxis dataKey="month" tick={{ fill: '#a3a3a3', fontSize: 11 }} />
          <YAxis tick={{ fill: '#a3a3a3', fontSize: 11 }} domain={['auto', 'auto']} />
          <Tooltip
            contentStyle={{ background: '#1d1d20', border: '1px solid #2a2a2f', borderRadius: 8 }}
            labelStyle={{ color: '#e5e5e5' }}
          />
          <Legend wrapperStyle={{ fontSize: 12 }} />
          <Line
            type="monotone"
            dataKey={metric}
            name={cfg.label}
            stroke={cfg.color}
            strokeWidth={2}
            dot={{ r: 3 }}
            activeDot={{ r: 5 }}
            unit={cfg.unit}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
