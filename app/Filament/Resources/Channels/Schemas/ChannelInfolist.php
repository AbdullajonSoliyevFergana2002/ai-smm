<?php

namespace App\Filament\Resources\Channels\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ChannelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('channel_name')
                    ->label('Kanal nomi'),

                TextEntry::make('telegram_channel_id')
                    ->label('Telegram kanal ID')
                    ->copyable(),

                TextEntry::make('user.first_name')
                    ->label('Foydalanuvchi')
                    ->placeholder('—'),

                TextEntry::make('created_at')
                    ->label('Qo\'shilgan')
                    ->dateTime('d.m.Y H:i'),
            ]);
    }
}
