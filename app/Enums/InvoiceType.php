<?php

namespace App\Enums;

enum InvoiceType: string
{
    case BankFees = 'bank_fees';
    case GeneralExpenses = 'general_expenses';
    case Water = 'water';
    case Electricity = 'electricity';
    case Supplies = 'supplies';
    case ProfessionalFees = 'professional_fees';
    case FixedAsset = 'fixed_asset';
    case ItInvoice = 'it_invoice';
    case Rent = 'rent';
    case Software = 'software';
    case Maintenance = 'maintenance';
    case Goods = 'goods';
    case RawMaterials = 'raw_materials';
    case Advertising = 'advertising';
    case Subcontracting = 'subcontracting';
    case Telecommunications = 'telecommunications';
    case Transport = 'transport';

    public function label(): string
    {
        return match ($this) {
            self::BankFees => 'Frais bancaires',
            self::GeneralExpenses => 'Frais généraux divers',
            self::Water => 'Eau',
            self::Electricity => 'Électricité',
            self::Supplies => 'Fournitures',
            self::ProfessionalFees => 'Honoraires',
            self::FixedAsset => 'Immobilisation',
            self::ItInvoice => 'Facture informatique',
            self::Rent => 'Location',
            self::Software => 'Logiciels',
            self::Maintenance => 'Maintenance',
            self::Goods => 'Achat de marchandises',
            self::RawMaterials => 'Achat de matières premières',
            self::Advertising => 'Publicité',
            self::Subcontracting => 'Sous-traitance / Prestations',
            self::Telecommunications => 'Télécommunications',
            self::Transport => 'Transport',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::cases());
    }
}
