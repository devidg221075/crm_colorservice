<?php
declare(strict_types=1);

namespace App\Services;

/** Esito di CsvImporter::parse(): cosa verrebbe creato, cosa aggiornato e quali righe sono da scartare. */
final class ImportPreview
{
    /**
     * @param array<int,array{nome:string,provider:string,data_acquisto:?string,data_scadenza:string,riga:int}> $nuovi
     * @param array<int,array{id:int,nome:string,provider:string,data_acquisto:?string,data_scadenza:string,riga:int}> $aggiornati
     * @param array<int,string> $errori messaggio per numero di riga del file (la riga 1 e' l'intestazione)
     */
    public function __construct(
        public array $nuovi = [],
        public array $aggiornati = [],
        public array $errori = [],
        public string $provider = 'altro',
        public string $file = '',
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $a): self
    {
        return new self(
            (array) ($a['nuovi'] ?? []),
            (array) ($a['aggiornati'] ?? []),
            (array) ($a['errori'] ?? []),
            (string) ($a['provider'] ?? 'altro'),
            (string) ($a['file'] ?? ''),
        );
    }
}
