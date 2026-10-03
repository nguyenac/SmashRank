export type SkillLevel = 'pro' | 'advanced' | 'intermediate' | 'beginner';
export type Category = 'MS' | 'WS' | 'MD' | 'WD' | 'XD';
export type EquipmentType = 'racket' | 'shoes';

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'user';
  two_factor_enabled: boolean;
  two_factor_method?: 'totp' | 'email';
}

export interface RankingHistoryPoint {
  month: string;
  points: number;
  elo_rating: number;
  win_rate: number;
}

export interface AthleteSkills {
  agility: number;
  power: number;
  stamina: number;
  technique: number;
  defense: number;
  mentality: number;
}

export interface Athlete {
  id: number;
  full_name: string;
  nickname?: string | null;
  nationality: string;
  country_code: string;
  category: Category;
  world_rank?: number | null;
  ranking_points: number;
  elo_rating: number;
  skill_level: SkillLevel;
  win_rate?: number;
  dominant_hand: 'right' | 'left';
  avatar_url?: string | null;
  racket?: string | null;
  shoes?: string | null;
  club?: string | null;
  birth_year?: number | null;
  grassroots_rank?: string | null;
  verified?: boolean;
  skills?: AthleteSkills;
  ranking_history?: RankingHistoryPoint[];
}

export interface EquipmentItem {
  id: number;
  name: string;
  type: EquipmentType;
  brand: string;
  model: string;
  description?: string | null;
  price?: string | null;
  image_url?: string | null;
  specifications?: Record<string, string | number> | null;
  associated_athletes?: { id: number; full_name: string; country_code: string; world_rank?: number | null }[];
}

export interface BackupRecord {
  id: number;
  filename: string;
  size_kb: number;
  drive_file_id: string | null;
  status: 'pending' | 'uploaded' | 'failed' | 'local_only';
  type: string;
  created_at: string;
}

export interface BackupSettings {
  frequency: 'daily' | 'weekly' | 'monthly';
  enabled: boolean;
  drive_folder_id: string | null;
  last_run_at: string | null;
  drive_configured: boolean;
}

export interface AnalyticsSummary {
  total_athletes: number;
  total_users: number;
  new_this_week: number;
  new_this_month: number;
  country_distribution: { country: string; country_code: string; total: number }[];
  top_elo_growers: {
    id: number; full_name: string; country_code: string;
    avatar_url?: string | null; elo_now: number; elo_prev: number; elo_growth: number;
  }[];
  skill_levels: Record<string, number>;
}

export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export const SKILL_LEVEL_VI: Record<SkillLevel, string> = {
  pro: 'Chuyên nghiệp',
  advanced: 'Nâng cao',
  intermediate: 'Trung bình khá',
  beginner: 'Mới bắt đầu',
};

export const CATEGORY_VI: Record<Category, string> = {
  MS: 'Đơn Nam',
  WS: 'Đơn Nữ',
  MD: 'Đôi Nam',
  WD: 'Đôi Nữ',
  XD: 'Đôi Nam Nữ',
};

// ---------- Mở rộng: chi tiết VĐV / Kỷ lục / Giải đấu / Thương hiệu ----------

export interface AthleteDetails {
  birth_date?: string | null;
  height_cm?: number | null;
  weight_kg?: number | null;
  playing_style?: string | null;
  coach?: string | null;
  association?: string | null;
  titles: number;
  finals: number;
  total_matches: number;
  total_wins: number;
  goat_points: number;
  not_played_matches: number;
  win_streak_current: number;
  win_streak_career: number;       // bao gồm W.O.
  win_streak_career_excl_wo: number; // không tính W.O.
  super_streak: number;
}

export interface RecordItem {
  id: number;
  type: string;
  title: string;
  value: string;
  period?: string | null;
  description?: string | null;
  athlete?: { id: number; full_name: string; country_code: string; avatar_url?: string | null; elo_rating: number } | null;
}

export interface Tournament {
  id: number;
  name: string;
  level: 'super1000' | 'super750' | 'super500' | 'super300' | 'super100' | 'other';
  is_asian_games: boolean;
  is_team_event: boolean;
  has_live_scores: boolean;
  association?: string | null;
  host_country?: string | null;
  start_date?: string | null;
  end_date?: string | null;
  winners?: {
    athlete_id: number;
    athlete_name?: string;
    country_code?: string;
    category: string;
    placement: string;
    achieved_at?: string | null;
  }[];
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  country?: string | null;
  logo_url?: string | null;
  description?: string | null;
  rackets_count?: number;
  shoes_count?: number;
  products?: { id: number; name: string; type: EquipmentType; model: string; price?: string | null }[];
}

export interface CrawlLogItem {
  id: number;
  target: string;
  source: string;
  items_found: number;
  items_upserted: number;
  status: string;
  message?: string | null;
  created_at: string;
}

export interface BirthdayEntry {
  id: number;
  full_name: string;
  country_code: string;
  category: Category;
  elo_rating: number;
  avatar_url?: string | null;
  birth_date: string;
  turning_age: number;
}

export interface QuickStats {
  skill_distribution: Record<string, number>;
  grassroots_distribution: Record<string, number>;
  equipment_by_brand: { brand: string; rackets: number; shoes: number; total: number }[];
}

// ---------- Mở rộng lần 2: leaderboard / trận / bình luận / tin tức ----------

export interface LeaderboardRow {
  rank: number;
  rank_change: number;
  period_points?: number | null;
  athlete: { id: number; full_name: string; country_code: string; category: string; elo_rating: number; avatar_url?: string | null } | null;
}

export interface CommentItem {
  id: number;
  user: { id: number; name: string };
  body: string;
  parent_id?: number | null;
  created_at: string;
  replies: CommentItem[];
}

export interface NewsItem {
  id: number;
  title: string;
  body?: string;
  source?: string | null;
  published_at?: string;
}

export interface TournamentMatchRow {
  id: number;
  round: number;
  slot: number;
  athlete1?: { id: number; full_name: string; country_code: string } | null;
  athlete2?: { id: number; full_name: string; country_code: string } | null;
  score1: number;
  score2: number;
  status: 'scheduled' | 'live' | 'completed';
  starts_at?: string | null;
}

export interface TrainingGoal {
  id: number;
  title: string;
  target?: string | null;
  drill?: string | null;
  frequency?: string | null;
  deadline?: string | null;
  status: string;
  progress: number;
  coach_advice?: string | null;
}

export interface ReportData {
  top_products: { name: string; brand: string; sold: number; revenue: number }[];
  member_registrations: { weekly: { label: string; total: number }[]; monthly: { label: string; total: number }[] };
  court_usage: { weekly: { label: string; total: number }[]; monthly: { label: string; total: number }[] };
}

export const GRASSROOT_RANKS = ['Newbie', 'TBY', 'TB', 'Khá', 'Giỏi', 'Xuất Sắc'] as const;

// ---------- Mở rộng lần 3: global search / cân nặng / chấn thương / HLV ----------

export interface SearchResult {
  athletes: { id: number; full_name: string; country_code: string; category: string; elo_rating: number }[];
  clubs: { club: string; members: number }[];
  equipment: { id: number; name: string; type: string; brand: string; model: string; price?: string | null }[];
  tournaments: { id: number; name: string; level: string; start_date?: string | null }[];
}

export interface WeightEntryItem { id: number; measured_at: string; weight_kg: string }
export interface InjuryItem { id: number; type: string; description?: string | null; occurred_at: string; expected_recovery?: string | null; status: string }
export interface CoachNoteItem { id: number; body: string; created_at: string; author?: { id: number; name: string } }
export interface TrainingScheduleItem { id: number; day_of_week: number; drill: string; detail?: string | null; done: boolean; week_start?: string }
export interface DrillItem { id: number; title: string; detail: string; category: string; difficulty: string; duration_min: number; is_reference?: boolean }
export interface SkillProgressPoint { date: string; power: number; speed: number; defense: number; net_play: number; stamina: number }
export interface RacketSuggestion { id: number; name: string; brand: string; model: string; price?: string | null; match_reason: string }
export interface MatchHistoryItem { date: string; venue?: string | null; opponent?: string | null; score: string; result: 'win' | 'loss'; walkover: boolean }
export interface CourtSuggestion {
  date: string; weekday: string;
  suggestions: { court: { id: number; name: string }; best_slots: { hour: string; busy_score: number; score: number; prime_time: boolean }[] }[];
  note: string;
}

export const DRILL_CATEGORY_VI: Record<string, string> = {
  attack: 'Tấn công', defense: 'Phòng thủ', footwork: 'Bộ pháp di chuyển',
  technique: 'Kỹ thuật động tác', stamina: 'Thể lực',
};

export const DRILL_DIFFICULTY_VI: Record<string, string> = {
  easy: 'Dễ', medium: 'Trung bình', hard: 'Khó',
};

export const EQUIPMENT_TYPES = ['racket', 'shoes', 'bag', 'grip', 'string', 'apparel'] as const;

export const EQUIPMENT_TYPE_VI: Record<string, string> = {
  racket: 'Vợt', shoes: 'Giày', bag: 'Túi đựng vợt',
  grip: 'Quấn cán', string: 'Cước', apparel: 'Tất & trang phục',
};

export const LEVEL_VI: Record<Tournament['level'], string> = {
  super1000: 'Super 1000',
  super750: 'Super 750',
  super500: 'Super 500',
  super300: 'Super 300',
  super100: 'Super 100',
  other: 'Khác',
};
