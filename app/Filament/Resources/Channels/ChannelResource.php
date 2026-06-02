<?php

namespace App\Filament\Resources\Channels;

use App\Filament\Resources\Channels\Pages\ListChannels;
use App\Filament\Resources\Channels\Pages\ViewChannel;
use App\Filament\Resources\Channels\Schemas\ChannelInfolist;
use App\Filament\Resources\Channels\Tables\ChannelsTable;
use App\Models\Channel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $modelLabel = 'Kanal';

    protected static ?string $pluralModelLabel = 'Kanallar';

    public static function infolist(Schema $schema): Schema
    {
        return ChannelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChannelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    // --- Read-only (monitoring) rejimi ---

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannels::route('/'),
            'view' => ViewChannel::route('/{record}'),
        ];
    }
}
