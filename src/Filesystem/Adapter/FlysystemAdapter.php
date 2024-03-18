<?php

/*
 * This file is part of the Acme PHP project.
 *
 * (c) Titouan Galopin <galopintitouan@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AcmePhp\Core\Filesystem\Adapter;

use AcmePhp\Core\Filesystem\FilesystemInterface;
use League\Flysystem\FilesystemOperator;

class FlysystemAdapter implements FilesystemInterface
{
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function write(string $path, string $content)
    {
        $this->filesystem->write($path, $content);
    }

    public function delete(string $path)
    {

        $isOnRemote = $this->filesystem->has($path);
        if ($isOnRemote) {
            $this->filesystem->delete($path);
        }
    }

    public function createDir(string $path)
    {
        $this->filesystem->createDirectory($path);
    }
}
