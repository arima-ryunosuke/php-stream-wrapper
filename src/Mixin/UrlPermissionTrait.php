<?php
namespace ryunosuke\StreamWrapper\Mixin;

use ryunosuke\StreamWrapper\Utils\Stat;

trait UrlPermissionTrait
{
    abstract protected function setMetadata(string $url, array $metadata): void;

    public function _chmod(string $url, int $permissions): bool
    {
        $stat = new Stat([]);
        $this->setMetadata($url, $stat->chmod($permissions));
        return true;
    }

    public function _chown(string $url, int $uid): bool
    {
        $stat = new Stat([]);
        $this->setMetadata($url, $stat->chown($uid));
        return true;
    }

    public function _chgrp(string $url, int $gid): bool
    {
        $stat = new Stat([]);
        $this->setMetadata($url, $stat->chgrp($gid));
        return true;
    }
}
