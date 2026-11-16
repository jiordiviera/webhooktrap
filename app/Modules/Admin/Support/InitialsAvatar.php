<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament's default avatar loads from ui-avatars.com with the user's name
 * in the URL. The panel draws its own: an inline SVG, nothing leaves the page.
 */
final class InitialsAvatar implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);
        $initial = htmlspecialchars(mb_strtoupper(mb_substr(ltrim($name), 0, 1)) ?: '?', ENT_XML1);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#1f2937"/>'
            .'<text x="32" y="42" font-family="sans-serif" font-size="30" text-anchor="middle" fill="#fff">'.$initial.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
