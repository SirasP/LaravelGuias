<?php

namespace App\Services\PurchaseRequests\Reading;

/** Cómo se leyó el documento. Queda registrado para poder auditarlo. */
final class PurchaseRequestSourceKind
{
    public const PDF_TEXT = 'pdf_text';

    public const PDF_SCAN = 'pdf_scan';

    public const IMAGE = 'image';

    /**
     * No se leyó ningún documento: lo escribió una persona.
     *
     * Pasa cuando la compra ya está hecha y la factura en la mano: obligar a
     * escanear un papel para anotar cuatro precios que ya se conocen es
     * trabajo inventado.
     */
    public const TEXT = 'text';
}
