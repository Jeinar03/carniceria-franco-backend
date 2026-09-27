# Venta de mostrador automática — estado del trabajo (24 sep 2026)

Este archivo es para retomar donde se quedó después de reiniciar la PC.
El plan completo (con las 3 fases) está guardado en:
`C:\Users\Jesus Einar\.claude\plans\que-onda-claude-pudieras-soft-babbage.md`

## Rama de trabajo
`feature/mostrador-auto-entrega` (creada desde `produccion`, ya tiene 1 commit).

```bash
cd F:\Proyectos\carniceria-franco-backend
git checkout feature/mostrador-auto-entrega
git log --oneline -3
```

## ✅ Fase 1 — YA HECHA y migrada (lo que pidió tu tío)

Commit: `9d1352c` — "Auto-entregar ventas de mostrador (Cliente General) sin pasar por Despachos"

Cambios:
- `app/Models/Sale.php`: constantes de estatus/estado_envío + método `marcarEntregadaMostrador()`.
- Migración `2026_09_24_000000_add_entregado_at_to_sales_table.php` → **ya corrida** en la base local `carniceria` (agrega `sales.entregado_at` y `sales.entregado_por`).
- `app/Http/Livewire/Despachos/DespachosController.php`: checkbox **"Entregado en mostrador"** en el modal Crear Orden (encendido por defecto con Cliente General, apagado con cliente elegido). Al aprobar una transferencia de Cliente General también se auto-entrega.
- Vistas de Ventas (`ventas-controller.blade.php`, `detail-form.blade.php`): ahora se ve el estado de entrega (Entregado/Enviado/Procesando/Pendiente).

**Falta por hacer de la Fase 1:** probarlo en el navegador (no se alcanzó a hacer antes de este corte). Pendiente de verificar:
1. Venta de Cliente General en efectivo → debe salir como **Entregado** y NO aparecer en Despachos.
2. Venta de cliente de mayoreo → debe seguir en Despachos como **Pendiente**, como siempre.
3. Venta de Cliente General por transferencia → queda pendiente y se entrega sola al aprobar la transferencia.

## ⏳ Fase 2 — NO iniciada
Ventana emergente con el tiempo de los pedidos pendientes (lo que tu tío dijo "para otra ocasión"). Ver detalle en el plan.

## ⏳ Fase 3 — NO iniciada
Seguridad crítica (cliente puede autoasignarse descuento de mayorista, el backend confía en el descuento que manda el navegador), corte de caja, reportes Excel, respaldos automáticos, etc. Ver detalle completo en el plan.

## Importante para cuando reinicies
- **MySQL de Laragon no arrancaba solo** (el ícono de Laragon no lo levantó); tuve que iniciar `mysqld.exe` a mano apuntando a `C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini` con datadir `C:\laragon\data\mysql-8.4`. Si al reiniciar Laragon tampoco lo levanta solo, avísame y lo repetimos.
- La migración de este cambio ya quedó aplicada, así que no hay que volver a correr `php artisan migrate` para esto (a menos que reviertas la base de datos).

## Siguiente paso al regresar
Levantar el panel (Laragon/Apache o `php artisan serve`) y hacer las 3 pruebas de arriba. Si todo sale bien, seguimos con la Fase 2 o con la seguridad de la Fase 3 (tú decides el orden).
