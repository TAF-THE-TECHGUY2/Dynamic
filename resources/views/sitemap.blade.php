<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($pages as $page)
    <url>
        <loc>{{ route($page['route']) }}</loc>
        <lastmod>{{ $lastModified }}</lastmod>
        <changefreq>{{ $page['frequency'] }}</changefreq>
        <priority>{{ $page['priority'] }}</priority>
    </url>
@endforeach
</urlset>
