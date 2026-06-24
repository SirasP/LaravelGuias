<?php

namespace App\Services\Webfleet;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WebfleetApiService
{
    public function configured(): bool
    {
        return empty($this->missingConfig());
    }

    public function missingConfig(): array
    {
        return collect([
            'WEBFLEET_ACCOUNT' => config('services.webfleet.account'),
            'WEBFLEET_USERNAME' => config('services.webfleet.username'),
            'WEBFLEET_PASSWORD' => config('services.webfleet.password'),
            'WEBFLEET_API_KEY' => config('services.webfleet.api_key'),
        ])
            ->filter(fn ($value) => blank($value))
            ->keys()
            ->all();
    }

    public function objectReport(): array
    {
        if (! $this->configured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => 'Faltan credenciales Webfleet en el archivo .env.',
            ];
        }

        try {
            $response = $this->request([
                'action' => 'showObjectReportExtern',
            ]);

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json() ?? [],
                'raw' => $response->body(),
                'error' => $response->successful() ? null : $response->body(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function tripReport(string $from, string $to, ?string $objectNo = null): array
    {
        if (! $this->configured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => 'Faltan credenciales Webfleet en el archivo .env.',
            ];
        }

        try {
            $params = [
                'action' => 'showTripReportExtern',
                'rangefrom_string' => $from,
                'rangeto_string' => $to,
            ];

            if (! empty($objectNo)) {
                $params['objectno'] = $objectNo;
            }

            $response = $this->request($params);
            $data = $response->json() ?? [];

            // Si la respuesta es un objeto único (asociativo) y no una lista, lo envolvemos en un array.
            if (is_array($data) && ! array_is_list($data) && ! empty($data)) {
                if (isset($data['errorCode'])) {
                    return [
                        'ok' => false,
                        'status' => $response->status(),
                        'data' => [],
                        'raw' => $response->body(),
                        'error' => $data['errorMsg'] ?? 'Error de Webfleet.',
                    ];
                }
                $data = [$data];
            }

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'data' => $data,
                'raw' => $response->body(),
                'error' => $response->successful() ? null : $response->body(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function eventReport(string $from, string $to, ?string $objectNo = null): array
    {
        if (! $this->configured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => 'Faltan credenciales Webfleet en el archivo .env.',
            ];
        }

        try {
            $params = [
                'action' => 'showEventReportExtern',
                'rangefrom_string' => $from,
                'rangeto_string' => $to,
            ];

            if (! empty($objectNo)) {
                $params['objectno'] = $objectNo;
            }

            $response = $this->request($params);
            $data = $response->json() ?? [];

            if (is_array($data) && ! array_is_list($data) && ! empty($data)) {
                if (isset($data['errorCode'])) {
                    return [
                        'ok' => false,
                        'status' => $response->status(),
                        'data' => [],
                        'raw' => $response->body(),
                        'error' => $data['errorMsg'] ?? 'Error de Webfleet.',
                    ];
                }
                $data = [$data];
            }

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'data' => $data,
                'raw' => $response->body(),
                'error' => $response->successful() ? null : $response->body(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function idleExceptions(string $from, string $to, string $objectNo): array
    {
        if (! $this->configured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => 'Faltan credenciales Webfleet en el archivo .env.',
            ];
        }

        try {
            $params = [
                'action' => 'showIdleExceptions',
                'objectno' => $objectNo,
                'rangefrom_string' => $from,
                'rangeto_string' => $to,
            ];

            $response = $this->request($params);
            $data = $response->json() ?? [];

            if (is_array($data) && ! array_is_list($data) && ! empty($data)) {
                if (isset($data['errorCode'])) {
                    return [
                        'ok' => false,
                        'status' => $response->status(),
                        'data' => [],
                        'raw' => $response->body(),
                        'error' => $data['errorMsg'] ?? 'Error de Webfleet.',
                    ];
                }
                $data = [$data];
            }

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'data' => $data,
                'raw' => $response->body(),
                'error' => $response->successful() ? null : $response->body(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function objectCanSignals(?string $objectNo = null): array
    {
        if (! $this->configured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => 'Faltan credenciales Webfleet en el archivo .env.',
            ];
        }

        try {
            $params = [
                'action' => 'getObjectCanSignals',
            ];

            if (! empty($objectNo)) {
                $params['objectno'] = $objectNo;
            }

            $response = $this->request($params);
            $data = $response->json() ?? [];

            if (is_array($data) && ! array_is_list($data) && ! empty($data)) {
                if (isset($data['errorCode'])) {
                    return [
                        'ok' => false,
                        'status' => $response->status(),
                        'data' => [],
                        'raw' => $response->body(),
                        'error' => $data['errorMsg'] ?? 'Error de Webfleet.',
                    ];
                }
                $data = [$data];
            }

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'data' => $data,
                'raw' => $response->body(),
                'error' => $response->successful() ? null : $response->body(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function objectCanMalfunctions(?string $objectNo = null): array
    {
        if (! $this->configured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => 'Faltan credenciales Webfleet en el archivo .env.',
            ];
        }

        try {
            $params = [
                'action' => 'getObjectCanMalfunctions',
            ];

            if (! empty($objectNo)) {
                $params['objectno'] = $objectNo;
            }

            $response = $this->request($params);
            $data = $response->json() ?? [];

            if (is_array($data) && ! array_is_list($data) && ! empty($data)) {
                if (isset($data['errorCode'])) {
                    return [
                        'ok' => false,
                        'status' => $response->status(),
                        'data' => [],
                        'raw' => $response->body(),
                        'error' => $data['errorMsg'] ?? 'Error de Webfleet.',
                    ];
                }
                $data = [$data];
            }

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'data' => $data,
                'raw' => $response->body(),
                'error' => $response->successful() ? null : $response->body(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'data' => [],
                'raw' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function request(array $parameters): Response
    {
        return Http::asForm()
            ->acceptJson()
            ->withBasicAuth(
                (string) config('services.webfleet.username'),
                (string) config('services.webfleet.password')
            )
            ->timeout(20)
            ->get((string) config('services.webfleet.base_url'), array_merge([
                'lang' => 'es',
                'account' => config('services.webfleet.account'),
                'apikey' => config('services.webfleet.api_key'),
                'outputformat' => 'json',
                'useUTF8' => 'true',
                'useISO8601' => 'true',
            ], $parameters));
    }
}
