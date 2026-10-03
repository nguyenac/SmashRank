import { PieChart, Pie, Cell, Tooltip, Legend, ResponsiveContainer } from 'recharts';

const COLORS = ['#34d399', '#fbbf24', '#fb7185', '#60a5fa', '#a78bfa', '#f97316', '#22d3ee', '#e879f9', '#4ade80', '#facc15', '#f472b6', '#38bdf8'];

/** Pie Chart phân bổ vận động viên theo quốc gia cho Analytics Dashboard. */
export default function CountryPieChart({
  data,
  height = 320,
}: {
  data: { country: string; total: number }[];
  height?: number;
}) {
  return (
    <div style={{ width: '100%', height }}>
      <ResponsiveContainer>
        <PieChart>
          <Pie
            data={data}
            dataKey="total"
            nameKey="country"
            cx="50%"
            cy="50%"
            innerRadius="45%"
            outerRadius="75%"
            paddingAngle={2}
          >
            {data.map((_, i) => (
              <Cell key={i} fill={COLORS[i % COLORS.length]} />
            ))}
          </Pie>
          <Tooltip
            contentStyle={{ background: '#1d1d20', border: '1px solid #2a2a2f', borderRadius: 8 }}
            formatter={(v: number | string, name: string) => [`${v} VĐV`, name]}
          />
          <Legend wrapperStyle={{ fontSize: 12 }} />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
}
