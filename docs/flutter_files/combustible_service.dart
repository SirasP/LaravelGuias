// lib/services/combustible_service.dart
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:async';

class CombustibleService {
  // ⚙️ CONFIGURACIÓN - Cambia esta URL por la de tu servidor
  static const String baseUrl = 'http://TU-SERVIDOR-IP/api';

  Timer? _pollingTimer;

  /// Inicia el monitoreo de notificaciones cada 30 segundos
  void iniciarMonitoreo(int userId, Function(List<NotificacionCombustible>) callback) {
    // Cancela cualquier timer existente
    detenerMonitoreo();

    // Verifica inmediatamente
    _checkNotificaciones(userId, callback);

    // Luego cada 30 segundos
    _pollingTimer = Timer.periodic(
      const Duration(seconds: 30),
      (_) => _checkNotificaciones(userId, callback),
    );

    print('🔥 Monitoreo de combustible iniciado');
  }

  Future<void> _checkNotificaciones(
    int userId,
    Function(List<NotificacionCombustible>) callback,
  ) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/notificaciones?user_id=$userId&limit=20'),
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = json.decode(response.body);

        if (data['ok'] == true) {
          final notifs = (data['data'] as List)
              .map((json) => NotificacionCombustible.fromJson(json))
              .where((n) => n.esCombustible) // Solo Diesel/Gasolina
              .toList();

          if (notifs.isNotEmpty) {
            print('🔔 ${notifs.length} notificaciones de combustible nuevas');
            callback(notifs);
          }
        }
      }
    } catch (e) {
      print('⚠️ Error al verificar notificaciones: $e');
    }
  }

  /// Marca una notificación como leída
  Future<bool> marcarLeida(int notifId, int userId) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/notificaciones/$notifId/leer'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'user_id': userId}),
      ).timeout(const Duration(seconds: 5));

      return response.statusCode == 200;
    } catch (e) {
      print('⚠️ Error al marcar leída: $e');
      return false;
    }
  }

  /// Obtiene el stock actual de combustibles
  Future<Map<String, double>> obtenerStock() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/combustible/stock'),
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = json.decode(response.body);

        if (data['ok'] == true) {
          final productos = data['data'] as List;
          return {
            for (var p in productos)
              p['nombre'] as String: (p['cantidad'] as num).toDouble()
          };
        }
      }
    } catch (e) {
      print('⚠️ Error al obtener stock: $e');
    }
    return {};
  }

  /// Detiene el monitoreo
  void detenerMonitoreo() {
    _pollingTimer?.cancel();
    _pollingTimer = null;
    print('🛑 Monitoreo de combustible detenido');
  }
}

/// Modelo de notificación de combustible
class NotificacionCombustible {
  final int id;
  final String tipo;
  final String titulo;
  final String mensaje;
  final String? productoNombre;
  final double? productoCantidad;
  final DateTime createdAt;

  NotificacionCombustible({
    required this.id,
    required this.tipo,
    required this.titulo,
    required this.mensaje,
    this.productoNombre,
    this.productoCantidad,
    required this.createdAt,
  });

  factory NotificacionCombustible.fromJson(Map<String, dynamic> json) {
    return NotificacionCombustible(
      id: json['id'],
      tipo: json['tipo'] ?? '',
      titulo: json['titulo'] ?? '',
      mensaje: json['mensaje'] ?? '',
      productoNombre: json['producto_nombre'],
      productoCantidad: json['producto_cantidad']?.toDouble(),
      createdAt: DateTime.parse(json['created_at']),
    );
  }

  /// Verifica si es una notificación de combustible (Diesel o Gasolina)
  bool get esCombustible =>
      productoNombre == 'Diesel' || productoNombre == 'Gasolina';

  /// Ícono según el tipo de combustible
  String get icono => productoNombre == 'Diesel' ? '⛽' : '🛢️';
}
