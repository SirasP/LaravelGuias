<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Envío de correo
    |--------------------------------------------------------------------------
    |
    | Controla si los avisos del módulo salen además por correo. Los avisos
    | dentro del sistema funcionan siempre, con esto encendido o apagado.
    |
    */
    'mail_enabled' => (bool) env('PURCHASE_REQUESTS_MAIL', true),

    /*
    |--------------------------------------------------------------------------
    | Empresa compradora
    |--------------------------------------------------------------------------
    |
    | Su RUT permite reconocer, en una cotización, cuál de los RUT del
    | documento es el del cliente y cuál el del proveedor; y avisar si llega
    | una cotización dirigida a otra empresa.
    |
    */
    'company' => [
        'code' => env('PURCHASE_REQUESTS_COMPANY_CODE', 'EHE'),
        'name' => env('PURCHASE_REQUESTS_COMPANY_NAME', 'Agrícola EHE SpA'),
        'tax_id' => env('PURCHASE_REQUESTS_COMPANY_RUT', '77.415.879-0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Asistente de lectura de cotizaciones
    |--------------------------------------------------------------------------
    |
    | Lee un PDF o una foto de cotización y propone las partidas. Apagado por
    | defecto: encenderlo requiere un modelo disponible en `base_url`.
    |
    | Todo lo que produce es un borrador para que una persona lo confirme. El
    | asistente nunca envía una solicitud a revisión por su cuenta.
    |
    */
    'reader' => [
        'enabled' => (bool) env('PURCHASE_REQUESTS_READER', false),

        /*
         * Cualquier servidor que hable el protocolo de OpenAI. Sirven todos
         * sin cambiar una línea de código, sólo estas dos variables:
         *
         *   LM Studio local   http://localhost:1234/v1
         *   Ollama local      http://localhost:11434/v1
         *   Google Gemini     https://generativelanguage.googleapis.com/v1beta/openai
         *   Groq              https://api.groq.com/openai/v1
         *
         * Con un proveedor externo, los documentos de compra salen de la red
         * de la empresa. Es una decisión a tomar a conciencia, no un detalle
         * de configuración.
         */
        'base_url' => rtrim((string) env('PURCHASE_REQUESTS_READER_URL', 'http://localhost:1234/v1'), '/'),
        'api_key' => env('PURCHASE_REQUESTS_READER_KEY', 'lm-studio'),

        // Modelo con visión, para fotos y PDF escaneados.
        'vision_model' => env('PURCHASE_REQUESTS_READER_VISION_MODEL', 'qwen2.5-vl-3b-instruct'),
        // Modelo de texto, para PDF que ya traen su texto. Si se deja vacío,
        // se usa el de visión también para texto.
        'text_model' => env('PURCHASE_REQUESTS_READER_TEXT_MODEL', ''),

        // Un modelo pequeño en CPU puede tardar bastante; el trabajo corre en
        // segundo plano, así que la espera no la sufre nadie frente a la pantalla.
        'timeout' => (int) env('PURCHASE_REQUESTS_READER_TIMEOUT', 300),

        // Límite para el asistente de texto libre, que corre dentro de la
        // petición. Corto a propósito: ahí la persona está esperando frente a
        // la pantalla. Con un modelo lento hay que subirlo, asumiendo la espera.
        'draft_timeout' => (int) env('PURCHASE_REQUESTS_READER_DRAFT_TIMEOUT', 45),

        /*
         * Minutos que el modelo queda en memoria tras la última lectura.
         *
         * LM Studio y compatibles aceptan este dato en cada petición: cargan
         * el modelo al recibirla y lo sueltan pasado este tiempo sin uso. Así
         * la máquina que lo hospeda no carga con varios gigas ocupados todo el
         * día por un asistente que se usa unas pocas veces.
         *
         * Cargar de nuevo cuesta unos segundos; sólo lo paga la primera
         * lectura después de un rato de silencio. En 0 se desactiva y el
         * modelo queda cargado indefinidamente.
         */
        'keep_loaded_minutes' => (int) env('PURCHASE_REQUESTS_READER_KEEP_LOADED', 10),

        // Cuántas páginas del PDF se miran. Una cotización rara vez pasa de 3.
        'max_pages' => (int) env('PURCHASE_REQUESTS_READER_MAX_PAGES', 3),

        // Rutas de las herramientas de PDF.
        'pdftotext' => env('PDFTOTEXT_PATH', 'pdftotext'),
        'pdftoppm' => env('PDFTOPPM_PATH', 'pdftoppm'),
    ],
];
