---
name: security-resilience
description: >-
  Metodología y directrices para la auditoría de seguridad ofensiva y defensiva en srvctl (Debian 13).
  Cubre inyecciones de comandos, validación estricta de entradas (IPv4, FQDN, paths), escalada de privilegios en sudoers,
  mitigación de fuerza bruta, aislamiento de procesos, protección CSRF y sanitización XSS.
---

# Directrices de Auditoría de Seguridad y Resiliencia para srvctl

Este documento establece los vectores de ataque y criterios de evaluación para certificar que el sistema sea resistente a vulnerabilidades y manipulación hostil.

## 1. Vectores de Inyección de Comandos (Command Injection)
- **Superficie de ataque:** Todo argumento transferido desde PHP (`panel_srvctl()`) hacia `srvctl-web-wrapper` y `bin/srvctl`.
- **Criterio estricto:** Ninguna variable del usuario puede concatenarse sin validación por lista blanca previa (`^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$`).
- **Verificación:** Probar inyecciones tipo `; rm -rf /`, `$(whoami)`, `` `id` ``, `| cat /etc/shadow`, `\n`, `&& reboot`.

## 2. Path Traversal y Manipulación de Archivos
- **Superficie de ataque:** Parámetros de archivo en `backup restore-db <dump>`, descargas `?action=download&project=<name>`, restauración de proyectos `backup rollback-project <proj> <snap>`.
- **Criterio estricto:** Rechazar categóricamente secuencias `../`, `/`, `..\\`, caracteres de control y caracteres nulos (`%00`).
- **Verificación:** Comprobar que solo se permitan nombres de archivo contenidos dentro de los directorios autorizados (`/var/backups/srvctl/`, `/var/www/`).

## 3. Seguridad de Sesiones, Autenticación y Fuerza Bruta
- **Superficie de ataque:** `POST action=login`, regeneración de tokens, cookies de sesión.
- **Criterio estricto:**
  - Cookies con flags `HttpOnly`, `Secure`, `SameSite=Lax`.
  - Rate limiting / throttling por IP (máximo 5 intentos fallidos en 15 minutos).
  - CSRF Token validado estrictamente en todo `POST` antes de ejecutar cualquier lógica.
  - Expiración de sesión tras inactividad (30 min).

## 4. Auditoría de Elevación de Privilegios (Sudoers y Wrapper)
- **Superficie de ataque:** `/etc/sudoers.d/srvctl-web` y `/usr/local/bin/srvctl-web-wrapper`.
- **Criterio estricto:**
  - `www-data` SOLO puede ejecutar `/usr/local/bin/srvctl-web-wrapper`.
  - El wrapper debe validar la variable `SRVCTL_CMD` con regex ancladas (`^...$`) y rechazar cualquier argumento no reconocido.
  - Ningún subcomando puede permitir la ejecución de subshells o comandos arbitrarios.
