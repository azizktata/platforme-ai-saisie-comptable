<?php

namespace Database\Seeders;

use App\Models\AccountingEntry;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => env('DEMO_USER_EMAIL', 'demo@example.test')],
            [
                'name' => 'Alexandre Chen',
                'password' => env('DEMO_USER_PASSWORD', 'password'),
            ],
        );

        $samples = [
            [
                'original_filename' => 'facture-atelier-mistral.pdf',
                'supplier_name' => 'Atelier Mistral',
                'invoice_number' => 'AM-2026-0921',
                'days_ago' => 2,
                'account_code' => '606100',
                'description' => 'Fournitures d’atelier',
                'subtotal' => 840.00,
                'vat_amount' => 168.00,
                'total_amount' => 1008.00,
                'status' => Document::STATUS_POSTED,
            ],
            [
                'original_filename' => 'facture-studio-lune.pdf',
                'supplier_name' => 'Studio Lune',
                'invoice_number' => 'SL-2026-0118',
                'days_ago' => 5,
                'account_code' => '622600',
                'description' => 'Honoraires de création',
                'subtotal' => 1200.00,
                'vat_amount' => 240.00,
                'total_amount' => 1440.00,
                'status' => Document::STATUS_POSTED,
            ],
            [
                'original_filename' => 'facture-transports-nord.pdf',
                'supplier_name' => 'Transports du Nord',
                'invoice_number' => 'TN-2026-0914',
                'days_ago' => 14,
                'account_code' => '624100',
                'description' => 'Transport de marchandises',
                'subtotal' => 280.00,
                'vat_amount' => 56.00,
                'total_amount' => 336.00,
                'status' => Document::STATUS_POSTED,
            ],
            [
                'original_filename' => 'facture-epicerie-moderne.pdf',
                'supplier_name' => 'L’Épicerie Moderne',
                'invoice_number' => 'LEM-2026-0842',
                'days_ago' => 1,
                'account_code' => null,
                'description' => 'Achats de fournitures',
                'subtotal' => 320.42,
                'vat_amount' => 64.08,
                'total_amount' => 384.50,
                'status' => Document::STATUS_NEEDS_REVIEW,
            ],
            [
                'original_filename' => 'facture-bureau-clair.pdf',
                'supplier_name' => 'Bureau Clair',
                'invoice_number' => 'BC-2026-0730',
                'days_ago' => 3,
                'account_code' => null,
                'description' => 'Fournitures de bureau',
                'subtotal' => 128.00,
                'vat_amount' => 25.60,
                'total_amount' => 153.60,
                'status' => Document::STATUS_NEEDS_REVIEW,
            ],
            [
                'original_filename' => 'facture-cafe-des-arts.pdf',
                'supplier_name' => 'Café des Arts',
                'invoice_number' => 'CDA-2026-0591',
                'days_ago' => 6,
                'account_code' => null,
                'description' => 'Frais de réception',
                'subtotal' => 96.00,
                'vat_amount' => 19.20,
                'total_amount' => 115.20,
                'status' => Document::STATUS_NEEDS_REVIEW,
            ],
            [
                'original_filename' => 'facture-nuage-numerique.pdf',
                'supplier_name' => 'Nuage Numérique',
                'invoice_number' => 'NN-2026-3104',
                'days_ago' => 8,
                'account_code' => null,
                'description' => 'Abonnement logiciel',
                'subtotal' => 49.00,
                'vat_amount' => 9.80,
                'total_amount' => 58.80,
                'status' => Document::STATUS_NEEDS_REVIEW,
            ],
            [
                'original_filename' => 'facture-maison-des-plantes.pdf',
                'supplier_name' => 'Maison des Plantes',
                'invoice_number' => 'MDP-2026-1770',
                'days_ago' => 11,
                'account_code' => null,
                'description' => 'Entretien des locaux',
                'subtotal' => 215.00,
                'vat_amount' => 43.00,
                'total_amount' => 258.00,
                'status' => Document::STATUS_NEEDS_REVIEW,
            ],
        ];

        foreach ($samples as $sample) {
            $invoiceDate = Carbon::today()->subDays($sample['days_ago']);
            $document = Document::updateOrCreate(
                ['user_id' => $user->id, 'invoice_number' => $sample['invoice_number']],
                [
                    'user_id' => $user->id,
                    'original_filename' => $sample['original_filename'],
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 128_000,
                    'supplier_name' => $sample['supplier_name'],
                    'invoice_date' => $invoiceDate,
                    'due_date' => $invoiceDate->copy()->addDays(30),
                    'currency' => 'EUR',
                    'account_code' => $sample['account_code'],
                    'description' => $sample['description'],
                    'subtotal' => $sample['subtotal'],
                    'vat_amount' => $sample['vat_amount'],
                    'total_amount' => $sample['total_amount'],
                    'status' => $sample['status'],
                ],
            );

            if ($document->status === Document::STATUS_POSTED) {
                AccountingEntry::updateOrCreate(
                    ['document_id' => $document->id],
                    [
                        'entry_date' => $document->invoice_date,
                        'description' => $document->description,
                        'supplier_name' => $document->supplier_name,
                        'invoice_number' => $document->invoice_number,
                        'account_code' => $document->account_code,
                        'subtotal' => $document->subtotal,
                        'vat_amount' => $document->vat_amount,
                        'total_amount' => $document->total_amount,
                        'currency' => $document->currency,
                    ],
                );
            }
        }
    }
}
