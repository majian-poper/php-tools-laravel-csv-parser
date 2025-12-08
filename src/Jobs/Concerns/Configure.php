<?php

namespace PHPTools\LaravelCsvParser\Jobs\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use PHPTools\LaravelCsvParser\Contracts\CsvFile;

trait Configure
{
    public CsvFile & Model $file;

    public int $timeout;

    public int $chunkSize;

    protected function configure(CsvFile & Model $file): void
    {
        $this->file = $file->unsetRelations();

        $this->chunkSize = config('csv-parser.chunk_size');
        $this->timeout = config('csv-parser.timeout');
    }

    public function displayName(): string
    {
        return \get_class($this) . ' #' . $this->file->getKey();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->displayName()))->dontRelease()];
    }
}
