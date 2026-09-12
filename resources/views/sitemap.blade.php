{{-- Khung XML cho Public\SitemapController.
     Dòng khai báo <?xml ... do CONTROLLER ghép vào trước (chuỗi có "?>" đặt trong Blade rất
     dễ làm hỏng khối PHP sau khi biên dịch).

     SỬA 12/9 — bám theo source khách gửi (education-main/public/sitemap.xml + src/seo.js):
       · <lastmod> giờ nhận cả Carbon lẫn chuỗi, và chỉ in khi thật sự có mốc thời gian
         (bản cũ dùng @isset nên giá trị null vẫn lọt qua và gọi ->toAtomString() trên null).
       · Định dạng W3C Datetime (toAtomString) đúng chuẩn sitemaps.org.
       · <changefreq>/<priority> chỉ in khi có — Google đã bỏ qua 2 thẻ này từ lâu nhưng Bing
         và Cốc Cốc vẫn đọc, nên giữ lại, chỉ không in thẻ rỗng. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if (! empty($url['lastmod']))
        <lastmod>{{ $url['lastmod'] instanceof \Illuminate\Support\Carbon ? $url['lastmod']->toAtomString() : \Illuminate\Support\Carbon::parse($url['lastmod'])->toAtomString() }}</lastmod>
@endif
@isset($url['changefreq'])
        <changefreq>{{ $url['changefreq'] }}</changefreq>
@endisset
@isset($url['priority'])
        <priority>{{ number_format($url['priority'], 1) }}</priority>
@endisset
    </url>
@endforeach
</urlset>
