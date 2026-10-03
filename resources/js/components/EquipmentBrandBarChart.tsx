import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import type { QuickStats } from '../types';

/** Biểu đồ cột: số lượng thiết bị (vợt/giày) theo thương hiệu — cho Dashboard nhanh Admin. */
export default function EquipmentBrandBarChart({ data, height = 320 }: { data: QuickStats['equipment_by_brand']; height?: number }) {
  return (
    <div style={{ width: '100%', height }}>
      <ResponsiveContainer>
        <BarChart data={data} margin={{ top: 8, right: 16, bottom: 8, left: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
          <XAxis dataKey="brand" tick={{ fill: '#a3a3a3', fontSize: 11 }} />
          <YAxis tick={{ fill: '#a3a3a3', fontSize: 11 }} allowDecimals={false} />
          <Tooltip
            contentStyle={{ background: '#1d1d20', border: '1px solid #2a2a2f', borderRadius: 8 }}
            cursor={{ fill: '#1d1d2055' }}
          />
          <Legend wrapperStyle={{ fontSize: 12 }} />
          <Bar dataKey="rackets" name="Vợt" fill="#34d399" radius={[4, 4, 0, 0]} />
          <Bar dataKey="shoes" name="Giày" fill="#fbbf24" radius={[4, 4, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
