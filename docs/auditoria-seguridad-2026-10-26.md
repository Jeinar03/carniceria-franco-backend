AUDITORIA DE SEGURIDAD DEL BACKEND - CARNICERIA FRANCO
Fecha: 26 de octubre de 2026
Repositorio: Jeinar03/carniceria-franco-backend
Codigo revisado: rama main, commit 7157a76 (Merge pull request #28)
Metodo: skill oficial de Cloudflare security-audit, perfil estandar, sus 6 fases (reconocimiento,
  busqueda por oleadas, validacion, salida estructurada, verificacion independiente y reporte).
Regla aplicada: quien encuentra un hallazgo nunca es quien lo confirma. Un verificador distinto
  intenta desmentirlo; despues otro verificador nuevo revisa el registro final.

COMO LEER ESTE DOCUMENTO
- CONFIRMADO significa: el recorrido completo esta en el codigo y un verificador independiente no
  pudo desmentirlo. NO se ejecuto la aplicacion: el repo no trae la carpeta vendor y la skill no
  permite descargar dependencias. Cada 'confirmado' sale de leer el codigo y del comportamiento de
  las versiones fijadas en composer.lock (Laravel 8.83.29, Livewire 2.12.8, Sanctum 2.15.1). Donde
  el resultado se pudo comprobar con una herramienta del sistema (el analizador de URL de PHP y el
  de Node), se hizo y esta indicado.
- POR COMPROBAR significa: el codigo apunta a un problema, pero falta un dato que no esta en el repo
  (como esta configurado el servidor de Hostinger, que contrasenas hay en la base de produccion).
  Esos no tienen gravedad. Cada uno trae la comprobacion que puede hacer el dueno sin tocar nada.
- DESCARTADO significa: un verificador mostro que no hay un atacante que gane algo que no deba
  tener.
- Gravedad: ALTA = control de seguridad derrotado por completo con consecuencias reales (por
  ejemplo, tomar la cuenta de otro cliente). MEDIA = una regla importante se salta, con dano
  acotado. BAJA = fallo menor o que exige condiciones raras.
- Las lineas son del commit 7157a76. Los 4 commits locales de Einar en la rama fix/dia-mexico no
  estan en main y NO se revisaron: si arreglan algo de esto, el cambio todavia no esta en main.

====================================================================================================
1. RESUMEN
====================================================================================================
1. Se reviso main (commit 7157a76) con la skill de Cloudflare: 14 hallazgos confirmados (1 alto, 5 medios, 8 bajos), 8 por comprobar y 3 descartados; la app no se ejecuto, todo sale de leer el codigo.
2. Lo mas urgente: todo cliente dado de alta desde el panel queda con la contrasena fija 'defaultpassword' y cualquiera que sepa su correo entra a su cuenta y la toma (S-01).
3. Tambien confirmados: un empleado marcado Bloqueado sigue entrando (S-04), un cliente puede cancelar pedidos ya pagados o entregados (S-03) y los pedidos de Mercado Pago sin pagar se pueden surtir y enviar (S-02).
4. De los 29 hallazgos de la auditoria del 2026-10-20 ninguno esta corregido del todo (B15 solo en parte); esta auditoria agrega 10 registros nuevos y descarta 3 como no seguridad.
5. La cobertura quedo INCOMPLETA: el critico final aun propuso 2 zonas mas (diferidas) y 8 puntos dependen de datos de Hostinger que no estan en el repo; comprueba hoy V-01 (contrasenas '123' en la base).

====================================================================================================
2. HALLAZGOS CONFIRMADOS (14)
====================================================================================================

Tabla (el detalle de cada uno esta mas abajo)
----------------------------------------------------------------------------------------------------
ID    GRAVEDAD  ARCHIVO Y LINEA / TITULO
----------------------------------------------------------------------------------------------------
S-01  ALTA      app/Http/Livewire/Clientes/ClientesController.php:140
                Clientes creados desde el panel quedan con la contrasena fija 'defaultpassword'
                utilizable en el login publico de la API
S-02  MEDIA     app/Http/Livewire/Despachos/DespachosController.php:1692
                Pedidos de Mercado Pago sin pagar entran a la cola de Despachos y se pueden surtir y
                enviar
S-03  MEDIA     app/Http/Controllers/SalesController.php:453
                El cliente puede cancelar por la API una venta ya cobrada o entregada y reintegrar
                su stock
S-04  MEDIA     app/Http/Controllers/Auth/LoginController.php:22
                El estatus LOCKED de los usuarios del panel no impide iniciar sesion ni usar el
                panel
S-05  MEDIA     app/Http/Controllers/CustomersController.php:156
                Suspender o inactivar un cliente no revoca ni bloquea sus tokens Sanctum vigentes
S-06  MEDIA     app/Http/Controllers/SalesController.php:81-83
                Un cliente recien registrado puede retener todo el inventario con pedidos por
                transferencia que nunca paga
S-07  BAJA      app/Http/Controllers/CustomersController.php:242
                Cambio de correo y contrasena del cliente sin contrasena actual ni revocacion de
                otros tokens
S-08  BAJA      app/Http/Controllers/CustomersController.php:102
                Las respuestas de error de la API publica incluyen el texto interno de la excepcion
                (SQL, esquema, conexion)
S-09  BAJA      app/Http/Controllers/LogController.php:30-33
                La bitacora anonima permite atribuir acciones falsas a cualquier cliente y fijarles
                la fecha
S-10  BAJA      app/Http/Livewire/Despachos/DespachosController.php:1286
                Aprobar o rechazar una transferencia reactiva una venta ya cancelada
S-11  BAJA      app/Http/Livewire/Despachos/DespachosController.php:1690-1697
                Despachos permite surtir y enviar una venta ya cancelada (gate sin estatus y sin
                lock frente a la cancelacion)
S-12  BAJA      app/Http/Controllers/SalesController.php:803-823
                El cliente puede reemplazar y borrar el comprobante de transferencia ya aprobado y
                la constancia de quien lo valido
S-13  BAJA      app/Rules/UbicacionGoogleMaps.php:19
                El validador de link de Google Maps se evade con una barra invertida y el panel
                enlaza a un host arbitrario
S-14  BAJA      app/Models/Sale.php:85
                La API serializa Sale y Customers completos y entrega al cliente notas internas del
                personal e ids de empleados
----------------------------------------------------------------------------------------------------

----------------------------------------------------------------------------------------------------
S-01  GRAVEDAD ALTA  -  Clientes creados desde el panel quedan con la contrasena fija 'defaultpassword' utilizable en el login publico de la API
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Livewire/Clientes/ClientesController.php:140 (login:
  app/Http/Controllers/CustomersController.php:146-167)
  Frente a la auditoria anterior: NUEVO
  Que pasa:
    ClientesController::Store asigna a todo cliente dado de alta desde el panel Clientes la
    contrasena Hash::make('defaultpassword'). El formulario del panel no tiene campo de contrasena,
    el cliente no recibe correo ni enlace de activacion, y Update solo cambiaria la contrasena via
    $this->password, que no es propiedad declarada ni ligada en la vista, asi que la contrasena fija
    permanece. El login anonimo POST /api/v1/clientes/login (y el alias usuarios/login) busca por
    correo, hace Hash::check y, si el estatus es 'activo' (valor por defecto), emite un token
    Sanctum de 7 dias y devuelve el registro del cliente. No exige correo verificado. Cualquier
    persona que conozca o adivine el correo de un cliente creado en el panel obtiene su sesion: lee
    su PII (nombre, direccion, telefono, RFC, notas, descuento preferencial), su historial y
    evidencias, compra a su nombre con su descuento de mayorista y, via PUT /api/v1/clientes/{id},
    cambia correo y contrasena sin conocer la actual, tomando la cuenta de forma permanente.
  Como se explota:
    Quien: Usuario anonimo de Internet que conoce el correo de un cliente dado de alta por el
    personal en el panel Clientes.
    Peticion de ejemplo: POST /api/v1/clientes/login Content-Type: application/json
    {"correo":"cliente.panel@example.test","password":"defaultpassword"}
    Resultado esperado: Derivado del codigo fuente y del comportamiento de Hash/Sanctum fijado en
    composer.lock (laravel/framework v8.83.29, sanctum v2.15.1); no ejecutado (vendor ausente).
    Store guarda bcrypt('defaultpassword'); login (135-178) devuelve 200 con token Sanctum y el
    objeto cliente; update (286-297) acepta nuevo correo y contrasena sin prueba de la anterior.
  Por que esta gravedad:
    Probabilidad MEDIA: Solo requiere el correo de un cliente creado en el panel y una contrasena
    identica para todos ellos y facil de adivinar; el limite de 60/min por IP no frena un intento
    dirigido. Depende de que el personal use el panel para dar de alta clientes.
    Impacto ALTA: Toma completa de cuentas de otros clientes: PII, historial y comprobantes, pedidos
    a su nombre con su descuento de mayorista y cambio permanente de correo y contrasena.
  Prueba del verificador:
    [verif-23ead8c36f; revision final final-23ead8c36f] Releyo el codigo: Store guarda
    bcrypt('defaultpassword') para todo cliente creado en el panel, el formulario del panel no tiene
    campo de contrasena y el login publico acepta esa contrasena si el estatus es 'activo' y entrega
    un token de 7 dias. Es el unico lugar donde aparece ese literal y la ruta hermana del mostrador
    si usa una contrasena aleatoria (DespachosController.php:266). La revision final lo aprobo sin
    cambios.
  Arreglo propuesto:
    Generar una contrasena aleatoria no divulgada por cliente (Str::random(40)) o dejar la cuenta
    sin credencial utilizable y enviar un enlace de activacion/restablecimiento de un solo uso;
    migrar los clientes existentes cuyo hash verifique 'defaultpassword' a una contrasena aleatoria
    y revocar sus tokens. Opcionalmente exigir la contrasena actual en el cambio de
    contrasena/correo de la API.
    Archivo: app/Http/Livewire/Clientes/ClientesController.php
      $payload['password'] = Hash::make(Str::random(40));

----------------------------------------------------------------------------------------------------
S-02  GRAVEDAD MEDIA  -  Pedidos de Mercado Pago sin pagar entran a la cola de Despachos y se pueden surtir y enviar
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Livewire/Despachos/DespachosController.php:1692 y
  app/Models/Sale.php:211-215
  Frente a la auditoria anterior: A1 (sigue)
  Que pasa:
    Al iniciar el checkout de Mercado Pago, createPreference crea y confirma (commit) una venta con
    metodo_pago mercado_pago, estatus pendiente y estado_envio Pendiente antes de que exista cobro.
    La cola de Despachos (Sale::scopeEnColaDespacho) solo excluye canceladas y el gate
    canManageDispatch solo exige validacion para transferencias, por lo que un pedido de Mercado
    Pago que nunca se pago aparece en la lista igual que uno cobrado (total en verde, URGENTE tras 3
    horas, contado en el aviso PedidosPorTerminar) y el Despachador puede abrirlo, marcar sus
    productos y enviarlo; enviarPedido deja estado_envio Enviado y notifica al cliente. Ni la tabla
    ni el modal muestran el estatus de pago. Como en este sistema las ventas no-transferencia
    creadas en el panel nacen completadas (DespachosController.php:471) y la API solo acepta
    transferencia (SalesController.php:80), una venta no-transferencia pendiente en la cola solo
    puede ser un Mercado Pago impago, pero el panel no lo distingue. Un cliente registrado puede
    generar pedidos a domicilio sin pagar con solo abandonar el checkout. Ademas, como el stock solo
    se descuenta en procesarPagoAprobado, el envio no deja salida de inventario.
  Como se explota:
    Quien: Cliente registrado de la tienda que inicia el checkout de Mercado Pago y no paga.
    Peticion de ejemplo: POST /api/v1/mercadopago/create-preference con Authorization: Bearer <token
    cliente de prueba> y cuerpo {"productos":[{"product_id":1,"cantidad":2}],"metodo_pago":"mercado_
    pago","tipo_entrega":"mandadito","acepta_aviso_mandadito":true,"direccion_entrega":"Calle de
    prueba 123"}
    Resultado esperado: Derivado por trazado de codigo fuente en el commit 7157a76, no ejecutado
    (vendor/ ausente). El control de flujo muestra que la venta pendiente de Mercado Pago pasa los
    filtros de scopeEnColaDespacho y canManageDispatch, que
    openModal/toggleProductDespacho/enviarPedido no revisan estatus ni el pago, y que enviarPedido
    deja estado_envio Enviado y llama a OrderNotificationService con la venta aun pendiente sin
    cobro y sin salida de stock.
  Por que esta gravedad:
    Probabilidad MEDIA: Cualquier cliente registrado crea el pedido impago con solo abandonar el
    checkout; la explotacion depende de que el personal siga el flujo normal del panel, que no
    muestra el estado del pago, aunque el cliente queda identificado por su cuenta, direccion y
    telefono.
    Impacto MEDIA: Entrega de mercancia sin cobro y descuadre de inventario por pedido; afecta
    dinero e inventario del comercio, acotado al valor de cada pedido y sin acceso a datos ni
    cuentas ajenas.
  Prueba del verificador:
    [verif-977974caee; revision final final-977974caee] Siguio el flujo completo: createPreference
    crea la venta 'pendiente' sin cobro (MercadoPagoController.php:335), la cola solo excluye
    canceladas, canManageDispatch devuelve true para todo metodo distinto de transferencia, y
    enviarPedido deja la venta en Enviado y avisa al cliente. Las ventas hechas en el panel nacen
    'completada', asi que una venta pendiente que no sea transferencia solo puede ser un Mercado
    Pago sin pagar. La revision final solo completo el ejemplo de la peticion.
  Arreglo propuesto:
    Exigir pago aprobado para despachar ventas de Mercado Pago: en canManageDispatch devolver false
    para canceladas y para mercado_pago con estatus distinto de completada; filtrar la cola de
    despacho (y el aviso PedidosPorTerminar) para excluir pendientes de Mercado Pago o mostrarlas
    bloqueadas como 'pago pendiente'; cancelar automaticamente las pendientes al expirar la
    preferencia (24 h). Prueba de regresion: una venta mercado_pago pendiente no aparece en
    enColaDespacho y openModal/enviarPedido emiten error.
    Archivo: app/Http/Livewire/Despachos/DespachosController.php
      public function canManageDispatch(Sale $sale): bool
      {
          if ($sale->estatus === Sale::ESTATUS_CANCELADA) {
              return false;
          }
      
          if ($sale->metodo_pago === 'mercado_pago') {
              return $sale->estatus === Sale::ESTATUS_COMPLETADA;
          }
      
          if ($sale->metodo_pago !== 'transferencia') {
              return true;
          }
      
          return $sale->transferencia_estado === 'aprobada';
      }
    Archivo: app/Models/Sale.php
      public function scopeEnColaDespacho($query)
      {
          return $query->whereIn('estado_envio', [self::ENVIO_PENDIENTE, self::ENVIO_PROCESANDO, self::ENVIO_LISTO])
              ->where('estatus', '!=', self::ESTATUS_CANCELADA)
              ->where(function ($q) {
                  $q->where('metodo_pago', '!=', 'mercado_pago')
                    ->orWhere('estatus', self::ESTATUS_COMPLETADA);
              });
      }

----------------------------------------------------------------------------------------------------
S-03  GRAVEDAD MEDIA  -  El cliente puede cancelar por la API una venta ya cobrada o entregada y reintegrar su stock
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/SalesController.php:453 (reintegro de stock en 464-468)
  Frente a la auditoria anterior: A2 (sigue)
  Que pasa:
    PUT /api/v1/ventas/{saleId}/cancelar (auth:sanctum) solo rechaza ventas ya canceladas. Un
    cliente registrado puede cancelar su propia venta en estatus 'completada' (pago de Mercado Pago
    aprobado o transferencia aprobada en Despachos), aunque su estado_envio sea Enviado o Entregado.
    La cancelacion crea por cada linea una entrada de inventario 'cancelacion' por la cantidad
    completa (stock fantasma de producto ya entregado, que permite sobrevender a otros clientes y al
    mostrador), deja la venta cobrada como 'cancelada' fuera del registro de ventas completadas y
    descuenta las estadisticas del cliente. La misma operacion en el panel
    (VentasController::cancelSale) esta reservada al rol Admin. No hay reembolso automatico.
  Como se explota:
    Quien: Cliente de la tienda con una compra propia ya pagada y entregada.
    Peticion de ejemplo: PUT /api/v1/ventas/{id_de_venta_completada_propia}/cancelar con
    Authorization: Bearer <token del cliente>
    Resultado esperado: Derivado del codigo fuente y de Laravel 8.83.29 fijado en composer.lock; no
    ejecutado (vendor/ ausente, ejecucion prohibida en esta corrida). La peticion pasa la propiedad
    (448) y la guarda de la linea 453; restoreCancelledSale encuentra la salida 'venta' y crea una
    entrada 'cancelacion' de 2 unidades (stock calculado del producto pasa de 8 a 10 aunque la carne
    ya se entrego); total_compras y numero_compras del cliente se reducen; la venta queda estatus
    'cancelada' y se responde 200 'Compra cancelada exitosamente'.
  Por que esta gravedad:
    Probabilidad MEDIA: Basta una cuenta de cliente con una compra completada propia y una peticion
    directa a la API; no requiere privilegios ni interaccion de terceros.
    Impacto MEDIA: Evade una facultad reservada al Admin y rompe la integridad del inventario (stock
    fantasma que lleva a sobreventa) y del registro de ventas cobradas; limitado a ventas propias
    del cliente y no mueve dinero automaticamente.
  Prueba del verificador:
    [verif-f7cde6f72f; revision final final-f7cde6f72f] Confirmo que la unica guarda de estado es
    estatus == 'cancelada'; una venta completada o entregada pasa, restoreCancelledSale crea la
    entrada de inventario por cada linea y el indice unico no lo impide porque el origen es
    distinto. En el panel esa misma operacion exige rol Admin (VentasController.php:106). La
    revision final solo corrigio un numero de linea.
  Arreglo propuesto:
    Restringir la cancelacion por el cliente a ventas pendientes de pago y no despachadas; dejar las
    demas al Admin desde el panel. Agregar prueba: cancelar una venta 'completada' o con
    estado_envio distinto de 'Pendiente' devuelve 409 y no crea movimientos de inventario.
    Archivo: app/Http/Controllers/SalesController.php
      if ($sale->estatus !== Sale::ESTATUS_PENDIENTE || $sale->estado_envio !== Sale::ENVIO_PENDIENTE) {
          DB::rollBack();
          return response()->json(['success' => false, 'status' => 409, 'message' => 'Solo puedes cancelar pedidos pendientes que aun no se preparan', 'data' => null], 409);
      }

----------------------------------------------------------------------------------------------------
S-04  GRAVEDAD MEDIA  -  El estatus LOCKED de los usuarios del panel no impide iniciar sesion ni usar el panel
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/Auth/LoginController.php:22 y app/Http/Kernel.php:40
  Frente a la auditoria anterior: A3 (sigue)
  Que pasa:
    El Admin puede marcar a un usuario del personal como Bloqueado (users.status = LOCKED) desde
    Sistema > Usuarios, pero ni el login (AuthenticatesUsers sin override), ni el reset de
    contrasena (ResetsPasswords, que inicia sesion automaticamente), ni ninguna capa por request
    revisa ese campo. Un empleado bloqueado inicia sesion con su contrasena, mediante reset de
    contrasena, o sigue con una sesion o cookie remember ya emitida, y conserva todos los permisos
    de su rol (spatie no cambia al bloquear): ver PII de clientes y comprobantes, aprobar/rechazar
    transferencias, crear pedidos, o todo el panel si su rol es Admin.
  Como se explota:
    Quien: Empleado del panel (por ejemplo Despachador) cuya cuenta el Admin marco como Bloqueado.
    Peticion de ejemplo: POST /login con _token=<csrf de GET
    /login>&email=despachador@dummy.test&password=<su contrasena> sobre una fila users con
    status='LOCKED' y rol Despachador
    Resultado esperado: No ejecutado: la app no puede correr en esta corrida (sin vendor/). Derivado
    de la fuente del repositorio y del comportamiento fijado de laravel/ui v3.4.6
    (AuthenticatesUsers::credentials devuelve solo email y password; ResetsPasswords inicia sesion
    tras el reset) y laravel/framework v8.83.29 (SessionGuard sin chequeo de status): el login
    responde 302 a /clientes/despachos (PanelRoles::homeUrl) y la pantalla carga con 200, igual que
    para un usuario ACTIVE.
  Por que esta gravedad:
    Probabilidad MEDIA: El panel ofrece el bloqueo como control normal para dar de baja personal; el
    empleado bloqueado ya conoce su contrasena y no necesita nada mas. Requiere un insider
    previamente autorizado.
    Impacto MEDIA: Derrota completa del control de bloqueo: el empleado conserva acceso a PII de
    clientes, comprobantes bancarios y acciones de dinero (aprobar transferencias, crear pedidos) o
    al panel completo si era Admin. El radio esta limitado a una cuenta ya conocida por el comercio
    y el Admin puede mitigar borrando la cuenta.
  Prueba del verificador:
    [verif-1976ee2c92; revision final final-1976ee2c92] Busco 'status' y 'LOCKED' en todo app/,
    routes y providers: solo UsersController lo escribe y lo cuenta; el login usa AuthenticatesUsers
    sin cambios (laravel/ui v3.4.6, solo correo y contrasena), el reset de contrasena tambien inicia
    sesion sin revisarlo y AuthenticateSession esta comentado. La revision final lo aprobo sin
    cambios.
  Arreglo propuesto:
    Incluir status=ACTIVE en las credenciales del login y agregar un middleware en el grupo auth del
    panel (y como persistent middleware de Livewire) que cierre la sesion de usuarios no ACTIVE;
    cubre sesiones vivas, cookies remember y el login automatico tras reset. Al bloquear desde
    UsersController, rotar remember_token. Regresion: prueba que un usuario LOCKED recibe error en
    POST /login y es expulsado en el siguiente request con sesion previa.
    Archivo: app/Http/Controllers/Auth/LoginController.php
      protected function credentials(\Illuminate\Http\Request $request)
      {
          return array_merge($request->only($this->username(), 'password'), ['status' => 'ACTIVE']);
      }
    Archivo: app/Http/Middleware/EnsureUserIsActive.php
      public function handle($request, \Closure $next)
      {
          $user = $request->user();
          if ($user && strtoupper((string) $user->status) !== 'ACTIVE') {
              \Illuminate\Support\Facades\Auth::guard('web')->logout();
              $request->session()->invalidate();
              $request->session()->regenerateToken();
              return redirect()->route('login')->withErrors(['email' => 'Usuario bloqueado']);
          }
          return $next($request);
      }
      // Registrar en routes/web.php: Route::middleware(['auth', 'active'])->group(...) y en Livewire::addPersistentMiddleware.

----------------------------------------------------------------------------------------------------
S-05  GRAVEDAD MEDIA  -  Suspender o inactivar un cliente no revoca ni bloquea sus tokens Sanctum vigentes
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/CustomersController.php:156 y
  app/Http/Livewire/Clientes/ClientesController.php:164 y 186
  Frente a la auditoria anterior: M3 (sigue)
  Que pasa:
    El estatus del cliente (activo/inactivo/suspendido) solo se valida al emitir el token en el
    login de la API (CustomersController::login, linea 156). Cuando el Admin cambia el estatus a
    inactivo o suspendido desde el panel (ClientesController::Update, linea 164) o el borrado lo
    convierte en inactivo porque el cliente tiene ventas (destroy, linea 186), solo se actualiza la
    columna: no se borran los tokens del cliente. Ni el guard auth:sanctum (Sanctum 2.15.1 solo
    valida token, expiracion y tokenable), ni el middleware Authenticate, ni los controladores del
    grupo protegido consultan el estatus, y la unica llamada a borrado de tokens en app/ es el
    logout voluntario (currentAccessToken()->delete(), CustomersController 194). Por lo tanto, un
    cliente suspendido con un token emitido antes de la suspension sigue, hasta 7 dias, leyendo sus
    datos, modificando su perfil y contrasena, creando pedidos por transferencia que descuentan
    inventario (addSaleExit), creando preferencias de Mercado Pago y subiendo evidencias, mientras
    que un nuevo login devolveria 403. El cliente no puede reactivarse a si mismo (estatus no esta
    en los campos permitidos de CustomersController::update).
  Como se explota:
    Quien: Cliente registrado de la tienda al que el Admin suspende o inactiva (por ejemplo por
    fraude o impago) despues de haber iniciado sesion y conservando su token Bearer.
    Peticion de ejemplo: GET /api/v1/clientes/data Authorization: Bearer <token emitido antes de la
    suspension>
    Resultado esperado: Derivado del codigo fuente y del comportamiento de laravel/framework 8.83.29
    y laravel/sanctum 2.15.1 fijados en composer.lock; no ejecutado. Ningun punto entre auth:sanctum
    y SalesController::store (ni en el resto de rutas del grupo) consulta el estatus del cliente, y
    el cambio de estatus no borra tokens; la peticion se atiende como si el cliente estuviera activo
    mientras un nuevo login devolveria 403.
  Por que esta gravedad:
    Probabilidad BAJA: Requiere que el Admin suspenda a un cliente que conserva un token de menos de
    7 dias y que ese cliente siga usandolo; es un evento operativo poco frecuente pero ocurre
    siempre que se da.
    Impacto MEDIA: Se anula durante hasta 7 dias un control explicito del Admin: el cliente
    suspendido sigue creando pedidos por transferencia que descuentan inventario, preferencias de MP
    y cambios de perfil. El alcance se limita a lo que el cliente ya podia hacer sobre su propia
    cuenta y la ventana esta acotada por la expiracion.
  Prueba del verificador:
    [verif-fbb9e7cacd; revision final final-fbb9e7cacd] El estatus solo se revisa en el login; ni
    auth:sanctum ni los controladores lo consultan, el cambio de estatus en el panel no borra tokens
    (no existe ningun tokens()->delete() en app/ salvo el logout voluntario) y los tokens duran 7
    dias (config/sanctum.php:49). La revision final lo aprobo sin cambios.
  Arreglo propuesto:
    Revocar todos los tokens del cliente cuando su estatus deje de ser activo
    (ClientesController::Update y destroy) y, como defensa adicional, anadir un middleware en el
    grupo auth:sanctum que responda 403 si el usuario autenticado es Customers con estatus distinto
    de activo.
    Archivo: app/Http/Livewire/Clientes/ClientesController.php
      $user->update($payload);
      if ($user->estatus !== 'activo') {
          $user->tokens()->delete();
      }
      // y en destroy, tras update(['estatus' => 'inactivo']):
      $user->tokens()->delete();
    Archivo: app/Http/Middleware/ClienteActivo.php
      public function handle($request, Closure $next)
      {
          $u = $request->user();
          if ($u instanceof \App\Models\Customers && $u->estatus !== 'activo') {
              return response()->json(['success' => false, 'status' => 403, 'message' => 'Cuenta inactiva o suspendida.', 'data' => null], 403);
          }
          return $next($request);
      }

----------------------------------------------------------------------------------------------------
S-06  GRAVEDAD MEDIA  -  Un cliente recien registrado puede retener todo el inventario con pedidos por transferencia que nunca paga
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/SalesController.php:81-83 y 207;
  app/Console/Kernel.php:25-28
  Frente a la auditoria anterior: M2 (sigue)
  Que pasa:
    POST /api/v1/ventas (auth:sanctum, sin middleware verified) crea la venta por transferencia en
    estatus 'pendiente' y registra de inmediato la salida real de inventario
    (InventoryService::addSaleExit), sin tope de cantidad por linea, de lineas por pedido, de
    pedidos pendientes por cliente ni de tiempo. El registro de clientes es abierto y entrega un
    token Sanctum al instante sin exigir verificar el correo. Con una sola peticion que pida todo el
    stock visible de cada producto (expuesto por GET /api/v1/productos/{id}/stock), currentStock
    queda en 0: el catalogo los oculta, nuevas compras por transferencia o preferencias de Mercado
    Pago se rechazan por stock insuficiente, y el mostrador del panel (Despachos createOrder) falla
    en addSaleExit. Si un tercero ya habia creado una preferencia MP antes de la retencion y paga
    despues, procesarPagoAprobado registra el cobro pero no puede completar la venta (requiere
    reembolso manual). No hay tarea programada que expire pedidos, y rechazar la transferencia deja
    la venta en 'pendiente' con el stock retenido; solo una cancelacion manual por venta (Ventas
    cancelSale o edicion en Despachos) lo libera, y el atacante puede repetir con cuentas nuevas.
  Como se explota:
    Quien: Usuario anonimo de internet que se registra como cliente.
    Peticion de ejemplo: POST /api/v1/clientes/registro {nombre, apellido, correo ficticio,
    password, telefono}
    Resultado esperado: Derivado por trazado de fuente y del comportamiento de Laravel 8.83.29
    fijado en composer.lock; no ejecutado (vendor/ ausente). La primera venta responde 201
    'pendiente' con salidas de 10 y 5; currentStock de ambos productos queda en 0; cualquier otra
    venta (API transferencia, nueva preferencia MP, mostrador) es rechazada por stock insuficiente
    hasta que el personal cancele manualmente la venta; no existe expiracion automatica.
  Por que esta gravedad:
    Probabilidad MEDIA: Registro abierto y una sola peticion autenticada; solo depende del horario
    de atencion y del limite general de 60/min por IP.
    Impacto MEDIA: Bloquea las ventas en linea y de mostrador del catalogo hasta intervencion manual
    del personal y es repetible con cuentas nuevas; no expone datos ni dinero directamente, y es
    reversible cancelando la venta.
  Prueba del verificador:
    [verif-6f5aa10287; revision final final-6f5aa10287] Confirmo que la venta por transferencia
    descuenta stock real estando 'pendiente', sin tope de cantidad ni de lineas, que el registro
    abierto entrega token sin verificar el correo, que el stock exacto es publico (GET
    productos/{id}/stock) y que no hay tarea que expire pedidos. Cualquier otra venta posterior
    falla por stock insuficiente (InventoryService.php:332). La revision final lo aprobo sin
    cambios.
  Arreglo propuesto:
    Limitar el numero de pedidos por transferencia pendientes por cliente (p. ej. 1-2) y la cantidad
    por linea/pedido, exigir correo verificado para crear pedidos, y programar una tarea que cancele
    (con restoreCancelledSale) transferencias pendientes sin evidencia o rechazadas tras un plazo.
    Prueba de regresion: un segundo pedido pendiente del mismo cliente devuelve 409.
    Archivo: app/Http/Controllers/SalesController.php
      $pendientes = Sale::where('customer_id', $request->user()->id)->where('metodo_pago', 'transferencia')->where('estatus', Sale::ESTATUS_PENDIENTE)->count();
      if ($pendientes >= 2) {
          return response()->json(['success' => false, 'status' => 409, 'message' => 'Ya tienes pedidos por transferencia pendientes de pago', 'data' => null], 409);
      }
      // y en las reglas: 'productos' => 'required|array|min:1|max:50', 'productos.*.cantidad' => 'required|numeric|min:0.01|max:100'
    Archivo: app/Console/Kernel.php
      $schedule->command('ventas:expirar-transferencias')->hourly(); // cancela pendientes sin pago tras N horas reintegrando stock

----------------------------------------------------------------------------------------------------
S-07  GRAVEDAD BAJA  -  Cambio de correo y contrasena del cliente sin contrasena actual ni revocacion de otros tokens
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/CustomersController.php:242 y 291-297
  Frente a la auditoria anterior: M8 (sigue)
  Que pasa:
    PUT/PATCH /api/v1/clientes/{id} (alias usuarios/update/{id}) deja que el portador de cualquier
    token Sanctum vigente del cliente cambie el correo de acceso y la contrasena sin presentar la
    contrasena actual, y no revoca los demas tokens. Asi, un token robado (vigencia de 7 dias) se
    convierte en control permanente de la cuenta: el atacante cambia correo y contrasena, el dueno
    ya no puede iniciar sesion y la API no tiene flujo de recuperacion de contrasena para clientes,
    de modo que solo un Admin del panel puede restablecerla. Al reves, si el dueno cambia su
    contrasena para recuperar la cuenta, los tokens del atacante siguen sirviendo hasta que caducan.
    El chequeo de estatus del cliente solo se hace en el login, asi que suspender la cuenta tampoco
    expulsa los tokens ya emitidos.
  Como se explota:
    Quien: Portador de un token Sanctum ajeno de un cliente ficticio (id 7) que quiere convertir ese
    acceso temporal en control permanente de la cuenta.
    Peticion de ejemplo: PUT /api/v1/clientes/7 con Authorization: Bearer <token_del_cliente_7> y
    Content-Type: application/json, cuerpo {"correo":"atacante@example.test","password":"nueva123"}
    Resultado esperado: Derivado del codigo fuente y del comportamiento de Laravel 8.83.29 y Sanctum
    2.15.1; no se ejecuto. La respuesta seria 200 con data.campos_actualizados = [correo, password];
    el hash nuevo y el correo se guardan, y ningun token del cliente se revoca.
  Por que esta gravedad:
    Probabilidad BAJA: Hace falta tener ya un token valido del cliente; el repositorio no muestra
    una forma de obtenerlo sin otro fallo previo (XSS en la tienda, robo del dispositivo o fuga del
    token).
    Impacto MEDIA: Convierte un acceso temporal en toma permanente de una cuenta de cliente (PII,
    pedidos y comprobantes) e impide que el dueno expulse al atacante cambiando la contrasena.
    Afecta una cuenta a la vez y un Admin puede restablecerla desde el panel.
  Prueba del verificador:
    [verif-9c9066a519; revision final final-9c9066a519] El unico control de update es que el id sea
    el del token; password y correo se guardan sin pedir la contrasena actual y no se borra ningun
    otro token. La revision final lo aprobo sin cambios.
  Arreglo propuesto:
    Cuando lleguen 'password' o un 'correo' distinto, exigir 'current_password' y comprobarlo con
    Hash::check contra $cliente->password (si falla, responder 403/422). Despues de save(), revocar
    todos los tokens del cliente salvo el actual. Ademas conviene reiniciar email_verified_at al
    cambiar el correo y revisar el estatus del cliente en cada peticion autenticada.
    Archivo: app/Http/Controllers/CustomersController.php
      $cambiaCorreo = $request->exists('correo') && $request->input('correo') !== $cliente->correo;
      if (($request->filled('password') || $cambiaCorreo) && ! Hash::check((string) $request->input('current_password'), $cliente->password)) {
          return response()->json(['success' => false, 'status' => 403, 'message' => 'Contrasena actual incorrecta', 'data' => null], 403);
      }
      // ... despues de $cliente->save():
      if (isset($changes['password']) || isset($changes['correo'])) {
          $actual = $request->user()->currentAccessToken();
          $cliente->tokens()->where('id', '!=', $actual->id)->delete();
      }

----------------------------------------------------------------------------------------------------
S-08  GRAVEDAD BAJA  -  Las respuestas de error de la API publica incluyen el texto interno de la excepcion (SQL, esquema, conexion)
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/CustomersController.php:102 y 183 (mismo patron en
  SalesController.php:248 y MercadoPagoController.php:496)
  Frente a la auditoria anterior: M10 (sigue)
  Que pasa:
    El registro anonimo (POST /api/v1/clientes/registro y el alias POST /api/v1/usuarios), el login
    anonimo y muchas rutas de cliente atrapan la excepcion y concatenan $e->getMessage() en el campo
    message del JSON. En Laravel 8.83.29 el mensaje de un QueryException es el error de PDO seguido
    de '(SQL: ...)' con la sentencia completa y los valores enlazados. Un anonimo lo provoca de
    forma trivial en el registro: 'nombre' y 'apellido' solo se validan como required|string (sin
    max) y 'codigo_postal'/'rfc'/'ciudad'/'estado' como nullable|string, mientras que las columnas
    son VARCHAR(255), VARCHAR(10) y VARCHAR(20) y la conexion mysql usa strict => true. Un valor
    demasiado largo produce SQLSTATE[22001] 'Data too long for column' y la respuesta devuelve la
    tabla customers, la lista de columnas del INSERT (incluidos campos internos como tipo_cliente,
    estatus, limite_credito, descuento_preferencial) y los valores. Esto ocurre aunque APP_DEBUG sea
    false porque el controlador arma la respuesta antes del manejador de excepciones. Los valores
    enlazados son los del propio atacante (incluido el hash bcrypt de su propia contrasena), asi que
    no se demuestra fuga de datos de otros usuarios; los mensajes de fallo de conexion (usuario/host
    de BD) solo salen si la BD falla por causas ajenas al atacante.
  Como se explota:
    Quien: Anonimo en internet
    Peticion de ejemplo: POST /api/v1/clientes/registro con JSON {"nombre":"<300 caracteres
    'A'>","apellido":"X","correo":"nuevo-
    dummy@example.test","password":"secreto123","telefono":"5512345678"}
    Resultado esperado: Derivado del codigo fuente y del comportamiento fijado de laravel/framework
    v8.83.29 (QueryException::formatMessage agrega '(SQL: ...)' con los bindings); no ejecutado.
    Resultado esperado: message = 'Error en la base de datos al crear usuario: SQLSTATE[22001]:
    String data, right truncated: 1406 Data too long for column 'nombre' at row 1 (SQL: insert into
    `customers` (`nombre`, `apellido`, ..., `tipo_cliente`, `estatus`, `limite_credito`,
    `descuento_preferencial`, ...) values (...))', revelando tabla, columnas y el hash bcrypt de la
    contrasena del propio atacante.
  Por que esta gravedad:
    Probabilidad ALTA: Cualquier anonimo lo provoca con una sola peticion de registro con un campo
    largo; no requiere condiciones raras.
    Impacto BAJA: Solo expone SQL, nombres de tabla/columnas y los datos del propio atacante; no se
    demuestra lectura de datos de otros usuarios ni credenciales. Fallos de conexion podrian exponer
    usuario/host de BD, pero el atacante no los controla.
  Prueba del verificador:
    [verif-db14dd73ad; revision final final-db14dd73ad] Mostro un disparador sencillo para un
    anonimo: nombre, apellido, codigo_postal y rfc no tienen max y las columnas son de largo fijo
    con MySQL estricto, asi que un dato largo produce un QueryException cuyo texto (con el SQL,
    tabla y columnas) se devuelve en la respuesta aunque APP_DEBUG sea false. Los valores del SQL
    son los del propio atacante. La revision final lo aprobo sin cambios.
  Arreglo propuesto:
    En todos los catch de la API responder un mensaje generico con codigo 500 y registrar la
    excepcion con Log::error/report($e); conservar solo mensajes de negocio controlados (p. ej.
    stock insuficiente) usando excepciones propias. Ademas agregar max:255 (y max:10/max:20 donde
    corresponda) a los campos de texto del registro y la actualizacion de perfil para que el dato
    invalido regrese 422.

----------------------------------------------------------------------------------------------------
S-09  GRAVEDAD BAJA  -  La bitacora anonima permite atribuir acciones falsas a cualquier cliente y fijarles la fecha
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/LogController.php:30-33 y app/Models/Logs.php:18
  Frente a la auditoria anterior: B7 (sigue)
  Que pasa:
    POST /api/v1/historial/guardar-accion es publico (solo cors y throttle:30,1) y guarda el cuerpo
    completo con asignacion masiva. Cualquiera en Internet, sin token, puede crear registros con
    usuario igual al id de cualquier cliente y con created_at/updated_at a eleccion. La pantalla
    Historial del Admin (/logs, role:Admin) muestra esos registros con el nombre del cliente
    resuelto por la relacion customer() y con la fecha falsa, y su filtro por cliente los presenta
    como actividad de esa persona. Un tercero puede fabricar o retrofechar un rastro de actividad de
    un cliente y mezclarlo con la actividad real. La bitacora no alimenta ninguna otra decision del
    sistema (no hay otros lectores de Logs en app/), por lo que el efecto se limita a la integridad
    de lo que lee el Admin.
  Como se explota:
    Quien: Cliente anonimo en Internet sin token
    Peticion de ejemplo: POST /api/v1/historial/guardar-accion Content-Type: application/json
    {"accion":"Elimino su cuenta","contenido":"accion de
    prueba","usuario":2,"created_at":"2020-01-01 00:00:00","updated_at":"2020-01-01 00:00:00"}
    Resultado esperado: Derivado del codigo fuente y del comportamiento de laravel/framework
    v8.83.29 fijado en composer.lock; no ejecutado (vendor/ no existe en esta corrida). La respuesta
    seria 201 con el registro guardado con usuario=2 y created_at=2020-01-01 00:00:00, y la vista
    Historial lo mostraria como accion del cliente 2 con esa fecha.
  Por que esta gravedad:
    Probabilidad ALTA: Ruta publica, una sola peticion, ids secuenciales; solo limitada por 30
    peticiones por minuto
    Impacto BAJA: Solo afecta la integridad de una bitacora informativa que lee el Admin; ningun
    otro codigo usa Logs para decisiones y no cambia dinero, inventario ni cuentas
  Prueba del verificador:
    [verif-acdbf5b64a; revision final final-acdbf5b64a] La ruta es publica, guarda todo el cuerpo
    con asignacion masiva, 'usuario' acepta el id de cualquier cliente y created_at es asignable;
    Laravel 8 respeta un created_at ya asignado. La pantalla Historial del Admin muestra el nombre
    del cliente y la fecha falsa. La revision final solo corrigio un numero de linea.
  Arreglo propuesto:
    Ligar la atribucion a la identidad autenticada y dejar las fechas al servidor: sacar
    created_at/updated_at de $fillable, guardar solo accion/contenido con longitudes maximas y poner
    usuario desde auth('sanctum')->user() cuando sea Customers (o null si es anonimo). La vista
    deberia marcar como anonimos los registros sin cliente.
    Archivo: app/Models/Logs.php
      protected $fillable = ['accion', 'contenido', 'usuario'];
    Archivo: app/Http/Controllers/LogController.php
      $data = $request->validate(['accion' => 'required|string|max:255', 'contenido' => 'required|string|max:2000']);
      $cliente = auth('sanctum')->user();
      $data['usuario'] = $cliente instanceof \App\Models\Customers ? $cliente->id : null;
      $log = Logs::create($data);

----------------------------------------------------------------------------------------------------
S-10  GRAVEDAD BAJA  -  Aprobar o rechazar una transferencia reactiva una venta ya cancelada
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Livewire/Despachos/DespachosController.php:1286 y 1310 (tambien
  confirmarTransferenciaMostrador, 1240 y 1265)
  Frente a la auditoria anterior: NUEVO
  Que pasa:
    validateTransferDecision (approveTransfer/rejectTransfer) y confirmarTransferenciaMostrador leen
    la venta por transferValidationSaleId con Sale::find, sin lockForUpdate y sin revisar si estatus
    es cancelada; openTransferValidationModal tampoco lo revisa y acepta cualquier saleId de
    transferencia. Si la venta por transferencia con evidencia fue cancelada (cliente via PUT
    /api/v1/ventas/{id}/cancelar, o Admin via Ventas cancelSale), ambas rutas ya reintegraron stock
    (movimiento entrada/cancelacion) y restaron estadisticas del cliente; aprobar la pone en
    completada y rechazar en pendiente. La venta sale del estado terminal 'cancelada' (que
    updateOrder y la edicion de pedidos si respetan), vuelve a enColaDespacho y puede surtirse sin
    salida de inventario vigente (el stock ya fue reintegrado y las estadisticas del cliente no se
    restauran); si la decision cambia y hay customer_id se envia correo de aprobacion/rechazo.
    Ademas la venta resucitada queda atascada: un nuevo intento de cancelarla (cliente o Admin)
    vuelve a llamar restoreCancelledSale, choca con el indice unico (sale_detail_id, type, origin)
    de inventory_movements y se revierte, por lo que no se puede volver a cancelar por los caminos
    normales.
  Como se explota:
    Quien: Personal del panel (Despachador) que decide una transferencia de una venta ya cancelada,
    por carrera con la cancelacion del cliente o invocando la accion Livewire directamente.
    Peticion de ejemplo: PUT /api/v1/ventas/<saleId>/cancelar (cliente dueno, token Sanctum)
    Resultado esperado: Derivado por trazado de codigo fuente (Laravel 8.83.29 fijado en
    composer.lock), no ejecutado: tras la cancelacion, validateTransferDecision no revisa estatus y
    guarda estatus=completada (o pendiente al rechazar) sobre la venta cancelada; la venta vuelve a
    enColaDespacho con su stock ya reintegrado y sin nueva salida, y si transferencia_estado cambia
    se envia el correo de aprobacion/rechazo. Un segundo intento de cancelacion pasa el guard de
    estatus pero restoreCancelledSale intenta insertar otro movimiento entrada/cancelacion para la
    misma linea, el indice unico lo rechaza, la transaccion se revierte (respuesta 500 al cliente;
    error en cancelSale) y la venta queda completada sin poder cancelarse por los caminos normales.
  Por que esta gravedad:
    Probabilidad BAJA: Requiere que el personal decida una transferencia de una venta ya cancelada
    (carrera con la cancelacion o accion directa); el cliente solo no puede forzarlo.
    Impacto BAJA: Integridad del estado de ventas e inventario en un solo comercio: venta cancelada
    resucitada y despachable con el stock ya reintegrado, estadisticas del cliente desalineadas,
    correo erroneo y venta que ya no se puede volver a cancelar; no hay doble reintegro (lo impide
    el indice unico), perdida directa de dinero ni acceso a datos ajenos.
  Prueba del verificador:
    [verif-03ce037e36; revision final final-03ce037e36] Aprobar o rechazar una transferencia lee la
    venta sin bloqueo y sin revisar si esta cancelada, y la deja 'completada' o 'pendiente'. La
    revision final encontro que la descripcion original decia que el stock se reintegraba dos veces
    y eso era falso: el indice unico de inventory_movements lo impide, asi que una segunda
    cancelacion falla y la venta queda sin poder cancelarse. Otro verificador nuevo reviso esa
    correccion y la aprobo.
  Arreglo propuesto:
    En validateTransferDecision y confirmarTransferenciaMostrador, leer la venta dentro de
    DB::transaction con lockForUpdate (mismo orden de bloqueo que cancelPurchase/cancelSale) y
    rechazar la accion si estatus es cancelada; aplicar el mismo chequeo en
    openTransferValidationModal. El indice unico ya impide el doble reintegro; opcionalmente hacer
    restoreCancelledSale explicitamente idempotente para que una inconsistencia no produzca un 500.
    Prueba: aprobar, rechazar o confirmar en mostrador una venta cancelada no cambia su estatus ni
    envia correo.
    Archivo: app/Http/Livewire/Despachos/DespachosController.php
      DB::transaction(function () use ($decision) {
          $sale = Sale::lockForUpdate()->find($this->transferValidationSaleId);
          if (!$sale) { throw new \RuntimeException('Venta no encontrada'); }
          if ($sale->estatus === Sale::ESTATUS_CANCELADA) {
              throw new \RuntimeException('La venta fue cancelada; no se puede validar la transferencia');
          }
          // ... resto de la logica de decision ...
      });

----------------------------------------------------------------------------------------------------
S-11  GRAVEDAD BAJA  -  Despachos permite surtir y enviar una venta ya cancelada (gate sin estatus y sin lock frente a la cancelacion)
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Livewire/Despachos/DespachosController.php:1690-1697 y 1418
  Frente a la auditoria anterior: NUEVO
  Que pasa:
    canManageDispatch, el unico gate de openModal, toggleProductDespacho y enviarPedido, solo revisa
    metodo_pago y transferencia_estado; no revisa estatus=cancelada. Las tres acciones releen la
    venta con Sale::find, sin transaccion ni lockForUpdate, por lo que no se serializan con
    SalesController::cancelPurchase (cliente) ni con VentasController::cancelSale (Admin), que si
    bloquean la fila. Si el cliente cancela su pedido por PUT /api/v1/ventas/{id}/cancelar mientras
    el despachador lo tiene abierto en el modal, la cancelacion reintegra stock, descuenta
    estadisticas del cliente y pone estatus=cancelada sin tocar estado_envio; despues el despachador
    sigue marcando lineas y enviarPedido pasa la venta a Enviado y manda el correo de salida. El
    estado final es inconsistente: venta cancelada, mercancia fisicamente entregada y movimiento de
    entrada por cancelacion sin salida compensatoria (existencias infladas). El listado
    (scopeEnColaDespacho) oculta las canceladas en el siguiente render, pero el modal abierto
    conserva selectedSaleId y saleDetails y las acciones no vuelven a filtrar.
  Como se explota:
    Quien: Cliente registrado dueno de un pedido despachable que lo cancela por la API mientras el
    personal lo esta surtiendo; el panel no lo detiene.
    Peticion de ejemplo: PUT /api/v1/ventas/{saleId}/cancelar (Authorization: Bearer <token del
    cliente dueno>)
    Resultado esperado: Derivado del codigo fuente en el commit 7157a76, no ejecutado (la app no
    corre en esta corrida): canManageDispatch sigue devolviendo true porque transferencia_estado
    permanece 'aprobada'; toggleProductDespacho pone estado_envio=Listo_para_enviar; enviarPedido
    pasa la revision de ENVIO_LISTO y de lineas despachadas, pone estado_envio=Enviado y llama
    sendStatusNotification. Estado final: estatus=cancelada, estado_envio=Enviado, entrada de
    inventario por cancelacion sin salida compensatoria y correo de salida al cliente.
  Por que esta gravedad:
    Probabilidad BAJA: Requiere que el cliente cancele durante la ventana en que el pedido esta
    abierto en Despachos y que el personal no note que desaparecio de la lista; no lo controla con
    precision un atacante.
    Impacto BAJA: Integridad de negocio: el inventario queda inflado por un reintegro de mercancia
    que si salio y la venta queda cancelada pero enviada; no da acceso a datos ni dinero
    directamente. Una perdida economica solo existe si el comercio reembolsa fuera del sistema las
    ventas canceladas, hecho no visible en el repo.
  Prueba del verificador:
    [verif-8f1ef8cacc; revision final final-8f1ef8cacc] canManageDispatch no revisa 'cancelada' y
    enviarPedido relee la venta sin bloqueo; si el cliente (o el Admin) cancela mientras el modal
    esta abierto, el pedido se marca Enviado y se manda el correo 'Tu pedido ya salio'. El listado
    si excluye canceladas, pero las acciones Livewire reciben el id directamente. La revision final
    lo aprobo sin cambios.
  Arreglo propuesto:
    Agregar estatus != cancelada al gate de despacho y re-chequearlo dentro de una transaccion con
    la fila bloqueada antes de cambiar estado_envio; opcionalmente impedir en
    cancelPurchase/cancelSale la cancelacion cuando estado_envio ya es Enviado/Entregado (candidato
    hermano).
    Archivo: app/Http/Livewire/Despachos/DespachosController.php
      public function canManageDispatch(Sale $sale): bool
      {
          if ($sale->estatus === Sale::ESTATUS_CANCELADA) {
              return false;
          }
          if ($sale->metodo_pago !== 'transferencia') {
              return true;
          }
          return $sale->transferencia_estado === 'aprobada';
      }
      
      // En toggleProductDespacho y enviarPedido: envolver en DB::transaction, leer con
      // Sale::lockForUpdate()->find(...), llamar canManageDispatch sobre esa fila y solo
      // entonces modificar detalles/estado_envio; enviar el correo despues del commit.

----------------------------------------------------------------------------------------------------
S-12  GRAVEDAD BAJA  -  El cliente puede reemplazar y borrar el comprobante de transferencia ya aprobado y la constancia de quien lo valido
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/SalesController.php:803-823 (borrado del comprobante en 813)
  Frente a la auditoria anterior: B3 (sigue; este hallazgo la amplia: ademas borra el comprobante)
  Que pasa:
    POST /api/v1/ventas/{saleId}/evidencia-transferencia (auth:sanctum) solo rechaza ventas que no
    son por transferencia o que estan canceladas. En una venta cuya transferencia ya fue aprobada
    por el personal (estatus completada, incluso ya enviada o entregada), el cliente dueno puede
    volver a subir un archivo: el controlador borra fisicamente del disco public el comprobante que
    el personal valido, guarda el nuevo, regresa transferencia_estado a 'pendiente' y anula
    transferencia_validada_at y transferencia_validada_por. Para ventas de clientes registrados, la
    aprobacion (validateTransferDecision) solo escribe transferencia_estado,
    transferencia_validada_at/por y estatus; no agrega nota a la venta ni registra log o historial
    en la BD (la nota solo se agrega al rechazar), y Sale no tiene observer, asi que en el sistema
    del comercio se pierde la unica constancia del comprobante aprobado y de quien lo aprobo (el
    unico aviso externo es un correo al propio cliente). Un cliente que logro que se aprobara un
    comprobante falso puede, tras recibir la mercancia, destruir esa evidencia y dejar cualquier
    otro archivo en su lugar.
  Como se explota:
    Quien: Cliente registrado dueno de una venta por transferencia cuyo comprobante ya fue aprobado
    por el personal.
    Peticion de ejemplo: POST /api/v1/ventas/{saleId}/evidencia-transferencia multipart/form-data:
    evidencia=@blanco.png (Authorization: Bearer <token del cliente dueno>)
    Resultado esperado: Derivado del codigo fuente y del comportamiento de Laravel 8.83.29 fijado en
    composer.lock; no ejecutado (la app no puede correr en esta corrida). La peticion pasa la
    validacion del archivo (766), el ownership (790) y las guardas de 794 y 803;
    transferencias/a.png se borra (813); transferencia_evidencia_path apunta al nuevo archivo,
    transferencia_estado='pendiente', transferencia_validada_at y transferencia_validada_por quedan
    null; estatus sigue 'completada' y estado_envio 'Entregado'. Respuesta 200 'Evidencia de
    transferencia subida correctamente'.
  Por que esta gravedad:
    Probabilidad MEDIA: Basta una peticion autenticada del cliente dueno, sin carrera ni rol
    especial; requiere motivacion (ocultar un comprobante ya aprobado).
    Impacto BAJA: Limitado a las ventas del propio cliente y no da dinero ni acceso a datos ajenos,
    pero destruye en el sistema del comercio el comprobante validado y la constancia del empleado
    que lo aprobo, y deja ventas entregadas con estado de transferencia incoherente; posibles
    respaldos externos no son visibles en el repo.
  Prueba del verificador:
    [verif-a6d567f318; revision final final-a6d567f318] uploadTransferEvidence solo rechaza ventas
    que no son transferencia o que estan canceladas; en una venta ya aprobada o entregada borra el
    comprobante validado del disco, guarda el nuevo y deja en nulo quien lo valido y cuando. La
    aprobacion no deja nota ni registro en otro lado. La revision final corrigio una afirmacion
    inexacta sobre el flujo de mostrador, sin cambiar la causa ni la gravedad.
  Arreglo propuesto:
    Rechazar la re-subida cuando la transferencia ya esta aprobada o la venta ya salio, no borrar
    comprobantes previos (conservar historial de evidencias) y registrar la aprobacion en un
    registro inmutable (nota o log con usuario y fecha).
    Archivo: app/Http/Controllers/SalesController.php
      if ($sale->transferencia_estado === 'aprobada'
          || in_array($sale->estado_envio, [Sale::ENVIO_ENVIADO, Sale::ENVIO_ENTREGADO], true)) {
          return response()->json([
              'success' => false,
              'status' => 409,
              'message' => 'La transferencia ya fue validada; no se puede reemplazar la evidencia',
              'data' => null
          ], 409);
      }
      // Ademas: no llamar Storage::delete sobre la evidencia anterior; guardarla en una tabla de historial.

----------------------------------------------------------------------------------------------------
S-13  GRAVEDAD BAJA  -  El validador de link de Google Maps se evade con una barra invertida y el panel enlaza a un host arbitrario
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Rules/UbicacionGoogleMaps.php:19 (se emite en
  resources/views/livewire/despachos/modal.blade.php:83)
  Frente a la auditoria anterior: NUEVO
  Que pasa:
    Cualquier cliente registrado (el registro es abierto y entrega token Sanctum de inmediato) puede
    guardar como ubicacion de entrega un valor como https://evil.example\@www.google.com/maps al
    crear un pedido (POST v1/ventas, o create-preference de Mercado Pago), al registrarse o al
    editar su perfil (PUT v1/clientes/{id}). UbicacionGoogleMaps lo acepta porque parse_url de PHP
    reporta user 'evil.example\' y host www.google.com. Segun el estandar WHATWG URL, que
    implementan los navegadores, en esquemas especiales (http/https) la barra invertida termina la
    autoridad igual que '/', asi que el mismo texto resuelve al host evil.example con ruta
    /@www.google.com/maps. Ambos lados se reprodujeron con los parsers nativos (PHP 8.3.6 parse_url
    y la clase URL WHATWG de Node v22.22.0), sin ejecutar codigo del objetivo. El valor se guarda
    sin normalizar (solo trim) y Sale::enlaceMapaEntrega lo devuelve tal cual; el escapado {{ }} de
    Blade no altera la barra invertida. Termina en el href del boton 'Abrir en Google Maps' del
    modal de Despachos (Admin/Cajero/Despachador) y del detalle de Ventas (Admin/Cajero), y en la
    linea 'Mapa:' del texto que el personal copia para el mandadito por WhatsApp. El personal que lo
    abra llega a una pagina controlada por el atacante (p. ej. phishing con apariencia de Google),
    justo lo que la regla declara impedir. El correo order-status tambien lo enlaza, pero va al
    mismo cliente que lo escribio (sin cruce de frontera). No hay javascript: ni XSS: el esquema
    queda limitado a http/https, la salida esta escapada y el enlace lleva rel=noopener noreferrer;
    el impacto es un enlace a sitio arbitrario en una superficie de confianza.
  Como se explota:
    Quien: Cliente registrado de la tienda (internet, registro abierto) que quiere que el personal
    del panel o el mandadito abran una pagina suya creyendo que es Google Maps.
    Peticion de ejemplo: {"metodo_pago":"transferencia","productos":[{"product_id":1,"cantidad":1}],
    "tipo_entrega":"mandadito","acepta_aviso_mandadito":true,"direccion_entrega":"Calle
    1","ubicacion_url":"https://evil.example\\@www.google.com/maps"}
    Resultado esperado: Derivado del codigo fuente y de los parsers nativos; la aplicacion no se
    ejecuto (no hay vendor/). Con PHP 8.3.6 parse_url devuelve {scheme:https, host:www.google.com,
    user:'evil.example\', path:/maps} y una copia literal de la logica de passes() (lineas 15-40)
    devuelve true; htmlspecialchars deja el valor igual. Con la clase URL WHATWG de Node v22.22.0 el
    mismo texto da host 'evil.example', pathname '/@www.google.com/maps', href
    https://evil.example/@www.google.com/maps (tambien con @maps.google.com y @maps.app.goo.gl). El
    trazado de fuente muestra que el valor llega sin normalizar al href de Despachos y Ventas y al
    texto para el mandadito. No se abrio un navegador ni WhatsApp.
  Por que esta gravedad:
    Probabilidad MEDIA: Lo puede hacer cualquier cliente con registro abierto y una sola peticion;
    el personal abre el mapa como parte normal del despacho a domicilio. Requiere el clic del
    personal y horario de atencion para crear el pedido.
    Impacto BAJA: Evade por completo el control explicito de origen, pero la consecuencia es un
    enlace a sitio arbitrario (phishing/redireccion) en una pestana nueva con noopener noreferrer:
    no hay ejecucion de script en el origen del panel, ni acceso a datos o sesion sin una segunda
    accion del personal.
  Prueba del verificador:
    [verif-19282e515b; revision final final-19282e515b] Reprodujo la diferencia con los analizadores
    nativos, sin ejecutar codigo del repo. PHP 8.3
    parse_url('https://evil.example\@www.google.com/maps') da host www.google.com y la regla lo
    acepta; la clase URL de Node 22 (estandar WHATWG que usan los navegadores) da host evil.example.
    Dos verificadores nuevos revisaron la promocion de 'falta comprobar' a confirmado. El tramo de
    WhatsApp queda como condicion fuera del repo. La regla es de codigo posterior a la auditoria
    anterior (commit 4cf11cf).
  Arreglo propuesto:
    Hacer que la regla valide lo mismo que resolvera el navegador: rechazar barras invertidas,
    espacios y caracteres de control, y rechazar user/pass/puerto en la autoridad antes de comprobar
    el host (todas las rutas de escritura -API de ventas, Mercado Pago, registro, perfil y panel- ya
    usan esta regla, asi que el invariante queda en un solo lugar). Como defensa en profundidad,
    revalidar al emitir en Sale::enlaceMapaEntrega para que los valores ya guardados antes del
    arreglo caigan a la busqueda por direccion en lugar de enlazarse; opcionalmente guardar un URL
    reconstruido desde las partes validadas (https://host + path + query) en vez del texto original.
    Revisar y limpiar los valores existentes de sales.ubicacion_url y customers.ubicacion_url que
    contengan '\' o '@' antes de '/'.
    Archivo: app/Rules/UbicacionGoogleMaps.php
      <?php
      
      namespace App\Rules;
      
      use Illuminate\Contracts\Validation\Rule;
      
      /**
       * Link de Google Maps (el que se copia con Compartir). Se acepta solo ese origen porque
       * el link se le pasa tal cual al mandadito: no debe ser una pagina cualquiera.
       */
      class UbicacionGoogleMaps implements Rule
      {
          public function passes($attribute, $value): bool
          {
              if (! is_string($value) || strlen($value) > 500) {
                  return false;
              }
      
              $value = trim($value);
      
              // parse_url y el parser WHATWG de los navegadores difieren con '\' (el navegador la trata
              // como '/' y termina la autoridad) y con espacios/caracteres de control (los elimina).
              // Un link de Compartir de Maps nunca los trae: se rechazan.
              if (preg_match('/[\\\\\x00-\x20\x7F]/', $value)) {
                  return false;
              }
      
              $partes = parse_url($value);
              if (! $partes || ! in_array($partes['scheme'] ?? '', ['http', 'https'], true) || empty($partes['host'])) {
                  return false;
              }
      
              // Sin usuario, contrasena ni puerto: la autoridad debe ser solo el host de Google.
              if (isset($partes['user']) || isset($partes['pass']) || isset($partes['port'])) {
                  return false;
              }
      
              $host = strtolower($partes['host']);
              $ruta = $partes['path'] ?? '';
      
              // Links cortos de la app de Maps.
              if ($host === 'maps.app.goo.gl') {
                  return true;
              }
              if ($host === 'goo.gl') {
                  return str_starts_with($ruta, '/maps');
              }
      
              // maps.google.com y google.com/maps (con o sin pais: .com.mx, www, etc.).
              if (! preg_match('/^(www\.|maps\.)?google\.com(\.mx)?$/', $host)) {
                  return false;
              }
      
              return str_starts_with($host, 'maps.') || str_starts_with($ruta, '/maps');
          }
      
          public function message(): string
          {
              return 'Pega el link de Google Maps (en Maps toca tu ubicación, Compartir y Copiar link).';
          }
      }
      
    Archivo: app/Models/Sale.php
          /** Link para abrir en Maps: el que dio el cliente o, si no hay, una busqueda con su direccion. */
          public function enlaceMapaEntrega(): ?string
          {
              // Se revalida al emitir: los valores guardados antes de endurecer la regla no se confian.
              $link = $this->ubicacionDeEntrega();
              if ($link && (new \App\Rules\UbicacionGoogleMaps())->passes('ubicacion_url', $link)) {
                  return trim($link);
              }
      
              $direccion = $this->direccionDeEntrega();
      
              return $direccion ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($direccion) : null;
          }
      

----------------------------------------------------------------------------------------------------
S-14  GRAVEDAD BAJA  -  La API serializa Sale y Customers completos y entrega al cliente notas internas del personal e ids de empleados
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Models/Sale.php:85 y app/Models/Customers.php:65;
  app/Http/Controllers/SalesController.php:326 y CustomersController.php:212
  Frente a la auditoria anterior: NUEVO
  Que pasa:
    Los endpoints del cliente (crear venta, detalle, historial, recientes, cancelar; login,
    registro, getData, update) devuelven los modelos Eloquent sin recurso ni lista blanca. Sale no
    define $hidden (solo $fillable, $casts y $appends) y Customers solo oculta password y
    remember_token. El panel escribe en esas mismas columnas: Despachos anexa 'Transferencia
    rechazada: <motivo>' y las 'Notas internas del pedido' a sales.notas, y el panel de Clientes
    guarda 'Observaciones sobre el cliente' en customers.notas. El cliente autenticado recibe esas
    notas de sus propios registros, ademas de usuario_id, entregado_por y transferencia_validada_por
    (ids de empleados). Matiz: sales.notas tambien contiene la nota que el propio cliente envia al
    crear el pedido y el motivo de rechazo puede ser intencionalmente visible al cliente, por lo que
    lo claramente indebido son customers.notas (observaciones del personal), las notas internas de
    pedido y los ids de empleados. Agravante: customers.notas es escribible por el cliente (registro
    y PUT clientes/{id}), de modo que puede sobrescribir las observaciones del personal.
  Como se explota:
    Quien: Cliente registrado que consulta sus propios pedidos y su perfil.
    Peticion de ejemplo: GET /api/v1/ventas/{saleId_propio} con Authorization: Bearer <token>
    Resultado esperado: Derivado del codigo fuente y del comportamiento de laravel/framework
    v8.83.29 (Model::toArray omite solo $hidden), no ejecutado: la respuesta JSON incluye todas las
    columnas no listadas en $hidden, entre ellas notas e ids de personal.
  Por que esta gravedad:
    Probabilidad ALTA: Cualquier cliente registrado recibe los campos en cada respuesta normal, sin
    payload especial.
    Impacto BAJA: Solo se exponen anotaciones e ids de empleados sobre los propios registros del
    cliente; no hay datos de otros clientes ni dinero, y parte de sales.notas es contenido del
    propio cliente.
  Prueba del verificador:
    [verif-d1ce3602bb; revision final final-d1ce3602bb] Sale no define $hidden y Customers solo
    oculta password y remember_token; los endpoints del cliente devuelven el modelo completo, y el
    panel escribe 'Notas internas' y 'Observaciones sobre el cliente' en esas mismas columnas. El
    verificador anoto que parte de sales.notas es texto del propio cliente y que el motivo de
    rechazo puede ser visible por diseno: lo claramente indebido son customers.notas y los ids de
    empleados. La revision final lo aprobo sin cambios.
  Arreglo propuesto:
    Definir una proyeccion explicita para el cliente (API Resource o arrays con lista blanca) y/o
    ampliar $hidden en Sale (usuario_id, entregado_por, transferencia_validada_por) y Customers
    (notas y, si no se desea exponer, tipo_cliente, limite_credito, saldo_cuenta). Separar la nota
    aportada por el cliente de la nota interna del personal en columnas distintas y retirar 'notas'
    de allowedFields de update si es campo del personal. Prueba de regresion: GET detalle de venta y
    clientes/data no contienen esas claves.
    Archivo: app/Models/Sale.php
      protected $hidden = ['usuario_id', 'entregado_por', 'transferencia_validada_por'];
      // Separar notas internas (p. ej. notas_internas) de las notas del cliente y ocultar las internas.
    Archivo: app/Models/Customers.php
      protected $hidden = ['password', 'remember_token', 'notas'];

====================================================================================================
3. POR COMPROBAR (8) - sin gravedad hasta tener el dato que falta
====================================================================================================

ID    ARCHIVO Y LINEA / TITULO
----------------------------------------------------------------------------------------------------
V-01  database/seeders/UserSeeder.php:26
      Las semillas crean un Admin y un Cajero con contrasena '123' y clientes activos con
      'cliente123' sin guarda de entorno
V-02  app/Http/Middleware/TrustProxies.php:15
      TrustProxies '*' acepta X-Forwarded-Host/For del cliente: envenenamiento del enlace de reset
      del panel y evasion de limites por IP
V-03  app/Http/Controllers/SalesController.php:816
      Comprobantes de transferencia en el disco public: copia servible por el servidor web que no
      pasa por el control de showTransferEvidence
V-04  app/Http/Controllers/CustomersController.php:323-364
      Imagenes de seguimiento base64 sin limite de tamano, cantidad ni cuota se persisten en MySQL
V-05  app/Http/Controllers/CustomersController.php:19-22, 83
      Registro anonimo y reenvio de verificacion usan el SMTP de la tienda para mandar correos con
      texto del atacante a cualquier direccion, sin tope por destinatario ni global
V-06  app/Http/Controllers/LogController.php:14-15
      Bitacora anonima sin tope de tamano ni retencion y vista Logs del Admin que carga y pinta
      todas las filas sin paginar
V-07  app/Http/Controllers/IndicadoresApiController.php:101-104
      Encuesta de indicadores: el arreglo respuestas[] no tiene tope y cada elemento dispara varias
      consultas SQL por peticion
V-08  app/Http/Middleware/DetectInjectionAttempts.php:53, 113-128
      Un anonimo puede hacer crecer sin tope storage/logs/laravel.log: el middleware global de
      deteccion registra el cuerpo completo por el logger de emergencia, antes del throttle y sin
      rotacion
----------------------------------------------------------------------------------------------------

----------------------------------------------------------------------------------------------------
V-01  Las semillas crean un Admin y un Cajero con contrasena '123' y clientes activos con 'cliente123' sin guarda de entorno
----------------------------------------------------------------------------------------------------
  Archivo y linea: database/seeders/UserSeeder.php:26 y 34; CustomerSeeder.php:44;
  DatabaseSeeder.php:16
  Frente a la auditoria anterior: NUEVO
  Que pasa en el codigo:
    DatabaseSeeder::run (lineas 15-21) llama UserSeeder y CustomerSeeder sin revisar APP_ENV.
    UserSeeder crea luisfaax@gmail.com (status ACTIVE, rol Admin asignado en la linea 54) y
    melisa@gmail.com (status ACTIVE, rol Cajero en la linea 41), los dos con bcrypt('123') (lineas
    26 y 34). CustomerSeeder crea tres clientes activos @test.com con Hash::make('cliente123'); uno
    es distribuidor con 8% de descuento preferencial. Ninguna migracion ni codigo de la app cambia
    esas contrasenas ni obliga a cambiarlas al primer login. El login del panel (AuthenticatesUsers)
    solo revisa correo y contrasena, y el login de la API (CustomersController.php:156) solo pide
    estatus 'activo'. CatalogoRealSeeder.php:19-20 dice que la base con el catalogo real 'deja
    usuarios, clientes y config del sitio', lo que hace pensar que la base real se lleno primero con
    DatabaseSeeder. Si produccion se sembro asi y nadie cambio las contrasenas, un anonimo de
    internet entra al panel como Admin (acceso total) con credenciales que estan publicadas en el
    repo. Con lo que se ve en el codigo no se puede saber si eso paso.
  Que falta saber (no se ve en el repo):
    - No se ve en el repositorio si la base de produccion (Hostinger) se lleno con
    DatabaseSeeder/UserSeeder/CustomerSeeder, ni si despues se cambiaron o borraron esas cuentas.
    Ese dato decide si el hallazgo es explotable.
  Como lo comprueba el dueno sin tocar nada:
    El dueno, en produccion y sin cambiar nada: correr php artisan tinker y ejecutar App\Models\User
    ::whereIn('email',['luisfaax@gmail.com','melisa@gmail.com'])->get(['id','email','status']). Para
    cada cuenta que aparezca, correr Hash::check('123', $u->password). Hacer lo mismo con
    App\Models\Customers::where('correo','like','%@test.com')->get() y Hash::check('cliente123',
    $c->password). Si alguno da true, el hallazgo queda confirmado: hay que cambiar la contrasena o
    borrar la cuenta de inmediato y revisar los logs de acceso.
  [verificador verif-e54fa0033b; revision final final-e54fa0033b]

----------------------------------------------------------------------------------------------------
V-02  TrustProxies '*' acepta X-Forwarded-Host/For del cliente: envenenamiento del enlace de reset del panel y evasion de limites por IP
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Middleware/TrustProxies.php:15 y 22; app/Http/Kernel.php:17;
  app/Providers/AppServiceProvider.php:28
  Frente a la auditoria anterior: M4 (parte de la IP) y NUEVO (enlace de reset con host falso)
  Que pasa en el codigo:
    TrustProxies usa $proxies='*' y acepta X-Forwarded-For, -Host, -Port, -Proto y AWS_ELB. Con
    fideloper/proxy 4.4.2, '*' hace que se confie en el REMOTE_ADDR de cada peticion, sea quien sea.
    TrustHosts esta comentado y no hay URL::forceRootUrl ni ResetPassword::createUrlUsing ni
    override de sendPasswordResetNotification en User. Si el ingreso del hosting deja pasar estos
    encabezados, un anonimo manda POST /password/email con el correo de un Admin y X-Forwarded-Host:
    atacante.example. La notificacion ResetPassword de Laravel 8.83.29 arma
    url(route('password.reset', [token, email], false)), y la raiz sale de request->root(). Symfony
    5.4 getHost() toma el primer valor de X-Forwarded-Host si la peticion viene de un proxy de
    confianza. El Admin recibe un correo legitimo con su token de reset apuntando al host del
    atacante; si le da clic, el atacante obtiene el token y puede tomar la cuenta Admin. Variantes
    con la misma raiz: (a) el enlace firmado de verificacion de correo del cliente
    (VerifyCustomerEmail::verificationUrl, URL::temporarySignedRoute) tambien sale con el host del
    encabezado, con impacto bajo; (b) request->ip() sale de X-Forwarded-For, y eso rompe la clave
    email|ip de ThrottlesLogins (login del panel, 5 intentos) y el limitador 'api' (60/min por IP
    cuando no hay usuario), que es el unico limite del login y registro de clientes. Asi se pueden
    adivinar contrasenas sin bloqueo. La variante (b) solo funciona si el cliente llega directo a
    PHP: si hay un proxy que agrega al final la IP real, Symfony la toma desde la derecha y no se
    puede falsificar. Si se confirma, la toma de la cuenta Admin seria alta (requiere un clic del
    Admin); la evasion de limites seria media.
  Que falta saber (no se ve en el repo):
    - No se ve en el repo si el ingreso de Hostinger (LiteSpeed/Apache, o un CDN/proxy delante)
    quita, sobrescribe o solo agrega al final X-Forwarded-Host y X-Forwarded-For de la peticion del
    cliente. Si los sobrescribe, el ataque no funciona. Si los pasa o los agrega al final,
    X-Forwarded-Host funciona (Symfony toma el primer valor). X-Forwarded-For solo funciona si el
    cliente llega directo a PHP sin un proxy que agregue la IP real.
    - La app no se puede ejecutar en esta corrida (no hay vendor/), asi que no se observo el enlace
    generado ni la clave del limitador. La conclusion sale de la fuente y del comportamiento de las
    versiones fijadas en composer.lock (laravel/framework v8.83.29, fideloper/proxy 4.4.2,
    symfony/http-foundation v5.4.50).
    - Para tomar la cuenta, el Admin tiene que abrir el enlace del correo envenenado antes de que
    pasen 60 minutos y antes de pedir otro reset.
  Como lo comprueba el dueno sin tocar nada:
    Sin cambiar nada, el dueno, desde su propia maquina, pide el reset de SU PROPIA cuenta de prueba
    del panel: POST https://carniceriafrancoadmin.shop/password/email con su correo, un token CSRF
    valido y el encabezado X-Forwarded-Host: prueba.invalid. Luego revisa el host del enlace del
    correo. Si dice prueba.invalid, la variante principal esta confirmada, y el token se invalida
    pidiendo otro reset normal. Para X-Forwarded-For: con un correo de prueba inexistente, manda 6
    POST /login con contrasena mala y X-Forwarded-For distinto (203.0.113.1..6), y revisa si sale el
    bloqueo. Tambien revisa en hPanel si hay CDN/proxy delante y como trata esos encabezados.
  [verificador verif-2f5e015b6d; revision final final-2f5e015b6d]

----------------------------------------------------------------------------------------------------
V-03  Comprobantes de transferencia en el disco public: copia servible por el servidor web que no pasa por el control de showTransferEvidence
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/SalesController.php:816; config/filesystems.php:38-42 y 76;
  public/.htaccess:2-3
  Frente a la auditoria anterior: B2
  Que pasa en el codigo:
    uploadTransferEvidence guarda el comprobante bancario del cliente (PII: nombre, banco, cuenta,
    monto) con store('transferencias','public'). El disco public tiene root storage/app/public, url
    APP_URL/storage y visibility public, y el enlace configurado es public/storage ->
    storage/app/public. showTransferEvidence revisa dueno/personal antes de servir el archivo. Pero
    si storage:link existe en el hosting, el mismo archivo tambien se sirve como estatico en
    /storage/transferencias/<40 caracteres aleatorios>.<ext> sin pasar por Laravel. Esa copia no
    pide autenticacion, no expira y sigue disponible despues de cancelar la venta o borrar al
    cliente, porque el archivo no se borra (solo se borra el anterior al resubir). Desde el codigo
    no hay fuga de la ruta a un principal de menor confianza. transferencia_evidencia_path solo se
    serializa al propio dueno (getPurchaseDetail tras clienteNoEsDueno) y al personal en Despachos.
    La URL que la aplicacion publica (transferencia_evidencia_url) es siempre la ruta autenticada,
    nunca la estatica /storage/... El nombre aleatorio de 40 caracteres no es adivinable. La
    exposicion real depende de hechos de despliegue: que exista el enlace y que el servidor web
    liste el directorio /storage/transferencias/. public/.htaccess solo pone -Indexes dentro de
    IfModule mod_negotiation.c, y para un directorio existente las reglas !-d no lo mandan a
    index.php. Si hay listado, cualquier anonimo descarga los comprobantes de todos los clientes. Si
    no lo hay, queda como defensa en profundidad: un control de acceso basado solo en un nombre
    secreto, sin revocacion.
  Que falta saber (no se ve en el repo):
    - No es visible desde el repositorio si storage:link (public/storage) existe en el hosting real
    de Hostinger.
    - No es visible si el servidor web real (Apache o LiteSpeed de Hostinger) permite listar el
    directorio /storage/transferencias/ cuando el IfModule mod_negotiation.c no aplica.
  Como lo comprueba el dueno sin tocar nada:
    El dueno, en su propio hosting y sin tocar datos de clientes: verificar en el administrador de
    archivos si public_html/storage es un enlace a storage/app/public. Abrir en ventana privada
    https://<APP_URL>/storage/transferencias/ y confirmar que responde 403/404 y no un listado.
    Revisar si Options -Indexes esta activo a nivel de servidor. Si aparece el listado, quedan
    expuestos todos los comprobantes. Si no, queda como endurecimiento: mover la evidencia a un
    disco privado y borrar el archivo al cancelar o borrar.
  [verificador verif-3fcb4f8bcb; revision final final-3fcb4f8bcb]

----------------------------------------------------------------------------------------------------
V-04  Imagenes de seguimiento base64 sin limite de tamano, cantidad ni cuota se persisten en MySQL
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/CustomersController.php:323-364
  Frente a la auditoria anterior: B4
  Que pasa en el codigo:
    Cualquier persona de internet puede registrarse en POST /api/v1/clientes/registro y recibir de
    inmediato un token Sanctum (sin verificar correo; la ruta de imagenes no usa el middleware
    verified). Con ese token, POST /api/v1/clientes/imagenes acepta un arreglo sin limite de
    elementos y cada images.*.image es una cadena sin max; el unico chequeo es base64_decode
    estricto (cualquier dato base64, no necesariamente imagen) y la cadena completa se inserta en
    seguimiento_clientes_imagenes.image (longText) sin cuota por cliente ni limpieza automatica. En
    el repo no hay tope de cuerpo: bootstrap/app.php incluso sube memory_limit a 512M y
    public/.htaccess no fija LimitRequestBody. El tamano maximo por peticion queda definido solo por
    post_max_size de PHP, max_allowed_packet de MySQL (por fila) y limites del servidor web de
    Hostinger, que no son visibles. throttle:api es 60/min por id de cliente, y cada cuenta nueva
    trae su propio cupo, asi que el crecimiento agregado de la BD compartida lo controla el
    atacante. El efecto es persistente (solo se borra por deleteImage del propio cliente o borrado
    del cliente en cascada) y, si se agota la cuota de disco de la BD del hosting, afectaria a la
    tienda y al panel. El impacto real depende de los limites y cuotas del despliegue, que no se
    pueden observar desde el codigo.
  Que falta saber (no se ve en el repo):
    - post_max_size y memory_limit efectivos de PHP en Hostinger (el ini_set de 512M puede estar
    restringido por el hosting) no estan en el repo y fijan el maximo de bytes por peticion.
    - max_allowed_packet de MySQL (tope por fila insertada) y la cuota de disco de la base de datos
    del plan de hosting no son visibles en el repo.
    - No se ve si hay WAF o limite de cuerpo en LiteSpeed/Apache o en un proxy delante de la app.
    - La app no se puede ejecutar en esta corrida (no hay vendor/), asi que no hay prueba local de
    la insercion.
  Como lo comprueba el dueno sin tocar nada:
    El dueno revisa, sin enviar carga, en el panel de Hostinger: post_max_size, upload_max_filesize
    y memory_limit efectivos de PHP; max_allowed_packet de MySQL; la cuota de tamano de la base de
    datos; y si LiteSpeed o un WAF limitan el cuerpo de la peticion. Despues ejecuta SELECT
    COUNT(*), SUM(LENGTH(image)), MAX(LENGTH(image)) FROM seguimiento_clientes_imagenes para ver el
    tamano actual y si hay crecimiento anomalo.
  [verificador verif-49ea334aee; revision final final-49ea334aee]

----------------------------------------------------------------------------------------------------
V-05  Registro anonimo y reenvio de verificacion usan el SMTP de la tienda para mandar correos con texto del atacante a cualquier direccion, sin tope por destinatario ni global
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/CustomersController.php:19-22, 83 y 532; routes/api.php:106
  Frente a la auditoria anterior: NUEVO
  Que pasa en el codigo:
    Cualquier persona en Internet puede registrar una cuenta de cliente (POST
    api/v1/clientes/registro o el alias POST api/v1/usuarios) con el correo de un tercero y un
    'nombre' libre (sin regla max; el tope real es VARCHAR(255) de la columna). El registro emite un
    token Sanctum y envia de inmediato, dentro de la misma peticion (cola sync por defecto y en
    .env.production.example), el correo 'Confirma tu correo - Carniceria Franko' desde no-reply del
    dominio de la tienda al tercero, con el saludo 'Hola <nombre>'. Blade escapa el nombre ({{ }}),
    asi que no hay inyeccion HTML, pero si texto arbitrario (incluida una URL en texto plano que
    muchos clientes de correo vuelven enlace) dentro de un correo legitimo de la marca. Con el token
    devuelto, POST api/v1/clientes/email/reenviar reenvia el correo mientras email_verified_at sea
    null, limitado solo por throttle:6,1 por cuenta (la autenticacion corre antes del throttle por
    la prioridad de middleware del framework) y throttle:api 60/min: hasta unos 8640 correos al dia
    por cuenta, sin tope total, diario, por destinatario ni global. PUT api/v1/clientes/{id} permite
    cambiar 'correo' a otra direccion sin verificarla ni reiniciar email_verified_at, asi que una
    misma cuenta no verificada puede apuntar el reenvio a nuevas victimas. El registro admite 60
    cuentas por minuto por IP (y la IP del limitador anonimo depende de TrustProxies '*', candidato
    aparte). No hay CAPTCHA ni ningun otro control en el codigo. Ninguna ruta usa el middleware
    'verified', por lo que tambien los correos de pedido
    (OrderNotificationService::enviarCorreo/sendStatusNotification, p.ej. tras crear un pedido en
    SalesController:223) salen a la direccion no verificada con nombre y apellido del atacante. Lo
    que el codigo establece: un principal anonimo hace que el SMTP de la tienda envie correos de
    marca, con texto suyo, a terceros que nunca dieron su consentimiento, de forma repetible y sin
    cuota. Lo que no establece: el impacto compartido (agotar la cuota de envio del buzon de
    Hostinger o bloquear el dominio, con lo que dejan de salir los correos de verificacion y de
    pedidos a clientes reales), que depende de limites del proveedor no visibles en el repositorio.
    Severidad estimada: baja a media segun esa cuota (abuso de funcionalidad / spam de marca; sin
    acceso a datos ni dinero).
  Que falta saber (no se ve en el repo):
    - La cuota diaria/horaria de envio del buzon SMTP de Hostinger (no-
    reply@carniceriafrancoadmin.shop) y su politica de suspension por spam o rebotes no estan en el
    repositorio; de ella depende el impacto compartido decisivo (que los correos a clientes reales
    dejen de salir).
    - La app no se puede ejecutar en esta corrida (sin vendor/ y sin red), por lo que no se observo
    localmente el envio con un mailer de prueba; el flujo se deriva solo del codigo y de Laravel
    8.83.29.
    - No se sabe si existe CAPTCHA, WAF o limite de registro delante del hosting, ni que IP ve la
    app detras de la infraestructura de Hostinger (afecta el limite de 60 registros/min por IP).
  Como lo comprueba el dueno sin tocar nada:
    El dueno revisa en hPanel de Hostinger, sin generar trafico, el limite de envio por hora/dia del
    buzon no-reply@carniceriafrancoadmin.shop y si el proveedor lo suspende o lo limita al superarlo
    o por quejas de spam; revisa el historial de envio del buzon buscando picos de correos 'Confirma
    tu correo' a direcciones sin compras; y confirma si hay CAPTCHA, WAF o limite de tasa delante de
    /api/v1/clientes/registro y /api/v1/clientes/email/reenviar.
  [verificador verif-d11927de72; revision final final-d11927de72]

----------------------------------------------------------------------------------------------------
V-06  Bitacora anonima sin tope de tamano ni retencion y vista Logs del Admin que carga y pinta todas las filas sin paginar
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/LogController.php:14-15 y 33;
  app/Http/Livewire/Logs/LogsController.php:33-36
  Frente a la auditoria anterior: NUEVO (relacionado con B7)
  Que pasa en el codigo:
    POST /api/v1/historial/guardar-accion es anonimo (sin auth:sanctum) y valida 'accion' y
    'contenido' solo como required|string, sin max. 'contenido' se guarda en una columna TEXT (hasta
    65535 bytes por fila; con strict=true un valor mayor falla con error 500 en lugar de truncarse,
    asi que el tope efectivo por fila es ~64 KB). El limite efectivo es throttle:30,1 por IP (el
    grupo api suma throttle:api 60/min, tambien por IP para anonimos). Desde una sola IP eso permite
    ~30 x 64 KB = ~1.9 MB por minuto, ~2.7 GB por dia de filas persistentes; no existe en app/
    ningun comando, prune ni borrado de logs. Si ademas la IP del limitador es falsificable via
    X-Forwarded-For (TrustProxies '*', depende de la topologia real), el limite por origen
    desaparece. Del lado lector, LogsController::render ejecuta
    Logs::orderBy('created_at','desc')->get() sobre toda la tabla (o todas las filas de un cliente)
    mas Customers::all() en cada render y en cada interaccion Livewire (wire:model selectedClient),
    y la vista pinta el contenido completo de cada fila y $log->customer sin eager loading (una
    consulta por fila). Con unos miles de filas grandes la pagina /logs del Admin puede agotar
    memory_limit o max_execution_time y quedar inutilizable; el crecimiento sin tope tambien consume
    la cuota de la base MySQL del hosting, que comparte ventas e inventario. La recuperacion es
    manual (borrar/truncar la tabla). El impacto concreto (umbral de filas que rompe la vista y si
    la cuota de BD afecta al resto de la tienda) depende de limites de PHP y del plan de hosting que
    no estan en el repo.
  Que falta saber (no se ve en el repo):
    - memory_limit, max_execution_time y post_max_size reales de PHP en Hostinger no estan en el
    repo; fijan cuantas filas rompen la vista y el tamano maximo por peticion.
    - La cuota de almacenamiento de la base MySQL del plan de hosting no es visible; de ella depende
    si el crecimiento afecta a ventas e inventario o solo al visor de logs.
    - La app no se puede ejecutar en esta corrida (sin vendor/); no se midio el render de /logs con
    una tabla grande.
  Como lo comprueba el dueno sin tocar nada:
    El dueno revisa en hPanel memory_limit, max_execution_time y post_max_size de PHP y la cuota de
    la base MySQL, y ejecuta solo lectura: SELECT COUNT(*), SUM(LENGTH(contenido)) FROM logs; para
    conocer el tamano actual. Tambien confirma si hay un proxy/CDN delante que fije X-Forwarded-For
    (define si el limite por IP es evadible).
  [verificador verif-31781e2a60; revision final final-31781e2a60]

----------------------------------------------------------------------------------------------------
V-07  Encuesta de indicadores: el arreglo respuestas[] no tiene tope y cada elemento dispara varias consultas SQL por peticion
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Controllers/IndicadoresApiController.php:101-104 y 171
  Frente a la auditoria anterior: NUEVO
  Que pasa en el codigo:
    POST /api/v1/indicadores/pedidos/{saleId}/respuestas (auth:sanctum, throttle:api) valida
    'respuestas' solo como required|array|min:1, sin max ni distinct. La regla
    'respuestas.*.pregunta_id' => exists:indicador_preguntas,id hace una consulta SELECT por
    elemento durante la validacion, antes de comprobar propiedad o estado del pedido. Tras pasar las
    comprobaciones, el controlador recorre el arreglo con in_array y ejecuta updateOrCreate (SELECT
    mas INSERT/UPDATE) por elemento dentro de DB::transaction, sin deduplicar pregunta_id. Un
    cliente autenticado con un pedido propio en estado enviado/entregado podria mandar un JSON con
    muchos elementos minimos dentro de post_max_size; una sola peticion cuenta como 1 de las 60/min
    de throttle:api (por id de usuario), asi que el throttle limita peticiones y no costo. La
    persistencia queda acotada por el indice unico (pregunta_id, sale_id, customer_id), por lo que
    el impacto es transitorio por peticion (CPU PHP, consultas MySQL, tiempo de worker). Hallazgo
    derivado de lectura de codigo, no ejecutado. El impacto real depende de limites de despliegue no
    visibles (post_max_size, max_execution_time, workers y conexiones MySQL del hosting); ademas,
    con arreglos muy grandes la expansion de reglas con comodin podria agotar memory_limit 512M y
    terminar con error fatal aislado a esa peticion, lo que limitaria el alcance de la
    amplificacion. Severidad maxima estimada: baja (disponibilidad, requiere cuenta de cliente con
    pedido finalizado).
  Que falta saber (no se ve en el repo):
    - post_max_size real de PHP en el hosting y cualquier limite de cuerpo del servidor web:
    determina cuantos elementos caben en una peticion.
    - max_execution_time, numero de workers PHP / entry processes y max_connections de MySQL del
    plan compartido: determinan si pocas peticiones agotan workers o base, o si el hosting corta
    antes la peticion.
    - Si la expansion de reglas con comodin de Laravel 8.83.29 agota memory_limit (512M) antes de
    ejecutar muchas consultas, el efecto seria un error fatal aislado a la peticion y el impacto de
    disponibilidad seria menor; solo se puede medir con vendor instalado.
    - El atacante debe ser cliente registrado con un pedido propio ya enviado/entregado (estado
    fijado por personal); no se ejecuto la aplicacion (vendor/ ausente) y el conteo de consultas por
    elemento se deriva del comportamiento de laravel/framework v8.83.29.
  Como lo comprueba el dueno sin tocar nada:
    El propietario del hosting debe confirmar post_max_size, max_execution_time, limite de
    procesos/entry processes y max_connections de MySQL del plan, y revisar en el panel de recursos
    o slow query log si, en una cuenta de prueba y con volumen pequeno y creciente, una peticion con
    respuestas[] grande ocupa un worker hasta el timeout.
  [verificador verif-f69f1a2d08; revision final final-f69f1a2d08]

----------------------------------------------------------------------------------------------------
V-08  Un anonimo puede hacer crecer sin tope storage/logs/laravel.log: el middleware global de deteccion registra el cuerpo completo por el logger de emergencia, antes del throttle y sin rotacion
----------------------------------------------------------------------------------------------------
  Archivo y linea: app/Http/Middleware/DetectInjectionAttempts.php:53, 113-128;
  app/Http/Kernel.php:25; config/logging.php:44-48
  Frente a la auditoria anterior: NUEVO (relacionado con M9)
  Que pasa en el codigo:
    DetectInjectionAttempts es middleware global (Kernel.php:25), posterior a ValidatePostSize
    (linea 23) y anterior a throttle:api (middleware de grupo, Kernel.php:47). Si cualquier
    parametro de query o cuerpo contiene un patron de riesgo alto (p. ej. 'drop table') o medio (p.
    ej. 'union select'), logDetection escribe el payload completo (request->all(), sin truncar; solo
    se oculta password/token de primer nivel), URL completa y user-agent. El canal 'security' no
    esta definido en config/logging.php ni en ningun otro lugar del repo; en laravel/framework
    v8.83.29 (composer.lock) LogManager::get() atrapa la excepcion de canal indefinido y devuelve el
    logger de emergencia (StreamHandler a storage/logs/laravel.log, nivel debug fijo), por lo que el
    try/catch del middleware no se activa, se anexa un registro 'emergency' con traza y luego el
    warning con el payload, aunque LOG_LEVEL=error (.env.production.example:14). El canal por
    defecto stack->single (logging.php 38-48) no rota. El tamano de cada entrada solo lo acota
    post_max_size/limite del servidor, y no hay rate limit previo en el repo, por lo que un anonimo
    puede crecer el archivo de forma sostenida. El comportamiento del framework se derivo de la
    version fijada (vendor/ ausente) y no se ejecuto. El impacto real (llenar la cuota de hosting
    compartido con sesiones y cache en archivo) depende de post_max_size, cuota de disco, rotacion
    externa y proxies/WAF, hechos fuera del repo. Verificacion independiente: trazas y lineas
    correctas; no se encontro control en el repo que lo refute. Nota: el log se escribe antes de
    abort(403), asi que las peticiones de riesgo alto tambien se registran.
  Que falta saber (no se ve en el repo):
    - vendor/ no existe: el comportamiento de Illuminate\Log\LogManager::get/createEmergencyLogger
    en v8.83.29 no se pudo leer ni ejecutar; se afirma por la version fijada en composer.lock
    (derivado de conocimiento del framework, no observado).
    - post_max_size, limite de cuerpo del servidor y max_input_vars efectivos del hosting no estan
    en el repositorio y fijan el tamano maximo de cada entrada de log.
    - La cuota de disco/inodos y el efecto de llenarla (sesiones y cache en archivo) son hechos de
    despliegue; tambien si un proxy/WAF delante filtra los patrones o limita por IP.
    - El valor real de LOG_CHANNEL/LOG_LEVEL y si existe rotacion o limpieza externa (logrotate,
    cron) en el servidor.
  Como lo comprueba el dueno sin tocar nada:
    Dueno del hosting: revisar post_max_size y el limite de cuerpo del servidor, la cuota de disco,
    si hay rotacion/limpieza de storage/logs/laravel.log y el tamano actual del archivo y las lineas
    'Unable to create configured logger' / 'Intento de inyeccion detectado'. Correccion sugerida:
    definir el canal 'security' (daily con days y nivel) o loguear solo campos acotados (claves y
    longitudes), truncar el payload a unos cientos de bytes, mover el middleware tras el throttle y
    usar LOG_CHANNEL=daily.
  [verificador verif-85f6cb8218; revision final final-85f6cb8218]

====================================================================================================
4. DESCARTADOS (3) - el verificador mostro que no son hallazgos de seguridad
====================================================================================================
  X-01 Venta por monto: se cobra el monto exacto y se registra la cantidad redondeada a 2 decimales
  (diseno intencional, sin cruce de frontera) [verificador verif-f5da5b146a]
      Motivo: Es el diseno de la venta por monto (SaleDetail.php:88-97 y el panel hacen lo mismo):
      se cobra el monto exacto y los kilos son una aproximacion a 10 g; el redondeo es simetrico y
      no da ganancia a nadie.
  X-02 Editar un pedido sin salidas de inventario lo completa sin descontar stock [verificador
  verif-809ce122e3]
      Motivo: El defecto es real (editar un pedido de Mercado Pago pendiente a efectivo lo completa
      sin descontar stock; era el M7 de la auditoria anterior), pero solo lo dispara personal con
      permiso de editar pedidos: es un error de operacion y no cruza una frontera de seguridad.
  X-03 El webhook de Mercado Pago procesa avisos con firma invalida o sin firma y sin control de
  frescura del ts [verificador verif-2aa2edcac3]
      Motivo: La firma no es obligatoria por omision y no se revisa la frescura del ts (era B1),
      pero el aviso solo aporta un id: estado, monto y venta se vuelven a consultar a Mercado Pago
      con el token del comercio, y el proceso es idempotente. Queda como endurecimiento.

====================================================================================================
5. COMPARACION CON LA AUDITORIA DEL 2026-10-20
====================================================================================================
  La auditoria anterior (docs/auditoria-2026-10.md en la rama claude/zen-volta-53cat0, revisada
  sobre el commit 1a713d8) tenia 29 hallazgos: 4 altos (A1-A4), 10 medios (M1-M10) y 15 bajos
  (B1-B15). Cada uno se volvio a revisar contra el codigo actual con un agente aparte, sin pasar por
  los cazadores de esta auditoria. Desde 1a713d8 solo entraron dos commits (direccion de entrega y
  detalle de la venta).

  Resultado: ninguno de los 29 esta corregido del todo; B15 esta corregido solo en parte. Cuatro
  dependen de datos del servidor y quedan 'no se pudo comprobar' (B2, B4, B13 y la parte de M4 sobre
  la IP). Los demas siguen tal cual. Cada fila dice tambien si esta auditoria lo convirtio en
  hallazgo de seguridad (S), lo dejo por comprobar (V), lo descarto (X) o lo trata como error de
  funcionamiento o nota de endurecimiento (-).

ANTES  ESTADO AHORA  ->  ID NUEVO / NOTA
----------------------------------------------------------------------------------------------------
A1     SIGUE. Ahora: S-02. Antes ALTA, ahora MEDIA: el verificador independiente re-puntuo el dano
        al valor de cada pedido.
A2     SIGUE. Ahora: S-03. Antes ALTA, ahora MEDIA. Las lineas se movieron (452 a 453).
A3     SIGUE. Ahora: S-04. Antes ALTA, ahora MEDIA. La cita 'Kernel.php:286-298' del reporte
        anterior estaba mal (el archivo tiene 76 lineas); lo correcto es Kernel.php:40.
A4     SIGUE. Ahora: -. Error de funcionamiento (un cobro aprobado que no se completa solo queda en
        el log). No cruza una frontera de seguridad, por eso no es hallazgo aqui.
M1     SIGUE. Ahora: -. Error de funcionamiento: una venta cancelada revive si llega un pago
        aprobado.
M2     SIGUE. Ahora: S-06. Sigue; ahora con el recorrido completo desde el registro abierto.
M3     SIGUE. Ahora: S-05. Sigue igual.
M4     SIGUE (sin limite propio de login/registro); NO SE PUDO COMPROBAR (si la IP se puede
        falsificar). Ahora: V-02. Depende de si el servidor reescribe X-Forwarded-For. Se agrego un
        efecto nuevo con la misma causa: el enlace de recuperacion de contrasena del panel.
M5     SIGUE. Ahora: -. Error de funcionamiento: folios duplicados con dos ventas simultaneas
        (Sale.php:297).
M6     SIGUE. Ahora: -. Error de funcionamiento: compra por pesos de un producto en gramos cobra
        1000 veces mas; el afectado es el propio cliente.
M7     SIGUE. Ahora: X-02. El defecto sigue, pero solo lo provoca personal autorizado: error de
        funcionamiento, no de seguridad.
M8     SIGUE. Ahora: S-07. Sigue igual.
M9     SIGUE. Ahora: -. Datos personales en laravel.log; ademas el commit 4cf11cf agrego la
        direccion de entrega a lo que se registra. Nota de endurecimiento: depende de LOG_LEVEL y de
        quien lea los logs.
M10    SIGUE. Ahora: S-08. Sigue; hay mas casos de los que se listaron (login, imagenes,
        recomendaciones).
B1     SIGUE. Ahora: X-03. Sigue en el codigo; descartado como hallazgo (el estado se reconsulta a
        Mercado Pago). El valor real de MERCADOPAGO_FIRMA_ESTRICTA en produccion no se pudo
        comprobar.
B2     NO SE PUDO COMPROBAR. Ahora: V-03. El codigo no cambio; falta saber si el servidor tiene el
        enlace storage y si lista la carpeta.
B3     SIGUE. Ahora: S-12. Sigue y esta auditoria encontro algo peor con la misma causa: re-subir
        tambien BORRA el comprobante ya validado.
B4     NO SE PUDO COMPROBAR. Ahora: V-04. El codigo sigue sin limites; el dano real depende de los
        limites de PHP y MySQL del hosting.
B5     SIGUE. Ahora: -. Exagerado en el reporte anterior: con Laravel 8.83.29 una foto llamada
        foto.php no pasa la validacion. Solo el Admin llega ahi. Nota de endurecimiento.
B6     SIGUE. Ahora: -. Nota de endurecimiento (per_page sin tope, order_by libre, show() sin
        filtrar inactivos).
B7     SIGUE. Ahora: S-09. Se agrego: 'usuario' acepta el id de cualquier cliente.
B8     SIGUE. Ahora: -. Error de funcionamiento: precio 0 permitido por el panel.
B9     SIGUE. Ahora: -. Error de funcionamiento: horario con cierre despues de medianoche.
B10    SIGUE. Ahora: -. Error de funcionamiento: totales de Ventas suman pedidos sin pagar.
B11    SIGUE. Ahora: -. Error de funcionamiento: producto repetido en un pedido se pierde al editar.
B12    SIGUE. Ahora: -. Error de funcionamiento: cantidades decimales para piezas.
B13    NO SE PUDO COMPROBAR. Ahora: -. Depende de que el servidor corra config:cache; el patron
        sigue en AppServiceProvider.php:27.
B14    SIGUE. Ahora: -. Error de funcionamiento: el filtro anti-inyeccion bloquea texto normal.
B15    SIGUE, corregido solo en parte. Ahora: -. La direccion ya tiene max:255 (commit 4cf11cf).
        Notas, nombre y el resto de los campos siguen sin tope.
----------------------------------------------------------------------------------------------------

Casos borde de la seccion 2 de la auditoria anterior: E1 (programados con transferencia rechazada) y E2 (Mercado Pago sin fecha de entrega) siguen igual; E3 y E4 eran puntos positivos y se conservan. Los puntos de rendimiento (R1-R4) y los huecos de pruebas (seccion 3) no son parte de una auditoria de seguridad y no se volvieron a revisar.

NUEVOS en esta auditoria (no estaban en la anterior):
  - S-01 Clientes creados desde el panel quedan con la contrasena fija 'defaultpassword' utilizable
    en el login publico de la API
  - S-10 Aprobar o rechazar una transferencia reactiva una venta ya cancelada
  - S-11 Despachos permite surtir y enviar una venta ya cancelada (gate sin estatus y sin lock
    frente a la cancelacion)
  - S-13 El validador de link de Google Maps se evade con una barra invertida y el panel enlaza a un
    host arbitrario
  - S-14 La API serializa Sale y Customers completos y entrega al cliente notas internas del
    personal e ids de empleados
  - V-01 Las semillas crean un Admin y un Cajero con contrasena '123' y clientes activos con
    'cliente123' sin guarda de entorno
  - V-05 Registro anonimo y reenvio de verificacion usan el SMTP de la tienda para mandar correos
    con texto del atacante a cualquier direccion, sin tope por destinatario ni global
  - V-06 Bitacora anonima sin tope de tamano ni retencion y vista Logs del Admin que carga y pinta
    todas las filas sin paginar
  - V-07 Encuesta de indicadores: el arreglo respuestas[] no tiene tope y cada elemento dispara
    varias consultas SQL por peticion
  - V-08 Un anonimo puede hacer crecer sin tope storage/logs/laravel.log: el middleware global de
    deteccion registra el cuerpo completo por el logger de emergencia, antes del throttle y sin
    rotacion
  Nota: S-13 (enlace de Google Maps) esta en codigo que se agrego despues de la auditoria anterior
    (commit 4cf11cf).

====================================================================================================
6. COBERTURA Y LIMITES
====================================================================================================
  - Perfil estandar, sin presupuesto fijo. Se uso un plan de 38 unidades de cobertura (superficie,
    frontera, subsistema y clase de ataque) en 5 oleadas de cazadores (8, 3, 1, 1 y 1 agentes) y 4
    criticos de cobertura (despues de las oleadas 1, 2 y 4 y uno final limpio). En total
    participaron 68 agentes.
  - Estado de las unidades al cerrar: 11 revisadas sin candidato, 25 con uno o mas candidatos
    ligados y 2 diferidas. Ninguna quedo bloqueada.
  - CORRIDA INCOMPLETA (motivo: critic_final_returned_additional_units_search_stopped_after_wave_5).
    El critico final limpio todavia propuso dos unidades y se detuvo la busqueda porque cada oleada
    nueva solo agregaba hallazgos de gravedad baja. Quedan SIN REVISAR: (a) pedidos de Mercado Pago
    pendientes sin tope que llenan la cola de Despachos y el aviso del encabezado del panel, que se
    carga completo cada 30 segundos (extension del hallazgo S-02); (b) el costo de cada aviso
    anonimo al webhook de Mercado Pago, que consulta a la API con el token del comercio (relacionado
    con el descarte X-03). No se debe leer este informe como 'el resto esta limpio'.
  - Independencia: ningun hallazgo lo confirmo quien lo encontro. Cada candidato paso por un
    verificador nuevo que intento desmentirlo, y cada registro final por otro verificador nuevo. Los
    dos cambios grandes de la revision final (la correccion de la causa de S-10 y la promocion de
    S-13 a confirmado) los reviso otro agente nuevo antes de aplicarse.
  - Ejecucion: la skill exige no descargar dependencias y el repo no trae vendor, asi que no se
    corrio la aplicacion ni sus pruebas (algunas pruebas del repo crean y borran bases MySQL y estan
    prohibidas). Los 'confirmados' son recorridos completos del codigo mas el comportamiento de las
    versiones fijadas en composer.lock. Solo la diferencia entre el analizador de URL de PHP y el de
    los navegadores (S-13) se comprobo con herramientas reales del sistema (PHP 8.3.6 y Node 22).
  - Auditoria anterior: no habia registros compatibles de la skill, solo el informe en prosa; por
    eso sus 29 hallazgos se revisaron aparte y no se uso su lista para guiar a los cazadores.
  - La skill de Cloudflare se instalo solo para esta sesion y sus archivos no estan en esta rama.
    Los 4 commits locales de la rama fix/dia-mexico de Einar no estaban en main y no se revisaron.
  - Fuera de alcance: rendimiento (R1-R4 de la auditoria anterior), huecos de pruebas, el front de
    la tienda (otro repo), y todo lo que depende del hosting.

====================================================================================================
7. NOTAS DE ENDURECIMIENTO (no son hallazgos; sin gravedad)
====================================================================================================
  - Un Admin puede cancelar en el panel ventas ya entregadas y reintegrar su stock
    (VentasController::cancelSale); es el rol de mayor confianza, pero conviene la misma regla que
    se pide en S-03.
  - Sitio Web: configLogoActual y alertImagenActual son propiedades publicas de Livewire que se usan
    para borrar archivos del disco publico (SitioController.php:211-217 y 396-402); y el logo/alerta
    acepta SVG (image|max:2048). Solo el Admin llega ahi.
  - Las subidas del panel guardan con getClientOriginalExtension() (Productos, Categorias,
    Usuarios); hoy es seguro porque Livewire 2 la deriva del contenido, pero conviene extension() o
    store().
  - Descuento manual de Cajero/Admin en createOrder/updateOrder sin tope configurable ni autor
    registrado; credito se marca completada sin cuenta por cobrar.
  - dompdf v1.2.2 tiene fallos conocidos y se usa con isPhpEnabled=true
    (InventoryReceiptController.php:62-69); hoy solo recibe datos escapados de Admin. Desactivar
    isPhpEnabled.
  - Las encuestas aceptan pedidos 'enviados' aunque esten cancelados o sin cobrar, y se pueden
    reescribir sin limite de tiempo; los KPI del Admin cuentan respuestas de ventas canceladas.
  - Webhook de Mercado Pago: activar MERCADOPAGO_FIRMA_ESTRICTA con la clave guardada, rechazar ts
    viejos y exigir que el id firmado sea el que se procesa (X-03).
  - El log guarda direccion, telefono y cuerpo completo de los avisos; el canal 'security' no existe
    y DetectInjectionAttempts oculta solo claves de primer nivel (M9, V-08).
  - SalesController::clienteNoEsDueno deja pasar a cualquier principal que no sea Customers; hoy es
    inalcanzable por la API, pero conviene negar por defecto. La habilidad 'cliente' del token nunca
    se revisa.
  - Contrasena minima de 6 caracteres para clientes y personal; no hay recuperacion de contrasena
    para clientes (solo el Admin puede restablecerla).
  - Raiz del repo: index.html vacio y server.php. Confirmar que el docroot de Hostinger es public/;
    si fuera la raiz quedarian expuestos .env y storage/logs.
  - README.md con marcas de conflicto de merge sin resolver; public/asset_public.zip y
    resources/views/layouts/theme.zip en git (no traen secretos).
  - Laravel 8 y Livewire 2 ya no tienen soporte; planear la actualizacion.

Archivos de esta entrega: docs/auditoria-seguridad-2026-10-26.md (este informe) y docs/auditoria-seguridad-2026-10-26.json (registros de la skill, validados con su validador).
