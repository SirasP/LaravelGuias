# 🔥 Integración Flutter - Notificaciones de Combustible

## 📋 Resumen

Este documento explica cómo integrar la aplicación Flutter para recibir notificaciones cuando llegan **Diesel** o **Gasolina** desde los DTEs procesados por el sistema.

---

## ✅ Opción 1: API REST (Recomendada para empezar)

### Endpoints disponibles:

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/notificaciones?user_id={id}` | Obtener notificaciones no leídas |
| POST | `/api/notificaciones/{id}/leer` | Marcar como leída |
| GET | `/api/combustible/movimientos` | Últimos movimientos de combustible |
| GET | `/api/combustible/stock` | Stock actual de Diesel y Gasolina |

### Ejemplo Flutter:

```dart
// lib/services/combustible_service.dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:async';

class CombustibleService {
  static const String baseUrl = 'http://109.72.119.62/api';
  Timer? _pollingTimer;

  /// Inicia monitoreo cada 30 segundos
  void iniciarMonitoreo(int userId, Function(List<Notificacion>) callback) {
    _pollingTimer = Timer.periodic(
      const Duration(seconds: 30),
      (_) => _checkNotificaciones(userId, callback),
    );
  }

  Future<void> _checkNotificaciones(
    int userId,
    Function(List<Notificacion>) callback,
  ) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/notificaciones?user_id=$userId&limit=20'),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final notifs = (data['data'] as List)
            .map((json) => Notificacion.fromJson(json))
            .toList();

        if (notifs.isNotEmpty) {
          callback(notifs);
        }
      }
    } catch (e) {
      print('Error al obtener notificaciones: $e');
    }
  }

  Future<bool> marcarLeida(int notifId, int userId) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/notificaciones/$notifId/leer'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'user_id': userId}),
      );
      return response.statusCode == 200;
    } catch (e) {
      print('Error al marcar leída: $e');
      return false;
    }
  }

  Future<List<Movimiento>> obtenerMovimientos({int limit = 10}) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/combustible/movimientos?limit=$limit'),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return (data['data'] as List)
            .map((json) => Movimiento.fromJson(json))
            .toList();
      }
    } catch (e) {
      print('Error al obtener movimientos: $e');
    }
    return [];
  }

  Future<Map<String, double>> obtenerStock() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/combustible/stock'),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        final productos = data['data'] as List;

        return {
          for (var p in productos)
            p['nombre'] as String: (p['cantidad'] as num).toDouble()
        };
      }
    } catch (e) {
      print('Error al obtener stock: $e');
    }
    return {};
  }

  void detenerMonitoreo() {
    _pollingTimer?.cancel();
    _pollingTimer = null;
  }
}

// lib/models/notificacion.dart
class Notificacion {
  final int id;
  final String tipo;
  final String titulo;
  final String mensaje;
  final String? productoNombre;
  final double? productoCantidad;
  final DateTime createdAt;

  Notificacion({
    required this.id,
    required this.tipo,
    required this.titulo,
    required this.mensaje,
    this.productoNombre,
    this.productoCantidad,
    required this.createdAt,
  });

  factory Notificacion.fromJson(Map<String, dynamic> json) {
    return Notificacion(
      id: json['id'],
      tipo: json['tipo'] ?? '',
      titulo: json['titulo'],
      mensaje: json['mensaje'],
      productoNombre: json['producto_nombre'],
      productoCantidad: json['producto_cantidad']?.toDouble(),
      createdAt: DateTime.parse(json['created_at']),
    );
  }

  bool get esCombustible =>
      productoNombre == 'Diesel' || productoNombre == 'Gasolina';
}

// lib/models/movimiento.dart
class Movimiento {
  final int id;
  final String producto;
  final double cantidad;
  final String tipo;
  final String estado;
  final DateTime fechaMovimiento;

  Movimiento({
    required this.id,
    required this.producto,
    required this.cantidad,
    required this.tipo,
    required this.estado,
    required this.fechaMovimiento,
  });

  factory Movimiento.fromJson(Map<String, dynamic> json) {
    return Movimiento(
      id: json['id'],
      producto: json['producto'],
      cantidad: (json['cantidad'] as num).toDouble(),
      tipo: json['tipo'],
      estado: json['estado'],
      fechaMovimiento: DateTime.parse(json['fecha_movimiento']),
    );
  }
}
```

### Uso en la app:

```dart
// lib/screens/dashboard_screen.dart
class DashboardScreen extends StatefulWidget {
  @override
  _DashboardScreenState createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final CombustibleService _service = CombustibleService();
  final int _userId = 1; // Obtener del login

  @override
  void initState() {
    super.initState();
    _service.iniciarMonitoreo(_userId, _mostrarNotificacion);
  }

  void _mostrarNotificacion(List<Notificacion> notificaciones) {
    for (var notif in notificaciones) {
      if (notif.esCombustible) {
        // Mostrar notificación local
        _mostrarSnackBar(notif);

        // Marcar como leída
        _service.marcarLeida(notif.id, _userId);
      }
    }
  }

  void _mostrarSnackBar(Notificacion notif) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              notif.productoNombre == 'Diesel' ? Icons.local_gas_station : Icons.oil_barrel,
              color: Colors.white,
            ),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    notif.titulo,
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  Text(notif.mensaje),
                ],
              ),
            ),
          ],
        ),
        backgroundColor: Colors.green.shade700,
        duration: Duration(seconds: 8),
        action: SnackBarAction(
          label: 'Ver',
          textColor: Colors.white,
          onPressed: () {
            // Navegar a detalles
          },
        ),
      ),
    );
  }

  @override
  void dispose() {
    _service.detenerMonitoreo();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Tu UI aquí
  }
}
```

---

## 🚀 Opción 2: WebSocket (Tiempo Real)

Para notificaciones instantáneas sin polling.

### Configuración:

```yaml
# pubspec.yaml
dependencies:
  web_socket_channel: ^2.4.0
```

### Código Flutter:

```dart
// lib/services/websocket_service.dart
import 'package:web_socket_channel/web_socket_channel.dart';
import 'dart:convert';

class WebSocketService {
  WebSocketChannel? _channel;
  final String wsUrl = 'ws://109.72.119.62/ws';
  final int userId;
  final String userName;

  WebSocketService({required this.userId, required this.userName});

  void conectar(Function(Map<String, dynamic>) onMessage) {
    try {
      _channel = WebSocketChannel.connect(Uri.parse(wsUrl));

      // Registrar usuario
      _channel!.sink.add(json.encode({
        'type': 'register',
        'userId': userId,
        'name': userName,
      }));

      // Escuchar mensajes
      _channel!.stream.listen(
        (data) {
          try {
            final message = json.decode(data);
            onMessage(message);
          } catch (e) {
            print('Error al parsear mensaje: $e');
          }
        },
        onError: (error) {
          print('WebSocket error: $error');
          _reconectar(onMessage);
        },
        onDone: () {
          print('WebSocket cerrado');
          _reconectar(onMessage);
        },
      );

      print('✅ WebSocket conectado');
    } catch (e) {
      print('Error al conectar WebSocket: $e');
      _reconectar(onMessage);
    }
  }

  void _reconectar(Function(Map<String, dynamic>) onMessage) {
    Future.delayed(Duration(seconds: 5), () {
      print('Reconectando WebSocket...');
      conectar(onMessage);
    });
  }

  void desconectar() {
    _channel?.sink.close();
    _channel = null;
  }
}

// Uso:
class MyApp extends StatefulWidget {
  @override
  _MyAppState createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  late WebSocketService _ws;

  @override
  void initState() {
    super.initState();
    _ws = WebSocketService(userId: 1, userName: 'Usuario Flutter');
    _ws.conectar(_handleWebSocketMessage);
  }

  void _handleWebSocketMessage(Map<String, dynamic> message) {
    final type = message['type'];

    // Solo procesar notificaciones de combustible
    if (type == 'xml_entrada') {
      final producto = message['producto']; // 'Diesel' o 'Gasolina'
      final cantidad = message['cantidad'];
      final titulo = message['titulo'];
      final mensaje = message['mensaje'];

      // Mostrar notificación
      _mostrarNotificacion(
        titulo: titulo,
        mensaje: '$cantidad L de $producto',
        producto: producto,
      );
    }
  }

  void _mostrarNotificacion({
    required String titulo,
    required String mensaje,
    required String producto,
  }) {
    // Implementar tu UI de notificación
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            Icon(
              producto == 'Diesel' ? Icons.local_gas_station : Icons.oil_barrel,
              color: Colors.orange,
            ),
            SizedBox(width: 8),
            Text(titulo),
          ],
        ),
        content: Text(mensaje),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('OK'),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _ws.desconectar();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: DashboardScreen(),
    );
  }
}
```

---

## 🎯 Comparación de opciones

| Característica | API REST (Polling) | WebSocket |
|----------------|-------------------|-----------|
| **Latencia** | 15-30 segundos | Instantáneo |
| **Consumo de batería** | Medio | Bajo-Medio |
| **Consumo de datos** | Medio | Bajo |
| **Complejidad** | Baja | Media |
| **Confiabilidad** | Alta | Media (requiere reconexión) |
| **Recomendado para** | MVP, Apps simples | Producción, Tiempo real |

---

## 🔧 Configuración del servidor

### CORS (si es necesario):

Agregar en `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
'allowed_headers' => ['*'],
```

### Rate Limiting (Opcional):

```php
// app/Http/Kernel.php
'api' => [
    'throttle:60,1',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

---

## 📱 Notificaciones Push (Opcional - Firebase)

Para notificaciones cuando la app está cerrada:

1. Instalar Firebase en Flutter
2. Guardar FCM tokens en Laravel
3. Enviar push desde el comando:

```php
// En GmailLeerXml.php después de línea 245
use Google\Client as GoogleClient;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

$fcmTokens = DB::table('device_tokens')->pluck('fcm_token');
foreach ($fcmTokens as $token) {
    try {
        $factory = (new Factory)->withServiceAccount(storage_path('app/firebase-credentials.json'));
        $messaging = $factory->createMessaging();

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification([
                'title' => "Ingreso de {$productoNombre}",
                'body' => "+{$cantidad} L",
            ])
            ->withData([
                'producto' => $productoNombre,
                'cantidad' => $cantidad,
                'movimiento_id' => $movimientoId,
            ]);

        $messaging->send($message);
    } catch (\Throwable $e) {
        \Log::error("Error FCM: {$e->getMessage()}");
    }
}
```

---

## ✅ Testing

### Probar endpoints:

```bash
# Obtener notificaciones
curl "http://109.72.119.62/api/notificaciones?user_id=1"

# Stock actual
curl "http://109.72.119.62/api/combustible/stock"

# Movimientos
curl "http://109.72.119.62/api/combustible/movimientos?limit=5"
```

---

## 📞 Soporte

Si tienes problemas, revisa:
- Logs de Laravel: `storage/logs/laravel.log`
- Estado de la conexión a base de datos `fuelcontrol`
- El comando `gmail:leer-xml` está corriendo
