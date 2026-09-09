{{-- SỬA 9/9 (9) — khung XML cho Public\SitemapController. Dòng khai báo <?xml ... do
     CONTROLLER ghép vào trước (chuỗi có "?>" đặt trong Blade rất dễ làm hỏng khối PHP biên dịch). --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@isset($url['lastmod'])
        <lastmod>{{ $url['lastmod']->toAtomString() }}</lastmod>
@endisset
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ number_format($url['priority'], 1) }}</priority>
    </url>
@endforeach
</urlset>
