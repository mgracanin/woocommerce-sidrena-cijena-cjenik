# Sidrena Cijena i Cjenik za WooCommerce

[![Quality checks](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/actions/workflows/quality.yml/badge.svg)](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/actions/workflows/quality.yml)
[![Latest release](https://img.shields.io/github/v/release/mgracanin/woocommerce-sidrena-cijena-cjenik)](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/releases/latest)

Besplatan WordPress dodatak za bazne cijene i javni CSV/XML cjenik. Naziv dodatka ostaje **Sidrena Cijena i Cjenik**.

## Verzija 2.1.0 — zasebna arhiva i jasnije korisničko sučelje

**[Preuzmi instalacijski ZIP 2.1.0](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/releases/download/v2.1.0/sidrena-cijena-v2.1.0.zip)** · [Bilješke izdanja](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/releases/tag/v2.1.0)

Ova verzija mijenja strukturu izvoza. Integracije koje čitaju CSV/XML treba prilagoditi prije nadogradnje. Nakon provjere postojećih iznosa, datuma i novih postavki ponovno generirajte cjenik.

Implementacija prati Pravilnik o isticanju bazne cijene proizvoda (1583) i Pravilnik o načinu objave cjenika proizvoda (1584), objavljene 16.11.2026., s početkom primjene 17.11.2026. [Službeni tekstovi, str. 5–7](https://narodne-novine.nn.hr/eli/sluzbeni/2026/132/pdf).

## Nadogradnja s 1.x

1. Napravite sigurnosnu kopiju i provjerite nadogradnju na testnoj trgovini.
2. U WordPressu otvorite **Dodaci → Dodaj dodatak → Prenesi dodatak** i prenesite instalacijski ZIP iz odgovarajućeg izdanja.
3. U **Sidrena cijena → Bazna cijena** odaberite zadani referentni datum i tekst oznake, npr. **Bazna cijena na dan {datum}**.
4. Provjerite sve ranije iznose. Meta-ključ `_anchor_price` i iznosi ostaju sačuvani, ali 1.x je mogao kopirati aktualnu ili akcijsku cijenu. Takav unos nije dokaz povijesne cijene. Iznosi se unose na istoj poreznoj osnovi kao WooCommerce cijene; prikaz i izvoz preračunavaju ih u iznose s PDV-om prema osnovnim poreznim stopama trgovine.
5. U kartici **Inventar** pojedinog proizvoda odaberite datum bazne cijene ako odstupa od zadanog. Varijacije imaju zaseban datum i iznos. Datum varijacije može naslijediti roditelja; povijesni iznos ne nasljeđuje se s roditelja.
6. U **Cjenik** unesite OIB pravne osobe ili matični broj trgovca fizičke osobe, podatke o prodajnom mjestu i broj pohrane. Odaberite cjenik robe ili usluga. Jedan cjenik koristi jednu strukturu; uključite samo odgovarajuće stavke. Različita prodajna mjesta s različitim cijenama zahtijevaju zasebne cjenike; ovaj dodatak upravlja jednom WooCommerce trgovinom/cjenikom.
7. Dopunite neto količinu, jedinicu mjere, opći barkod i EAN gdje su primjenjivi. Označite obvezu jedinične cijene za odgovarajuću robu.
8. Generirajte cjenik i provjerite javne CSV/XML poveznice i arhivu bez prijave. Nepotpuni obvezni podaci blokiraju novo generiranje; prethodni cjenik ostaje dostupan, ali više ne mora odgovarati novim cijenama. Pogrešku odmah ispravite i ponovno generirajte cjenik.
9. Prilagodite vanjske integracije novim nazivima i redoslijedu stupaca. Za cjenik koristite javnu stranicu s kratkim kodom `[sidrena_cjenik format="oba" arhiva="da"]`.

Ne postoji vlastiti automatski updater. Nadogradnja se izvodi prijenosom ZIP-a.

## Bazne cijene i datumi

Zadani datum odabire se između **02.05.2025.** za hranu, piće, kozmetiku, sredstva za čišćenje, toaletne potrepštine i proizvode za kućanstvo te **10.09.2026.** za ostalu robu i usluge. Za novu ponudu nakon relevantnog datuma unesite stvarni datum prvog nuđenja. Datum se ne mijenja svakodnevno i nije datum zadnje izmjene proizvoda.

Za sniženje na referentni dan primjenjuje se čl. 3. Pravilnika 1583: za skupinu s datumom 02.05.2025. najniža maloprodajna cijena 30 dana prije početka posebnog uvjeta/oblika prodaje; za ostalu robu/usluge prethodno primijenjena maloprodajna cijena. Trgovac mora osigurati povijesnu evidenciju; dodatak je ne može rekonstruirati.

Prazan povijesni iznos više se ne kopira iz današnje cijene i ne prikazuje se kao poznata bazna cijena. Postojeći vlastiti tekst oznake ostaje sačuvan. Samo raniji zadani tekst prelazi na **Bazna cijena na dan {datum}**; oznaku možete proizvoljno preimenovati, uz očuvanje jasnoće.

Varijabilni i grouped roditelji nisu zasebni retci cjenika. Varijacije i podproizvodi izvoze se zasebno. Bazne cijene varijabilnog proizvoda vidljive su i prije odabira varijacije. Isti iznos i oznaka mogu se objediniti; različiti datumi/iznosi navode se uz naziv varijacije. Nakon odabira prikazuje se točan datum i iznos odabrane varijacije.

## Postavljanje na mrežno mjesto

Preuzimanje, arhivu i vrijeme ažuriranja možete postaviti odvojeno na stranice, u objave ili u widgete koji podržavaju kratke kodove.

| Namjena | Kratki kod |
|---|---|
| Poveznica u podnožju, bez datuma i arhive | `[sidrena_cjenik tekst="Preuzmi cjenik" azurirano="ne"]` |
| CSV cjenik s datumom, bez arhive (zadano) | `[sidrena_cjenik]` |
| XML cjenik | `[sidrena_cjenik format="xml"]` |
| Oba formata s vlastitim tekstom | `[sidrena_cjenik format="oba" tekst_csv="Preuzmi CSV" tekst_xml="Preuzmi XML"]` |
| Samo arhiva obaju formata | `[sidrena_cjenik_arhiva]` |
| Samo arhiva CSV cjenika | `[sidrena_cjenik_arhiva format="csv"]` |
| Preuzimanje i arhiva zajedno | `[sidrena_cjenik format="oba" arhiva="da"]` |
| Samo datum i vrijeme ažuriranja | `[sidrena_cjenik_azurirano tekst="Cjenik ažuriran:"]` |

Za odvojenu arhivu izradite javnu stranicu, primjerice **Arhiva cjenika**, dodajte `[sidrena_cjenik_arhiva]` te poveznicu na tu stranicu postavite u podnožje ili izbornik. Naziv i adresu stranice birate sami; dodatak ne stvara stranicu automatski. Arhiva mora ostati javno dostupna.

**Promjena u odnosu na 2.0.0:** `[sidrena_cjenik tekst="Preuzmi cjenik"]` više ne prikazuje arhivu. Vrijeme ažuriranja ostaje uključeno, a može se sakriti parametrom `azurirano="ne"`. Izričito navedeni `arhiva="da"` i dalje radi. Stalne CSV/XML adrese služe izravnom preuzimanju važećih datoteka i ne prikazuju arhivu.

## Neto količina i porezi

U Inventaru unesite količinu pakiranja u odabranoj jedinici: **500 + ml** daje cijenu po litri, a **250 + g** cijenu po kilogramu. Kod kg/L/m/m2/m3 koristi se odgovarajuća osnovna jedinica. Transportna težina proizvoda ne koristi se kao neto količina. Za `kom` zadana količina je 1.

Cijene s PDV-om računaju se prema osnovnim poreznim stopama trgovine, ne prema lokaciji ili poreznom oslobođenju trenutačno prijavljenog kupca. Provjerite porezne razrede i povijesne iznose, osobito ako su se porezne stope mijenjale. Za personalizirane cijene, dodatke za dinamičke popuste ili posebne porezne režime potrebna je zasebna provjera.

## Format i objava cjenika

Stalne adrese ostaju:

- `https://vasa-domena.hr/cjenik-proizvoda.csv`
- `https://vasa-domena.hr/cjenik-proizvoda.xml`

CSV je UTF-8 s razdjelnikom **točka-zarez**, neovisno o starim postavkama. XML sadrži iste podatke istim redoslijedom.

Cjenik robe: `naziv; sifra_proizvoda; brend; neto_kolicina; jedinica_mjere; maloprodajna_cijena; poseban_oblik_prodaje; cijena_po_jedinici; barkod; ean; bazna_cijena`.

Cjenik usluga: `naziv; maloprodajna_cijena; poseban_oblik_prodaje; bazna_cijena`.

Preuzeta datoteka u nazivu sadrži identifikator trgovca, prodajno mjesto/adresu/oznaku (ili `ZA_SVA_PRODAJNA_MJESTA` samo ako je cjenik jednak za sva mjesta), vrstu robe/usluga, broj pohrane s brojačem objave i lokalnu vremensku oznaku `GGGGMMDD_SSMM`. Brojač sprečava prepisivanje više arhiva nastalih u istoj minuti.

## Osvježavanje i arhiva

Izmijenjeni cjenik objavljuje se najkasnije do **11:00 na dan izmjene**. Za kasnije promjene preporučuje se neposredna objava; specifično tumačenje takvog roka provjerite za svoju djelatnost.

Dodatak pokušava generiranje na kraju zahtjeva koji mijenja proizvod, nakon spremanja svih polja, uz odgođeni ponovni pokušaj i dnevni WP-Cron. Veliki katalozi mogu produljiti zahtjev; provjerite vremenska ograničenja poslužitelja. Za pouzdanost postavite poslužiteljski cron koji redovito pokreće `wp-cron.php` i nadzirite zadnju pogrešku. WP-Cron sam ne jamči izvršavanje u određeno vrijeme. Izmjene izravno u bazi, mimo WooCommerce API-ja, zahtijevaju ručno generiranje.

Arhiva se zadržava najmanje **15 dana od zamjene cjenika**, uz mogućnost duljeg čuvanja. Aktivni cjenik ne istječe prema starosti. Kratki kod za preuzimanje prema zadanim postavkama ne prikazuje arhivu. Objavite je zasebnim kratkim kodom ili je izričito uključite na istome mjestu. Za javne datoteke omogućite automatizirano preuzimanje i izuzmite ih iz zastarjele predmemorije/WAF zabrana.

## Isključivanje i iznimke

**Inventar → Prikaz u cjeniku → Uvijek izostavi iz cjenika** ili isto polje u brzom uređivanju izostavlja pojedini proizvod. Postavke nisu pravna odluka o izuzeću. Skriveni proizvod koji se i dalje nudi potrošaču ne mora biti izuzet. Za opravdano izuzeće cijelog proizvoda prema čl. 4. postoji i **Proizvod je izuzet od obveze**: uklanja bazni prikaz i stavku cjenika. Varijacija može naslijediti postavku roditelja ili je izričito odrediti. Nije namijenjeno izostavljanju redovne robe samo zato što pojedini kupac ima personalizirani popust. Isključivanje kategorija nije podržano.

## Zahtjevi i provjere

WordPress 6.0+, WooCommerce 6.0+, PHP 7.4+. Deklarirana je kompatibilnost s HPOS-om i Cart/Checkout Blocks; prikaz cijene u konkretnoj temi i blokovima treba provjeriti na trgovini.

```powershell
node ./tests/static-check.mjs
php ./tests/shortcodes.php
./scripts/build-release.ps1
```

CI provjerava PHP 7.4/8.3, stvarne CSV/XML datoteke uz testne WooCommerce podatke, datume, poreze, objavu, arhiviranje, neuspješno generiranje i instalacijski paket. To nije zamjena za provjeru stvarne trgovine, baze, teme i svih integracija.

Izvoze za oglašivačke platforme i prikaze cijena izvan WooCommerce HTML hookova dodatak ne prilagođava automatski; provjerite ih zasebno.

Dodatak je tehnička pomoć, a ne pravno mišljenje ili jamstvo usklađenosti. Trgovac odgovara za obuhvat ponude, iznimke, točnost podataka i pravodobnu objavu.

## Autor i licenca

Matija Gračanin — [matijag@gmail.com](mailto:matijag@gmail.com). [GPL v2 ili noviji](LICENSE). Prijave problema: [GitHub Issues](https://github.com/mgracanin/woocommerce-sidrena-cijena-cjenik/issues). Sigurnosne prijave: [SECURITY.md](SECURITY.md).
