<?php

namespace App\Services;

/**
 * Dịch thuật tên sản phẩm/thương hiệu Anh ↔ Trung cho dữ liệu crawl từ
 * badmintoncn.com (dữ liệu của nguồn này cần được dịch từ tiếng Anh sang
 * tiếng Trung khi đồng bộ).
 *
 * Cơ chế 2 lớp:
 *  1) Bảng thuật ngữ (glossary) tích hợp sẵn — các cụm từ cầu lông phổ biến.
 *  2) Adapter dịch ngoài (tùy chọn): GOOGLE_TRANSLATE_API_KEY trong .env
 *     sẽ dùng Google Cloud Translation v2; không có key thì fallback về glossary.
 */
class TranslationService
{
    private array $glossary = [
        // Thương hiệu
        'Yonex' => '尤尼克斯',
        'Victor' => '胜利',
        'Li-Ning' => '李宁',
        'Mizuno' => '美津浓',
        ' Kawasaki' => '川崎',
        // Loại sản phẩm
        'racket' => '球拍',
        'racquet' => '球拍',
        'shoes' => '羽毛球鞋',
        'shoe' => '羽毛球鞋',
        'strings' => '羽毛球线',
        'string' => '羽毛球线',
        'grip' => '手胶',
        'bag' => '球包',
        'apparel' => '服装',
        // Thuật ngữ kỹ thuật
        'head heavy' => '进攻型',
        'head light' => '防守型',
        'even balance' => '平衡型',
        'stiff' => '硬',
        'extra stiff' => '超硬',
        'flexible' => '弹性',
        'medium flex' => '适中',
        'power cushion' => '动力垫',
        'aero' => '风速',
        'turbo charging' => '蓄力',
        'nanoscience' => '纳米科技',
        'carbon' => '碳纤维',
        'graphene' => '石墨烯',
        'professional' => '专业',
        'training' => '训练',
        'original' => '正品',
        'limited edition' => '限量版',
        'new' => '新款',
        'smash' => '扣杀',
        'control' => '控制',
        'speed' => '速度',
        'endurance' => '耐力',
    ];

    /** Dịch tiếng Anh → tiếng Trung. */
    public function enToZh(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }

        if (config('services.translate.api_key')) {
            $translated = $this->viaGoogle($text, 'zh-CN');
            if ($translated !== null) {
                return $translated;
            }
        }

        $result = str_ireplace(array_keys($this->glossary), array_values($this->glossary), $text);

        // Các từ còn lại (tên model) giữ nguyên — tên mã sản phẩm thường không dịch.
        return $result;
    }

    private function viaGoogle(string $text, string $target): ?string
    {
        try {
            $response = \Http::get('https://translation.googleapis.com/language/translate/v2', [
                'key' => config('services.translate.api_key'),
                'q' => $text,
                'target' => $target,
                'source' => 'en',
                'format' => 'text',
            ]);
            if ($response->successful()) {
                return $response->json('data.translations.0.translatedText');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
