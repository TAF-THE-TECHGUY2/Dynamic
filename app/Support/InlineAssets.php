<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Inlines the compiled stylesheet for PDF rendering.
 *
 * Browsershot loads the document from a temporary file, so a @vite tag pointing
 * at http://the-app/build/assets/app-xxx.css is simply not fetched and the PDF
 * comes out as unstyled HTML — the single most confusing way this stack fails.
 * The fonts have the same problem, so they are embedded as data URIs.
 *
 * The browser print route keeps using @vite; only the PDF path pays for this.
 */
final class InlineAssets
{
    private static ?string $cached = null;

    /**
     * The full stylesheet, fonts and all, ready to drop into a <style> tag.
     */
    public static function forPdf(): string
    {
        return self::$cached ??= self::embedFonts(self::compiledCss());
    }

    public static function isAvailable(): bool
    {
        return file_exists(public_path('build/manifest.json'))
            || file_exists(public_path('build/.vite/manifest.json'));
    }

    private static function compiledCss(): string
    {
        $manifestPath = collect([
            public_path('build/manifest.json'),
            public_path('build/.vite/manifest.json'),
        ])->first(fn (string $path) => file_exists($path));

        if ($manifestPath === null) {
            throw new RuntimeException(
                'No Vite manifest found. Run `npm run build` before rendering a PDF.'
            );
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $entry = $manifest['resources/css/app.css']['file'] ?? null;

        if ($entry === null) {
            throw new RuntimeException('resources/css/app.css is missing from the Vite manifest.');
        }

        return (string) file_get_contents(public_path('build/'.$entry));
    }

    /**
     * Swap every /fonts/*.woff2 reference for the font itself.
     *
     * Chromium will not reach out to the app for them from a file:// page, and a
     * document rendered in Times New Roman is not the document that was approved
     * on screen.
     */
    private static function embedFonts(string $css): string
    {
        return (string) preg_replace_callback(
            "~url\(['\"]?/fonts/([A-Za-z0-9._-]+\.woff2)['\"]?\)~",
            function (array $matches): string {
                $path = public_path('fonts/'.$matches[1]);

                if (! file_exists($path)) {
                    return $matches[0];
                }

                return sprintf(
                    "url('data:font/woff2;base64,%s')",
                    base64_encode((string) file_get_contents($path)),
                );
            },
            $css,
        );
    }
}
