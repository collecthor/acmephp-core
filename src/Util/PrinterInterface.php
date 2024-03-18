<?php
declare(strict_types=1);

namespace AcmePhp\Core\Util;

interface PrinterInterface
{

    public function write(string $data): void;
}
