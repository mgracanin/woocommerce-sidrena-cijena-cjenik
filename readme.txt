=== Sidrena Cijena i Cjenik | Matija Gračanin ===
Contributors: matijag
Tags: woocommerce, cijena, cjenik, csv, hrvatska
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prikaz sidrene cijene i javni strojno čitljivi CSV/XML cjenik za WooCommerce prema odlukama NN 101/2026.

Autor: Matija Gračanin (matijag@gmail.com)

== Description ==

Dodatak pruža tehničku podršku za dvije povezane obveze koje se primjenjuju od 1. listopada 2026.:

* prikaz dodatne, odnosno sidrene cijene prema Odluci NN 101/2026, broj 1212,
* objavu i čuvanje strojno čitljivog cjenika prema Odluci NN 101/2026, broj 1213.

= Sidrena cijena =

* polje na svakom proizvodu i svakoj varijaciji,
* automatsko početno spremanje trenutačne aktualne cijene ako je polje prazno, bez kasnijeg automatskog prepisivanja,
* prikaz uz WooCommerce cijenu na proizvodu, trgovini, kategorijama i drugim popisima,
* Quick Edit i pregled broja proizvoda kojima cijena nedostaje,
* alat za početno popunjavanje samo praznih polja iz redovne cijene, bez prepisivanja postojećih vrijednosti,
* prilagodljiv tekst, font, stil, boja i veličina za desktop i mobitel.

Automatsko početno popunjavanje nije zamjena za evidenciju cijena. Današnja redovna cijena ne mora biti cijena koja je vrijedila 10.09.2026. Sve predložene iznose treba provjeriti.

= Cjenik =

* javni CSV i opcionalni XML,
* stalne javne adrese `/cjenik-proizvoda.csv` i `/cjenik-proizvoda.xml`, pogodne za automatizirane alate,
* kratki kod `[sidrena_cjenik]` za CSV, `[sidrena_cjenik format="xml"]` za XML i `[sidrena_cjenik format="oba"]` za oba gumba,
* opcija `arhiva="da"` za javni popis arhive odabranih formata,
* dnevno generiranje prije 8:00 te odgođeno osvježavanje nakon promjene proizvoda ili zalihe,
* arhivske datoteke s vrstom objekta, adresom, oznakom objekta, skladištem i vremenskom oznakom,
* najmanje 30 dana arhive,
* zasebni stupci za oznaku i naziv posebnog oblika prodaje,
* podrška za WooCommerce Brands te česte dodatke za brendove i barkodove,
* pregled spremnosti: nedostajuće cijene, sidrene cijene, šifre, brendovi i barkodovi,
* email obavijest i prikaz zadnje greške.
* ugrađeni tab Pomoć s brzim početkom, opisom funkcija, shortcodeovima, dijagnostikom i rješavanjem problema.

Odluka propisuje skup podataka, ali u trenutku izrade ove verzije nije bio objavljen obvezni redoslijed CSV stupaca, razdjelnik ni XML shema. Dodatak zato nudi stabilan, dokumentiran format i filtere za prilagodbu. Ovo je tehnička pomoć, a ne pravni savjet.

= Zahtjevi =

* WordPress s aktivnim WooCommerce dodatkom
* PHP 7.4 ili noviji

== Installation ==

1. U WordPress administraciji otvorite Plugins → Add New Plugin → Upload Plugin.
2. Odaberite ZIP, instalirajte i aktivirajte dodatak.
3. Otvorite Sidrena cijena → Sidrena cijena i unesite ili provjerite sidrene cijene.
4. Otvorite tab Cjenik i unesite vrstu objekta, oznaku objekta i broj skladišta. Adresa se preuzima iz WooCommerce → Settings → General.
5. Kliknite „Generiraj cjenik sada“ i provjerite stalnu javnu CSV poveznicu u anonimnom prozoru.
6. Na javnu stranicu umetnite `[sidrena_cjenik]`.
7. Za pouzdano izvršenje prije 8:00 postavite poslužiteljski cron koji redovito pokreće `wp-cron.php`; ugrađeni WP-Cron ovisi o posjetima stranici.

== Frequently Asked Questions ==

= Hoće li alat prepisati postojeće sidrene cijene? =

Ne. Masovno početno popunjavanje dira samo prazna polja.

= Podržava li varijabilne proizvode? =

Da. Varijacije se izvoze kao zasebne stavke i mogu imati vlastitu sidrenu cijenu i barkod.

= Gdje se cjenik sprema? =

U `wp-content/uploads/cjenik/`. Stalna javna poveznica skriva promjene stvarnog naziva aktualne datoteke, dok arhivske datoteke zadržavaju propisane podatke u nazivu.

= Je li ugrađeno dnevno generiranje potpuno zajamčeno u točno vrijeme? =

Ne. WP-Cron se pokreće prometom na web-stranici. Za zajamčeno izvršenje upotrijebite pravi cron poslužitelja koji poziva WordPress cron.

= Je li ovo pravni savjet ili jamstvo usklađenosti? =

Ne. Dodatak tehnički podržava javno objavljene zahtjeve. Vlasnik trgovine mora provjeriti podatke, poslovna pravila i konačnu usklađenost.

== Changelog ==

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
