# WhatsApp Clone — Symfony + Supabase Realtime

Clon de WhatsApp Web construido con **Symfony 6**, **Doctrine ORM** y **MySQL**.  
La mensajería en tiempo real se gestiona con **Supabase Realtime (Broadcast)**, sin necesidad de servidor WebSocket propio.

---

## Requisitos previos

| Herramienta | Versión mínima |
|---|---|
| PHP | 8.1 |
| Composer | 2.x |
| MySQL | 5.7 / 8.x |
| Symfony CLI _(opcional pero recomendado)_ | 5.x |
| Cuenta en [supabase.com](https://supabase.com) | — |

---

## 1. Clonar e instalar dependencias

```bash
# Descomprime el proyecto o clona el repositorio
cd whatsapp-clone-supabase

# Instala las dependencias PHP
composer install
```

---

## 2. Configurar la base de datos

Edita el fichero `.env` y ajusta la cadena de conexión a tu MySQL:

```dotenv
DATABASE_URL=mysql://usuario:contraseña@127.0.0.1:3306/whatsapp
```

Crea la base de datos y ejecuta las migraciones:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

Esto creará las tablas `user`, `message` y `messenger_messages`.

---

## 3. Crear el proyecto en Supabase

1. Accede a [supabase.com](https://supabase.com) y crea una cuenta gratuita si no tienes una.
2. Crea un nuevo proyecto (el nombre y región son libres).
3. Una vez creado, ve a **Project Settings → API**.
4. Copia los dos valores que necesitas:
   - **Project URL** — tiene la forma `https://xxxxxxxxxxxx.supabase.co`
   - **anon / public key** — la clave pública del proyecto

> Realtime está habilitado por defecto en todos los proyectos nuevos de Supabase. No es necesario configurar nada adicional en el dashboard.

---

## 4. Configurar las variables de Supabase

En el fichero `.env`, rellena las dos variables con los valores del paso anterior:

```dotenv
SUPABASE_URL=https://xxxxxxxxxxxx.supabase.co
SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6...
```

---

## 5. Arrancar el servidor

Con Symfony CLI:

```bash
symfony server:start
```

Sin Symfony CLI:

```bash
php -S localhost:8000 -t public/
```

Abre el navegador en `http://localhost:8000`.

---

## 6. Crear usuarios de prueba

Accede a `http://localhost:8000/register` y crea al menos **dos usuarios** para poder probar la mensajería entre ellos.

Necesitarás los contactos vinculados. Si la tabla de contactos se rellena automáticamente (todos los usuarios se ven entre sí), ya puedes iniciar sesión y empezar a chatear.

---

## Cómo funciona el tiempo real

```
Usuario A escribe un mensaje y pulsa Enter
  │
  ▼
POST /post/touser/{id}  →  Symfony guarda en MySQL via Doctrine
  │
  ▼
PHP hace curl → Supabase Broadcast API
  canal: "chat:{min(idA,idB)}-{max(idA,idB)}"
  │
  ▼
Supabase empuja el evento al navegador de Usuario B
  │
  ▼
app.js recibe el evento → createDOMMessage() → el mensaje aparece
```

El canal se nombra siempre con los IDs de los dos usuarios ordenados de menor a mayor (`chat:3-7`), de forma que emisor y receptor usan el mismo canal independientemente de quién envía.

---

## Estructura de ficheros relevantes

```
├── src/Controller/ChatController.php   ← guarda mensajes y publica en Supabase
├── public/js/app.js                    ← suscripción Supabase Realtime + DOM
├── templates/base.html.twig            ← carga el SDK de Supabase
└── .env                                ← credenciales DB y Supabase
```

---

## Variables de entorno

| Variable | Descripción |
|---|---|
| `DATABASE_URL` | Cadena de conexión Doctrine a MySQL |
| `SUPABASE_URL` | URL del proyecto Supabase |
| `SUPABASE_ANON_KEY` | Clave pública (anon key) del proyecto Supabase |

---

## Solución de problemas frecuentes

**Los mensajes no aparecen en tiempo real**
- Comprueba en la consola del navegador que no hay errores de conexión con Supabase.
- Verifica que `SUPABASE_URL` y `SUPABASE_ANON_KEY` en `.env` son correctos.
- Asegúrate de que Realtime está habilitado en tu proyecto de Supabase (Project Settings → Realtime).

**Error al guardar mensajes**
- Comprueba que la migración se ejecutó correctamente con `php bin/console doctrine:schema:validate`.
- Revisa que el usuario de MySQL tiene permisos de escritura en la base de datos.

**La página carga en blanco**
- Limpia la caché de Symfony: `php bin/console cache:clear`.
