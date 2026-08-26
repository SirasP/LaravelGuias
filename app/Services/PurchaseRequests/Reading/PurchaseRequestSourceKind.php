<?php

namespace App\Services\PurchaseRequests\Reading;

/** Cómo se leyó el documento. Queda registrado para poder auditarlo. */
final class PurchaseRequestSourceKind
{
    public const PDF_TEXT = 'pdf_text';

    public const PDF_SCAN = 'pdf_scan';

    public const IMAGE = 'image';
}
