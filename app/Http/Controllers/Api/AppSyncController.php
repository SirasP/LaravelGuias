<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza equipos, mantenciones, kits y órdenes de trabajo
 * entre la app Flutter y la base de datos MySQL (guias).
 */
class AppSyncController extends Controller
{
    private function ok($data = null, string $msg = 'OK'): \Illuminate\Http\JsonResponse
    {
        return response()->json(['ok' => true, 'message' => $msg, 'data' => $data]);
    }

    private function err(string $msg, int $code = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $msg], $code);
    }

    // ── Equipos ──────────────────────────────────────────────────────────────

    public function indexEquipos(): \Illuminate\Http\JsonResponse
    {
        $equipos = DB::table('equipos')->orderBy('nombre')->get()
            ->map(function ($e) {
                $e->documentos = json_decode($e->documentos ?? '[]', true);
                return $this->equipoToFlutter($e);
            });
        return $this->ok($equipos->values());
    }

    public function upsertEquipo(Request $req): \Illuminate\Http\JsonResponse
    {
        $d = $req->validate([
            'id'                   => 'required|string',
            'name'                 => 'required|string',
            'type'                 => 'required|string',
            'brand'                => 'required|string',
            'model'                => 'required|string',
            'year'                 => 'required|integer',
            'identifier'           => 'required|string',
            'location'             => 'required|string',
            'status'               => 'required|string',
            'hourMeter'            => 'required|integer',
            'responsible'          => 'required|string',
            'nextMaintenanceDate'  => 'required|string',
            'nextMaintenanceHours' => 'nullable|integer',
            'notes'                => 'nullable|string',
            'documents'            => 'nullable|array',
        ]);

        DB::table('equipos')->upsert([[
            'id'            => $d['id'],
            'nombre'        => $d['name'],
            'tipo'          => $d['type'],
            'marca'         => $d['brand'],
            'modelo'        => $d['model'],
            'anio'          => $d['year'],
            'identificador' => $d['identifier'],
            'ubicacion'     => $d['location'],
            'status'        => $d['status'],
            'horometro'     => $d['hourMeter'],
            'responsable'   => $d['responsible'],
            'proxima_mant'  => substr($d['nextMaintenanceDate'], 0, 10),
            'proximas_horas'=> $d['nextMaintenanceHours'] ?? null,
            'notas'         => $d['notes'] ?? '',
            'documentos'    => json_encode($d['documents'] ?? []),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]], ['id'], [
            'nombre','tipo','marca','modelo','anio','identificador',
            'ubicacion','status','horometro','responsable','proxima_mant',
            'proximas_horas','notas','documentos','updated_at',
        ]);

        return $this->ok(null, 'Equipo guardado');
    }

    public function deleteEquipo(string $id): \Illuminate\Http\JsonResponse
    {
        DB::table('equipos')->where('id', $id)->delete();
        return $this->ok(null, 'Equipo eliminado');
    }

    // ── Mantenciones ─────────────────────────────────────────────────────────

    public function indexMantenciones(): \Illuminate\Http\JsonResponse
    {
        $rows = DB::table('mantenciones')->orderBy('fecha','desc')->get();
        $result = $rows->map(function ($m) {
            $cons = DB::table('part_consumptions')
                ->where('maintenance_id', $m->id)->get()
                ->map(fn($c) => [
                    'partId'    => $c->part_id,
                    'partName'  => $c->part_name,
                    'quantity'  => (float)$c->quantity,
                    'unitPrice' => (float)$c->unit_price,
                ])->values()->toArray();
            $m->consumptions = $cons;
            $m->photos       = json_decode($m->fotos ?? '[]', true);
            return $this->mantencionToFlutter($m);
        });
        return $this->ok($result->values());
    }

    public function upsertMantencion(Request $req): \Illuminate\Http\JsonResponse
    {
        $d = $req->validate([
            'id'                   => 'required|string',
            'equipmentId'          => 'required|string',
            'type'                 => 'required|string',
            'date'                 => 'required|string',
            'hoursAtService'       => 'required|integer',
            'technician'           => 'required|string',
            'description'          => 'required|string',
            'partsCost'            => 'required|numeric',
            'laborCost'            => 'required|numeric',
            'nextMaintenanceDate'  => 'nullable|string',
            'nextMaintenanceHours' => 'nullable|integer',
            'consumptions'         => 'nullable|array',
            'photos'               => 'nullable|array',
        ]);

        DB::transaction(function () use ($d) {
            DB::table('mantenciones')->upsert([[
                'id'             => $d['id'],
                'equipment_id'   => $d['equipmentId'],
                'tipo'           => $d['type'],
                'fecha'          => substr($d['date'], 0, 10),
                'horas_servicio' => $d['hoursAtService'],
                'tecnico'        => $d['technician'],
                'descripcion'    => $d['description'],
                'costo_repuestos'=> $d['partsCost'],
                'costo_mano_obra'=> $d['laborCost'],
                'proxima_mant'   => isset($d['nextMaintenanceDate'])
                    ? substr($d['nextMaintenanceDate'], 0, 10) : null,
                'proximas_horas' => $d['nextMaintenanceHours'] ?? null,
                'fotos'          => json_encode($d['photos'] ?? []),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]], ['id'], [
                'equipment_id','tipo','fecha','horas_servicio','tecnico',
                'descripcion','costo_repuestos','costo_mano_obra',
                'proxima_mant','proximas_horas','fotos','updated_at',
            ]);

            DB::table('part_consumptions')->where('maintenance_id', $d['id'])->delete();
            foreach ($d['consumptions'] ?? [] as $c) {
                DB::table('part_consumptions')->insert([
                    'id'             => $d['id'].'-'.$c['partId'],
                    'maintenance_id' => $d['id'],
                    'part_id'        => $c['partId'],
                    'part_name'      => $c['partName'],
                    'quantity'       => $c['quantity'],
                    'unit_price'     => $c['unitPrice'] ?? 0,
                ]);
            }
        });

        return $this->ok(null, 'Mantención guardada');
    }

    // ── Kits ─────────────────────────────────────────────────────────────────

    public function indexKits(): \Illuminate\Http\JsonResponse
    {
        $rows = DB::table('kits')->orderBy('created_at','desc')->get();
        $result = $rows->map(function ($k) {
            $items = DB::table('kit_items')->where('kit_id', $k->id)->get()
                ->map(fn($i) => [
                    'productId'   => (int)$i->product_id,
                    'productName' => $i->product_name,
                    'unidad'      => $i->unidad,
                    'quantity'    => (float)$i->quantity,
                ])->values()->toArray();
            $k->items = $items;
            return $this->kitToFlutter($k);
        });
        return $this->ok($result->values());
    }

    public function upsertKit(Request $req): \Illuminate\Http\JsonResponse
    {
        $d = $req->validate([
            'id'          => 'required|string',
            'equipmentId' => 'required|string',
            'name'        => 'required|string',
            'emoji'       => 'nullable|string',
            'usageCount'  => 'nullable|integer',
            'createdAt'   => 'required|string',
            'items'       => 'nullable|array',
        ]);

        DB::transaction(function () use ($d) {
            DB::table('kits')->upsert([[
                'id'           => $d['id'],
                'equipment_id' => $d['equipmentId'],
                'nombre'       => $d['name'],
                'emoji'        => $d['emoji'] ?? '🔧',
                'uso_count'    => $d['usageCount'] ?? 0,
                'created_at'   => substr($d['createdAt'], 0, 19),
                'updated_at'   => now(),
            ]], ['id'], ['nombre','emoji','uso_count','updated_at']);

            DB::table('kit_items')->where('kit_id', $d['id'])->delete();
            foreach ($d['items'] ?? [] as $item) {
                DB::table('kit_items')->insert([
                    'kit_id'       => $d['id'],
                    'product_id'   => $item['productId'],
                    'product_name' => $item['productName'],
                    'unidad'       => $item['unidad'],
                    'quantity'     => $item['quantity'],
                ]);
            }
        });

        return $this->ok(null, 'Kit guardado');
    }

    // ── Órdenes de trabajo ────────────────────────────────────────────────────

    public function upsertWorkOrder(Request $req): \Illuminate\Http\JsonResponse
    {
        $d = $req->validate([
            'id'          => 'required|string',
            'equipmentId' => 'required|string',
            'title'       => 'required|string',
            'description' => 'required|string',
            'assignee'    => 'required|string',
            'dueDate'     => 'required|string',
            'status'      => 'required|string',
            'priority'    => 'required|string',
            'createdAt'   => 'required|string',
            'checklist'   => 'nullable|array',
        ]);

        DB::transaction(function () use ($d) {
            DB::table('work_orders')->upsert([[
                'id'           => $d['id'],
                'equipment_id' => $d['equipmentId'],
                'titulo'       => $d['title'],
                'descripcion'  => $d['description'],
                'asignado'     => $d['assignee'],
                'fecha_limite' => substr($d['dueDate'], 0, 10),
                'status'       => $d['status'],
                'prioridad'    => $d['priority'],
                'created_at'   => substr($d['createdAt'], 0, 19),
                'updated_at'   => now(),
            ]], ['id'], ['titulo','descripcion','asignado','fecha_limite','status','prioridad','updated_at']);

            DB::table('checklist_items')->where('work_order_id', $d['id'])->delete();
            foreach ($d['checklist'] ?? [] as $item) {
                DB::table('checklist_items')->insert([
                    'work_order_id' => $d['id'],
                    'tarea'         => $item['task'],
                    'status'        => $item['status'] ?? 'pending',
                    'nota'          => $item['note'] ?? '',
                ]);
            }
        });

        return $this->ok(null, 'Orden guardada');
    }

    // ── Mappers → formato Flutter ─────────────────────────────────────────────

    private function equipoToFlutter(object $e): array
    {
        return [
            'id'                   => $e->id,
            'name'                 => $e->nombre,
            'type'                 => $e->tipo,
            'brand'                => $e->marca,
            'model'                => $e->modelo,
            'year'                 => (int)$e->anio,
            'identifier'           => $e->identificador,
            'location'             => $e->ubicacion,
            'status'               => $e->status,
            'hourMeter'            => (int)$e->horometro,
            'responsible'          => $e->responsable,
            'nextMaintenanceDate'  => $e->proxima_mant.'T00:00:00.000',
            'nextMaintenanceHours' => $e->proximas_horas ? (int)$e->proximas_horas : null,
            'notes'                => $e->notas ?? '',
            'documents'            => is_array($e->documentos) ? $e->documentos : [],
        ];
    }

    private function mantencionToFlutter(object $m): array
    {
        return [
            'id'                   => $m->id,
            'equipmentId'          => $m->equipment_id,
            'type'                 => $m->tipo,
            'date'                 => $m->fecha.'T00:00:00.000',
            'hoursAtService'       => (int)$m->horas_servicio,
            'technician'           => $m->tecnico,
            'description'          => $m->descripcion,
            'partsCost'            => (float)$m->costo_repuestos,
            'laborCost'            => (float)$m->costo_mano_obra,
            'nextMaintenanceDate'  => $m->proxima_mant
                ? $m->proxima_mant.'T00:00:00.000' : null,
            'nextMaintenanceHours' => $m->proximas_horas ? (int)$m->proximas_horas : null,
            'consumptions'         => $m->consumptions ?? [],
            'photos'               => $m->photos ?? [],
        ];
    }

    private function kitToFlutter(object $k): array
    {
        return [
            'id'          => $k->id,
            'equipmentId' => $k->equipment_id,
            'name'        => $k->nombre,
            'emoji'       => $k->emoji ?? '🔧',
            'usageCount'  => (int)($k->uso_count ?? 0),
            'createdAt'   => $k->created_at,
            'items'       => $k->items ?? [],
        ];
    }
}
