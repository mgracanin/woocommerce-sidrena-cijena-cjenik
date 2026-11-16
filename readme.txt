=== Sidrena Cijena i Cjenik za WooCommerce ===
Contributors: mgracanin
Tags: woocommerce, cijena, cjenik, csv, hrvatska
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bazne cijene i javni CSV/XML cjenik prema pravilnicima NN 132/2026.

== Description ==

Naziv dodatka ostaje Sidrena Cijena i Cjenik. Verzija 2.0 uvodi datume po proizvodu i varijaciji, oznaku s {datum}, propisani redoslijed izvoza za robu/usluge, neto količinu, odvojeni EAN, cijene s PDV-om i jedinične cijene.

Postojeći iznosi ostaju sačuvani. Ranije automatski kopirane cijene provjerite prema vlastitoj evidenciji. Nepoznate bazne cijene više se ne zamjenjuju aktualnim cijenama. Bez obveznih podataka novo generiranje ne uspijeva, a zadnji cjenik ostaje objavljen.

== Installation ==

1. Instalirajte odgovarajući ZIP iz GitHub Releases.
2. Odaberite zadani referentni datum: 02.05.2025. ili 10.09.2026. prema asortimanu. Za novu ponudu ili odstupanje datum uredite u Inventaru proizvoda ili na varijaciji.
3. Unesite povijesne iznose u istoj poreznoj osnovi kao WooCommerce cijene; provjerite sniženja na referentni dan prema čl. 3. Pravilnika 1583.
4. U kartici Cjenik unesite OIB pravne osobe ili matični broj fizičke osobe, podatke prodajnog mjesta, broj pohrane i vrstu robe/usluga.
5. Dopunite neto količinu, jedinicu mjere i barkodove kada su primjenjivi. Označite obveznu jediničnu cijenu za odgovarajuću robu.
6. Generirajte cjenik i postavite [sidrena_cjenik format="oba" arhiva="da"] na javnu stranicu.
7. Provjerite javne datoteke, arhivu i automatizirano preuzimanje. Za pouzdan WP-Cron postavite poslužiteljski cron i nadzirite greške.

== Frequently Asked Questions ==

= Mijenja li se datum svaki dan? =
Ne. To je povijesni datum ili datum prvog nuđenja novog proizvoda. Oznaku možete promijeniti, npr. Bazna cijena na dan {datum}.

= Što se mijenja pri nadogradnji s 1.x? =
Izvozna shema se mijenja. Prilagodite vanjske integracije i provjerite sve iznose, datume i poreznu osnovu. Postojeći meta-ključevi i iznosi se ne brišu.

= Kada se osvježava cjenik? =
Pokušaj po završetku zahtjeva koji mijenja proizvod, WP-Cron ponovni pokušaj i dnevno generiranje. Rok prema Pravilniku je 11:00 na dan izmjene; kasnije izmjene objavite odmah i provjerite tumačenje za svoju djelatnost. Arhiva je dostupna najmanje 15 dana od zamjene.

= Je li zajamčena pravna usklađenost? =
Ne. Trgovac odgovara za točne povijesne podatke, asortiman, iznimke, poreze i pravodobnu objavu. Potrebna je provjera stvarne trgovine. Detaljne upute i izvori su u README.md repozitorija.

== Changelog ==

= 2.0.0 =
* Prilagodba pravilnicima NN 132/2026 od 17.11.2026.; naziv dodatka ostaje isti.
* Datumi bazne cijene po proizvodu/varijaciji i oznaka s {datum}.
* Uklonjeno automatsko kopiranje aktualne cijene i fallback za nepoznate povijesne iznose.
* Nova CSV/XML struktura za robu i usluge, neto količina, EAN, izračun cijena s PDV-om i jedinične cijene.
* Identifikator trgovca i propisani elementi naziva preuzete datoteke.
* Arhiviranje od zamjene cjenika, javna arhiva i neposredni pokušaj osvježavanja.
* VAŽNO: promijenjena izvozna shema; potrebna provjera postojećih iznosa i konfiguracije prije generiranja.


= 1.2.4 =
* Složeni (grouped) roditelji više se ne izvoze kao zasebni proizvodi u CSV/XML cjeniku; njihovi podproizvodi ostaju zasebno navedeni.

= 1.2.3 =
* Interni PHP namespace promijenjen je u neutralni SidrenaCijenaCjenik.

= 1.2.2 =
* Ispravljena obnova generation locka kada se više stranica proizvoda obradi unutar iste sekunde.

= 1.2.1 =
* Konfiguracija sidrene cijene premještena je u namespaciranu Config klasu uz kompatibilnost postojećih ključeva.

= 1.2.0 =
* Sigurniji CSV izvoz i zajednički atomski lock za generiranje.
* Javni download služi zadnji dovršeni cjenik bez sinkronog generiranja.
* Strujno i paginirano generiranje te atomska objava CSV/XML datoteka.
* Pouzdanije dirty stanje, DST-aware scheduling i PHP 8.4 kompatibilnost.

= 1.1.5 =
* Dodan administratorski tab Pomoć s potpunim vodičem kroz sidrenu cijenu i cjenik.
* Dodani dinamički statusi prikaza, automatike, formata, zadnjeg i sljedećeg generiranja te pogrešaka.
* Dodane upute za javne URL-ove, shortcodeove, polja proizvoda, WP-Cron i rješavanje problema.
* Dodane službene poveznice na obje odluke NN 101/2026 i kontakt autora.

= 1.1.4 =
* Dodana stalna javna XML adresa `/cjenik-proizvoda.xml`.
* Kratki kod sada podržava CSV, XML ili oba formata te odvojene arhive.
* XML izvoz izričito koristi UTF-8 i sigurno kodira posebne znakove.
* Javni CSV i XML odgovori dobili su ETag i Last-Modified zaglavlja radi učinkovitog automatiziranog preuzimanja.
* Administracija prikazuje obje stalne javne adrese.

= 1.1.3 =
* Prazne sidrene cijene pri nadogradnji početno se popunjavaju trenutačnom aktualnom WooCommerce cijenom.
* Novi proizvodi i varijacije pri prvom spremanju dobivaju sidrenu cijenu iz tadašnje aktualne cijene.
* Postojeće ručno unesene sidrene cijene nikada se automatski ne prepisuju.
* Prikaz i CSV koriste aktualnu cijenu kao sigurnu početnu vrijednost ako snimka još nije izrađena.

= 1.1.2 =
* Dodana službena deklaracija kompatibilnosti s WooCommerce HPOS pohranom narudžbi.
* Dodana deklaracija kompatibilnosti s WooCommerce Cart i Checkout blokovima; dodatak ne mijenja njihov tok.

= 1.1.1 =
* Autor dodatka promijenjen je u Matija Gračanin (matijag@gmail.com).
* Uklonjene su vidljive oznake prethodnog autora iz naziva dodatka i administratorskog izbornika.

= 1.1.0 =
* Dodana stalna javna CSV adresa i kratki kod za preuzimanje/arhivu.
* Dodana provjera spremnosti podataka i prikaz zadnje greške.
* Dodano sigurno masovno popunjavanje samo praznih sidrenih cijena.
* Naziv arhive sada uključuje vrstu prodajnog objekta.
* Dodan naziv posebnog oblika prodaje kao zaseban stupac.
* Dodane kompatibilne lokacije brenda i barkoda.
* Osvježavanje nakon izmjena sada je odgođeno radi bržeg uređivanja proizvoda.
* Ispravljeno planiranje dnevnog vremena u WordPress vremenskoj zoni.
* Spriječeno dvostruko prikazivanje sidrene cijene kod varijacija.

= 1.0.0 =
* Prva verzija.
