# Changelog

Sve značajne promjene projekta bilježe se u ovoj datoteci.

## [1.2.4] - 2026-11-13

### Ispravljeno

- složeni (grouped) roditelji izostavljaju se iz CSV/XML cjenika, uključujući kada imaju postavku „Uvijek uključi u cjenik”
- podproizvodi ostaju zasebno navedeni prema vlastitim postavkama vidljivosti; proizvodi s valjanom cijenom 0,00 ostaju uključeni

## [1.2.3] - 2026-09-21

### Refaktorirano

- interni PHP namespace promijenjen je u neutralni `SidrenaCijenaCjenik`

## [1.2.2] - 2026-09-21

### Ispravljeno

- brzo generiranje više ne prijavljuje lažni gubitak vlasništva nad lockom kada se obnova dogodi unutar iste sekunde

## [1.2.1] - 2026-09-21

### Refaktorirano

- konfiguracija sidrene cijene premještena je u namespaciranu `Config` klasu
- postojeći meta-ključevi, opcije i globalne konstante ostaju kompatibilni

## [1.2.0] - 2026-09-21

### Sigurnost i pouzdanost

- CSV tekstualne vrijednosti neutraliziraju formule pri otvaranju u tabličnim programima
- javni download više ne pokreće sinkrono generiranje cjenika
- svi generatori koriste zajednički atomski lock i verzionirano stanje promjena
- datoteke se objavljuju iz privremenih datoteka, uz povrat prethodne verzije pri pogrešci
- nedostupan WooCommerce više ne može zamijeniti ispravan cjenik praznim datotekama

### Poboljšano

- proizvodi se obrađuju u stranicama i CSV/XML se zapisuju strujno
- dnevno generiranje prati odabrano lokalno vrijeme i nakon promjene ljetnog računanja vremena
- readiness razlikuje spremljenu sidrenu cijenu od privremenog fallbacka
- dodana kompatibilnost s PHP-om 8.4 za CSV zapis
- release paket uključuje sve runtime module, licencu i obavijest o podrijetlu

## [1.1.5] - 2026-09-21

### Dodano

- javne stabilne adrese za CSV i XML cjenik
- odabir CSV/XML formata i kratki kodovi za poveznice
- ugrađena kartica Pomoć s dijagnostikom i rješavanjem problema
- početna sidrena cijena iz trenutačne aktivne WooCommerce cijene kada je polje prazno
- deklarirana kompatibilnost s HPOS-om te Cart i Checkout Blocks značajkama

### Poboljšano

- cjenik podržava arhivu, HTTP predmemoriranje i UTF-8 XML
- postojeće sidrene cijene ostaju netaknute pri inicijalizaciji
