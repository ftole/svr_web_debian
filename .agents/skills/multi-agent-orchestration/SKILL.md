---
name: multi-agent-orchestration
description: >-
  Metodología y protocolo para la invocación, coordinación y validación cruzada de múltiples agentes
  en el ciclo de desarrollo de srvctl. Garantiza paralelismo eficiente, contextos limpios y cero regresiones.
---

# Protocolo de Orquestación y Coordinación Multi-Agente

Este documento describe el flujo de trabajo operativo para ejecutar tareas con múltiples agentes concurrentes en el entorno de desarrollo de `srvctl`.

## 1. Principio de Especialización y División de Trabajo
Cuando se recibe una instrucción que abarca múltiples capas del sistema (ej. un nuevo subcomando CLI que requiere cambios en scripts de sistema, wrapper de seguridad, vistas en el dashboard PHP y pruebas automatizadas), el agente orquestador DEBE dividir el trabajo en roles concurrentes:

```
                      ┌───────────────────────────────┐
                      │      Agente Orquestador       │
                      │  (Desglose, Plan y Consenso)  │
                      └──────────────┬────────────────┘
             ┌───────────────────────┼───────────────────────┐
             ▼                       ▼                       ▼
┌─────────────────────────┐ ┌─────────────────┐ ┌─────────────────────────┐
│  Agente Infra / Shell   │ │   Agente UI     │ │  Agente Caos y Seg    │
│ (bin/srvctl, modules/)  │ │ (Dashboard PHP) │ │ (Auditoría ofensiva)   │
└────────────┬────────────┘ └────────┬────────┘ └────────────┬────────────┘
             └───────────────────────┼───────────────────────┘
                                     ▼
                      ┌───────────────────────────────┐
                      │    Agente QA y Verificador    │
                      │   (bash -n, unit tests, CI)   │
                      └───────────────────────────────┘
```

## 2. Invocación Asíncrona sin Sondeo (No-Polling Pattern)
Al lanzar subagentes mediante `invoke_subagent`:
- Proporcionar un prompt conciso, claro y con el contexto exacto de los archivos relevantes.
- NO entrar en bucles de comprobación (`status`). La plataforma despierta automáticamente al agente padre cuando un subagente emite su mensaje o completa su tarea.
- Aprovechar el tiempo de espera para preparar documentación, analizar dependencias o estructurar la siguiente fase.

## 3. Matriz de Validación Cruzada
Ningún código desarrollado por un subagente de implementación se acepta de inmediato:
1. **Paso 1 (Implementación):** El agente de Shell o UI genera el cambio respetando las reglas de estilo y robustez.
2. **Paso 2 (Auditoría Ofensiva):** El agente de Caos y Seguridad evalúa si el cambio introduce vectores de inyección, roturas por clics dobles o riesgo de auto-bloqueo.
3. **Paso 3 (Aprobación de Calidad):** El agente de QA corre `bash -n`, `shellcheck`, linters y la suite `tests/test_validator.sh`.
4. **Paso 4 (Commit Atómico):** El orquestador integra el archivo validado y genera un commit atómico individual.
