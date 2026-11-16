<?php

declare(strict_types=1);

namespace App\Modules\Admin\Pages;

use App\Modules\Agent\Application\OperatorFleet;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Server runner tokens (name, last 4, allowed networks, last poll) and the
 * agent and runner versions in use (decision 74). Read-only: tokens are
 * issued and revoked with `php artisan runner:token` only.
 */
final class Runners extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): Heroicon
    {
        return Heroicon::OutlinedServerStack;
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }

    public function getTitle(): string
    {
        return 'Runners and versions';
    }

    public function content(Schema $schema): Schema
    {
        $versions = app(OperatorFleet::class)->versions();

        return $schema->components([
            EmbeddedTable::make(),
            Section::make('Versions in use')
                ->description('Live agents and live runner tokens.')
                ->columns(4)
                ->schema($versions === []
                    ? [TextEntry::make('none')->hiddenLabel()->state('No live agent or runner.')]
                    : array_map(
                        fn (array $v, int $i): TextEntry => TextEntry::make("version_{$i}")
                            ->label(ucfirst($v['kind']).' '.$v['version'])
                            ->state($v['count']),
                        $versions,
                        array_keys($versions),
                    )),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Server runner tokens')
            ->records(fn (): array => collect(app(OperatorFleet::class)->runners())->keyBy('id')->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->description(fn (array $record): string => $record['id']),
                TextColumn::make('last4')->label('Last 4')->fontFamily('mono'),
                TextColumn::make('cidrs')->label('Allowed networks')->state(fn (array $record): string => implode(', ', $record['cidrs']))->placeholder('any'),
                IconColumn::make('binding_required')->label('Binding')->boolean(),
                TextColumn::make('version')->placeholder('unknown'),
                TextColumn::make('last_seen_at')->label('Last poll')->since()->placeholder('never'),
                TextColumn::make('issued_at')->label('Issued')->dateTime(),
                IconColumn::make('revoked')->boolean()->trueColor('danger')->falseColor('gray'),
            ]);
    }
}
