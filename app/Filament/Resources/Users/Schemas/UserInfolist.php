<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('telegram_id')
                    ->label('Telegram ID')
                    ->copyable(),

                TextEntry::make('username')
                    ->label('Username')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => $state ? '@'.$state : null),

                TextEntry::make('first_name')
                    ->label('Ism')
                    ->placeholder('—'),

                TextEntry::make('created_at')
                    ->label('Ro\'yxatdan o\'tgan')
                    ->dateTime('d.m.Y H:i'),
            ]);
    }
}
