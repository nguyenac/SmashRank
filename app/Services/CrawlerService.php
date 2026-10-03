<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\AthleteDetail;
use App\Models\Brand;
use App\Models\CrawlLog;
use App\Models\EquipmentItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Crawler / đồng bộ dữ liệu cho Admin.
 *
 *  1. Vận động viên: badmintonranks.com  → đồng bộ với bwfbadminton.com/rankings
 *  2. Thương hiệu & sản phẩm: shopvnb.com → đồng bộ với badmintoncn.com/cbo_eq/
 *     (dữ liệu badmintoncn được dịch EN → ZH qua TranslationService).
 *
 * ⚠️ Lưu ý tuân thủ: kiểm tra robots.txt & điều khoản của từng nguồn trước khi
 * bật crawl định kỳ; giới hạn tần suất (rate limit) và đặt User-Agent rõ ràng.
 * Cấu trúc HTML của nguồn có thể thay đổi — các parser nên được rà soát lại
 * trước khi đưa vào lịch tự động.
 */
class CrawlerService
{
    private const UA = 'SmashRankBot/1.0 (+https://your-domain.com; admin contact)';

    public function __construct(private TranslationService $translator)
    {
    }

    // ------------------------------------------------------------------
    // 1) Vận động viên — badmintonranks.com
    // ------------------------------------------------------------------

    /** @return array{found:int, upserted:int} */
    public function crawlAthletesFromBadmintonRanks(): array
    {
        $found = 0;
        $upserted = 0;

        try {
            // Trang danh sách tay vợt (elo ranking)
            $response = Http::withHeaders(['User-Agent' => self::UA])
                ->timeout(30)
                ->get('https://badmintonranks.com/rankings/elo/men-singles');

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status().' từ badmintonranks.com');
            }

            // Parser: mỗi dòng xếp hạng chứa link profile + tên + quốc gia + điểm
            // (regex bám theo cấu trúc HTML hiện tại; cần rà soát khi nguồn thay đổi)
            preg_match_all(
                '/<a[^>]+href="\/player\/(?P<slug>[^"]+)"[^>]*>(?P<name>[^<]+)<\/a>.*?flag-(?P<cc>[a-z]{2,3}).*?(?P<elo>\d{3,5})/is',
                $response->body(),
                $rows,
                PREG_SET_ORDER
            );

            foreach ($rows as $row) {
                $found++;
                $athlete = $this->upsertAthlete([
                    'source' => 'badmintonranks',
                    'source_id' => $row['slug'],
                    'full_name' => html_entity_decode(trim($row['name'])),
                    'country_code' => strtoupper($row['cc']),
                    'elo_rating' => (int) $row['elo'],
                ]);
                if ($athlete) {
                    $upserted++;
                }
            }

            $this->log('athletes', 'badmintonranks', $found, $upserted, 'success');
        } catch (\Throwable $e) {
            Log::warning('Crawl badmintonranks thất bại: '.$e->getMessage());
            $this->log('athletes', 'badmintonranks', $found, $upserted, 'failed', $e->getMessage());
        }

        return ['found' => $found, 'upserted' => $upserted];
    }

    /**
     * Đồng bộ thứ hạng BWF chính thức (JSON API công khai của bwfbadminton.com)
     * vào world_rank / ranking_points của VĐV khớp tên.
     */
    public function syncWithBwfRankings(string $category = 'MS'): array
    {
        $found = 0;
        $upserted = 0;

        try {
            // Endpoint JSON công khai của BWF cho bảng xếp hạng (mặc định: đơn nam)
            $response = Http::withHeaders(['User-Agent' => self::UA])
                ->timeout(30)
                ->get('https://bwfbadminton.com/rankings/2/bwf-world-rankings/'.$category);

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status().' từ bwfbadminton.com');
            }

            // Trích "tên — điểm — hạng" từ bảng xếp hạng (parser HTML bảng)
            preg_match_all(
                '/<tr[^>]*>.*?<td[^>]*>(?P<rank>\d+)<\/td>.*?<a[^>]*>(?P<name>[^<]+)<\/a>.*?<td[^>]*>(?P<points>[\d,]+)<\/td>/is',
                $response->body(),
                $rows,
                PREG_SET_ORDER
            );

            foreach ($rows as $row) {
                $found++;
                $athlete = Athlete::where('full_name', 'like', '%'.trim($row['name']).'%')->first()
                    ?? Athlete::whereRaw('REPLACE(full_name, " ", "") = ?', [str_replace(' ', '', trim($row['name']))])->first();

                if ($athlete) {
                    $athlete->update([
                        'world_rank' => (int) $row['rank'],
                        'ranking_points' => (int) str_replace(',', '', $row['points']),
                    ]);
                    $upserted++;
                }
            }

            $this->log('athletes', 'bwfbadminton', $found, $upserted, 'success');
        } catch (\Throwable $e) {
            Log::warning('Sync BWF rankings thất bại: '.$e->getMessage());
            $this->log('athletes', 'bwfbadminton', $found, $upserted, 'failed', $e->getMessage());
        }

        return ['found' => $found, 'upserted' => $upserted];
    }

    // ------------------------------------------------------------------
    // 2) Thương hiệu & sản phẩm — shopvnb.com
    // ------------------------------------------------------------------

    /** @return array{found:int, upserted:int} */
    public function crawlProductsFromShopVnb(): array
    {
        $found = 0;
        $upserted = 0;

        try {
            $response = Http::withHeaders(['User-Agent' => self::UA])
                ->timeout(30)
                ->get('https://shopvnb.com/vot-cau-long.html');

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status().' từ shopvnb.com');
            }

            // Parser danh sách sản phẩm: link product + tên + giá
            preg_match_all(
                '/<a[^>]+href="(?P<url>https:\/\/shopvnb\.com\/[^"]+\.html)"[^>]*title="(?P<name>[^"]+)".*?(?P<price>[\d.,]+)₫/is',
                $response->body(),
                $rows,
                PREG_SET_ORDER
            );

            foreach ($rows as $row) {
                $found++;
                $name = html_entity_decode(trim($row['name']));
                $brandName = $this->guessBrand($name);

                $item = EquipmentItem::updateOrCreate(
                    ['source' => 'shopvnb', 'source_id' => md5($row['url'])],
                    [
                        'name' => $name,
                        'type' => str_contains(strtolower($name), 'giay|shoe') ? 'shoes' : 'racket',
                        'brand' => $brandName,
                        'brand_id' => $this->ensureBrand($brandName, 'shopvnb')->id,
                        'model' => $name,
                        'price' => (float) preg_replace('/[^\d]/', '', $row['price']),
                        'description' => 'Dữ liệu đồng bộ từ shopvnb.com',
                    ]
                );
                if ($item) {
                    $upserted++;
                }
            }

            $this->log('products', 'shopvnb', $found, $upserted, 'success');
        } catch (\Throwable $e) {
            Log::warning('Crawl shopvnb thất bại: '.$e->getMessage());
            $this->log('products', 'shopvnb', $found, $upserted, 'failed', $e->getMessage());
        }

        return ['found' => $found, 'upserted' => $upserted];
    }

    /**
     * Đồng bộ với badmintoncn.com/cbo_eq/ — kho equipment Trung Quốc.
     * Dữ liệu nguồn (tiếng Anh) được dịch sang tiếng Trung trước khi lưu.
     */
    public function syncWithBadmintonCn(): array
    {
        $found = 0;
        $upserted = 0;

        try {
            $response = Http::withHeaders(['User-Agent' => self::UA])
                ->timeout(30)
                ->get('https://www.badmintoncn.com/cbo_eq/');

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status().' từ badmintoncn.com');
            }

            preg_match_all(
                '/<a[^>]+href="(?P<url>[^"]+)"[^>]*>(?P<name>[^<]{4,80})<\/a>/is',
                $response->body(),
                $rows,
                PREG_SET_ORDER
            );

            foreach ($rows as $row) {
                $nameEn = html_entity_decode(trim(strip_tags($row['name'])));
                if (! preg_match('/(racket|racquet|shoe|NS-|AX|TK|ASTROX|ARCN)/i', $nameEn)) {
                    continue; // lọc link điều hướng
                }
                $found++;

                $nameZh = $this->translator->enToZh($nameEn);
                $brandName = $this->guessBrand($nameEn);

                EquipmentItem::updateOrCreate(
                    ['source' => 'badmintoncn', 'source_id' => md5($row['url'])],
                    [
                        'name' => $nameZh, // ← dữ liệu badmintoncn được dịch từ tiếng Anh sang tiếng Trung
                        'type' => preg_match('/shoe/i', $nameEn) ? 'shoes' : 'racket',
                        'brand' => $brandName,
                        'brand_id' => $this->ensureBrand($brandName, 'badmintoncn')->id,
                        'model' => $nameEn,
                        'description' => 'Đồng bộ từ badmintoncn.com (dịch EN→ZH)',
                    ]
                );
                $upserted++;
            }

            $this->log('products', 'badmintoncn', $found, $upserted, 'success');
        } catch (\Throwable $e) {
            Log::warning('Sync badmintoncn thất bại: '.$e->getMessage());
            $this->log('products', 'badmintoncn', $found, $upserted, 'failed', $e->getMessage());
        }

        return ['found' => $found, 'upserted' => $upserted];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function upsertAthlete(array $data): ?Athlete
    {
        if (empty($data['full_name'])) {
            return null;
        }

        $athlete = Athlete::firstOrNew(
            ['source_id' => $data['source_id'], 'full_name' => $data['full_name']]
        );
        $athlete->source = $data['source'];
        $athlete->nationality = $data['country_code'] ?? ($athlete->nationality ?? 'UNK');
        $athlete->country_code = $data['country_code'] ?? ($athlete->country_code ?? 'UNK');
        $athlete->elo_rating = (int) ($data['elo_rating'] ?? $athlete->elo_rating ?? config('elo.start_rating'));
        $athlete->skill_level = $athlete->skill_level ?? 'pro';
        $athlete->save();

        AthleteDetail::firstOrCreate(['athlete_id' => $athlete->id]);

        return $athlete;
    }

    private function guessBrand(string $name): string
    {
        foreach (['Yonex', 'Victor', 'Li-Ning', 'Mizuno', 'Kawasaki'] as $brand) {
            if (stripos($name, $brand) !== false) {
                return $brand;
            }
        }

        return 'Khác';
    }

    private function ensureBrand(string $name, string $source): Brand
    {
        return Brand::firstOrCreate(
            ['name' => $name],
            ['slug' => Str::slug($name), 'source' => $source]
        );
    }

    private function log(string $target, string $source, int $found, int $upserted, string $status, ?string $message = null): void
    {
        CrawlLog::create([
            'target' => $target,
            'source' => $source,
            'items_found' => $found,
            'items_upserted' => $upserted,
            'status' => $status,
            'message' => $message,
        ]);
    }
}
