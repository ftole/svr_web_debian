---
name: qa-automated-verifier
description: >-
  Arnés de verificación automatizada, pruebas de regresión, linters y validación de sintaxis para srvctl.
  Asegura que ningún cambio se integre sin pasar la suite completa de pruebas unitarias y análisis estático.
---

# Arnés de Control de Calidad y Verificación Automatizada (QA)

Este documento especifica el protocolo estricto de pruebas y control de calidad previo a la integración de cualquier cambio en el repositorio.

## 1. Verificación Estática de Sintaxis (Zero Syntax Errors)
Todo archivo modificado con extensión `.sh` o dentro de `bin/` debe validarse con:
```bash
bash -n *.sh bin/* core/*.sh modules/*/*.sh tests/*.sh
```
Si se reporta cualquier error de sintaxis o cierre de comillas/bloques, el cambio queda bloqueado.

## 2. Linters y Buenas Prácticas (ShellCheck)
Análisis de seguridad y prevención de errores sutiles:
```bash
shellcheck -S warning *.sh bin/* core/*.sh modules/*/*.sh tests/*.sh
```
- No se toleran advertencias (`SC2086` por falta de entrecomillado, variables sin inicializar, etc.).

## 3. Pruebas Unitarias de Validación y Caos
Ejecución de la suite completa de validadores de red y nombres:
```bash
bash tests/test_validator.sh
```
Debe obtenerse siempre un resultado de `PASS` en el 100% de los casos (IPv4, FQDN, nombres de proyecto y subdominios).

## 4. Pruebas del Wrapper de Seguridad
Validación de la lista blanca estricta del wrapper web:
```bash
bash tests/test_wrapper.sh
```
Certifica que todos los comandos legítimos sean admitidos (`ALLOW`) y que cualquier intento de inyección de comandos o manipulación de rutas sea rechazado (`DENY`).

## 5. Auditoría de Formato y Políticas del Repositorio
- **Finales de línea:** Garantizar formato LF en todo archivo nuevo o editado.
- **Archivos ignorados:** Verificar con `git status` que jamás se incluyan credenciales operativas ni `debian_pruebas.txt`.
- **Commits atómicos:** Confirmar que cada commit modifique exclusivamente un solo archivo con mensaje descriptivo en español.
