<?php

namespace App\Http\Controllers\Tables;

use App\Database\Exceptions\PrivilegeException;
use App\Database\Exceptions\TableNotFoundException;
use App\Database\TableRecordForm;
use App\Enums\PrivilegeEnum;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TableRecordsController extends TableController
{
    /**
     * Add the row filled in the form of the table, checked against the structure of its columns.
     */
    public function store(Project $project, string $table, Request $request): JsonResponse
    {
        try {
            $driver = $this->driver($project);
            $driver->assertPrivilege(PrivilegeEnum::Insert, $table);
            $form = new TableRecordForm($driver, $table);
        } catch (TableNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        } catch (PrivilegeException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        $values = $form->normalize($request->validate(
            $form->rules(),
            attributes: array_column($form->fields(), 'name', 'name'),
        ));

        return $this->respond(fn (): array => ['values' => $form->insert($values)]);
    }
}
