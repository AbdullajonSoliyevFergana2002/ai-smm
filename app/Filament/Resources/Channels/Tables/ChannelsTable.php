<?php

namespace App\Filament\Resources\Channels\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('channel_name')
                    ->label('Kanal nomi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('telegram_channel_id')
                    ->label('Telegram kanal ID')
                    ->searchable()
                    ->copyable(),

                // User relationship orqali egasining ismi.
                TextColumn::make('user.first_name')
                    ->label('Foydalanuvchi')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Qo\'shilgan')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Read-only: bulk amallar yo'q.
            ]);
    }
}
