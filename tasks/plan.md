# NN 132/2026 — implementacija 2.0.0

Izvor: https://narodne-novine.nn.hr/eli/sluzbeni/2026/132/pdf, pravilnici 1583 i 1584, str. 5–7. Koristimo konačni objavljeni tekst, ne nacrte.

## Odluke

- Naziv dodatka i stari meta-ključ iznosa ostaju; novi izvoz ima novu glavnu verziju zbog promjene sheme.
- Datum je zadan po trgovini, nadjačava se na proizvodu i varijaciji. Iznos dolazi samo iz evidencije trgovca; nema automatskog kopiranja današnjih cijena.
- Jedna konfigurirana trgovina/cjenik: roba ili usluge, s jedinstvenom strukturom. Različita prodajna mjesta zahtijevaju zasebne cjenike; jednaki koriste propisanu oznaku.
- Novu objavu s nepotpunim podacima blokirati i sačuvati postojeću datoteku. Prikazati pogrešku, ne tvrdnju pravne usklađenosti.
- Računati bruto iznose prema osnovnim stopama trgovine. Povijesne porezne promjene i prilagođene cjenovne integracije traže trgovčevu provjeru.

## Koraci i provjere

1. Datumi, oznake, iznosi i administrativna polja; regresije nasljeđivanja i uklanjanja fallbacka.
2. Roba/usluge, neto količina, porezi i barkodovi; provjeriti točan redoslijed stvarnih CSV/XML datoteka.
3. Podaci trgovca i nazivi datoteka; provjeriti stabilan URL i arhive u istoj minuti.
4. Arhiva od zamjene, osvježavanje i greške; dokazati očuvanje objave nakon neuspjeha.
5. Dokumentacija i instalacijski ZIP; PHP 7.4/8.3 sintaksa i testovi, statičke provjere, provjera paketa.

## Ograničenja prihvata

Nema pristupa stvarnoj trgovini. Lokalni testovi s lažnim WooCommerce podacima ne dokazuju prikaz u konkretnoj temi, stvarnu bazu/zaključavanje ili pravnu primjenjivost iznimaka. Prije instalacije u produkciju provjeriti na testnoj kopiji trgovine.
