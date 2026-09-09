<?php

namespace App\Filament\Resources\Leads;

use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Schemas\LeadForm;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Caixa de entrada dos pedidos do site. As leads nascem dos formulários
 * públicos — não se criam aqui; consultam-se, marcam-se como tratadas
 * (futuro) e podem apagar-se pedidos de spam.
 */
class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $modelLabel = 'dúvida';

    protected static ?string $pluralModelLabel = 'Dúvidas dos clientes';

    // Sem isto o Filament capitaliza cada palavra e sai "Dúvidas Dos Clientes".
    protected static ?string $navigationLabel = 'Dúvidas dos clientes';

    protected static ?int $navigationSort = 2;

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * O número ao lado do menu é o que falta fazer: pedidos por responder.
     *
     * Contava os últimos sete dias, respondidos ou não — ficava lá um número
     * mesmo com a caixa toda tratada, e ninguém sabia o que ele queria dizer.
     * Sem nada por responder não aparece número nenhum.
     */
    public static function getNavigationBadge(): ?string
    {
        $porResponder = Lead::query()->whereNull('replied_at')->count();

        return $porResponder > 0 ? (string) $porResponder : null;
    }

    /** Âmbar enquanto houver pedidos à espera: é um aviso, não um total. */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return LeadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'edit' => EditLead::route('/{record}'),
        ];
    }
}
