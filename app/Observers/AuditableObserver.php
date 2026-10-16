<?php

namespace App\Observers;

use App\Jobs\WriteAuditLogJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->record('created', $model, null, $model->getAttributes());
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $model->getOriginal(), null);
    }

    public function restored(Model $model): void
    {
        $this->record('restored', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $this->record('updated', $model, array_intersect_key($model->getOriginal(), $changes), $changes);
    }

    protected function record(string $event, Model $model, ?array $oldValues, ?array $newValues): void
    {
        WriteAuditLogJob::dispatch(
            userId: Auth::id(),
            event: $event,
            auditableType: $model::class,
            auditableId: (int) $model->getKey(),
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }
}
