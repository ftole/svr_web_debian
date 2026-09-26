---
name: dashboard-bulletproof-ui
description: >-
  Estándares de diseño y desarrollo de interfaz en el Dashboard PHP de srvctl.
  Garantiza cumplimiento estricto de Content Security Policy (sin scripts inline),
  protección de formularios contra doble envío, tolerancia a caídas de servicios y degradación elegante.
---

# Directrices de Desarrollo de Interfaz Resiliente (Dashboard UI)

Este documento define las reglas de desarrollo para todas las vistas PHP, controladores y assets de JavaScript/CSS dentro de `templates/dashboard/`.

## 1. Cumplimiento Estricto de Content Security Policy (CSP)
- **Prohibición de scripts inline:** Jamás introducir bloques `<script>...</script>` dentro de archivos en `views/` o `partials/`. Toda lógica de cliente debe residir en `assets/js/` (`app.js`, `live.js`, `material-monitor.js`).
- **Prohibición de manejadores inline:** Queda estrictamente prohibido el uso de atributos HTML como `onclick="..."`, `onsubmit="..."` o `href="javascript:..."`. Todo evento debe registrarse mediante selectores semánticos (`addEventListener`) en el archivo JS correspondiente.
- **Prohibición de estilos inline dinámicos:** Los estilos deben gestionarse mediante clases utilitarias en `app.css`.

## 2. Inmunidad contra Doble Envío (Double Submission)
Todo formulario o botón que dispare una acción administrativa en el servidor debe incluir `data-loading="Mensaje..."`:
- Al hacer submit, `app.js` debe:
  1. Deshabilitar inmediatamente el botón emisor (`btn.disabled = true;`).
  2. Aplicar la clase de animación de carga (`loading`).
  3. Prevenir peticiones concurrentes duplicadas.
- En caso de fallo o resolución, restaurar el estado interactivo del botón en los bloques `.finally()`.

## 3. Desacoplamiento de Sesión para Procesos Largos
Las acciones de PHP que invocan procesos CLI de larga duración (ej. copias de seguridad, actualizaciones de paquetes o reinicio de servicios) deben liberar el cerrojo de sesión antes de la ejecución:
```php
session_write_close();
list($code, $output) = panel_srvctl(['backup', 'run']);
```
Esto previene que el sondeo en segundo plano de métricas (`live.js`) o la navegación del usuario quede bloqueada esperando que termine el proceso backend.

## 4. Degradación Elegante y Tolerancia a Servicios Caídos
- Si MariaDB o Redis están inactivos, las vistas (`database.php`, `overview.php`, `services.php`) jamás deben arrojar un `Fatal Error` ni pantalla blanca.
- El backend PHP debe capturar la falta de respuesta y retornar valores neutros (arrays vacíos, contadores en cero).
- El frontend debe pintar visualmente un estado de advertencia ("Servicio inactivo") y permitir su reactivación con un clic.
