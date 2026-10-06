<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds schema.org JSON-LD arrays for the SEO landing pages. Every builder
 * takes the same data the page renders visibly, so the structured data can't
 * drift from what users actually see.
 */
final class SeoSchema
{
    public static function softwareApplication(string $description, string $path): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => config('seo_pages.brand'),
            'applicationCategory' => 'MultimediaApplication',
            'operatingSystem' => 'Web browser',
            'url' => base_url($path),
            'description' => $description,
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'INR',
                'description' => 'One free karaoke video credit on signup; additional credits are paid.',
            ],
        ];
    }

    /**
     * @param array<int, array{name: string, path: string}> $items
     */
    public static function breadcrumbs(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => base_url($item['path']),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param array<int, array{q: string, a: string}> $faqs answers may contain inline HTML
     */
    public static function faq(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $faq): array => [
                '@type' => 'Question',
                'name' => self::plainText($faq['q']),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => self::plainText($faq['a'])],
            ], $faqs),
        ];
    }

    /**
     * @param array<int, array{title: string, text: string}> $steps
     */
    public static function howTo(string $name, string $description, string $path, array $steps): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $name,
            'description' => $description,
            'step' => array_map(static fn (array $step, int $index): array => [
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'name' => self::plainText($step['title']),
                'text' => self::plainText($step['text']),
                'url' => base_url($path) . '#step-' . ($index + 1),
            ], $steps, array_keys($steps)),
        ];
    }

    /**
     * JSON-LD <script> tag. JSON_HEX_TAG keeps any "</script>" inside the
     * data from closing the tag early.
     */
    public static function script(array $schema): string
    {
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    private static function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
