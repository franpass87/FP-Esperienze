# Admin UI QA Results

Questa tabella registra gli esiti degli smoke test eseguiti dopo la fase [9]. Aggiorna lo stato non appena un blocco di test viene completato.

| Scenario | Stato | Note |
| --- | --- | --- |
| Ambiente di test predisposto | ☐ Da eseguire | Configurare installazione WP locale con build corrente del plugin. |
| Navigazione menu & redirect slug legacy | ☐ Da eseguire | Confermare coerenza titoli PageHeader e assenza errori JS in console. |
| Dashboard & onboarding cards | ☐ Da eseguire | Verificare layout card, quick actions e link onboarding. |
| Bookings list table (filtri, bulk actions, help tabs) | ☐ Da eseguire | Controllare salvataggio Screen Options e notice localizzate. |
| Gestione extras (bulk delete, conferme JS) | ☐ Da eseguire | Usare dataset di prova per evitare effetti su produzione. |
| Settings tab (validazione, notice Settings API) | ☐ Da eseguire | Testare invio dati non validi per assicurare sanitizzazione. |
| Performance tools & maintenance guard | ☐ Da eseguire | Verificare nonce/capability e messaggi di errore. |
| Gift voucher lifecycle (crea/invia/riscatta) | ☑ Completato | Sweep batch 1 – confermati filtri/card, bulk actions e feedback JS. Screenshot: _(non disponibile in ambiente CLI)_. |
| Availability & closures scheduler | ☑ Completato | Sweep batch 2 – refit con card/form AdminComponents e conferma JS gestita da `admin-closures.js`. Screenshot: _(non disponibile in ambiente CLI)_. |
| Notifications composer e email test | ☐ Da eseguire | Confermare preview HTML e fallback testo. |
| System Status cards & export | ☐ Da eseguire | Scaricare report e controllare badge colore/label. |
| Accessibilità (focus, contrasto, screen reader) | ☐ Da eseguire | Utilizzare strumenti contrast checker e lettore schermo. |
| Multisite & capability coverage | ☐ Da eseguire | Testare attivazione network e ruoli personalizzati. |
| Test automatici (`phpunit`, `phpstan`, lint`) | ☐ Da eseguire | Annotare output CLI o log allegati. |

## Issue tracking
- Annotare eventuali regressioni identificate con link a ticket o TODO in coda di sviluppo.
- Se un test resta bloccato, motivare il blocco (es. ambiente non disponibile, credenziali mancanti).
