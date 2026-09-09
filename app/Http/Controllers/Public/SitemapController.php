<?php

namespace App\Http\Controllers\Public;

use App\Enums\CompetitionStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Course;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    private const CACHE_MINUTES = 60;

    public function index(): Response
    {
        $urls = [];

        foreach ([
            ['home', 1.0, 'daily'],
            ['courses.index', 0.9, 'daily'],
            ['practice.index', 0.9, 'daily'],
            ['materials.index', 0.8, 'daily'],
            ['competitions.index', 0.7, 'daily'],
            ['leaderboard.index', 0.6, 'daily'],
            ['teachers.index', 0.6, 'weekly'],
            ['info.index', 0.4, 'monthly'],
        ] as [$name, $priority, $freq]) {
            $urls[] = ['loc' => route($name), 'priority' => $priority, 'changefreq' => $freq];
        }

        foreach (['bao-mat', 'dieu-khoan', 'hoan-tien'] as $slug) {
            $urls[] = ['loc' => route('info.policies.show', $slug), 'priority' => 0.2, 'changefreq' => 'yearly'];
        }

        Course::query()->where('status', 'published')
            ->select('id', 'updated_at')->orderByDesc('updated_at')->limit(2000)
            ->each(function (Course $c) use (&$urls) {
                $urls[] = ['loc' => route('courses.show', $c->id), 'lastmod' => $c->updated_at, 'priority' => 0.8, 'changefreq' => 'weekly'];
            });

        Product::query()->where('status', 'published')->where('visibility', 'public')
            ->where('type', '!=', ProductType::Course->value)
            ->select('id', 'updated_at')->orderByDesc('updated_at')->limit(2000)
            ->each(function (Product $p) use (&$urls) {
                $urls[] = ['loc' => route('materials.show', $p->id), 'lastmod' => $p->updated_at, 'priority' => 0.7, 'changefreq' => 'weekly'];
            });

        Competition::query()->where('status', '!=', CompetitionStatus::Archived->value)
            ->select('id', 'updated_at')->orderByDesc('updated_at')->limit(1000)
            ->each(function (Competition $c) use (&$urls) {
                $urls[] = ['loc' => route('competitions.show', $c->id), 'lastmod' => $c->updated_at, 'priority' => 0.6, 'changefreq' => 'weekly'];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(self::CACHE_MINUTES * 60),
        ]);
    }
}
