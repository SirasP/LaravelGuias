<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * «Cuando RODASERVIC escribe esto, se refiere a este producto.»
 *
 * Lo que guarda no es una coincidencia calculada, sino una decisión humana.
 * Por eso se consulta como un diccionario —o está o no está— y no admite
 * grados: el parecido sirve para proponer, nunca para concluir.
 */
class PurchaseProductLink extends Model
{
    /** Lo dijo una persona mirando la pantalla. */
    public const CONFIRMADO = 'confirmed';

    /** Heredado del diccionario del módulo de facturas. Sin proveedor. */
    public const HEREDADO = 'inherited';

    /** El texto es una pieza de la partida, no la partida: el canastillo de la válvula de pie. */
    public const PIEZA = 'pieza';

    protected $fillable = [
        'company_code', 'odoo_partner_id', 'partner_name',
        'source_text', 'normalized_text',
        'odoo_product_id', 'odoo_product_name', 'canonical_text', 'fuelcontrol_product_id',
        'source', 'confirmed_by', 'confirmed_by_name', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'odoo_partner_id' => 'integer',
            'odoo_product_id' => 'integer',
            'fuelcontrol_product_id' => 'integer',
            'confirmed_by' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Busca la equivalencia de un texto, prefiriendo la de su proveedor.
     *
     * El orden importa: «jabón mano» de Unimarc puede no ser lo mismo que
     * «jabón mano» de otro proveedor. Sólo si no hay una específica se usa la
     * general, que vale menos porque nadie afirmó de quién venía.
     */
    public static function para(string $texto, ?int $odooPartnerId): ?self
    {
        $normalizado = self::normalizar($texto);

        if ($normalizado === '') {
            return null;
        }

        $clave = $odooPartnerId.'|'.$normalizado;

        if (static::$memoria !== null && array_key_exists($clave, static::$memoria)) {
            return static::$memoria[$clave];
        }

        $encontrada = static::buscar($normalizado, $odooPartnerId);

        if (static::$memoria !== null) {
            static::$memoria[$clave] = $encontrada;
        }

        return $encontrada;
    }

    /** @var array<string, ?self>|null Sólo mientras dura una comparación. */
    private static ?array $memoria = null;

    /**
     * Recuerda las equivalencias mientras dura `$trabajo`.
     *
     * Comparar una cotización pregunta por el mismo texto una vez por cada
     * renglón contra cada partida: catorce mil consultas en las de S&M, para
     * unas cien palabras distintas. Fuera de la comparación no se recuerda
     * nada, así que una equivalencia recién enseñada se ve en la siguiente.
     *
     * @template T
     *
     * @param  callable(): T  $trabajo
     * @return T
     */
    public static function conMemoria(callable $trabajo): mixed
    {
        $previa = static::$memoria;
        static::$memoria ??= [];

        try {
            return $trabajo();
        } finally {
            static::$memoria = $previa;
        }
    }

    /**
     * La partida de la que este texto es una pieza, según lo que alguien enseñó.
     *
     * Cooprinsem cotiza la válvula de pie en dos renglones; el canastillo no es
     * otra partida ni un sobrante, es parte de ésa. Se guarda aparte de los
     * alias normales porque no dice «es lo mismo»: dice «va dentro de».
     *
     * @return string|null el texto normalizado de la partida
     */
    public static function piezaDe(string $texto, ?int $odooPartnerId): ?string
    {
        $normalizado = self::normalizar($texto);

        if ($normalizado === '') {
            return null;
        }

        $base = static::query()->where('company_code', 'EHE')->where('normalized_text', $normalizado)->where('source', self::PIEZA);

        if ($odooPartnerId !== null && ($delProveedor = (clone $base)->where('odoo_partner_id', $odooPartnerId)->value('canonical_text')) !== null) {
            return $delProveedor;
        }

        return (clone $base)->whereNull('odoo_partner_id')->value('canonical_text');
    }

    private static function buscar(string $normalizado, ?int $odooPartnerId): ?self
    {
        // Una pieza no es un sinónimo: el canastillo no es la válvula de pie.
        $base = static::query()
            ->where('company_code', 'EHE')
            ->where('normalized_text', $normalizado)
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', self::PIEZA));

        if ($odooPartnerId !== null) {
            $delProveedor = (clone $base)->where('odoo_partner_id', $odooPartnerId)->first();

            if ($delProveedor !== null) {
                return $delProveedor;
            }
        }

        return (clone $base)->whereNull('odoo_partner_id')->first();
    }

    /**
     * El texto reducido a lo que lo hace comparable.
     *
     * Misma idea que `normalizeForSimilarity` del módulo de facturas: sin
     * tildes, sin mayúsculas, sin puntuación y sin espacios de más. Deliberada
     * copia y no préstamo: aquel módulo escribe en fuelcontrol y no se toca.
     *
     * Las fracciones de un carácter se escriben antes con números: `ascii()`
     * las convierte en un espacio, y «pernos anclaje ⅜/3"» y «pernos anclaje
     * ½"/3"» quedaban los dos en «pernos anclaje 3», el mismo perno para lo
     * aprendido.
     */
    public static function normalizar(string $texto): string
    {
        $texto = strtr($texto, [
            '¼' => ' 1/4 ', '½' => ' 1/2 ', '¾' => ' 3/4 ', '⅛' => ' 1/8 ',
            '⅜' => ' 3/8 ', '⅝' => ' 5/8 ', '⅞' => ' 7/8 ', '⅓' => ' 1/3 ', '⅔' => ' 2/3 ',
        ]);
        $limpio = \Illuminate\Support\Str::of($texto)->ascii()->lower()->value();
        $limpio = preg_replace('/[^a-z0-9]+/', ' ', $limpio) ?? '';

        return trim(preg_replace('/\s+/', ' ', $limpio) ?? '');
    }

    /**
     * ¿Estos dos textos significan lo mismo, porque alguien lo dijo?
     *
     * Hay dos formas de haberlo dicho. La fuerte es que los dos apunten al
     * mismo producto de Odoo. La otra es que uno apunte al texto del otro:
     * sirve para enseñar la equivalencia mirando una cotización al lado de la
     * solicitud, sin haber enviado nada a Odoo todavía —que es el momento en
     * que una persona de verdad se da cuenta de que «PROTECTOR SOLAR FPS50
     * B.BOAT» es su «Bloqueador solar 1000 ml».
     */
    public static function equivalentes(string $uno, string $otro, ?int $odooPartnerId): bool
    {
        $normalUno = self::normalizar($uno);
        $normalOtro = self::normalizar($otro);

        if ($normalUno === '' || $normalOtro === '') {
            return false;
        }

        if ($normalUno === $normalOtro) {
            return true;
        }

        $deUno = self::para($uno, $odooPartnerId);
        $deOtro = self::para($otro, $odooPartnerId);

        if ($deUno?->odoo_product_id !== null && $deUno->odoo_product_id === $deOtro?->odoo_product_id) {
            return true;
        }

        if ($deUno?->canonical_text === $normalOtro || $deOtro?->canonical_text === $normalUno) {
            return true;
        }

        return $deUno?->canonical_text !== null && $deUno->canonical_text === $deOtro?->canonical_text;
    }

    /** ¿Apunta a un producto que Odoo todavía tiene? */
    public function productoVigente(): bool
    {
        if ($this->odoo_product_id === null) {
            return false;
        }

        return OdooProduct::query()
            ->where('odoo_id', $this->odoo_product_id)
            ->usable()
            ->exists();
    }
}
