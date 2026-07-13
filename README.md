# NexusCommerce API

**NexusCommerce** es una RESTful API de comercio electrónico de alto rendimiento construida en PHP (Laravel 13). Ha sido diseñada como proyecto para portafolio enfocado en la aplicación rigurosa de principios **SOLID**, patrones de diseño de software limpios (Clean Architecture/DDD-lite) y técnicas avanzadas de optimización.

---

## 🛠️ Guía de Arquitectura para Desarrolladores no-Laravel

Si provienes de entornos como Spring Boot (Java), NestJS (TypeScript), .NET (C#) o Go, esta sección te ayudará a comprender cómo mapear la estructura de este proyecto con patrones arquitectónicos genéricos.

El proyecto está organizado en **tres capas principales** que desacoplan la lógica HTTP de la lógica de negocio y del acceso a datos:

```
[Cliente HTTP] 
      │
      ▼
┌────────────────────────────────────────────────────────┐
│ 1. CAPA DE PRESENTACIÓN (HTTP Layer)                   │
│    - Controllers: Enrutamiento e invocación de servicios│
│    - FormRequests: Validadores de entrada (DTOs http)  │
│    - Resources: Serializadores / Transformers JSON     │
└─────────────────────┬──────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────┐
│ 2. CAPA DE DOMINIO Y NEGOCIO (Domain/Service Layer)    │
│    - Services: Orquestadores de casos de uso (Checkout)│
│    - Events & Listeners: Comunicación asíncrona        │
│    - Exceptions: Excepciones de dominio personalizadas │
│    - DTOs: Objetos planos de transferencia de datos    │
└─────────────────────┬──────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────┐
│ 3. CAPA DE INFRAESTRUCTURA Y DATOS (Data/Infra Layer)  │
│    - Repositories: Contratos y accesos a BD (Eloquent) │
│    - Decorators: Lógica transversal (Caché transparente)│
│    - Providers: Contenedor de Inyección de Dependencias│
└────────────────────────────────────────────────────────┘
```

### Correspondencia de Directorios
* **`app/Http/Controllers/Api`**: Similar a los *Controllers* de Spring/NestJS o *Handlers* en Go. Reciben peticiones y delegan.
* **`app/Http/Requests`**: Similar a los *Pipes* o *Validation DTOs* de NestJS o anotaciones JSR-380 de Spring. Validan tipos y formatos de entrada antes de que el controlador los procese.
* **`app/Http/Resources`**: Equivalente a los *Serializers* o *DTOs de Salida*. Formatean las respuestas JSON para proteger columnas internas de base de datos.
* **`app/Services`**: Los *Services* o *Use Cases* de negocio puro. No saben nada de peticiones HTTP, cabeceras o cookies.
* **`app/Repositories`**: Capa DAO (Data Access Object) estructurada en Interfaces (Contratos) y Concreciones (Implementaciones SQL).
* **`app/Gateways`**: Puertos y adaptadores para integraciones de terceros (Pasarelas de Pago, APIs externas).

---

## 📐 Principios SOLID Aplicados

### 1. **S**ingle Responsibility Principle (Principio de Responsabilidad Única)
Cada clase tiene una única razón para cambiar:
* **Validación**: Separada en `FormRequests` dedicados como `RegisterRequest` o `CheckoutRequest`.
* **Orquestación**: `CheckoutService` solo coordina las compras; no valida el cuerpo HTTP de la petición ni maneja llamadas de red directas a Stripe.
* **Caché**: Separada completamente de la consulta SQL. `EloquentProductRepository` solo hace consultas; `CachedProductRepository` solo cachea.

### 2. **O**pen/Closed Principle (Principio de Abierto/Cerrado)
El software debe estar abierto a extensión pero cerrado a modificación:
* **Eventos desacoplados**: Cuando se completa una compra, el `CheckoutService` dispara el evento `OrderPlaced`. Los procesos adicionales (enviar correos de confirmación, notificar a administración, etc.) se agregan creando nuevos **Listeners** que escuchan este evento, sin tener que tocar una sola línea de código del `CheckoutService`.

### 3. **L**iskov Substitution Principle (Principio de Sustitución de Liskov)
Las subclases o implementaciones de un contrato deben poder reemplazar a la clase padre sin romper el programa:
* **Pasarelas de Pago**: `StripePaymentGateway` y `PayPalPaymentGateway` implementan `PaymentGatewayInterface`. Ambas reciben los mismos tipos de datos, devuelven el DTO unificado `PaymentResult` y lanzan la misma excepción `PaymentFailedException`. Se pueden intercambiar transparentemente en el contenedor de servicios.

### 4. **I**nterface Segregation Principle (Principio de Segregación de Interfaces)
Los clientes no deberían estar obligados a depender de interfaces que no utilizan:
* En lugar de tener un repositorio gigante `RepositoryInterface` con 50 métodos, se han creado contratos altamente enfocados: `ProductRepositoryInterface`, `CartRepositoryInterface` y `OrderRepositoryInterface`.

### 5. **D**ependency Inversion Principle (Principio de Inversión de Dependencias)
Los módulos de alto nivel no deben depender de módulos de bajo nivel; ambos deben depender de abstracciones:
* El controlador `ProductController` no sabe nada del ORM Eloquent ni de base de datos directa. Depende de la interfaz `ProductRepositoryInterface`.
* En `RepositoryServiceProvider`, vinculamos la interfaz a su concreción. Si decidimos migrar la base de datos de SQLite a DynamoDB, solo creamos la clase `DynamoProductRepository` y actualizamos el Service Provider. El controlador permanece intacto.

---

## ⚡ Optimización de Rendimiento

1. **Prevención de Consultas N+1 (Eager Loading)**:
   * Al listar productos u órdenes, se realiza la precarga de relaciones mediante `with(['category'])` y agregados dinámicos eficientes como `withAvg('reviews', 'rating')` (ejecutando subconsultas SQL optimizadas en lugar de bucles iterativos).
2. **Patrón Decorator para Caching**:
   * `CachedProductRepository` actúa como envoltura (middleware) de la base de datos. Si se consulta un listado de productos, se almacena en memoria (`Cache::remember`). Si se realiza un checkout y se actualiza el stock, se invalida el caché de ese producto en específico (`Cache::forget`), manteniendo los datos frescos de manera transparente.
3. **Procesamiento en Segundo Plano (Jobs & Queues)**:
   * El envío de correos de confirmación implementa `ShouldQueue`. Esto significa que la respuesta HTTP del checkout se devuelve al usuario de inmediato en milisegundos, delegando la carga pesada de comunicación a las colas de trabajo en segundo plano de Laravel.
4. **Garantía de Consistencia (Transacciones de BD)**:
   * El proceso de pago e inventario está envuelto en `DB::transaction`. Si la pasarela de pagos rechaza la tarjeta o un producto se queda sin stock en el último milisegundo, la base de datos se revierte por completo (Rollback), evitando inconsistencias.

---

## 📖 Pruebas con Swagger UI y Postman

### Opción 1: Swagger UI (Interactiva en el Navegador)
La API incluye documentación interactiva autocontenida que funciona tanto en local como en producción:
1. Abre tu navegador y dirígete a: **`http://localhost/docs`** (o a la URL de tu despliegue en Vercel, por ejemplo: `https://tu-proyecto.vercel.app/docs`).
2. Swagger UI cargará automáticamente el archivo de configuración `swagger.json` expuesto en `/swagger.json`.
3. Podrás interactuar con todos los endpoints directamente desde tu navegador.

### Opción 2: Colección de Postman (Flujo Automatizado de Tokens)
Para pruebas avanzadas y desarrollo, hemos incluido una colección de Postman oficial dentro del repositorio:
1. Importa el archivo [public/postman_collection.json](file:///C:/laragon/www/api-laravel/public/postman_collection.json) directamente en tu aplicación Postman.
2. La colección viene preconfigurada con variables internas (`base_url` apunta a `http://127.0.0.1:8000/api/v1` y `token` para autenticación Bearer).
3. **Magia de Autotoken (Test Scripts)**: Se han configurado scripts en las peticiones de **Registrar Usuario** e **Iniciar Sesión**. Al ejecutarlas con éxito, Postman extraerá el `access_token` de la respuesta y actualizará automáticamente la variable `{{token}}` de la colección. Los endpoints protegidos (carrito, checkout, pedidos) se autenticarán automáticamente sin que tengas que copiar y pegar tokens a mano.

---

## 🚀 Instalación y Uso Local

### Requisitos Previos
* PHP 8.3 o superior
* Composer
* SQLite habilitado en PHP

### Pasos
1. Clona el repositorio y entra al directorio:
   ```bash
   git clone <URL-DE-TU-REPOSITORIO>
   cd api-laravel
   ```
2. Instala dependencias PHP:
   ```bash
   composer install
   ```
3. Configura el archivo de entorno:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Crea una base de datos en tu servidor MySQL local (por defecto llamada `api_laravel`) y ejecuta las migraciones junto con los seeders para poblar datos de prueba:
   ```bash
   # En tu gestor de base de datos MySQL/Laragon:
   # CREATE DATABASE api_laravel;

   php artisan migrate --seed
   ```
5. Ejecuta los tests automatizados para validar que todo funcione correctamente:
   ```bash
   php artisan test
   ```
6. Inicia el servidor de desarrollo:
   ```bash
   php artisan serve
   ```
   La API estará disponible en `http://127.0.0.1:8000`. Accede a la documentación en `http://127.0.0.1:8000/docs`.

---

## 🖥️ Cómo Desplegar en Vercel

Este proyecto está preconfigurado para ser desplegado en **Vercel** usando el runtime serverless de PHP de la comunidad (`vercel-community/php`). Admite tanto una base de datos local SQLite (autocurativa y por defecto) como una base de datos remota **MySQL (compatible con TiDB)**.

### ¿Cómo funciona la base de datos en Vercel?
* **SQLite (Por defecto)**: Si no se configuran variables de entorno de base de datos, la API inicializará y poblará automáticamente una base de datos SQLite en `/tmp/database.sqlite` en cada arranque en frío de la función serverless (ideal para pruebas y demos rápidas sin servidor externo).
* **TiDB / MySQL (Producción/Portafolio Real)**: Si defines las variables de entorno de MySQL en Vercel, el punto de entrada de la API omitirá la configuración de SQLite y se conectará directamente a tu base de datos remota TiDB.

### Configuración para usar TiDB (MySQL):
1. **Crear tu base de datos en TiDB Cloud**: Regístrate en [TiDB Cloud](https://pingcap.com/tidb-cloud) y crea un clúster Serverless gratuito.
2. **Ejecutar las migraciones y seeders en tu base de datos TiDB**:
   Como Vercel es un entorno serverless de solo lectura para la ejecución HTTP, debes migrar tu base de datos TiDB desde tu entorno local.
   * Abre tu archivo `.env` localmente y configúralo temporalmente con las credenciales de tu TiDB Serverless (e.g. `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT=4000`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
   * Ejecuta el comando de migración local para poblar tu TiDB remoto:
     ```bash
     php artisan migrate --seed
     ```
   * Una vez completado, puedes restaurar tu `.env` local a SQLite si deseas seguir desarrollando localmente de forma desconectada.

### Pasos de Despliegue en Vercel:
1. Asegúrate de tener una cuenta en [Vercel](https://vercel.com/) y haber conectado tu GitHub.
2. Sube el repositorio a GitHub (ver guía más abajo).
3. Importa tu repositorio desde el panel web de Vercel.
4. En la configuración del proyecto en Vercel, agrega las siguientes **Environment Variables**:
   * `APP_KEY`: Tu clave de aplicación generada (e.g., `base64:...`).
   * `DB_CONNECTION`: `mysql`
   * `DB_HOST`: El host de tu clúster de TiDB Cloud.
   * `DB_PORT`: `4000` (puerto por defecto de TiDB).
   * `DB_DATABASE`: Nombre de la base de datos.
   * `DB_USERNAME`: Usuario de la base de datos.
   * `DB_PASSWORD`: Contraseña de la base de datos.
   * `MYSQL_ATTR_SSL_CA`: (Si tu clúster de TiDB requiere conexión segura SSL, puedes configurar los certificados o habilitar SSL en la configuración de Laravel).
   * `AUTO_DB_BOOTSTRAP`: `true` (opcional, recomendado solo para el primer deploy si la base de datos remota esta vacia; ejecuta `migrate` y `db:seed` automaticamente una vez cuando detecta ausencia de tablas o usuarios).
5. Haz clic en **Deploy**. ¡Tu API conectada a TiDB estará en línea!

### Inicializacion automatica de base de datos remota (opcional)
Si tu base de datos MySQL/TiDB esta creada pero vacia, puedes usar el bootstrap automatico:
1. Define `AUTO_DB_BOOTSTRAP=true` en Vercel.
2. Ejecuta un deploy.
3. Realiza una peticion a la API (por ejemplo `/api/v1/products`) para disparar el arranque serverless.
4. Verifica que ya existan tablas y datos semilla.
5. Cambia `AUTO_DB_BOOTSTRAP=false` (o elimina la variable) para evitar comprobaciones innecesarias en cada arranque.

---
