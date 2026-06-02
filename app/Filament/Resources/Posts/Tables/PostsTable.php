<?php

namespace App\Filament\Resources\Posts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Foydalanuvchi: username, bo'lmasa first_name.
                TextColumn::make('user.username')
                    ->label('Foydalanuvchi')
                    ->getStateUsing(fn ($record) => $record->user?->username
                        ? '@'.$record->user->username
                        : $record->user?->first_name)
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('category')
                    ->label('Kategoriya')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'commerce' => '🛍️ Savdo',
                        'travel' => '✈️ Sayohat',
                        'education' => '🎓 Ta\'lim',
                        'food' => '🍽️ Food',
                        default => $state,
                    })
                    ->placeholder('—'),

                TextColumn::make('mood')
                    ->label('Kayfiyat')
                    ->placeholder('—'),

                // 2. Yuklangan rasm (kichik ko'rinish).
                ImageColumn::make('image_path')
                    ->label('Rasm')
                    ->disk('public')
                    ->height(48)
                    ->square(),

                // 3. Generatsiya qilingan matn (50 belgi bilan cheklangan).
                TextColumn::make('generated_text')
                    ->label('Matn')
                    ->limit(50)
                    ->tooltip(fn (?string $state) => $state) // to'liq matn tooltip'da
                    ->placeholder('— hali yaratilmagan —')
                    ->wrap(),

                // 4. Status (rangli badge).
                TextColumn::make('status')
                    ->label('Holat')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Kutilmoqda',
                        'generated' => 'Yaratilgan',
                        'published' => 'Joylangan',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',   // sariq
                        'generated' => 'info',     // ko'k
                        'published' => 'success',  // yashil
                        default => 'gray',
                    }),

                // 5. Yaratilgan vaqti.
                TextColumn::make('created_at')
                    ->label('Yaratilgan')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Holat')
                    ->options([
                        'pending' => 'Kutilmoqda',
                        'generated' => 'Yaratilgan',
                        'published' => 'Joylangan',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Read-only: bulk amallar yo'q.
            ]);
    }
}
