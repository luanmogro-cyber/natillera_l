# La Semilla — Natillera comunitaria

Aplicación PHP 8.3 con arquitectura MVC, MySQL e interfaz adaptable a móviles (PWA).

## Estructura del proyecto

- `backend/`: lógica PHP, controladores, modelos, configuración y Composer.
- `frontend/views/`: plantillas HTML/PHP.
- `frontend/public/`: raíz pública web (`index.php`, `css`, `js`, `img`, `uploads`).
- `Dockerfile` y `railway.toml`: configuración lista para despliegue en la nube (Railway / Render).

## Ejecutar en local con Laragon

1. Copia `backend/.env.example` a `backend/.env` y ajusta credenciales MySQL (`DB_*`).
2. Desde `backend/`, instala dependencias:
   ```powershell
   composer install
   ```
3. Configura Apache apuntando `DocumentRoot` a `frontend/public/`.
4. Al abrir la aplicación con la base MySQL vacía, se crean automáticamente las tablas y se muestra `/instalar` para crear la cuenta inicial de Presidencia.

## Desplegar en la nube (Railway / Render)

1. Sube este repositorio a GitHub.
2. En **Railway**, crea un proyecto desde el repositorio y agrega un servicio **MySQL**.
3. Vincula las variables del servicio MySQL al servicio web (`MYSQL_URL` o `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`).
4. (Opcional para notificaciones push) Agrega `NATILLERA_VAPID_SUBJECT`, `NATILLERA_VAPID_PUBLIC_KEY` y `NATILLERA_VAPID_PRIVATE_KEY` generadas con `php backend/bin/generate_vapid_keys.php`.
5. Abre el dominio HTTPS generado: el sistema creará las tablas automáticamente y abrirá `/instalar` para configurar la natillera desde cero.
