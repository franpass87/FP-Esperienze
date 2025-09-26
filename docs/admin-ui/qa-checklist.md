# Admin UI QA Checklist

Questa checklist supporta la fase [9] del playbook "Admin UI/UX Revamp + Menu Reorg + Functional QA" per FP Esperienze. È organizzata per aree funzionali, così da guidare un giro di smoke test completo dopo il restyling dell'admin.

## 1. Preparazione ambiente
- [ ] WordPress aggiornato alla versione supportata dal plugin.
- [ ] PHP in modalità `WP_DEBUG` attiva, `WP_DEBUG_LOG` abilitato.
- [ ] Browser desktop a risoluzione ≥ 1280px con strumenti di accessibilità (es. inspector contrasto) disponibili.
- [ ] Plugin FP Esperienze installato dalla build corrente (`/dist` o checkout) e attivato.
- [ ] Utente tester con capability `manage_fp_experiences` (o ruolo amministratore) disponibile.

## 2. Navigazione e menu
- [ ] Verificare che la voce principale "FP Esperienze" utilizzi icona e label aggiornate.
- [ ] Aprire ciascun sottomenu e confermare che il titolo della pagina corrisponda al PageHeader e al percorso documentato.
- [ ] Testare i redirect dagli slug legacy (`page=` query string) verso i nuovi slug canonici.
- [ ] Usare la tastiera (TAB/SHIFT+TAB) per raggiungere tutte le voci, assicurandosi che il focus sia visibile.
- [ ] Assicurarsi che Screen Options e Help Tabs siano presenti dove previsti e che i loro toggle funzionino.

## 3. Dashboard & onboarding
- [ ] Verificare render dei metric cards, quick actions e alert component: nessun layout rotto, icone visibili.
- [ ] Attivare/disattivare eventuali card opzionali dalle Screen Options se disponibili.
- [ ] Confermare che i link di onboarding o documentazione aprano le destinazioni corrette.

## 4. Bookings & list tables
- [ ] Filtrare la list table delle prenotazioni per stato, data e ricerca testuale; confermare messaggi di empty state coerenti.
- [ ] Eseguire un bulk action (es. conferma o cancellazione) su più righe e validare notice di successo/errore.
- [ ] Aprire Screen Options della list table: modificare colonne visibili e "per page"; assicurarsi che le preferenze vengano salvate.
- [ ] Consultare le Help Tabs per comprendere le definizioni delle colonne e degli stati.
- [ ] Testare i pulsanti di azione inline (view, edit, resend email) assicurando conferme JS ove previsto.

## 5. Gestione esperienze & extras
- [ ] Creare una nuova esperienza e verificare che il form utilizzi i componenti (FormRow, TabNav) con label e descrizioni corrette.
- [ ] Aggiornare un'esperienza esistente e confermare che gli errori di validazione compaiano con stile WP.
- [ ] Nella schermata extras: usare bulk delete, azioni inline, e confermare avvisi localizzati.
- [ ] Verificare che l'importatore CSV (se presente) mostri notice coerenti e messaggi di errore tradotti.

## 6. Settings & integrazioni
- [ ] Per ogni tab di impostazioni, salvare modifiche valide e non valide per verificare sanitizzazione, notice e ritorno del form.
- [ ] Eseguire tool di manutenzione (purge cache, cron reset, ecc.) e controllare le capability/nonce.
- [ ] Testare la rigenerazione del secret dei gift voucher e confermare gestione errori/notice.
- [ ] Validare i collegamenti verso servizi terzi (API key, webhook) e la presenza di helper text contestuale.

## 7. Sistema & diagnostica
- [ ] Aprire la pagina System Status e controllare che ogni card (dipendenze, database, API) mostri badge e colori corretti.
- [ ] Scaricare eventuali report diagnostici / export e assicurarsi che i link siano funzionanti.
- [ ] Se disponibili, verificare che i cron job simulati o i check programmati mostrino ultimo esito aggiornato.

## 8. Notifiche, voucher, automation
- [ ] Testare la pagina notifiche: invio email di prova, toggle template, e preview contenuto.
- [ ] Gestire gift voucher: creare, inviare via email, riscattare da admin; confermare notice e aggiornamento list table.
- [ ] Verificare flussi di automazione (es. integrazione Zapier/Webhook) creando un webhook di prova e simulando una chiamata.

## 9. Accessibilità & usabilità
- [ ] Confermare contrasto ≥ 4.5:1 per testo normale e 3:1 per UI component (icone/badge) tramite strumento browser.
- [ ] Navigare moduli e tab con la tastiera; focus sempre visibile e ordine logico.
- [ ] Testare con screen reader (es. NVDA/VoiceOver) la lettura di heading, tab e notifiche.
- [ ] Verificare che gli elementi dinamici (notice dismissible, toggle) annuncino i cambi di stato.

## 10. Regressioni generali
- [ ] Creare/aggiornare/cancellare esperienze, extras, prenotazioni, notifiche senza errori PHP o avvisi.
- [ ] Controllare il registro errori (`debug.log`) per warning/notice dopo il giro completo.
- [ ] Verificare compatibilità multisite (se applicabile) attivando il plugin a livello network e per singolo sito.
- [ ] Eseguire eventuali test automatici disponibili (`phpunit`, `phpstan`, smoke scripts) e archiviare l'esito.

> Annotare eventuali regressioni o follow-up direttamente in `docs/admin-ui/qa-results.md`.
