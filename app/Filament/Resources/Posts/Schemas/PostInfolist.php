<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Kattalashtirilgan rasm.
                ImageEntry::make('image_path')
                    ->label('Rasm')
                    ->disk('public')
                    ->height(320),

                TextEntry::make('user.username')
                    ->label('Foydalanuvchi')
                    ->getStateUsing(fn ($record) => $record->user?->username
                        ? '@'.$record->user->username
                        : $record->user?->first_name)
                    ->placeholder('—'),

                TextEntry::make('category')
                    ->label('Kategoriya')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'commerce' => '🛍️ Savdo / Bozor',
                        'travel' => '✈️ Sayohat / Blog',
                        'education' => '🎓 Kurslar / Ta\'lim',
                        'food' => '🍽️ Kafe / Food',
                        default => $state,
                    })
                    ->placeholder('—'),

                TextEntry::make('mood')
                    ->label('Kayfiyat')
                    ->placeholder('—'),

                TextEntry::make('status')
                    ->label('Holat')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Kutilmoqda',
                        'generated' => 'Yaratilgan',
                        'published' => 'Joylangan',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'generated' => 'info',
                        'published' => 'success',
                        default => 'gray',
                    }),

                // AI yozgan to'liq matn.
                TextEntry::make('generated_text')
                    ->label('AI matni')
                    ->placeholder('— hali yaratilmagan —')
                    ->prose()
                    ->copyable()
                    ->columnSpanFull(),

                TextEntry::make('created_at')
                    ->label('Yaratilgan')
                    ->dateTime('d.m.Y H:i'),
            ]);
    }
}
