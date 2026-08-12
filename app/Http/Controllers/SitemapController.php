<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * The marketing pages only. Customer document links (/d/{token}) are never
 * listed and carry a noindex tag of their own.
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = [
            ['route' => 'home', 'priority' => '1.0', 'frequency' => 'daily'],
            ['route' => 'bulk-diesel', 'priority' => '0.9', 'frequency' => 'daily'],
            ['route' => 'truck-stop', 'priority' => '0.8', 'frequency' => 'weekly'],
            ['route' => 'quote', 'priority' => '0.8', 'frequency' => 'weekly'],
            ['route' => 'contact', 'priority' => '0.6', 'frequency' => 'monthly'],
        ];

        $xml = view('sitemap', ['pages' => $pages, 'lastModified' => now()->toAtomString()])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
