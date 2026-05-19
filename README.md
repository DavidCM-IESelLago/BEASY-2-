# Fintech Bancaria - Proyecto Intermodular
Plataforma bancaria digital con arquitectura Productor/Consumidor

## Instalación

- Clonar repositorio:
    ```bash
    git clone https://github.com/DavidCM-IESelLago/BEASY-2-.git
    cd BEASY-2-

- Crear .env con las credenciales reales:
    ```bash
    cp backend/.env.example backend/.env

- Instalar dependencias de Composer:
    ```bash
    cd backend
    composer install
    cd ..

- Levantar el stack:
    ```bash
    docker compose up -d
