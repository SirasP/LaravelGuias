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
    | Correo restringido a unas pocas direcciones
    |--------------------------------------------------------------------------
    |
    | Sirve para probar en un sistema con gente real trabajando: mientras haya
    | direcciones en esta lista, el correo sale SÓLO hacia ellas. Las demás
    | personas siguen viendo sus avisos dentro del sistema, en Avisos, igual
    | que siempre; lo único que no reciben es el correo.
    |
    | Vacío —lo normal— significa que el correo va a todo el mundo. Es una
    | medida temporal: cuando terminen las pruebas hay que quitar la variable
    | del .env, o el resto del equipo se queda sin correos sin que nadie
    | recuerde por qué.
    |
    */
    'mail_only' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PURCHASE_REQUESTS_MAIL_ONLY', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Odoo
    |--------------------------------------------------------------------------
    |
    | Apagado por defecto, y a propósito: encenderlo escribe en un sistema que
    | la empresa usa de verdad. La exportación crea como mucho una RFQ en
    | borrador, nunca confirma, y siempre la dispara una persona.
    |
    | `picking_type_id` es la recepción a la que llegaría la mercadería. Odoo
    | lo exige y la solicitud no lo dice, así que se fija aquí.
    |
    */
    'odoo' => [
        'enabled' => (bool) env('PURCHASE_REQUESTS_ODOO', false),
        'url' => env('ODOO_URL'),
        'db' => env('ODOO_DB'),
        'user' => env('ODOO_USER'),
        'password' => env('ODOO_PASSWORD'),
        'timeout' => (int) env('PURCHASE_REQUESTS_ODOO_TIMEOUT', 30),
        'picking_type_id' => (int) env('PURCHASE_REQUESTS_ODOO_PICKING_TYPE', 1),
    ],

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
         * Un minuto por defecto: suficiente para que varios documentos subidos
         * seguidos aprovechen el modelo ya cargado, y lo bastante corto para
         * que la máquina no quede con varios gigas ocupados el resto del día.
         *
         * Volver a cargarlo cuesta unos cuatro segundos, y sólo lo paga la
         * primera lectura tras un silencio. Bajarlo más ahorra poca memoria y
         * hace que cada documento pague esa espera.
         *
         * En 0 el modelo queda cargado indefinidamente.
         */
        /*
         * Cuánto tiempo se espera a que el lector vuelva antes de dar un
         * documento por no leído. El modelo vive en una máquina que se duerme,
         * así que las ausencias son normales y no deberían costar el trabajo
         * de quien ya subió su cotización.
         */
        'wait_hours' => (int) env('PURCHASE_REQUESTS_READER_WAIT_HOURS', 12),

        'keep_loaded_minutes' => (int) env('PURCHASE_REQUESTS_READER_KEEP_LOADED', 1),

        // Cuántas páginas del PDF se miran. Una cotización rara vez pasa de 3.
        'max_pages' => (int) env('PURCHASE_REQUESTS_READER_MAX_PAGES', 3),

        // Rutas de las herramientas de PDF.
        'pdftotext' => env('PDFTOTEXT_PATH', 'pdftotext'),
        'pdftoppm' => env('PDFTOPPM_PATH', 'pdftoppm'),
    ],
];
