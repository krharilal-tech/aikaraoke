<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Setting;
use App\Services\SeoSchema;

/**
 * Public SEO landing pages, one per search intent. Metadata lives in
 * Config/seo_pages.php; page copy, FAQs and their FAQPage/HowTo/Breadcrumb
 * JSON-LD live together in Views/seo/*.php so the schema always matches the
 * visible content.
 */
final class SeoPageController extends Controller
{
    public function aiKaraokeMaker(Request $request): void
    {
        $this->render('ai-karaoke-maker');
    }

    public function youtubeToKaraoke(Request $request): void
    {
        $this->render('youtube-to-karaoke');
    }

    public function karaokeVideoMaker(Request $request): void
    {
        $this->render('karaoke-video-maker');
    }

    public function karaokeGenerator(Request $request): void
    {
        $this->render('karaoke-generator');
    }

    public function aiVocalRemover(Request $request): void
    {
        $this->render('ai-vocal-remover');
    }

    private function render(string $slug): void
    {
        $page = config('seo_pages.pages.' . $slug);

        $this->view('seo/' . $slug, [
            'pageTitle' => $page['name'],
            'metaTitle' => $page['title'],
            'metaDescription' => $page['description'],
            'canonicalPath' => $page['path'],
            'structuredData' => [SeoSchema::softwareApplication($page['description'], $page['path'])],
            'page' => $page,
            'slug' => $slug,
            'maxDurationMinutes' => intdiv((int) Setting::get('max_video_length_seconds', '600'), 60),
        ]);
    }
}
