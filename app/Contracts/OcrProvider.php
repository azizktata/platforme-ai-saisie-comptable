<?php

namespace App\Contracts;

use App\Data\InvoiceOcrResult;
use App\Models\Invoice;

interface OcrProvider
{
    public function extract(Invoice $invoice): InvoiceOcrResult;
}
