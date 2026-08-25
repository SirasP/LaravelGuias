<?php

namespace App\Http\Requests\PurchaseRequests;

use App\Models\PurchaseRequest;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class PurchaseRequestDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $purchaseRequest = $this->route('purchaseRequest');
        $minimumDate = $purchaseRequest instanceof PurchaseRequest
            ? $purchaseRequest->request_date?->toDateString()
            : now()->toDateString();

        return [
            'department' => ['required', 'string', 'max:120'],
            'requested_for_name' => ['nullable', 'string', 'max:255'],
            'required_date' => ['required', 'date', 'after_or_equal:'.$minimumDate],
            'reason' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['normal', 'urgent'])],
            'urgent_reason' => ['nullable', 'required_if:priority,urgent', 'string', 'max:5000'],
            'cost_center' => ['nullable', 'string', 'max:120'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'suggested_suppliers' => ['nullable', 'array', 'max:4'],
            // `distinct` marcaría también la primera aparición, que no es la
            // repetida. Aquí sólo se señalan las filas que vuelven a nombrar
            // un proveedor ya escrito más arriba.
            'suggested_suppliers.*' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                $position = (int) str_replace('suggested_suppliers.', '', $attribute);
                $anteriores = array_slice((array) $this->input('suggested_suppliers', []), 0, $position);

                foreach ($anteriores as $anterior) {
                    if (is_string($anterior) && mb_strtolower(trim($anterior)) === mb_strtolower(trim((string) $value))) {
                        $fail('Este proveedor ya lo escribiste más arriba. Pon otro o deja la fila vacía.');

                        return;
                    }
                }
            }],
            'items' => ['nullable', 'array', 'max:200'],
            'items.*.sort_order' => ['required', 'integer', 'min:1'],
            'items.*.product_service' => ['required', 'string', 'max:1000'],
            'items.*.specification' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d{1,12}(?:\.\d{1,6})?$/',
            ],
            'items.*.unit' => ['required', 'string', 'max:80'],
            'items.*.quantity_note' => ['nullable', 'string', 'max:255'],
            'items.*.destination' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
            'lock_version' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'required_date.after_or_equal' => 'La fecha requerida no puede ser anterior a la fecha de solicitud.',
            'urgent_reason.required_if' => 'Debes explicar por qué la solicitud es urgente.',
            'items.*.quantity.regex' => 'La cantidad admite hasta 12 enteros y 6 decimales.',
            'attachments.*.max' => 'Cada adjunto puede pesar como máximo 10 MB.',
            'attachments.*.mimes' => 'Sólo se permiten archivos PDF, JPG, JPEG o PNG.',
            'attachments.*.mimetypes' => 'El contenido del adjunto no corresponde a un PDF o una imagen válida.',
            'items.*.product_service.required' => 'Escribe qué producto o servicio necesitas en la partida N° :position.',
            'items.*.quantity.required' => 'Indica la cantidad de la partida N° :position.',
            'items.*.quantity.gt' => 'La cantidad de la partida N° :position debe ser mayor que cero.',
            'items.*.unit.required' => 'Elige la unidad de la partida N° :position.',
        ];
    }

    /**
     * Nombres legibles de los campos.
     *
     * Sin esto el usuario lee «suggested_suppliers.1», que no le dice nada.
     * Los índices se numeran desde 1 y se generan según lo que realmente vino
     * en la petición, para que el mensaje señale la línea correcta.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'department' => 'el área o departamento',
            'requested_for_name' => 'el campo «Solicitado para»',
            'required_date' => 'la fecha requerida',
            'reason' => 'el motivo de la compra',
            'priority' => 'la prioridad',
            'urgent_reason' => 'la justificación de urgencia',
            'cost_center' => 'el centro de costo',
            'delivery_location' => 'el lugar de entrega',
            'internal_notes' => 'las observaciones internas',
            'suggested_suppliers' => 'los proveedores sugeridos',
            'items' => 'las partidas',
            'attachments' => 'los adjuntos',
        ];

        foreach (array_keys((array) $this->input('suggested_suppliers', [])) as $index) {
            $attributes['suggested_suppliers.'.$index] = 'el proveedor sugerido N° '.((int) $index + 1);
        }

        $campos = [
            'product_service' => 'el producto o servicio',
            'specification' => 'la especificación',
            'quantity' => 'la cantidad',
            'unit' => 'la unidad',
            'quantity_note' => 'la nota de cantidad',
            'destination' => 'el destino',
            'sort_order' => 'la posición',
        ];

        foreach (array_keys((array) $this->input('items', [])) as $index) {
            $linea = (int) $index + 1;
            foreach ($campos as $campo => $etiqueta) {
                $attributes['items.'.$index.'.'.$campo] = $etiqueta.' de la partida N° '.$linea;
            }
        }

        foreach (array_keys((array) $this->input('attachments', [])) as $index) {
            $attributes['attachments.'.$index] = 'el adjunto N° '.((int) $index + 1);
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item, int|string $position): array {
                // Compatibilidad conservadora con el nombre usado en los
                // primeros formularios de trabajo, sin persistir dos campos.
                $item['product_service'] ??= $item['description'] ?? null;
                $item['quantity'] = $this->normalizeDecimal($item['quantity'] ?? null);
                $item['sort_order'] = ((int) $position) + 1;

                return $item;
            })
            ->filter(function (array $item): bool {
                foreach (['product_service', 'specification', 'quantity', 'unit', 'quantity_note', 'destination'] as $field) {
                    if (filled($item[$field] ?? null)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->map(function (array $item, int $position): array {
                $item['sort_order'] = $position + 1;

                return $item;
            })
            ->all();

        $suppliers = collect($this->input('suggested_suppliers', []))
            ->filter(fn (mixed $supplier): bool => is_string($supplier) && trim($supplier) !== '')
            ->map(fn (string $supplier): string => trim($supplier))
            ->values()
            ->all();

        $this->merge([
            'urgent_reason' => $this->input('priority') === 'urgent' ? $this->input('urgent_reason') : null,
            'items' => $items,
            'suggested_suppliers' => $suppliers,
        ]);
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = preg_replace('/[\s\x{00A0}]+/u', '', trim($value));
        if ($normalized === null || $normalized === '') {
            return $normalized;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($lastComma !== false) {
            $parts = explode(',', $normalized);
            $decimal = array_pop($parts);
            $normalized = implode('', $parts).'.'.$decimal;
        } elseif (substr_count($normalized, '.') > 1) {
            $parts = explode('.', $normalized);
            $decimal = array_pop($parts);
            $normalized = implode('', $parts).'.'.$decimal;
        }

        return $normalized;
    }
}
