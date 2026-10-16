<?php

namespace App\Listeners;

use App\Events\LocationStored;
use App\Services\FieldTaskProgressService;

class UpdateFieldTaskProgress
{
    public function __construct(private FieldTaskProgressService $progress) {}
    public function handle(LocationStored $event): void { $this->progress->process($event->location); }
}
