<?php

namespace App\Data\Invoice;

use App\Data\Data;

class InvoiceCustomFieldData extends Data
{
    public function __construct(
        public int $id,
        public mixed $value = null,
    ) {
    }
}
