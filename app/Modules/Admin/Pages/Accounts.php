<?php

declare(strict_types=1);

namespace App\Modules\Admin\Pages;

use App\Modules\Admin\Support\Paginates;
use App\Modules\Identity\Application\OperatorDirectory;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Accounts (decision 74): search by address or `usr_` id. Addresses are
 * masked except the one the operator typed in full. Read-only.
 */
final class Accounts extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationIcon(): Heroicon
    {
        return Heroicon::OutlinedUsers;
    }

    public static function getNavigationSort(): int
    {
        return 1;
    }

    public function getTitle(): string
    {
        return 'Accounts';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, ?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage): LengthAwarePaginator {
                $result = app(OperatorDirectory::class)->search($search, $page, $recordsPerPage, $sortColumn, $sortDirection !== 'asc');

                return Paginates::page($result, $page, $recordsPerPage);
            })
            ->searchable()
            ->searchPlaceholder('Full address or usr_ id')
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('email')->label('Account')->description(fn (array $record): string => $record['id'])->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                IconColumn::make('verified')->boolean(),
                IconColumn::make('totp')->label('Authenticator')->boolean(),
                TextColumn::make('keys')->label('Passkeys / keys')->numeric(),
                TextColumn::make('providers')->label('OAuth')->placeholder('none'),
                TextColumn::make('claimed_guest_sessions')->label('Guest sessions claimed')->numeric(),
                IconColumn::make('operator')->boolean(),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
                TextColumn::make('last_sign_in_at')->label('Last sign-in')->dateTime()->placeholder('never')->sortable(),
            ])
            ->recordUrl(fn (array $record): string => AccountDetail::getUrl(['account' => $record['id']]));
    }
}
