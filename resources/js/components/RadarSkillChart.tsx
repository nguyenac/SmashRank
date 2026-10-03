import {
  Radar, RadarChart as RechartsRadar, PolarGrid, PolarAngleAxis, PolarRadiusAxis, ResponsiveContainer, Tooltip,
} from 'recharts';
import type { AthleteSkills } from '../types';

const LABELS: Record<keyof AthleteSkills, string> = {
  agility: 'Nhanh nhẹn',
  power: 'Sức mạnh',
  stamina: 'Sức bền',
  technique: 'Kỹ thuật',
  defense: 'Phòng thủ',
  mentality: 'Tâm lý',
};

/**
 * RadarChart trực quan hóa chỉ số kỹ năng cá nhân của vận động viên:
 * nhanh nhẹn, sức mạnh, sức bền, kỹ thuật, phòng thủ, tâm lý.
 */
export default function RadarSkillChart({ skills, height = 300 }: { skills: AthleteSkills; height?: number }) {
  const data = (Object.keys(LABELS) as (keyof AthleteSkills)[]).map((key) => ({
    metric: LABELS[key],
    value: skills[key] ?? 0,
  }));

  return (
    <div style={{ width: '100%', height }}>
      <ResponsiveContainer>
        <RechartsRadar data={data} outerRadius="72%">
          <PolarGrid stroke="#2a2a2f" />
          <PolarAngleAxis dataKey="metric" tick={{ fill: '#d4d4d4', fontSize: 12 }} />
          <PolarRadiusAxis domain={[0, 100]} tick={{ fill: '#737373', fontSize: 10 }} />
          <Tooltip
            contentStyle={{ background: '#1d1d20', border: '1px solid #2a2a2f', borderRadius: 8 }}
            formatter={(v: number | string) => [`${v}/100`, 'Điểm']}
          />
          <Radar dataKey="value" stroke="#34d399" fill="#34d399" fillOpacity={0.35} strokeWidth={2} />
        </RechartsRadar>
      </ResponsiveContainer>
    </div>
  );
}
