<?php

namespace SidrenaCijenaCjenik\Catalog;

if (!defined('ABSPATH')) {
    exit;
}

final class CsvStreamWriter
{
    private $handle;
    private $delimiter;
    private $columns;
    private $textColumns;

    public function __construct($path, $delimiter, array $columns, array $textColumns)
    {
        $this->handle = @fopen($path, 'wb');
        if (!$this->handle) {
            throw new \RuntimeException('Nije moguće otvoriti privremenu CSV datoteku.');
        }

        $this->delimiter = $delimiter;
        $this->columns = $columns;
        $this->textColumns = array_fill_keys($textColumns, true);

        $this->writeRaw("\xEF\xBB\xBF");
        $this->writeCsv($columns);
    }

    public function writeRow(array $row)
    {
        $values = array();
        foreach ($this->columns as $column) {
            $value = isset($row[$column]) ? (string) $row[$column] : '';
            if (isset($this->textColumns[$column])) {
                $value = self::spreadsheetSafeText($value);
            }
            $values[] = $value;
        }
        $this->writeCsv($values);
    }

    public function close()
    {
        if (is_resource($this->handle)) {
            if (!fflush($this->handle)) {
                fclose($this->handle);
                $this->handle = null;
                throw new \RuntimeException('CSV datoteku nije moguće spremiti do kraja.');
            }
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public static function spreadsheetSafeText($value)
    {
        $value = (string) $value;
        if ($value !== '' && preg_match('/^(?:[=+\-@\t\r]|\s+[=+\-@])/u', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    private function writeCsv(array $values)
    {
        if (fputcsv($this->handle, $values, $this->delimiter, '"', '') === false) {
            throw new \RuntimeException('Nije uspjelo zapisivanje retka u CSV datoteku.');
        }
    }

    private function writeRaw($contents)
    {
        if (fwrite($this->handle, $contents) === false) {
            throw new \RuntimeException('Zapisivanje CSV datoteke nije uspjelo.');
        }
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}

final class XmlStreamWriter
{
    private $handle;
    private $columns;

    public function __construct($path, array $columns, array $meta)
    {
        $this->handle = @fopen($path, 'wb');
        if (!$this->handle) {
            throw new \RuntimeException('Nije moguće otvoriti privremenu XML datoteku.');
        }
        $this->columns = $columns;

        $attributes = array(
            'datum_generiranja' => $meta['generated_at'],
            'vrsta_prodajnog_objekta' => $meta['object_type'],
            'prodajno_mjesto' => $meta['location_label'],
            'adresa' => $meta['address'],
            'oznaka_objekta' => $meta['location_label'],
            'broj_skladista' => $meta['storage_number'],
        );

        $root = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<cjenik";
        foreach ($attributes as $name => $value) {
            $root .= ' ' . $name . '="' . self::escape($value) . '"';
        }
        $this->writeRaw($root . ">\n");
    }

    public function writeRow(array $row)
    {
        $xml = "  <proizvod>\n";
        foreach ($this->columns as $column) {
            $value = isset($row[$column]) ? $row[$column] : '';
            $xml .= '    <' . $column . '>' . self::escape($value) . '</' . $column . ">\n";
        }
        $this->writeRaw($xml . "  </proizvod>\n");
    }

    public function close()
    {
        if (!is_resource($this->handle)) {
            return;
        }

        $this->writeRaw("</cjenik>\n");
        if (!fflush($this->handle)) {
            fclose($this->handle);
            $this->handle = null;
            throw new \RuntimeException('XML datoteku nije moguće spremiti do kraja.');
        }
        fclose($this->handle);
        $this->handle = null;
    }

    private static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function writeRaw($contents)
    {
        if (fwrite($this->handle, $contents) === false) {
            throw new \RuntimeException('Zapisivanje XML datoteke nije uspjelo.');
        }
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
