---
name: chaos-anti-breakage
description: >-
  Metodología para pruebas de caos, robustez y protección contra rupturas por interacción del usuario en srvctl (Debian 13).
  Garantiza que la interfaz web y los servicios backend sean inmunes a bloqueos, clics múltiples (double submission),
  errores no capturados (unhandled exceptions), caídas de servicios y corrupción de estado.
---

# Directrices de Auditoría a Prueba de Rupturas (Anti-Breakage) y Resiliencia

Este documento define las pruebas de robustez destinadas a garantizar que el usuario final no pueda provocar estados inconsistentes, pantallas blancas de la muerte (WSOD) ni bloqueos de la interfaz.

## 1. Protección contra Clics Múltiples y Doble Envío (Double Submission)
- **Riesgo:** El usuario hace doble clic o clic repetido en "Crear Proyecto", "Restaurar Backup" o "Reiniciar Servicios".
- **Requisito en UI (`app.js`):** Al disparar un formulario con `data-loading`, el botón de submit DEBE deshabilitarse de inmediato (`btn.disabled = true; btn.classList.add('loading')`) y se debe evitar la ejecución concurrente de la misma acción.
- **Requisito en Backend:** Manejar idempotencia o bloqueos temporales por sesión para operaciones críticas de creación o restauración.

## 2. Aislamiento y Tolerancia a Fallos de Servicios Caídos
- **Riesgo:** Si MariaDB o Redis caen o no responden, ¿el panel web colapsa o muestra una advertencia amigable?
- **Requisito:** Todas las llamadas a servicios externos (`systemctl`, `mariadb`, `redis-cli`, `openssl`) deben usar timeouts estrictos y bloques `try/catch` con valores predeterminados (fallbacks seguros), nunca lanzar `Fatal Error` ni excepciones no capturadas.
- **Aislamiento PHP-FPM:** El pool del Dashboard (`php8.4-fpm-dashboard.sock`) debe permanecer inalterado incluso si los proyectos de clientes colapsan por falta de memoria o timeouts en `www.sock`.

## 3. Resiliencia de Entradas y Casos Límite (Edge Cases)
- **Riesgo:** El usuario envía cadenas vacías, campos de 10.000 caracteres, emojis, etiquetas HTML, caracteres especiales, IPs inválidas (`256.0.0.1`), puertos fuera de rango (`999999`) o nombres de proyectos reservados (`prod`, `html`, `_dashboard`, `lost+found`).
- **Requisito:** Validación en dos niveles: validación client-side en formulario HTML5 (`pattern`, `maxlength`, `required`) y validación server-side categórica con mensajes descriptivos amigables.

## 4. Recuperación Automática y Manejo de Errores en UI
- **Riesgo:** Si el servidor pierde conectividad temporalmente o retorna HTTP 500/502/504, ¿la interfaz queda bloqueada con un modal o toast infinito?
- **Requisito:** Los bloques `.catch()` de Fetch en `app.js`, `live.js` y `material-monitor.js` deben restaurar siempre los estados de los botones, cerrar o desbloquear los modales, y mostrar un mensaje de error legible con botón de reintento.
