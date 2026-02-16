import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:inventariohuerto/screens/api_service.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:workmanager/workmanager.dart';
import 'package:inventariohuerto/screens/splash_screen.dart';
// 🔥 NUEVO: Importa el servicio de combustible
import 'package:inventariohuerto/services/combustible_service.dart';

/// 🔁 Tarea en segundo plano (se ejecuta incluso con la app cerrada en Android)
@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((task, inputData) async {
    print("⏰ Ejecutando tarea de sincronización en segundo plano...");
    await ApiService.initDb();
    await ApiService.sincronizarPendientes();
    print("✅ Sincronización en segundo plano completada");
    return Future.value(true);
  });
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await ApiService.initDb();
  await ApiService.sincronizarPendientes();
  await ApiService.sincronizarCatalogos(); // ✅ sincroniza catálogos al iniciar

  if (Platform.isAndroid) {
    // ⚙️ Inicializa el WorkManager
    await Workmanager().initialize(
      callbackDispatcher,
      isInDebugMode: true, // ⚠️ Desactiva en producción
    );

    // Evita registrar duplicados al relanzar
    await Workmanager().cancelAll();

    // 🕐 Programa la tarea periódica
    await Workmanager().registerPeriodicTask(
      "sincronizar_pendientes",
      "syncBackgroundTask",
      frequency: const Duration(minutes: 15),
      constraints: Constraints(
        networkType: NetworkType.connected,
      ),
    );
  }

  runApp(const InventarioApp());
}

class InventarioApp extends StatefulWidget {
  const InventarioApp({super.key});

  @override
  State<InventarioApp> createState() => _InventarioAppState();
}

class _InventarioAppState extends State<InventarioApp> {
  StreamSubscription<List<ConnectivityResult>>? _conexionSub;
  bool _conectado = false;

  // 🔥 NUEVO: Servicio de combustible
  final CombustibleService _combustibleService = CombustibleService();

  // 🔥 NUEVO: NavigatorKey para mostrar notificaciones desde cualquier lugar
  final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

  @override
  void initState() {
    super.initState();

    // 🕒 Sincronización periódica (válida en iOS también)
    Timer.periodic(const Duration(minutes: 15), (timer) async {
      final conectado = await ApiService.hayConexion();
      if (conectado) {
        print("⏰ Sincronización automática (Timer en ejecución)");
        await ApiService.sincronizarPendientes();
        await ApiService.sincronizarCatalogos(); // ✅ sincroniza catálogos
      }
    });

    // 🔥 NUEVO: Inicia monitoreo de combustible
    _iniciarMonitoreoCombustible();

    // 🧭 Verifica conexión inicial
    Connectivity().checkConnectivity().then((estado) {
      setState(() => _conectado =
          estado == ConnectivityResult.mobile || estado == ConnectivityResult.wifi);
    });

    // 🛰️ Escucha cambios en la red
    _conexionSub = Connectivity().onConnectivityChanged.listen((event) async {
      final estado = event.isNotEmpty ? event.first : ConnectivityResult.none;
      final hayInternet =
          estado == ConnectivityResult.mobile || estado == ConnectivityResult.wifi;

      setState(() => _conectado = hayInternet);

      if (hayInternet) {
        print("📶 Conexión detectada (${estado.name}), sincronizando...");
        await ApiService.sincronizarPendientes();
        await ApiService.sincronizarCatalogos(); // ✅ sincroniza también productos y vehículos

        // 🔥 NUEVO: Reinicia monitoreo al reconectar
        _iniciarMonitoreoCombustible();
      } else {
        print("📴 Sin conexión, sincronización pausada.");
        // 🔥 NUEVO: Detiene monitoreo sin conexión
        _combustibleService.detenerMonitoreo();
      }
    });
  }

  // 🔥 NUEVO: Método para iniciar monitoreo de combustible
  void _iniciarMonitoreoCombustible() {
    // Obtener el ID del usuario (ajusta según tu lógica de autenticación)
    // Por ahora usamos ID fijo, pero deberías obtenerlo del login
    const int userId = 1;

    _combustibleService.iniciarMonitoreo(userId, (notificaciones) {
      // Cuando llegan nuevas notificaciones, mostrarlas
      for (var notif in notificaciones) {
        _mostrarNotificacionCombustible(notif);

        // Marcar como leída
        _combustibleService.marcarLeida(notif.id, userId);
      }
    });
  }

  // 🔥 NUEVO: Muestra notificación de combustible
  void _mostrarNotificacionCombustible(NotificacionCombustible notif) {
    final context = navigatorKey.currentContext;
    if (context == null) return;

    // Opción 1: SnackBar (notificación en la parte inferior)
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Text(
              notif.icono,
              style: const TextStyle(fontSize: 24),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    notif.titulo,
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    notif.mensaje,
                    style: const TextStyle(
                      fontSize: 13,
                      color: Colors.white70,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        backgroundColor: notif.productoNombre == 'Diesel'
            ? Colors.orange.shade700
            : Colors.blue.shade700,
        duration: const Duration(seconds: 8),
        behavior: SnackBarBehavior.floating,
        action: SnackBarAction(
          label: 'Ver',
          textColor: Colors.white,
          onPressed: () {
            // 🔧 TODO: Navegar a la pantalla de detalles si es necesario
            print('Ver detalles de ${notif.productoNombre}');
          },
        ),
      ),
    );

    // Opción 2: Dialog (ventana emergente) - Descomentar si prefieres esto
    /*
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            Text(notif.icono, style: const TextStyle(fontSize: 28)),
            const SizedBox(width: 8),
            Expanded(child: Text(notif.titulo)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(notif.mensaje),
            if (notif.productoCantidad != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    Icon(Icons.local_gas_station,
                        color: Colors.green.shade700),
                    const SizedBox(width: 8),
                    Text(
                      '${notif.productoCantidad!.toStringAsFixed(1)} Litros',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.green.shade900,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('OK'),
          ),
        ],
      ),
    );
    */
  }

  @override
  void dispose() {
    _conexionSub?.cancel();
    // 🔥 NUEVO: Detiene el monitoreo al cerrar la app
    _combustibleService.detenerMonitoreo();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Inventario de Combustibles',
      debugShowCheckedModeBanner: false,
      // 🔥 NUEVO: NavigatorKey para acceder al contexto desde cualquier lugar
      navigatorKey: navigatorKey,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.teal),
        useMaterial3: true,
      ),
      home: const SplashScreen(),
    );
  }
}

class DeviceUser {
  static const _key = 'operador_nombre';

  static Future<String> obtenerUsuario() async {
    final prefs = await SharedPreferences.getInstance();
    final guardado = prefs.getString(_key);
    if (guardado != null && guardado.isNotEmpty) return guardado;

    final generado = await _generarNombre();
    await prefs.setString(_key, generado);
    return generado;
  }

  static Future<void> guardarUsuario(String nombre) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, nombre);
  }

  static Future<String> _generarNombre() async {
    final deviceInfo = DeviceInfoPlugin();

    try {
      if (Platform.isAndroid) {
        final info = await deviceInfo.androidInfo;
        final fabricante = info.manufacturer ?? 'Android';
        final modelo = info.model ?? 'Dispositivo';
        return '$fabricante $modelo'.trim();
      } else if (Platform.isIOS) {
        final info = await deviceInfo.iosInfo;
        final nombre = info.name ?? 'iPhone';
        final modelo = info.utsname.machine ?? '';
        return '$nombre $modelo'.trim();
      }
    } catch (_) {}
    return 'Dispositivo desconocido';
  }
}
