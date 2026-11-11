# Copia de la base de datos para MySQL Workbench

`natillera_l.sql` es una copia exportada de la base MySQL activa de la aplicación.
Incluye las tablas, sus relaciones y los datos que existían en la base al momento
de exportarla. La aplicación no se modificó ni se cambió de base de datos.

## Importar en MySQL Workbench

1. Abre MySQL Workbench y conéctate al servidor MySQL.
2. Selecciona **File > Open SQL Script** y abre `natillera_l.sql`.
3. Ejecuta el script con el botón del rayo.
4. En el panel **SCHEMAS**, actualiza la lista. La copia se crea con el nombre
   `natillera_l_import`.
5. Expande ese esquema para abrir y consultar sus tablas.

El script crea un esquema separado para no reemplazar por accidente `natillera_l`
ni otra base existente. Si ya existe `natillera_l_import`, cambia el nombre de
esquema en las dos primeras instrucciones del archivo antes de ejecutarlo, o
elige un esquema nuevo. Importa la copia una sola vez en un esquema vacío.

## Privacidad

La copia contiene los datos guardados en la natillera, incluidas cuentas de usuario
y hashes de contraseña. Los hashes no revelan la contraseña directamente, pero el
archivo debe tratarse como privado: no lo publiques ni lo compartas. La exportación
no contiene las contraseñas en texto plano.
