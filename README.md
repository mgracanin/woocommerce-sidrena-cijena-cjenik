# Sidrena Cijena i Cjenik za WooCommerce

[![Quality checks](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/actions/workflows/quality.yml/badge.svg)](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/actions/workflows/quality.yml)
[![Latest release](https://img.shields.io/github/v/release/mgracanin/woocommerce-sidrena-cijena-cjenik)](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/releases/latest)
[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL_v2_or_later-blue.svg)](LICENSE)

Besplatan WordPress dodatak za prikaz sidrene cijene te objavu strojno čitljivog CSV/XML cjenika u WooCommerce trgovini.

## Aktualno izdanje: 1.2.4

Objavljeno **13. studenoga 2026.** — [preuzmi instalacijski ZIP](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/releases/download/v1.2.4/sidrena-cijena-v1.2.4.zip) · [bilješke izdanja](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/releases/tag/v1.2.4).

Ispravljen je izvoz složenih (grouped) proizvoda: roditelj se više ne pojavljuje kao zaseban redak u CSV/XML cjeniku, a njegovi podproizvodi ostaju zasebno navedeni bez dupliciranja. Nakon nadogradnje **ponovno generirajte cjenik**.

Dodatak je napravljen kao tehnička pomoć vlasnicima internetskih trgovina koje koriste WooCommerce pri provedbi odluka objavljenih u Narodnim novinama 101/2026.

> [!IMPORTANT]
> Prije produkcijske uporabe provjerite postavke, podatke i obveze koje se primjenjuju na vašu trgovinu, po potrebi s računovođom i/ili pravnim savjetnikom. Dodatak pruža tehničku pomoć, ali sam po sebi ne jamči pravnu usklađenost internetske trgovine.

## Mogućnosti

- zasebna sidrena cijena za jednostavne proizvode i varijacije
- početno popunjavanje prazne sidrene cijene trenutačnom aktivnom WooCommerce cijenom
- postojeće sidrene cijene ne prepisuju se automatski
- prikaz sidrene cijene na stranici proizvoda, arhivama i drugim WooCommerce popisima
- prilagodljiv tekst, font, stil, boja i veličina prikaza za računala i mobilne uređaje
- javni CSV i XML cjenik sa stalnim URL-ovima
- ručno i dnevno generiranje cjenika
- odgođeno osvježavanje nakon promjene proizvoda ili zalihe
- najmanje 30 dana arhive cjenika
- izvoz varijacija, zalihe, brenda, barkoda i podataka o posebnom obliku prodaje
- izvoz podproizvoda složenih (grouped) proizvoda bez zasebnog retka grouped roditelja
- pregled spremnosti podataka i prikaz zadnje pogreške
- ugrađena pomoć i dijagnostika
- podrška za WooCommerce HPOS te Cart i Checkout Blocks

## Zahtjevi

- WordPress 6.0 ili noviji
- WooCommerce 6.0 ili noviji
- PHP 7.4 ili noviji

## Instalacija

1. Otvorite stranicu [najnovijeg izdanja](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/releases/latest).
2. U odjeljku **Assets** preuzmite instalacijski paket `sidrena-cijena-vX.Y.Z.zip`.
3. U WordPress administraciji otvorite **Dodaci → Dodaj dodatak → Prenesi dodatak**.
4. Odaberite preuzeti ZIP, a zatim instalirajte i aktivirajte dodatak.
5. Otvorite **Sidrena cijena → Sidrena cijena** i provjerite početno predložene vrijednosti.
6. Otvorite karticu **Cjenik**, unesite podatke o prodajnom objektu i kliknite **Generiraj cjenik sada**.
7. Provjerite javne CSV i XML poveznice u anonimnom prozoru preglednika.

Nadogradnja se trenutačno izvodi ponovnim prijenosom instalacijskog ZIP-a iz novog GitHub izdanja. Dodatak zasad ne sadrži vlastiti sustav automatskog ažuriranja izvan službenog WordPress direktorija dodataka.

## Sidrena cijena

Prilikom početne inicijalizacije prazno polje sidrene cijene automatski se popunjava trenutačnom aktivnom WooCommerce cijenom.

Postojeće sidrene cijene pritom se ne mijenjaju niti automatski prepisuju.

> [!WARNING]
> Trenutačna WooCommerce cijena ne mora odgovarati cijeni koja je vrijedila na relevantni datum. Automatski unesene vrijednosti potrebno je provjeriti prema stvarnoj povijesti cijena i obvezama koje se primjenjuju na trgovca.

Sidrena cijena može se zasebno uređivati za:

- jednostavne proizvode
- varijabilne proizvode
- pojedinačne varijacije

Izgled prikaza može se prilagoditi kroz postavke dodatka:

- tekst oznake
- veličina fonta
- veličina fonta na mobilnim uređajima
- vrsta fonta
- debljina i stil fonta
- boja teksta

## Javni cjenik

Nakon uključivanja odgovarajućeg formata, cjenik je dostupan na stalnim URL-ovima:

- `https://vasa-domena.hr/cjenik-proizvoda.csv`
- `https://vasa-domena.hr/cjenik-proizvoda.xml`

Stalne adrese ostaju jednake i nakon generiranja nove verzije cjenika, zbog čega su pogodne za vanjske sustave i automatizirano preuzimanje.

### Kratki kodovi

Za prikaz poveznica za preuzimanje na javnoj stranici mogu se koristiti sljedeći kratki kodovi:

```text
[sidrena_cjenik]
```

Prikaz poveznice za XML:

```text
[sidrena_cjenik format="xml"]
```

Prikaz poveznica za oba formata:

```text
[sidrena_cjenik format="oba"]
```

Prikaz poveznica za oba formata i javne arhive:

```text
[sidrena_cjenik format="oba" arhiva="da"]
```

## Generiranje i arhiva cjenika

Cjenik se može generirati ručno kroz administraciju dodatka.

Dodatak također podržava:

- dnevno automatsko generiranje
- odgođeno osvježavanje nakon promjene proizvoda ili zalihe
- arhiviranje prethodnih verzija cjenika
- čuvanje najmanje 30 dana arhive
- CSV i XML format
- HTTP zaglavlja `ETag` i `Last-Modified`

WordPressov WP-Cron pokreće se prilikom posjeta stranici. Zbog toga izvršavanje u točno određeno vrijeme nije zajamčeno na trgovinama s malo posjeta.

Za pouzdano dnevno generiranje preporučuje se postaviti poslužiteljski cron zadatak koji redovito poziva `wp-cron.php`.

## Podaci u cjeniku

Ovisno o vrsti proizvoda i dostupnim podacima, cjenik može sadržavati:

- naziv proizvoda
- šifru proizvoda
- brend
- jedinicu mjere
- cijenu po jedinici
- maloprodajnu cijenu
- oznaku posebnog oblika prodaje
- naziv posebnog oblika prodaje
- sidrenu cijenu
- barkod
- dostupnost
- podatke o varijacijama

Dodatak podržava WooCommerce Brands i više često korištenih dodataka za brendove i barkodove.

Nadređeni varijabilni i složeni (grouped) proizvodi ne izvoze se kao zasebni retci. Izvoze se njihove varijacije odnosno podproizvodi. Podproizvodi grouped proizvoda slijede vlastite postavke vidljivosti i uključivanja u cjenik.

Za izostavljanje pojedinog proizvoda u uređivanju proizvoda, uz SKU, odaberite **Prikaz u cjeniku → Uvijek izostavi iz cjenika**. Postavka je dostupna i u brzom uređivanju. Nakon spremanja ponovno generirajte cjenik. Isključivanje cijele kategorije zasad nije podržano.

Prije javne objave provjerite potpunost i točnost izvezenih podataka te odgovara li format trenutačno važećim tehničkim zahtjevima.

## Pravna podloga

- [Odluka NN 101/2026, broj 1212](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html) – prikaz dodatne cijene
- [Odluka NN 101/2026, broj 1213](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html) – objava i čuvanje cjenika

Dodatak olakšava tehničku provedbu navedenih odluka, ali ne jamči pravnu usklađenost konkretne internetske trgovine.

Vlasnik trgovine odgovoran je za:

- točnost i potpunost podataka
- ispravnu konfiguraciju dodatka
- provjeru automatski predloženih sidrenih cijena
- pravodobno generiranje i objavu cjenika
- usklađenost s važećim propisima

Dodatak se daje u dobroj namjeri i bez jamstva, u skladu s uvjetima licence GNU GPL.

## Razvoj i provjera

PHP sintaksa automatski se provjerava kroz GitHub Actions na PHP-u 7.4 i 8.3.

Lokalni instalacijski paket moguće je izraditi u PowerShellu:

```powershell
.\scripts\build-release.ps1
```

Paket nastaje u direktoriju `dist/` i sadrži samo datoteke potrebne za instalaciju dodatka u WordPress.

Statičke provjere moguće je pokrenuti naredbom:

```powershell
node .\tests\static-check.mjs
```

Regresijska provjera odabira proizvoda i CSV/XML izvoza koristi PHP, bez instalacije WordPressa:

```powershell
php .\tests\catalog-rows.php
```

## Prijava problema i doprinos

Prijave grešaka i prijedlozi dobrodošli su u odjeljku [Issues](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/issues).

Prije prijave problema provjerite postoji li već otvorena prijava za isti problem.

Upute za doprinos nalaze se u datoteci [CONTRIBUTING.md](CONTRIBUTING.md).

Moguće sigurnosne propuste nemojte objavljivati kao javni Issue. Upute za sigurnosne prijave nalaze se u datoteci [SECURITY.md](SECURITY.md).

## Autor

**Matija Gračanin**

Kontakt: [matijag@gmail.com](mailto:matijag@gmail.com)

## Licenca

Ovaj dodatak slobodan je softver objavljen pod licencom [GNU General Public License v2.0 ili novijom](LICENSE).

Možete ga koristiti, proučavati, mijenjati i distribuirati pod uvjetima navedene licence.
