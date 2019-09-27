<?php

namespace Makeable\SqlCheck;

class DiskSpace
{
    /**
     * @return bool|float
     */
    public function available()
    {
        return disk_free_space('/');
    }
}
