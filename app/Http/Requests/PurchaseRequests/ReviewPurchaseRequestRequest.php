<?php

namespace App\Http\Requests\PurchaseRequests;

use App\Enums\PurchaseRequestCorrection;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ReviewPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Devolver, rechazar, anular o pedir la anulación siempre dejan un
        // motivo escrito: es lo que después explica la decisión en el historial.
        $commentIsRequired = $this->routeIs(
            'purchase_requests.request_changes',
            'purchase_requests.reject',
            'purchase_requests.cancel',
            'purchase_requests.request_cancellation',
        );

        // Pedir la anulación no compite con ningún revisor, así que no exige
        // comprobación de versión.
        $needsLockVersion = ! $this->routeIs('purchase_requests.request_cancellation');

        return [
            'lock_version' => [$needsLockVersion ? 'required' : 'nullable', 'integer', 'min:0'],
            'comment' => [$commentIsRequired ? 'required' : 'nullable', 'string', 'max:5000'],

            // Puntos marcados al devolver: señalan dónde corregir. Son
            // opcionales; el comentario sigue siendo lo obligatorio.
            'corrections' => ['nullable', 'array', 'max:60'],
            'corrections.*' => ['string', 'max:40', function (string $attribute, mixed $value, Closure $fail): void {
                $isKnownField = in_array($value, PurchaseRequestCorrection::values(), true);
                $isItem = PurchaseRequestCorrection::itemPosition((string) $value) !== null;

                if (! $isKnownField && ! $isItem) {
                    $fail('Se marcó un punto de corrección que no existe.');
                }
            }],
        ];
    }

    /**
     * Un formulario HTML siempre envía texto. Sin este casteo, la versión
     * llegaría como string y la comparación estricta contra el entero del
     * modelo fallaría siempre, bloqueando toda revisión desde el navegador.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('lock_version') && is_string($this->input('lock_version'))) {
            $this->merge([
                'lock_version' => (int) $this->input('lock_version'),
            ]);
        }

        // Las casillas llegan sólo cuando están marcadas; se normalizan a una
        // lista limpia y sin repeticiones.
        $corrections = $this->input('corrections');

        if (is_array($corrections)) {
            $this->merge([
                'corrections' => array_values(array_unique(array_filter(
                    array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $corrections),
                    fn (string $value): bool => $value !== '',
                ))),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'lock_version.required' => 'No se pudo comprobar la versión de la solicitud. Recarga la página.',
            'comment.required' => 'Debes ingresar un comentario para realizar esta acción.',
        ];
    }
}
