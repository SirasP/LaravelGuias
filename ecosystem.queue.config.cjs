/**
 * Worker de la cola de LaravelGuias, gestionado por PM2.
 *
 * Procesa los correos del módulo de Solicitudes de Compra fuera de la
 * petición HTTP: la pantalla responde en milisegundos y el envío ocurre
 * después, sin que nadie quede mirando una página cargando.
 *
 *   pm2 start ecosystem.queue.config.cjs
 *   pm2 save
 */
module.exports = {
  apps: [
    {
      name: 'guias-queue',
      cwd: '/var/www/LaravelGuias',
      script: 'artisan',
      interpreter: 'php',
      args: [
        'queue:work',
        '--queue=default',
        // Tres intentos antes de darlo por fallido; el correo puede fallar por
        // un corte momentáneo de red y merece reintentos.
        '--tries=3',
        // Espera creciente entre reintentos: 10s, 30s y 60s.
        '--backoff=10,30,60',
        // Un trabajo que tarda más de 60s está colgado.
        '--timeout=60',
        // Descansa cuando no hay nada que hacer, en vez de consultar sin parar.
        '--sleep=3',
        // Se reinicia tras 1000 trabajos para no acumular memoria.
        '--max-jobs=1000',
        // Y tras una hora, por lo mismo.
        '--max-time=3600',
      ],
      autorestart: true,
      // El worker termina solo por --max-jobs/--max-time: PM2 lo relanza.
      // Sin esta espera, un fallo de arranque haría un bucle de reinicios.
      restart_delay: 5000,
      max_restarts: 50,
      watch: false,
      max_memory_restart: '256M',
      env: { NODE_ENV: 'production' },
      error_file: '/var/www/LaravelGuias/storage/logs/pm2-queue-error.log',
      out_file: '/var/www/LaravelGuias/storage/logs/pm2-queue-out.log',
      merge_logs: true,
      time: true,
    },
  ],
};
