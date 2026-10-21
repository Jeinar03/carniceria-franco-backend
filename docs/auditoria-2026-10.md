AUDITORIA DEL BACKEND - CARNICERIA FRANCO
Fecha: 21 de octubre de 2026
Rama revisada: produccion (commit 1a713d8)
Alcance: seguridad, correctitud, pruebas y rendimiento del backend Laravel.

Como leer este documento
- Todo lo que aparece aqui lo verifique leyendo el codigo. Cuando algo depende
  de como esta configurado el servidor (y eso no se ve en el repo), lo marco
  como SOSPECHA.
- No corri las pruebas: el repo no trae la carpeta vendor y esta revision era
  solo de lectura.
- Las lineas son de la rama produccion en el commit de arriba.
- Gravedad: ALTA = puede costar dinero o mercancia, o dejar entrar a quien no
  debe, con clientes reales. MEDIA = falla en casos que si van a pasar, pero
  con dano acotado. BAJA = conviene arreglarlo, pero no urge.

Resumen: 4 hallazgos altos, 10 medios y 15 bajos, mas los huecos de pruebas
(seccion 3) y 4 puntos de rendimiento (seccion 4). Lo que esta bien hecho
viene al final de la seccion 1.


=====================================================================
1. SEGURIDAD Y CORRECTITUD
=====================================================================

---------------------------------------------------------------------
A1. Pedidos de Mercado Pago SIN PAGAR aparecen en Despachos listos para preparar
Gravedad: ALTA
Archivos:
- app/Models/Sale.php:143-147 (scopeEnColaDespacho)
- app/Http/Livewire/Despachos/DespachosController.php:1637-1644 (canManageDispatch)
- resources/views/livewire/despachos/despachos-controller.blade.php:191
- app/Http/Livewire/Despachos/PedidosPorTerminar.php:37-46
Que pasa:
  Al crear la preferencia (MercadoPagoController.php:327-339) se guarda una
  venta con estatus "pendiente" y estado_envio "Pendiente", aunque el cliente
  todavia no pague. La cola de Despachos solo quita las ventas canceladas, y
  el candado de "no despachar sin pago" solo existe para transferencias. Para
  Mercado Pago canManageDispatch regresa true siempre, asi que la pantalla
  muestra el boton "Gestionar Despacho".
Escenario:
  Un cliente llena el carrito, llega a Mercado Pago y cierra la ventana sin
  pagar. Mercado Pago no manda aviso, asi que la venta se queda pendiente
  para siempre. En Despachos sale como un pedido normal: el despachador la
  prepara, la marca como enviada y el mandadito sale con carne que nadie
  pago. Ademas, el aviso de "pedidos por terminar" la cuenta y se pone en
  rojo. Con cada carrito abandonado la cola crece.
Arreglo propuesto:
  En scopeEnColaDespacho, excluir las ventas de mercado_pago que no esten
  "completada" (o, mas general: solo meter a la cola ventas completadas o
  transferencias). En canManageDispatch, regresar false para mercado_pago si
  estatus no es "completada". Agregar una tarea programada que cancele las
  preferencias vencidas (la preferencia expira a las 24 h,
  MercadoPagoController.php:441-444).

---------------------------------------------------------------------
A2. El cliente puede cancelar desde la API una compra pagada, enviada o entregada
Gravedad: ALTA
Archivo: app/Http/Controllers/SalesController.php:430-498 (cancelPurchase)
Que pasa:
  Solo revisa que la venta sea del cliente y que no este ya cancelada
  (linea 452). No revisa el metodo de pago ni el estado de envio. Al cancelar,
  regresa todo el stock (463-468) y le resta a las estadisticas del cliente
  sin tope en cero (472-473).
Escenario:
  1) Un cliente paga con Mercado Pago, la venta queda completada y se
     prepara. El cliente llama a PUT /api/v1/ventas/{id}/cancelar: la venta
     desaparece de Despachos, el stock "regresa" aunque la carne ya salio y
     no hay reembolso en Mercado Pago. El cobro y el inventario dejan de
     cuadrar.
  2) Un pedido ya entregado se cancela despues: el inventario sube con
     mercancia que ya no existe.
  3) Una venta de Mercado Pago pendiente (que nunca sumo a las estadisticas)
     se cancela: numero_compras y total_compras quedan negativos.
Arreglo propuesto:
  Permitir que el cliente cancele solo si estatus es "pendiente", el metodo
  es transferencia sin aprobar o Mercado Pago sin cobro, y estado_envio sigue
  en "Pendiente". Todo lo demas, que lo cancele el Admin desde el panel.
  Usar max(0, ...) en las estadisticas, igual que VentasController.php:131-132.

---------------------------------------------------------------------
A3. Un usuario del panel "Bloqueado" puede seguir entrando
Gravedad: ALTA
Archivos:
- app/Http/Controllers/Auth/LoginController.php (no cambia credentials() ni
  authenticated())
- app/Http/Livewire/UsersController.php:335-344 (guarda status LOCKED)
- app/Http/Kernel.php:286-298 (ningun middleware revisa status)
Que pasa:
  Bloquear a un usuario solo cambia la columna status a LOCKED. El login usa
  el AuthenticatesUsers de Laravel tal cual, que solo compara correo y
  contrasena. Nada en el login ni en los middleware lee status.
Escenario:
  Un cajero deja de trabajar en la carniceria; el Admin lo marca como
  Bloqueado. El ex empleado entra con su contrasena de siempre y sigue
  creando pedidos, editando pedidos (con descuento hasta del 100 %) o
  validando transferencias. Si ya tenia una sesion abierta, ni siquiera tiene
  que volver a entrar.
Arreglo propuesto:
  En LoginController agregar credentials() con 'status' => 'ACTIVE' (o
  revisar en authenticated() y cerrar la sesion). Agregar un middleware en
  el grupo del panel que saque a quien este LOCKED, para cortar las sesiones
  ya abiertas. Mientras tanto, al bloquear a alguien tambien hay que
  cambiarle la contrasena.

---------------------------------------------------------------------
A4. Cobro aprobado que no se puede completar: solo queda en el log
Gravedad: ALTA
Archivo: app/Http/Controllers/MercadoPagoController.php:728-738 y 759-766
Que pasa:
  Si Mercado Pago aprueba el pago pero el monto no coincide, o ya no hay
  stock al momento de aprobar (el stock no se aparta al crear la
  preferencia), la venta se queda "pendiente" con mercadopago_status
  "approved" y solo se escribe un Log::error. El webhook responde 200 y nadie
  en el panel se entera.
Escenario:
  Dos clientes compran los ultimos 2 kg de arrachera al mismo tiempo. Los dos
  pagan. El segundo pago se aprueba pero ya no hay stock: al cliente le
  cobraron y su pedido no aparece como completado. Por el hallazgo A1, ese
  pedido de todos modos sale en Despachos, pero se ve igual que uno sin pagar.
Arreglo propuesto:
  Marcar estas ventas con un estado propio (por ejemplo "requiere_revision")
  y mostrarlas en el panel con alerta (y si se puede, mandar correo al Admin).
  Opcional: apartar el stock al crear la preferencia y liberarlo cuando
  venza.

---------------------------------------------------------------------
M1. Una venta cancelada revive si despues llega un pago aprobado
Gravedad: MEDIA
Archivo: app/Http/Controllers/MercadoPagoController.php:720-723 y 679-685
Que pasa:
  procesarPagoAprobado solo se salta las ventas que ya estan "completada".
  Si la venta esta "cancelada", la completa, descuenta stock y manda el
  correo de compra confirmada. Ademas, un intento rechazado cancela la venta
  (681-683), aunque el cliente todavia puede reintentar en la misma
  preferencia.
Escenario:
  El Admin cancela un pedido de Mercado Pago que parecia abandonado. Horas
  despues el cliente paga en OXXO con la ficha que ya tenia: la venta vuelve
  a completada sin que nadie lo note y el pedido reaparece en Despachos.
Arreglo propuesto:
  Si la venta esta cancelada por el Admin o por el cliente y llega un pago
  aprobado, no completarla: marcarla para revision (ver A4). Para el rechazo
  seguido de reintento, no cancelar en el primer rechazo; mejor dejarla
  pendiente y que la tarea de vencimiento (A1) la cancele.

---------------------------------------------------------------------
M2. Las transferencias apartan stock para siempre, incluso si se rechazan
Gravedad: MEDIA
Archivos:
- app/Http/Controllers/SalesController.php:206 (descuenta stock al crear)
- app/Http/Livewire/Despachos/DespachosController.php:1298-1303 (rechazar)
- app/Console/Kernel.php:24-27 (no hay tareas programadas)
Que pasa:
  El pedido por transferencia descuenta stock en cuanto se crea. Si el
  cliente nunca transfiere, o la transferencia se rechaza, el stock se queda
  apartado: rechazar solo deja la venta en "pendiente".
Escenario:
  Un cliente pide 5 kg de bistec por transferencia y nunca paga. En la tienda
  el bistec aparece "sin stock" hasta que el Admin se acuerda de cancelar a
  mano.
Arreglo propuesto:
  Tarea programada (schedule) que cancele, regresando el stock, las
  transferencias sin evidencia despues de X horas, y las rechazadas despues
  de X horas sin evidencia nueva. Mostrar en el panel cuanto falta para que
  venzan.

---------------------------------------------------------------------
M3. Un cliente suspendido o inactivo sigue comprando con su token
Gravedad: MEDIA
Archivos:
- app/Http/Controllers/CustomersController.php:149 (solo se revisa al iniciar sesion)
- app/Http/Livewire/Clientes/ClientesController.php:186 y 252 (cambian estatus sin revocar tokens)
- routes/api.php:98 (solo auth:sanctum)
- config/sanctum.php:49 (el token dura 7 dias)
Que pasa:
  Suspender a un cliente no le borra los tokens y ninguna ruta protegida
  revisa estatus.
Escenario:
  El Admin suspende a un cliente que dejo varios pedidos por transferencia
  sin pagar. Durante 7 dias ese cliente puede seguir creando pedidos y
  apartando stock.
Arreglo propuesto:
  Al cambiar el estatus a inactivo o suspendido, ejecutar $cliente->tokens()->delete().
  Agregar un middleware en el grupo auth:sanctum que rechace a los clientes
  que no esten activos.

---------------------------------------------------------------------
M4. El login y el registro de clientes no tienen limite propio
Gravedad: MEDIA (la parte de las IPs es SOSPECHA)
Archivos:
- routes/api.php:54-58 (sin throttle propio)
- app/Providers/RouteServiceProvider.php:58-60 (solo el general de 60 por minuto)
- app/Http/Middleware/TrustProxies.php:15 (confia en cualquier proxy)
Que pasa:
  Solo aplica el limite general de 60 peticiones por minuto por IP. El
  registro manda un correo por cada cuenta nueva (CustomersController.php:76).
  Con TrustProxies en '*', la IP sale del encabezado X-Forwarded-For, que
  cualquiera puede inventar si el servidor no esta detras de un proxy que lo
  reescriba.
Escenario:
  Un bot prueba contrasenas contra el correo de un cliente: 60 intentos por
  minuto, o sin limite si cambia el X-Forwarded-For en cada intento. Otro bot
  registra cuentas falsas y gasta los correos del SMTP hasta que el
  proveedor bloquea la cuenta (y entonces tampoco salen los correos de los
  pedidos).
Arreglo propuesto:
  Poner throttle:5,1 en login y registro, con una llave por correo + IP.
  Poner en TrustProxies solo la IP del proxy real (o dejarlo vacio si no hay
  proxy). Subir la contrasena minima a 8.

---------------------------------------------------------------------
M5. Folios duplicados cuando entran dos ventas al mismo tiempo
Gravedad: MEDIA
Archivos:
- app/Models/Sale.php:229-239 (generarFolio)
- database/migrations/2021_04_24_051547_create_sales_table.php:24 (index normal, no unique)
Que pasa:
  El folio se calcula leyendo la ultima venta del dia y sumando 1, sin
  bloqueo, y la base de datos no impide repetidos.
Escenario:
  A la hora de la comida entran juntos un pedido de la tienda y uno de
  mostrador; los dos quedan con el folio 20261021-0015. El folio es la
  referencia que el cliente escribe en su transferencia, asi que dos clientes
  pueden mandar transferencias con la misma referencia.
Arreglo propuesto:
  Armar el folio con el id despues de insertar (por ejemplo fecha + id) o
  calcularlo dentro de una transaccion con bloqueo. Agregar un indice unique
  en folio despues de limpiar los duplicados que ya existan.

---------------------------------------------------------------------
M6. En la tienda, la compra "por pesos" de un producto en gramos cobra 1000 veces mas
Gravedad: MEDIA (solo afecta si hay productos con unidad "gramo")
Archivo: app/Http/Controllers/SalesController.php:124-141
Que pasa:
  Para la unidad gramo, la cantidad se calcula como (monto / precio) * 1000,
  pero el subtotal de la venta se calcula como precio * cantidad, o sea
  monto * 1000. El renglon de detalle si guarda el monto correcto
  (SaleDetail.php, calcularSubtotal), asi que el total de la venta y la suma
  de sus renglones no cuadran. La unidad "gramo" existe en el panel
  (ProductosController.php:306).
Escenario:
  Un cliente pide "$50 de chorizo" y el chorizo esta dado de alta en gramos:
  la venta queda por $50,000 y se le pide una transferencia por ese monto. Si
  el precio esta por kilo, ademas se descuentan 1000 veces mas gramos.
Arreglo propuesto:
  Calcular el subtotal como el monto en pesos cuando viene monto_pesos (como
  en Despachos y en Mercado Pago) y definir si el precio de un producto en
  gramos es por gramo o por kilo. Agregar una prueba.

---------------------------------------------------------------------
M7. Editar un pedido de Mercado Pago pendiente lo completa sin descontar stock
Gravedad: MEDIA
Archivos:
- app/Http/Livewire/Despachos/DespachosController.php:1027-1029 (no acepta mercado_pago)
- app/Http/Livewire/Despachos/DespachosController.php:926 y 974-976
- app/Services/InventoryService.php:93-100 (adjustSaleExit regresa null sin movimiento)
Que pasa:
  Un pedido de Mercado Pago pendiente todavia no tiene salidas de
  inventario. Para poder guardarlo, la edicion obliga a cambiar el metodo de
  pago (mercado_pago no es valido). Al cambiarlo a efectivo, la venta queda
  "completada", pero adjustSaleExit no encuentra movimiento y no descuenta
  nada.
Escenario:
  El cliente no pudo pagar en linea y paga en el mostrador. El cajero edita
  el pedido a "efectivo": la venta queda completada y el inventario no baja.
  Otro caso: el cajero cambia las cantidades de un pedido de Mercado Pago que
  sigue pendiente; despues el cliente paga el monto original, los montos ya
  no coinciden y la venta nunca se completa (A4).
Arreglo propuesto:
  No permitir editar ventas de Mercado Pago que no esten completadas. Si se
  cambia el metodo de pago, crear las salidas que falten (llamar a
  addSaleExit cuando adjustSaleExit regrese null).

---------------------------------------------------------------------
M8. El cliente puede cambiar su contrasena sin escribir la actual, y cambiar de correo sin verificarlo
Gravedad: MEDIA
Archivo: app/Http/Controllers/CustomersController.php:220-286
Que pasa:
  PUT /clientes/{id} acepta una contrasena nueva sin pedir la actual y no
  cierra los otros tokens. Si cambia el correo, email_verified_at no se
  reinicia.
Escenario:
  Alguien usa el celular de un cliente con la tienda abierta: cambia la
  contrasena y el correo, y se queda con la cuenta (direccion, telefono e
  historial de compras).
Arreglo propuesto:
  Pedir current_password para cambiar la contrasena o el correo. Al
  cambiarlos, borrar los demas tokens. Si cambia el correo, poner
  email_verified_at en null y volver a mandar la verificacion.

---------------------------------------------------------------------
M9. Datos personales en los logs
Gravedad: MEDIA
Archivo: app/Http/Controllers/MercadoPagoController.php:154, 182-186, 380-388 y 503
Que pasa:
  Se escribe en el log el request completo del checkout, el nombre, el correo
  y el cuerpo completo del webhook. Hoy casi todo es nivel info y
  .env.production.example tiene LOG_LEVEL=error, pero ese mismo archivo
  (linea 56) dice que se suba a info para depurar pagos. Ademas el log es
  "single" (config/logging.php:40-44): un solo archivo que nunca se rota.
Escenario:
  Se sube LOG_LEVEL a info para revisar un pago y se olvida regresarlo:
  laravel.log junta correos, nombres y direcciones de todos los clientes, y
  el archivo crece sin limite. Si se baja un respaldo del log, los datos
  viajan con el.
Arreglo propuesto:
  Quitar correo, nombre, direccion y el request completo de los logs; dejar
  solo ids de venta y de pago. Cambiar el canal a "daily" con 14 dias.

---------------------------------------------------------------------
M10. Los mensajes de error internos llegan al cliente
Gravedad: MEDIA
Archivos (ejemplos):
- CustomersController.php:95 (manda el texto del error SQL)
- SalesController.php:247, 293, 332, 494, 838
- MercadoPagoController.php:495 y 923
- DespachosController.php:525 (en el panel)
Que pasa:
  Muchas respuestas 500 incluyen $e->getMessage().
Escenario:
  Si la base de datos falla al registrar a un cliente, la tienda le muestra
  la consulta SQL con nombres de tablas y columnas. Esa informacion le sirve
  a un atacante.
Arreglo propuesto:
  Responder un mensaje generico y dejar el detalle solo en el log. Excepcion:
  los mensajes de "stock insuficiente", que si conviene mostrar.

---------------------------------------------------------------------
B1. El webhook en modo no estricto: aceptable por ahora, conviene cerrarlo
Gravedad: BAJA
Archivo: app/Http/Controllers/MercadoPagoController.php:513-519 y 28-53
Que pasa:
  Con la firma invalida el aviso igual se procesa. No es un hueco de cobro:
  el estado y el monto siempre se consultan a Mercado Pago (obtenerPago) y el
  external_reference no se puede falsificar. Pero cualquiera puede mandar
  avisos sin firma, y cada uno provoca llamadas salientes a Mercado Pago. La
  firma tampoco revisa que el ts sea reciente (no hay proteccion contra
  repeticion).
Escenario:
  Un bot manda miles de avisos falsos al webhook. Cada uno consulta a
  Mercado Pago con el token de la tienda y puede topar el limite de la API
  justo cuando llega un pago real.
Arreglo propuesto:
  Cuando el log muestre una semana de avisos reales con firma "valida",
  activar MERCADOPAGO_FIRMA_ESTRICTA=true. Rechazar ts con mas de 5 minutos.
  Ponerle un throttle propio al webhook.

---------------------------------------------------------------------
B2. La evidencia de transferencia se guarda en el disco publico
Gravedad: BAJA (que se pueda ver por URL es SOSPECHA: depende de que exista public/storage en el servidor)
Archivo: app/Http/Controllers/SalesController.php:815
Que pasa:
  El comprobante (con nombre, banco y a veces la cuenta del cliente) se
  guarda en storage/app/public/transferencias. Si el servidor tiene hecho
  storage:link, cualquiera que sepa el nombre del archivo lo abre sin
  sesion. El nombre es aleatorio de 40 caracteres, lo cual lo hace dificil de
  adivinar, pero no imposible si se filtra.
Arreglo propuesto:
  Guardar en el disco "local" (privado). El endpoint showTransferEvidence ya
  sirve el archivo con control de permisos, asi que solo cambia el disco.

---------------------------------------------------------------------
B3. Volver a subir la evidencia "des-aprueba" una transferencia ya validada
Gravedad: BAJA
Archivo: app/Http/Controllers/SalesController.php:811-821
Que pasa:
  Subir evidencia de nuevo pone transferencia_estado en "pendiente" aunque ya
  estuviera aprobada, y Despachos vuelve a bloquear el pedido.
Escenario:
  El cliente sube otra foto "por si acaso" despues de que se aprobo: el
  pedido aprobado se atora en Despachos hasta que alguien lo valide otra vez.
Arreglo propuesto:
  No permitir subir evidencia si ya esta aprobada.

---------------------------------------------------------------------
B4. Imagenes de seguimiento sin limite de tamano ni de cantidad
Gravedad: BAJA
Archivo: app/Http/Controllers/CustomersController.php:312-353
Que pasa:
  Acepta un arreglo de imagenes en base64 sin limite y las guarda en la base
  de datos; tampoco revisa que sean imagenes.
Escenario:
  Una cuenta manda imagenes grandes en bucle y la base de datos crece hasta
  llenar el disco del hosting (y fallan los respaldos).
Arreglo propuesto:
  Limitar a 5 imagenes por peticion y unos 2 MB cada una, revisar que de
  verdad sean imagenes y ponerle throttle a la ruta. Si la funcion no se usa,
  quitar la ruta.

---------------------------------------------------------------------
B5. Las subidas del panel usan la extension que manda el navegador
Gravedad: BAJA (solo el Admin llega a estas pantallas)
Archivos: UsersController.php:297, Productos/ProductosController.php:361, Categorias/CategoriasController.php:196
Que pasa:
  Se valida que el contenido sea imagen, pero el archivo se guarda con
  getClientOriginalExtension(). Una imagen valida llamada foto.php se
  guardaria como <uuid>.php en el disco publico.
Arreglo propuesto:
  Usar $archivo->extension() (sale del contenido) o store() como en
  SitioController.php:212.

---------------------------------------------------------------------
B6. El catalogo publico acepta per_page y order_by sin limite
Gravedad: BAJA
Archivo: app/Http/Controllers/ProductsController.php:44-50 y 135
Que pasa:
  per_page no tiene tope y order_by acepta cualquier nombre de columna (si la
  columna no existe, da error 500). show() regresa productos inactivos.
Arreglo propuesto:
  Tope de per_page en 50, lista blanca de columnas para order_by y
  ->active() en show().

---------------------------------------------------------------------
B7. El log de acciones anonimo deja escribir cualquier cosa
Gravedad: BAJA
Archivos: app/Http/Controllers/LogController.php:26-28, app/Models/Logs.php:14-18
Que pasa:
  Crea el registro con $request->all(), y created_at es asignable; contenido
  no tiene limite de largo. Cualquiera puede llenar la tabla o fechar
  registros como quiera (aunque tiene throttle de 30 por minuto).
Arreglo propuesto:
  Quitar created_at de fillable, limitar contenido a unos 1000 caracteres y
  guardar solo los campos validados.

---------------------------------------------------------------------
B8. Un producto con precio 0 se puede vender gratis o romper el calculo
Gravedad: BAJA
Archivos:
- Productos/ProductosController.php:303-304 (precio y precio_oferta min:0)
- SalesController.php:127 y MercadoPagoController.php:214 (dividen entre el precio)
Que pasa:
  El panel acepta precio 0 o precio de oferta 0. En la tienda eso da un
  producto gratis o, en la compra por pesos, una division entre cero. Esa
  division lanza DivisionByZeroError, que no la atrapa el catch (\Exception),
  y la transaccion se queda abierta hasta que termina la peticion.
Arreglo propuesto:
  Validar precio y precio_oferta con gt:0 y, en los controladores, rechazar
  los productos con precio final en 0.

---------------------------------------------------------------------
B9. El horario no soporta cierres despues de medianoche
Gravedad: BAJA
Archivo: app/Models/SiteConfig.php:75-79
Que pasa:
  Si la hora de cierre es menor que la de apertura (por ejemplo 18:00 -
  01:00), la tienda queda cerrada todo el dia. Hoy no aplica a una
  carniceria, pero el panel deja capturarlo.
Arreglo propuesto:
  Si fin < inicio, abierto = minutos >= inicio o minutos <= fin. O validar en
  el panel que el cierre sea despues de la apertura.

---------------------------------------------------------------------
B10. El total de Ventas suma pedidos sin pagar
Gravedad: BAJA
Archivo: app/Http/Livewire/Ventas/VentasController.php:173-184
Que pasa:
  "Total ventas" y "Ventas hoy" excluyen solo las canceladas: tambien suman
  las transferencias sin validar y los Mercado Pago sin pagar.
Arreglo propuesto:
  Sumar solo las ventas con estatus "completada", o mostrar las pendientes
  aparte.

---------------------------------------------------------------------
B11. El mismo producto dos veces en un pedido se pierde al editar
Gravedad: BAJA
Archivo: DespachosController.php:621 y 896 (keyBy por product_id)
Que pasa:
  La API de la tienda deja mandar el mismo producto en dos renglones. Al
  editar, el carrito se arma por producto y uno de los renglones queda fuera
  de la edicion: no se borra, pero el total se recalcula sin el.
Arreglo propuesto:
  En la tienda, juntar los renglones repetidos antes de guardar (o
  rechazarlos en la validacion).

---------------------------------------------------------------------
B12. Venta por pieza con cantidades decimales
Gravedad: BAJA
Archivos: SalesController.php:82, MercadoPagoController.php:160
Que pasa:
  La cantidad minima es 0.01 para cualquier unidad, asi que se pueden pedir
  0.3 piezas o 0.3 paquetes.
Arreglo propuesto:
  Exigir cantidad entera cuando la unidad sea pieza, paquete o caja.

---------------------------------------------------------------------
B13. forceScheme('https') depende de env() fuera de config
Gravedad: BAJA (SOSPECHA: solo falla si en el servidor se corre config:cache)
Archivo: app/Providers/AppServiceProvider.php:27-29
Que pasa:
  Con la configuracion en cache, env() regresa null fuera de los archivos de
  config/, y las URL generadas (links firmados de verificacion, evidencias)
  saldrian con http.
Arreglo propuesto:
  Usar config('app.env') o app()->environment('production').

---------------------------------------------------------------------
B14. El filtro anti-inyeccion bloquea texto normal
Gravedad: BAJA
Archivo: app/Http/Middleware/DetectInjectionAttempts.php:16-24
Que pasa:
  Cualquier texto con dos acentos graves (`asi`) o con $( ... ) se rechaza
  con 403. Por ejemplo, unas notas de pedido escritas desde un teclado que
  mete acentos graves. La proteccion real contra SQL la da Eloquent; este
  filtro casi no agrega seguridad.
Arreglo propuesto:
  Dejarlo solo para registrar en el log (sin bloquear), o quitar esos dos
  patrones.

---------------------------------------------------------------------
B15. Sin limite de largo en notas y textos libres
Gravedad: BAJA
Archivos: SalesController.php:84, MercadoPagoController.php:164, CustomersController.php:18-28 y 221-232
Que pasa:
  notas, direccion, nombre y los demas campos de texto son 'string' sin max.
  Un texto muy largo truena el INSERT (error 500) o llena los correos y la
  pantalla de Despachos.
Arreglo propuesto:
  Agregar max: notas 500, nombre 100, direccion 255, etc.

---------------------------------------------------------------------
Lo que esta bien (para no tocarlo)
- Los precios, el descuento de mayorista y el IVA siempre se calculan en el
  servidor; el monto del navegador se ignora (MercadoPagoController.php:
  203-323, SalesController.php:116-161). Lo cubren pruebas.
- El estado y el monto del pago se confirman contra la API de Mercado Pago,
  con bloqueo de la venta: el webhook y el regreso del cliente no descuentan
  stock dos veces (MercadoPagoController.php:717-787).
- Las salidas de inventario bloquean el producto y revisan existencia dentro
  de la transaccion (InventoryService.php:327-333), y el unique de
  inventory_movements impide doble salida por renglon. No vi forma de dejar
  stock negativo por carreras en los flujos de venta.
- Las rutas del panel tienen candado por rol, y el candado se vuelve a
  aplicar en las acciones de Livewire (AppServiceProvider.php:32); crear,
  editar y cancelar revisan el rol dentro del componente.
- La API revisa que el recurso sea del cliente en historial, detalle,
  cancelar, evidencia y encuestas.
- El registro y el perfil no dejan al cliente cambiarse tipo_cliente,
  descuento, credito ni estatus (asignacion masiva controlada).
- Las credenciales de Mercado Pago se guardan cifradas
  (MercadoPagoSetting.php:35-62). No encontre claves, contrasenas ni datos
  personales en el repo; .env.production.example trae los campos vacios.


=====================================================================
2. CASOS BORDE DE LA FORMA DE ENTREGA (revisados)
=====================================================================
- Pedido "recoger" y "mandadito": la tienda, Mercado Pago y el panel validan
  la forma de entrega contra Sale::TIPOS_ENTREGA. Sin dato, queda mandadito.
  Esta bien y tiene pruebas (FormaEntregaTest).
- Pedido de mostrador con fecha de entrega futura: no se auto-entrega
  (Sale.php:206). Bien.
- Pedido programado con transferencia rechazada: sigue apareciendo en la
  pestana de Programados de Despachos (el filtro de rechazadas solo esta en
  PedidosPorTerminar.php:40-43). Menor; conviene usar el mismo filtro en
  las dos pantallas.
- Mercado Pago no recibe fecha_entrega (MercadoPagoController.php:156-166):
  un pedido pagado en linea nunca puede ser programado. Si eso no es a
  proposito, la tienda pierde ese dato sin avisar.


=====================================================================
3. PRUEBAS: FLUJOS CRITICOS SIN TEST
=====================================================================
Busque cada flujo en tests/Feature. Estos no tienen prueba:
1. Que una venta de Mercado Pago pendiente NO aparezca en Despachos ni en el
   aviso de pedidos por terminar (A1).
2. Cancelar desde la API una venta propia: solo existe la prueba de que no se
   puede cancelar una ajena (ApiOwnershipTest.php:85). No hay prueba de
   cancelar pagadas, enviadas o entregadas, ni de que regrese el stock (A2).
3. Que un usuario LOCKED no pueda iniciar sesion en el panel (A3).
4. Que un cliente inactivo o suspendido no pueda usar su token (M3).
5. Editar un pedido: cambiar cantidades, quitar y agregar productos, y que el
   stock cuadre despues. Hoy updateOrder solo se prueba para cambiar la forma
   de entrega (FormaEntregaTest.php:179-193) y para el candado de rol.
6. Rechazar una transferencia y que pasa con el stock (M2).
7. Compra por pesos de un producto en gramos en la API de la tienda (M6).
8. Folios unicos (M5) y stock con dos ventas al mismo tiempo. Las carreras
   del webhook si estan probadas.
9. Limites de intentos en login y registro (M4): no hay ninguna prueba con
   throttle.
10. Pago aprobado sobre una venta cancelada (M1).
11. Cancelacion desde el panel (VentasController) regresando el stock de cada
    renglon: solo se prueba quien puede cancelar.

Nota: no pude correr la suite (falta vendor). Antes de cada cambio, correr
"php artisan test" en local.


=====================================================================
4. RENDIMIENTO DEL PANEL
=====================================================================

---------------------------------------------------------------------
R1. Despachos hace ~150 consultas extra en cada refresco (N+1 del stock)
Gravedad: MEDIA
Archivos:
- DespachosController.php:1477-1515 (dos listas de 25 productos sin withInventoryStock)
- resources/views/livewire/despachos/create-order-modal.blade.php:146-155
- resources/views/livewire/despachos/edit-order-modal.blade.php:106-115
- app/Models/Product.php:83-106 (cada lectura de ->stock hace una consulta)
Que pasa:
  Las ventanas de crear y editar pedido siempre se pintan (para Admin y
  Cajero). Cada producto lee ->stock 3 veces y cada lectura es una consulta
  SUM sobre inventory_movements: 25 x 3 x 2 = unas 150 consultas en cada
  render. Y Livewire vuelve a renderizar con cada tecla en el buscador y con
  cada clic.
Arreglo propuesto:
  Agregar ->withInventoryStock() a las dos consultas (ya se usa en
  ProductosController.php:78 e InventarioController.php:222). Es un cambio de
  dos lineas.

---------------------------------------------------------------------
R2. El aviso de pedidos por terminar carga toda la cola cada 30 segundos
Gravedad: BAJA (sube a MEDIA mientras no se arregle A1)
Archivos: PedidosPorTerminar.php:37-46, resources/views/livewire/despachos/pedidos-por-terminar.blade.php:1
Que pasa:
  Cada pestana abierta del panel trae todos los pedidos de la cola, con su
  cliente, cada 30 s, aunque solo muestra 15. Con A1, los carritos
  abandonados de Mercado Pago nunca salen de la cola y la consulta crece sin
  fin.
Arreglo propuesto:
  Hacer un count() para el numero y un limit(15) para la lista. Arreglar A1.

---------------------------------------------------------------------
R3. Filtros con whereDate y LIKE '%...%' no usan indices
Gravedad: BAJA
Archivos: Sale.php:137-156 (whereDate sobre fecha_entrega), Sale.php:232 (whereDate sobre created_at, que no tiene indice, en cada venta nueva), DespachosController.php:1603-1635 y VentasController.php:193-205
Que pasa:
  whereDate(columna) envuelve la columna en DATE() y MySQL ya no usa el
  indice. La busqueda por cliente hace LIKE con comodin al inicio, dentro de
  orWhereHas. Con pocos miles de ventas no se nota; con decenas de miles, si.
Arreglo propuesto:
  Comparar con rangos (fecha_entrega >= hoy 00:00) en lugar de whereDate.
  Para el folio, ver M5.

---------------------------------------------------------------------
R4. Recomendaciones del cliente con consultas en bucle
Gravedad: BAJA
Archivo: SalesController.php:563-712
Que pasa:
  Hace Product::find y lee ->category dentro de bucles (el mismo producto se
  busca hasta 4 veces), y cada ->stock es otra consulta: unas 60-80
  consultas por llamada.
Arreglo propuesto:
  Cargar los productos una sola vez con
  whereIn(...)->with('category')->withInventoryStock() y reutilizarlos.


=====================================================================
5. PLAN POR FASES
=====================================================================

Fase 1 - Antes de abrir a clientes reales (1 o 2 dias)
1. A1: sacar de Despachos los Mercado Pago sin pagar y bloquear su despacho.
2. A2: limitar la cancelacion del cliente a pedidos pendientes y sin
   preparar.
3. A3: que los usuarios bloqueados no entren y que se les cierre la sesion.
4. A4 y M1: marcar como "requiere revision" los cobros aprobados que no se
   pudieron completar y los que llegan sobre una venta cancelada, y
   mostrarlos en el panel.
5. M4: throttle en login y registro, y revisar TrustProxies con el hosting.
6. Escribir las pruebas 1, 2, 3 y 10 de la seccion 3 junto con estos
   arreglos.

Fase 2 - Primeras dos semanas
7. M2 y tarea de vencimiento de A1: un comando programado que cancele las
   transferencias y las preferencias de Mercado Pago abandonadas,
   regresando el stock. Confirmar que el cron del servidor corre
   "schedule:run" (hoy solo corre los respaldos).
8. M3: revocar tokens al suspender a un cliente y agregar el middleware de
   estatus.
9. M5: folio unico.
10. M7: no permitir editar Mercado Pago sin cobrar; crear las salidas de
    stock que falten.
11. M6: si hay productos en gramos, arreglarlo ya; si no, quitar "gramo" de
    las opciones del panel hasta arreglarlo.
12. R1: withInventoryStock en Despachos (rapido y se nota).
13. M10 y M9: mensajes genericos al cliente, logs sin datos personales y log
    diario.

Fase 3 - Primer mes
14. M8: pedir la contrasena actual para cambiar contrasena o correo.
15. B1: activar la firma estricta del webhook cuando el log muestre firmas
    validas.
16. B2 y B3: evidencia en disco privado y no re-subir evidencia aprobada.
17. R2, R3 y R4.
18. El resto de los hallazgos bajos (B4 a B15) y las pruebas 4 a 9 y 11 de la
    seccion 3.
