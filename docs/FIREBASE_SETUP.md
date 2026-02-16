# 🔥 Configuración Firebase Push Notifications

## 📋 Resumen

Esta guía te enseña cómo configurar **Firebase Cloud Messaging (FCM)** para que las notificaciones de combustible lleguen a Flutter **incluso cuando la app está cerrada**.

---

## ✅ Estado actual

- ✅ **Backend Laravel**: Completamente configurado
- ✅ **Base de datos**: Tabla `device_tokens` creada
- ✅ **API Endpoints**: Listos para registrar tokens FCM
- ⏳ **Firebase Project**: Necesitas crearlo (5-10 minutos)
- ⏳ **Flutter App**: Necesitas configurar Firebase (10-15 minutos)

---

## 🎯 PARTE 1: Configurar Firebase Console

### 1.1. Crear proyecto en Firebase

1. Ve a [Firebase Console](https://console.firebase.google.com/)
2. Click en **"Agregar proyecto"** o **"Add project"**
3. Nombre del proyecto: `FuelControl` (o el que prefieras)
4. Deshabilita Google Analytics (opcional, no lo necesitas)
5. Click en **"Crear proyecto"**

### 1.2. Agregar app Android

1. En el Dashboard de Firebase, click en el ícono de **Android** ⚙️
2. Nombre del paquete: `com.tuempresa.fuelcontrol` (debe coincidir con Flutter)
3. Apodo de la app: `FuelControl Android`
4. **Descargar `google-services.json`** → Guárdalo temporalmente
5. Click en **"Siguiente"** hasta terminar

### 1.3. Agregar app iOS (Opcional, solo si usarás iOS)

1. Click en el ícono de **iOS**
2. Bundle ID: `com.tuempresa.fuelcontrol`
3. **Descargar `GoogleService-Info.plist`** → Guárdalo temporalmente
4. Click en **"Siguiente"** hasta terminar

### 1.4. Obtener credenciales para Laravel (Server Key)

1. En Firebase Console → **Configuración del proyecto** (⚙️ arriba a la izquierda)
2. Pestaña **"Cuentas de servicio"**
3. Click en **"Generar nueva clave privada"**
4. Se descargará un archivo JSON (ejemplo: `fuelcontrol-firebase-adminsdk-xxxxx.json`)
5. **Guarda este archivo**, lo usarás en el siguiente paso

---

## 🎯 PARTE 2: Configurar Laravel

### 2.1. Guardar credenciales de Firebase en Laravel

```bash
# Crear directorio para credenciales
mkdir -p storage/app/firebase

# Copiar el archivo descargado (cambia el nombre según tu archivo)
cp ~/Downloads/fuelcontrol-firebase-adminsdk-xxxxx.json \
   storage/app/firebase/firebase-credentials.json
```

### 2.2. Verificar permisos del archivo

```bash
chmod 600 storage/app/firebase/firebase-credentials.json
```

### 2.3. Probar que Laravel puede leer las credenciales

```bash
# Ejecutar el comando con un XML de prueba
php artisan gmail:leer-xml
```

Si todo está bien, verás:
```
📱 Push enviadas: 0 exitosas
```

(0 porque aún no hay dispositivos registrados)

Si Firebase NO está configurado, verás:
```
⚠️  Firebase no configurado. Notificaciones push desactivadas.
```

---

## 🎯 PARTE 3: Configurar Flutter

### 3.1. Instalar dependencias Firebase

Agrega a `pubspec.yaml`:

```yaml
dependencies:
  firebase_core: ^2.24.0
  firebase_messaging: ^14.7.0
  flutter_local_notifications: ^16.3.0  # Para notificaciones locales
```

Luego ejecuta:
```bash
flutter pub get
```

### 3.2. Copiar archivos de configuración

#### Android:

1. Copia `google-services.json` a:
   ```
   android/app/google-services.json
   ```

2. Edita `android/build.gradle`:
   ```gradle
   buildscript {
       dependencies {
           classpath 'com.google.gms:google-services:4.4.0'  // ← Agregar esto
       }
   }
   ```

3. Edita `android/app/build.gradle`:
   ```gradle
   apply plugin: 'com.google.gms.google-services'  // ← Al final del archivo
   ```

#### iOS (Opcional):

1. Copia `GoogleService-Info.plist` a:
   ```
   ios/Runner/GoogleService-Info.plist
   ```

2. Abre Xcode y arrastra el archivo al proyecto `Runner`

### 3.3. Configurar permisos Android

Edita `android/app/src/main/AndroidManifest.xml`:

```xml
<manifest>
    <uses-permission android:name="android.permission.INTERNET"/>
    <uses-permission android:name="android.permission.VIBRATE"/>
    <uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>

    <application>
        <!-- Agregar esto dentro de <application> -->
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_channel_id"
            android:value="combustible_channel" />
    </application>
</manifest>
```

### 3.4. Código Flutter - Servicio de Firebase

Crea `lib/services/firebase_service.dart`:

```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class FirebaseService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  /// Inicializar Firebase y FCM
  static Future<void> inicializar() async {
    // Inicializar Firebase
    await Firebase.initializeApp();

    // Pedir permisos
    await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    // Configurar notificaciones locales
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings();
    const settings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );

    await _localNotifications.initialize(
      settings,
      onDidReceiveNotificationResponse: (details) {
        // Manejar click en notificación
        print('Notificación clickeada: ${details.payload}');
      },
    );

    // Crear canal de notificación (Android)
    const channel = AndroidNotificationChannel(
      'combustible_channel',
      'Notificaciones de Combustible',
      description: 'Notificaciones cuando llega Diesel o Gasolina',
      importance: Importance.high,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    // Manejar mensajes en primer plano
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // Manejar mensajes cuando la app está en segundo plano
    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    // Manejar click en notificación cuando la app está cerrada
    FirebaseMessaging.onMessageOpenedApp.listen(_handleMessageOpenedApp);
  }

  /// Registrar token FCM en Laravel
  static Future<void> registrarToken(int userId) async {
    try {
      final token = await _messaging.getToken();

      if (token == null) {
        print('❌ No se pudo obtener el token FCM');
        return;
      }

      print('✅ Token FCM: $token');

      // Enviar a Laravel
      final response = await http.post(
        Uri.parse('http://109.72.119.62/api/combustible/fcm-token'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'user_id': userId,
          'fcm_token': token,
          'device_type': 'android', // Cambia a 'ios' si es iPhone
          'device_name': 'Flutter App',
        }),
      );

      if (response.statusCode == 200) {
        print('✅ Token registrado en servidor');
      } else {
        print('❌ Error al registrar token: ${response.body}');
      }
    } catch (e) {
      print('❌ Error al registrar token: $e');
    }
  }

  /// Manejar mensajes cuando la app está abierta
  static void _handleForegroundMessage(RemoteMessage message) {
    print('📱 Mensaje recibido en primer plano');

    final notification = message.notification;
    final data = message.data;

    if (notification != null) {
      _mostrarNotificacionLocal(
        titulo: notification.title ?? 'Nueva notificación',
        mensaje: notification.body ?? '',
        data: data,
      );
    }
  }

  /// Mostrar notificación local
  static Future<void> _mostrarNotificacionLocal({
    required String titulo,
    required String mensaje,
    required Map<String, dynamic> data,
  }) async {
    const androidDetails = AndroidNotificationDetails(
      'combustible_channel',
      'Notificaciones de Combustible',
      channelDescription: 'Notificaciones cuando llega Diesel o Gasolina',
      importance: Importance.high,
      priority: Priority.high,
      icon: '@mipmap/ic_launcher',
    );

    const iosDetails = DarwinNotificationDetails();

    const details = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _localNotifications.show(
      DateTime.now().millisecondsSinceEpoch ~/ 1000,
      titulo,
      mensaje,
      details,
      payload: json.encode(data),
    );
  }

  /// Manejar click en notificación
  static void _handleMessageOpenedApp(RemoteMessage message) {
    print('🔔 Usuario abrió la app desde notificación');
    final data = message.data;
    print('Datos: $data');

    // Navegar a la pantalla correspondiente
    // Navigator.push(...);
  }
}

/// Handler para mensajes en segundo plano (debe ser top-level)
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print('📱 Mensaje recibido en segundo plano: ${message.notification?.title}');
}
```

### 3.5. Inicializar Firebase en la app

Edita `lib/main.dart`:

```dart
import 'package:flutter/material.dart';
import 'services/firebase_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Inicializar Firebase
  await FirebaseService.inicializar();

  runApp(MyApp());
}

class MyApp extends StatefulWidget {
  @override
  _MyAppState createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  @override
  void initState() {
    super.initState();

    // Registrar token FCM (después del login)
    // Por ahora usamos user_id = 1 como ejemplo
    FirebaseService.registrarToken(1);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'FuelControl',
      home: HomeScreen(),
    );
  }
}
```

---

## ✅ PARTE 4: Probar la integración

### 4.1. Ejecutar la app Flutter

```bash
flutter run
```

Deberías ver en la consola:
```
✅ Token FCM: dXXXXXXXXXXXXXXXXXX...
✅ Token registrado en servidor
```

### 4.2. Verificar token en base de datos

```sql
SELECT * FROM fuelcontrol.device_tokens;
```

Deberías ver tu token registrado.

### 4.3. Enviar un correo de prueba con XML

Envía un correo con un XML de Diesel o Gasolina.

### 4.4. Verificar notificación

Deberías recibir una notificación push **incluso si cierras la app**.

---

## 🐛 Troubleshooting

### "Firebase no configurado"
- Verifica que existe `storage/app/firebase/firebase-credentials.json`
- Ejecuta: `ls -la storage/app/firebase/`

### "No hay dispositivos registrados"
- Verifica que Flutter registró el token: `SELECT * FROM device_tokens;`
- Revisa los logs de Flutter para ver si hubo errores

### "Token inválido"
- El sistema automáticamente desactiva tokens inválidos
- Re-instala la app para generar un nuevo token

### Notificaciones no llegan en iOS
- Necesitas configurar APNs en Firebase
- Necesitas un certificado de Apple Developer

---

## 📱 Endpoints API disponibles

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/combustible/fcm-token` | Registrar token FCM |
| POST | `/api/combustible/fcm-token/deactivate` | Desactivar token |

### Ejemplo de registro:

```bash
curl -X POST http://109.72.119.62/api/combustible/fcm-token \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "fcm_token": "dXXXXXXXXXXXXX...",
    "device_type": "android",
    "device_name": "Mi Teléfono"
  }'
```

---

## 🎉 ¡Listo!

Ahora las notificaciones de **Diesel** y **Gasolina** llegarán a Flutter **incluso con la app cerrada**! 🔥📱
