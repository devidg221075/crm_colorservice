# Gestionale domini - Color Service

Applicazione web in PHP 8 e MySQL (senza framework) per gestire clienti e domini: scadenze con alert giornalieri, import CSV da Aruba e Serverplan, calcolo del margine, notifiche in-app ed export/cancellazione dei dati dei titolari (privacy). Interfaccia in italiano.

- Installazione su hosting condiviso: [docs/deploy.md](docs/deploy.md)
- Specifica: [docs/superpowers/specs/2026-11-04-gestionale-domini-design.md](docs/superpowers/specs/2026-11-04-gestionale-domini-design.md)
- Piano di implementazione: [docs/superpowers/plans/2026-11-04-gestionale-domini.md](docs/superpowers/plans/2026-11-04-gestionale-domini.md)

## Sviluppo

```
composer install
vendor/bin/phpunit
```
