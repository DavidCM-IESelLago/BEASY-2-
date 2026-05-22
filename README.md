# Fintech Bancaria - Proyecto Intermodular
Plataforma bancaria digital con arquitectura Productor/Consumidor

## Requisitos previos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y en ejecución
- Git

No es necesario tener PHP ni Composer instalados en tu máquina. Todo corre dentro de los contenedores.

## Instalación

**1. Clonar el repositorio**
```bash
git clone https://github.com/DavidCM-IESelLago/BEASY-2-.git
cd BEASY-2-
```

**2. Crear el archivo de variables de entorno**
```bash
cp backend/.env.example backend/.env
```
Abre `backend/.env` y rellena los valores reales (credenciales de BD, clave JWT, etc.).

**3. Levantar el stack**
```bash
docker compose up --build -d
```
El flag `--build` construye la imagen desde el `Dockerfile`. Durante ese proceso se instalan automáticamente las dependencias de Composer, por lo que no es necesario ningún paso manual adicional.

La base de datos se inicializa sola: Docker ejecuta `database/schema.sql` y `database/seeds.sql` al arrancar el contenedor de MySQL por primera vez.

## URLs de acceso

| Servicio    | URL                        |
|-------------|----------------------------|
| Frontend    | http://localhost:8081       |
| API backend | http://localhost/api/       |
| phpMyAdmin  | http://localhost:8080       |

## Parar el stack

```bash
docker compose down
```

Para eliminar también los datos de la base de datos:
```bash
docker compose down -v
```
