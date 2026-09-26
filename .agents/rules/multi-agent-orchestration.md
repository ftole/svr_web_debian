# Regla: Orquestación y Colaboración Multi-Agente en srvctl

Esta directriz establece el protocolo obligatorio de trabajo concurrente mediante múltiples agentes y subagentes en este repositorio.

## 1. Principio Fundamental de Paralelismo
Ninguna tarea compleja (nuevas funcionalidades, refactorizaciones de infraestructura, modificaciones de seguridad o rediseños de UI) debe ser abordada de forma monolítica o secuencial por un único contexto. Se deben invocar subagentes especializados (`self`, `research` o agentes custom) para acelerar el desarrollo y mantener los contextos limpios y enfocados.

## 2. Roles Especializados de Agentes

1. **Agente Coordinador / Arquitecto:**
   - Descompone las directivas del usuario en especificaciones atómicas e independientes.
   - Asigna prioridades y sincroniza dependencias entre agentes.
   - Aplica los commits atómicos (un archivo por commit) y coordina la entrega final.

2. **Agente de Infraestructura y Scripts de Sistema:**
   - Trabaja sobre `bin/srvctl`, `core/` y `modules/`.
   - Implementa patrones defensivos (`set -euo pipefail`, trampas `trap`, escrituras atómicas, `flock`, pre-vuelos de configuración).
   - Asegura la paridad de Debian 13 y compatibilidad estricta con systemd.

3. **Agente de Frontend y Dashboard:**
   - Trabaja sobre `templates/dashboard/` (`app/`, `views/`, `partials/`, `assets/`, `index.php`).
   - Garantiza accesibilidad, responsividad y cumplimiento estricto de Content Security Policy (cero scripts y estilos inline).
   - Implementa estados de carga inmediatos (`data-loading`, deshabilitación de botones) y degradación elegante ante servicios caídos.

4. **Agente Auditor de Caos y Seguridad:**
   - Ejecuta revisiones destructivas simuladas (intentos de inyección de comandos, colisiones de nombres, bypass de wrappers).
   - Valida que las acciones del usuario no puedan causar denegación de servicio (DoS) ni auto-bloqueo (SSH / UFW).
   - Verifica la inmutabilidad de recursos críticos y esquemas de base de datos del sistema.

5. **Agente Verificador de Calidad (QA y Regresión):**
   - Ejecuta `bash -n`, `shellcheck -S warning`, pruebas unitarias (`tests/test_validator.sh`) y pruebas de wrapper (`tests/test_wrapper.sh`).
   - Verifica finales de línea LF obligatorios (`* text=auto eol=lf`).

## 3. Matriz de Validación Cruzada Obligatoria
Antes de considerar concluido cualquier cambio:
- Todo código de shell DEBE ser auditado por el Agente de Calidad (QA).
- Todo cambio que exponga interacción con el usuario (web o CLI) DEBE ser validado por el Agente de Caos y Seguridad.
- Ningún commit debe contener más de un archivo modificado o creado.
